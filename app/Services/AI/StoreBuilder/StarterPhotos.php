<?php

namespace App\Services\AI\StoreBuilder;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A real photo for each starter product the store builder suggests.
 *
 * A preview of a shop with no pictures in it is a wireframe, and a merchant
 * judging their new store from a wireframe sees "شغل HTML", not a shop. So
 * each suggested product gets a photo before the preview is ever shown:
 *
 *  1. Pexels, searched with the English words the model wrote for it — when
 *     there is a key (services.pexels). Free, and covers any kind of shop.
 *  2. Otherwise our own demo library (config/demo.php → catalogue_keywords),
 *     when the search shares a word with what a catalogue's photos show.
 *  3. Otherwise nothing, and the product gets a drawn placeholder. A coffee
 *     shop shown a photo of a watch is worse than shown no photo.
 *
 * Fetched once, in the queue job that writes the plan, so the merchant's wait
 * for the plan covers it and applying the plan downloads nothing. The files
 * sit under the generation's own folder until the plan is applied.
 */
class StarterPhotos
{
    private const SEARCH_URL = 'https://api.pexels.com/v1/search';

    /** Larger than any product card draws it, smaller than a phone's screen. */
    private const MAX_BYTES = 3_000_000;

    /**
     * @param  array<int,string>  $queries  one English photo search per product
     * @return array<int,string|null>  a public-disk path per query, same keys
     */
    public function fetch(string $folder, array $queries): array
    {
        $key = (string) config('services.pexels.key');
        $used = [];
        $paths = [];

        foreach ($queries as $i => $query) {
            $query = trim($query);
            $paths[$i] = $query === '' ? null : (
                ($key !== '' ? $this->fromPexels($key, $query, "{$folder}/{$i}.jpg", $used) : null)
                ?? $this->fromLibrary($query, "{$folder}/{$i}.jpg", $used)
            );
        }

        return $paths;
    }

    /** @param array<int|string,true> $used */
    private function fromPexels(string $key, string $query, string $path, array &$used): ?string
    {
        try {
            $photos = Http::withHeaders(['Authorization' => $key])
                ->timeout(8)
                ->get(self::SEARCH_URL, ['query' => $query, 'per_page' => 8, 'orientation' => 'square'])
                ->throw()
                ->json('photos', []);

            // Not the same picture twice in one shop: two products searched
            // as "coffee beans" would otherwise both get the first hit.
            $photo = collect($photos)->first(fn (array $p) => ! isset($used['pexels:' . $p['id']]));

            if (! $photo || blank($url = $photo['src']['large'] ?? null)) {
                return null;
            }

            $image = Http::timeout(10)->get($url)->throw();

            if (! str_starts_with((string) $image->header('Content-Type'), 'image/') || strlen($image->body()) > self::MAX_BYTES) {
                return null;
            }

            Storage::disk('public')->put($path, $image->body());
            $used['pexels:' . $photo['id']] = true;

            return $path;
        } catch (\Throwable $e) {
            // A missing photo is not worth failing a store plan over.
            Log::warning('Starter photo from Pexels failed', ['query' => $query, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** @param array<int|string,true> $used */
    private function fromLibrary(string $query, string $path, array &$used): ?string
    {
        $words = collect(preg_split('/[^a-z\-]+/', Str::lower($query)) ?: [])->filter()->all();

        $catalogue = collect((array) config('demo.catalogue_keywords'))
            ->map(fn (array $keywords) => count(array_intersect($words, $keywords)))
            ->filter()
            ->sortDesc()
            ->keys()
            ->first();

        if ($catalogue === null) {
            return null;
        }

        $photo = collect((array) config("demo.catalogues.{$catalogue}.products"))
            ->pluck('photo')
            ->filter()
            ->first(fn ($id) => ! isset($used["library:{$id}"]) && is_file(resource_path("demo/photos/{$id}.jpg")));

        if ($photo === null) {
            return null;
        }

        Storage::disk('public')->put($path, file_get_contents(resource_path("demo/photos/{$photo}.jpg")));
        $used["library:{$photo}"] = true;

        return $path;
    }
}

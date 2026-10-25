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
 *  1. Pixabay, then Pexels, searched with the English words the model wrote
 *     for it — whichever has a key (services.pixabay / services.pexels).
 *     Free, and between them they cover any kind of shop. Both forbid
 *     hotlinking, so the file is downloaded, never linked.
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
        $pixabay = (string) config('services.pixabay.key');
        $pexels = (string) config('services.pexels.key');
        $used = [];
        $paths = [];

        foreach ($queries as $i => $query) {
            $query = trim($query);
            $path = "{$folder}/{$i}.jpg";

            $paths[$i] = $query === '' ? null : (
                ($pixabay !== '' ? $this->fromPixabay($pixabay, $query, $path, $used) : null)
                ?? ($pexels !== '' ? $this->fromPexels($pexels, $query, $path, $used) : null)
                ?? $this->fromLibrary($query, $path, $used)
            );
        }

        return $paths;
    }

    /** @param array<int|string,true> $used */
    private function fromPixabay(string $key, string $query, string $path, array &$used): ?string
    {
        try {
            $hits = Http::timeout(8)
                ->get('https://pixabay.com/api/', [
                    'key' => $key,
                    'q' => $query,
                    'image_type' => 'photo',
                    'safesearch' => 'true',
                    'per_page' => 8,
                ])
                ->throw()
                ->json('hits', []);

            $hit = collect($hits)->first(fn (array $h) => ! isset($used['pixabay:' . $h['id']]));

            // The 1280px rendition: sharp on a phone, a fraction of the original.
            if (! $hit || blank($url = $hit['largeImageURL'] ?? $hit['webformatURL'] ?? null)) {
                return null;
            }

            return $this->download($url, $path, $used, 'pixabay:' . $hit['id'], $query);
        } catch (\Throwable $e) {
            Log::warning('Starter photo from Pixabay failed', ['query' => $query, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<int|string,true>  $used
     */
    private function download(string $url, string $path, array &$used, string $id, string $query): ?string
    {
        $image = Http::timeout(10)->get($url)->throw();

        if (! str_starts_with((string) $image->header('Content-Type'), 'image/') || strlen($image->body()) > self::MAX_BYTES) {
            Log::warning('Starter photo rejected', ['query' => $query, 'url' => $url]);

            return null;
        }

        Storage::disk('public')->put($path, $image->body());
        $used[$id] = true;

        return $path;
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

            return $this->download($url, $path, $used, 'pexels:' . $photo['id'], $query);
        } catch (\Throwable $e) {
            // A missing photo is not worth failing a store plan over.
            Log::warning('Starter photo from Pexels failed', ['query' => $query, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Words that describe a thing without saying what it is. They help pick
     * between two photos of the right thing, but never make a match on their
     * own: "black t-shirt" must not land on a black watch.
     */
    private const QUALIFIERS = [
        'black', 'white', 'brown', 'blue', 'pink', 'red', 'green', 'gold', 'silver', 'beige', 'grey', 'gray',
        'men', 'women', 'set', 'small', 'luxury', 'classic', 'natural', 'box', 'gift', 'bottle', 'jar', 'cup',
        'bowl', 'bag', 'pot', 'case', 'holder', 'home', 'clothes', 'oriental', 'vintage', 'travel', 'sport',
    ];

    /**
     * The words the library's photos can be found by — what the model is
     * told to search with, so its searches have something to match.
     *
     * @return array<int,string>
     */
    public function vocabulary(): array
    {
        return collect((array) config('demo.photo_library'))
            ->flatMap(fn (string $tags) => explode(' ', $tags))
            ->reject(fn (string $word) => in_array($word, self::QUALIFIERS, true))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * The library photo sharing most words with the search, if one shares a
     * word that names the thing (see QUALIFIERS). Plurals are folded, so
     * "nuts" finds "nut" and "beans" finds "bean".
     *
     * @param  array<int|string,true>  $used
     */
    private function fromLibrary(string $query, string $path, array &$used): ?string
    {
        $words = $this->words($query);
        $qualifiers = $this->words(implode(' ', self::QUALIFIERS));
        $best = null;
        $bestScore = 0;

        foreach ((array) config('demo.photo_library') as $id => $tags) {
            if (isset($used["library:{$id}"]) || ! is_file(resource_path("demo/photos/{$id}.jpg"))) {
                continue;
            }

            $shared = array_unique(array_intersect($words, $this->words($tags)));
            $naming = array_diff($shared, $qualifiers);

            if ($naming === []) {
                continue;
            }

            // A naming word counts double; the first of a photo's tags is
            // what it is a photo of, so matching it settles a tie.
            $score = count($naming) * 2 + count($shared) + (in_array($this->words($tags)[0] ?? '', $shared, true) ? 1 : 0);

            if ($score > $bestScore) {
                [$best, $bestScore] = [$id, $score];
            }
        }

        if ($best === null) {
            return null;
        }

        Storage::disk('public')->put($path, file_get_contents(resource_path("demo/photos/{$best}.jpg")));
        $used["library:{$best}"] = true;

        return $path;
    }

    /** @return array<int,string> */
    private function words(string $text): array
    {
        return collect(preg_split('/[^a-z]+/', Str::lower(str_replace('-', '', $text))) ?: [])
            ->filter(fn (string $w) => strlen($w) > 1)
            // Plural to singular, crudely: enough for "nuts", "beans", "glasses".
            ->map(fn (string $w) => strlen($w) > 3 && str_ends_with($w, 's') && ! str_ends_with($w, 'ss') ? substr($w, 0, -1) : $w)
            ->values()
            ->all();
    }
}

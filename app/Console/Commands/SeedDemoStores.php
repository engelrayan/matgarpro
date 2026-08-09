<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Demo\DemoArtwork;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds one showroom store per theme.
 *
 * Idempotent: running it again updates the existing showrooms rather than
 * creating a second set, so it is safe on every deploy.
 */
class SeedDemoStores extends Command
{
    protected $signature = 'demo:stores {--fresh : Delete existing showrooms first}';

    protected $description = 'Create or refresh the demo storefront for every theme';

    public function __construct(private readonly DemoArtwork $artwork)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('fresh')) {
            Store::where('is_demo', true)->forceDelete();
            $this->warn('Existing showrooms removed.');
        }

        $owner = $this->owner();
        $themes = (array) config('themes.themes');

        foreach ($themes as $key => $theme) {
            $store = $this->store($owner, $key, $theme);
            $this->fill($store, $key);

            $this->line("  <fg=green>✓</> {$theme['name']} → https://{$store->platformHost()}");
        }

        $this->newLine();
        $this->info(count($themes) . ' showrooms ready.');

        return self::SUCCESS;
    }

    /**
     * A single platform-owned account holding every showroom.
     *
     * Not a real person's account: these stores must never appear in a
     * merchant's own store list.
     */
    private function owner(): User
    {
        $owner = User::firstOrCreate(
            ['email' => 'showroom@matgarpro.internal'],
            [
                'name' => 'متجر برو — المعرض',
                // Never signed into. A random secret means there is no password
                // to guess even if the row is somehow reachable.
                'password' => bcrypt(Str::random(40)),
            ],
        );

        // Assigned rather than mass-filled: `email_verified_at` is guarded, and
        // strict Eloquent throws on a non-fillable key instead of dropping it.
        if (! $owner->email_verified_at) {
            $owner->forceFill(['email_verified_at' => now()])->save();
        }

        return $owner;
    }

    /** @param array<string,mixed> $theme */
    private function store(User $owner, string $key, array $theme): Store
    {
        $catalogue = $this->catalogue($key);

        return Store::withoutGlobalScopes()->updateOrCreate(
            ['slug' => "demo-{$key}"],
            [
                'user_id' => $owner->id,
                'name' => $catalogue['store_name'],
                'description' => $catalogue['tagline'],
                'currency' => 'EGP',
                'status' => Store::STATUS_ACTIVE,
                'is_demo' => true,
                'settings' => ['theme' => $key],
            ],
        );
    }

    private function fill(Store $store, string $key): void
    {
        $catalogue = $this->catalogue($key);

        DB::transaction(function () use ($store, $catalogue) {
            // Rebuilt rather than merged: the catalogue is the source of truth
            // and a half-updated showroom is worse than a rebuilt one.
            $store->products()->forceDelete();
            $store->categories()->delete();

            $categories = collect($catalogue['categories'])->mapWithKeys(
                fn (string $name, int $i) => [$name => $store->categories()->create([
                    'name' => $name,
                    'slug' => Category::uniqueSlug($store->id, $name),
                    'sort_order' => $i,
                    'is_active' => true,
                ])],
            );

            foreach ($catalogue['products'] as $i => $row) {
                $product = $store->products()->create([
                    'name' => $row['name'],
                    'slug' => Product::uniqueSlug($store->id, $row['name']),
                    'description' => $this->description($row['name']),
                    'price' => $row['price'],
                    'compare_at_price' => $row['compare'],
                    // A real deadline so the storefront countdown has something
                    // honest to count to. Staggered, so the showroom shows a
                    // timer rather than every deal ending at the same second.
                    'sale_ends_at' => $row['compare'] ? now()->addDays(2 + $i)->setTime(23, 59) : null,
                    'track_stock' => true,
                    // Enough that nothing shows as sold out, low enough to be
                    // believable next to a real catalogue.
                    'stock' => 25,
                    'status' => Product::STATUS_ACTIVE,
                    'sort_order' => $i,
                    'options' => $this->optionsFor($row),
                ]);

                if ($category = $categories->get($row['category'])) {
                    $product->categories()->attach($category->id);
                }

                $this->artworkFor($product, $row);
                $this->variants($product);
            }
        });
    }

    /**
     * Draw and attach the product's artwork.
     *
     * Written to the public disk as a real file and attached as an ordinary
     * ProductImage, so a showroom image travels the same path as a merchant's
     * upload — the gallery, the card, the Open Graph tag — with no special
     * cases anywhere downstream.
     *
     * @param  array<string,mixed>  $row
     */
    private function artworkFor(Product $product, array $row): void
    {
        $kind = $this->artwork->kindFor($row['name']);
        $path = "demo/{$product->store_id}/{$product->id}.svg";

        Storage::disk('public')->put(
            $path,
            $this->artwork->render($kind, $row['hue'], $row['name']),
        );

        $product->images()->create([
            'path' => $path,
            'alt' => $row['name'],
            'sort_order' => 0,
        ]);
    }

    /** @param array<string,mixed> $row */
    private function optionsFor(array $row): ?array
    {
        // Only where a real shop would have them. Sizes on a hand cream are the
        // kind of detail that makes a demo read as generated.
        return str_contains($row['category'], 'رجالي') || str_contains($row['category'], 'حريمي') || str_contains($row['category'], 'أحذية')
            ? [['name' => 'المقاس', 'values' => ['S', 'M', 'L', 'XL']]]
            : null;
    }

    private function variants(Product $product): void
    {
        foreach ($product->options[0]['values'] ?? [] as $value) {
            $product->variants()->create([
                'options' => [$product->options[0]['name'] => $value],
                'price' => null,
                'stock' => 8,
            ]);
        }
    }

    private function description(string $name): string
    {
        return '<p>' . $name . ' — خامة ممتازة وتشطيب نضيف.</p>'
            . '<ul><li>ضمان سنتين ضد عيوب الصناعة</li>'
            . '<li>الدفع عند الاستلام</li>'
            . '<li>استبدال خلال ١٤ يوم</li></ul>';
    }

    /** @return array<string,mixed> */
    private function catalogue(string $themeKey): array
    {
        $map = (array) config('demo.theme_catalogue');
        $key = $map[$themeKey] ?? config('demo.fallback_catalogue');

        return config("demo.catalogues.{$key}");
    }
}

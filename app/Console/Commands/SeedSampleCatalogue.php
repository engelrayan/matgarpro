<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Services\Demo\DemoArtwork;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Fills a real merchant's store with a sample catalogue.
 *
 * For trying the storefront out before there is anything to sell. Unlike the
 * theme showrooms these products belong to the merchant and are ordinary rows
 * in every respect — they can be edited, hidden or deleted one by one, and a
 * real order against one is a real order.
 */
class SeedSampleCatalogue extends Command
{
    protected $signature = 'store:sample
        {store : Store id or slug}
        {--catalogue=fashion : Which catalogue from config/demo.php}
        {--clear : Remove the store\'s existing products first}';

    protected $description = 'Add sample products, categories and offers to a store';

    public function __construct(private readonly DemoArtwork $artwork)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $store = $this->resolveStore();

        if (! $store) {
            $this->error('Store not found.');

            return self::FAILURE;
        }

        $catalogue = config('demo.catalogues.' . $this->option('catalogue'));

        if (! $catalogue) {
            $this->error('Unknown catalogue. Options: ' . implode(', ', array_keys((array) config('demo.catalogues'))));

            return self::FAILURE;
        }

        if ($this->option('clear')) {
            $store->products()->forceDelete();
            $store->categories()->delete();
            $this->warn('Existing catalogue cleared.');
        }

        $this->build($store, $catalogue);

        $this->newLine();
        $this->info("«{$store->name}» now has {$store->products()->count()} products.");
        $this->line('  ' . $store->canonicalUrl());

        return self::SUCCESS;
    }

    private function resolveStore(): ?Store
    {
        $key = $this->argument('store');

        return is_numeric($key)
            ? Store::find($key)
            : Store::where('slug', $key)->first();
    }

    /** @param array<string,mixed> $catalogue */
    private function build(Store $store, array $catalogue): void
    {
        DB::transaction(function () use ($store, $catalogue) {
            $categories = collect($catalogue['categories'])->mapWithKeys(
                fn (string $name, int $i) => [$name => $store->categories()->firstOrCreate(
                    ['slug' => Category::uniqueSlug($store->id, $name)],
                    ['name' => $name, 'sort_order' => $i, 'is_active' => true],
                )],
            );

            foreach ($catalogue['products'] as $i => $row) {
                $product = $store->products()->create([
                    'name' => $row['name'],
                    'slug' => Product::uniqueSlug($store->id, $row['name']),
                    'description' => '<p>' . $row['name'] . ' — خامة ممتازة وتشطيب نضيف.</p>'
                        . '<ul><li>الدفع عند الاستلام</li><li>استبدال خلال ١٤ يوم</li></ul>',
                    'price' => $row['price'],
                    'compare_at_price' => $row['compare'],
                    // Staggered deadlines so the storefront countdown has a real
                    // date to count to and the deals do not all expire at once.
                    'sale_ends_at' => $row['compare'] ? now()->addDays(2 + $i)->setTime(23, 59) : null,
                    'track_stock' => true,
                    'stock' => 25,
                    'status' => Product::STATUS_ACTIVE,
                    'sort_order' => $i,
                ]);

                if ($category = $categories->get($row['category'])) {
                    $product->categories()->attach($category->id);
                }

                $path = "samples/{$store->id}/{$product->id}.svg";

                Storage::disk('public')->put($path, $this->artwork->render(
                    $this->artwork->kindFor($row['name']),
                    $row['hue'],
                    $row['name'],
                ));

                $product->images()->create(['path' => $path, 'alt' => $row['name'], 'sort_order' => 0]);

                $this->line("  <fg=green>✓</> {$row['name']}" . ($row['compare'] ? ' <fg=yellow>(عرض)</>' : ''));
            }
        });
    }
}

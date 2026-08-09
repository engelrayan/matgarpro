<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => 'منتج ' . fake()->unique()->numberBetween(1, 99999),
            'slug' => 'p-' . Str::lower(Str::random(8)),
            'price' => fake()->randomFloat(2, 50, 900),
            'track_stock' => true,
            'stock' => 25,
            'status' => Product::STATUS_ACTIVE,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => Product::STATUS_DRAFT]);
    }

    public function outOfStock(): static
    {
        return $this->state(['stock' => 0]);
    }

    /** Two options and the four combinations they produce. */
    public function withVariants(): static
    {
        return $this->state([
            'options' => [
                ['name' => 'اللون', 'values' => ['أحمر', 'أزرق']],
                ['name' => 'المقاس', 'values' => ['M', 'L']],
            ],
        ])->afterCreating(function (Product $product) {
            foreach (['أحمر', 'أزرق'] as $colour) {
                foreach (['M', 'L'] as $size) {
                    $product->variants()->create([
                        'options' => ['اللون' => $colour, 'المقاس' => $size],
                        'price' => null,
                        'stock' => 5,
                    ]);
                }
            }
        });
    }
}

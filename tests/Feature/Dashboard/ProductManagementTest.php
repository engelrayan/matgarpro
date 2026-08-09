<?php

namespace Tests\Feature\Dashboard;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::factory()->create();
        $this->store = Store::factory()->for($this->user)->create();
    }

    /** @return array<string,mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'قميص قطن',
            'slug' => '',
            'price' => '399',
            'status' => Product::STATUS_ACTIVE,
            'track_stock' => true,
            'stock' => 20,
        ], $overrides);
    }

    public function test_a_merchant_can_create_a_product_with_images(): void
    {
        $this->actingAs($this->user)
            ->post('http://localhost/products', $this->payload([
                'images' => [
                    UploadedFile::fake()->image('one.jpg'),
                    UploadedFile::fake()->image('two.jpg'),
                ],
            ]))
            ->assertRedirect();

        $product = Product::firstOrFail();

        $this->assertSame('قميص قطن', $product->name);
        $this->assertSame(2, $product->images()->count());
        $this->assertSame([0, 1], $product->images()->pluck('sort_order')->all());

        Storage::disk('public')->assertExists($product->images()->first()->path);
    }

    /** Str::slug() strips Arabic, so an unaided slug would be empty. */
    public function test_an_arabic_name_still_produces_a_usable_url(): void
    {
        $this->actingAs($this->user)->post('http://localhost/products', $this->payload());
        $this->actingAs($this->user)->post('http://localhost/products', $this->payload(['name' => 'بنطلون جينز']));

        $slugs = Product::pluck('slug');

        $this->assertCount(2, $slugs->unique(), 'two Arabic names must not collide');
        $slugs->each(fn ($slug) => $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug));
    }

    public function test_editing_a_product_keeps_its_url(): void
    {
        $this->actingAs($this->user)->post('http://localhost/products', $this->payload());
        $product = Product::firstOrFail();
        $originalSlug = $product->slug;

        $this->actingAs($this->user)
            ->post("http://localhost/products/{$product->id}", $this->payload([
                'name' => 'قميص قطن — معدّل',
                'slug' => $originalSlug,
            ]))
            ->assertRedirect();

        $this->assertSame(
            $originalSlug,
            $product->fresh()->slug,
            'a renamed product must not break links already running in ads',
        );
    }

    public function test_variant_stock_survives_an_edit(): void
    {
        $product = Product::factory()->for($this->store)->withVariants()->create();
        $product->variants()->first()->update(['stock' => 17]);

        $this->actingAs($this->user)
            ->post("http://localhost/products/{$product->id}", $this->payload([
                'name' => $product->name,
                'slug' => $product->slug,
                'options' => $product->options,
                'variants' => $product->variants()->get()
                    ->map(fn ($v) => ['options' => $v->options, 'price' => $v->price, 'stock' => $v->stock])
                    ->all(),
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(17, $product->variants()->orderBy('id')->first()->stock);
        $this->assertSame(4, $product->variants()->count());
    }

    public function test_removing_an_option_drops_its_combinations(): void
    {
        $product = Product::factory()->for($this->store)->withVariants()->create();

        $this->actingAs($this->user)
            ->post("http://localhost/products/{$product->id}", $this->payload([
                'name' => $product->name,
                'slug' => $product->slug,
                'options' => [['name' => 'اللون', 'values' => ['أحمر', 'أزرق']]],
                'variants' => [
                    ['options' => ['اللون' => 'أحمر'], 'stock' => 3],
                    ['options' => ['اللون' => 'أزرق'], 'stock' => 4],
                ],
            ]));

        $this->assertSame(2, $product->variants()->count());
    }

    public function test_duplicating_copies_images_but_lands_as_a_draft(): void
    {
        $this->actingAs($this->user)->post('http://localhost/products', $this->payload([
            'images' => [UploadedFile::fake()->image('one.jpg')],
        ]));

        $product = Product::firstOrFail();

        $this->actingAs($this->user)
            ->post("http://localhost/products/{$product->id}/duplicate")
            ->assertRedirect();

        $copy = Product::latest('id')->first();

        $this->assertNotSame($product->id, $copy->id);
        $this->assertSame(Product::STATUS_DRAFT, $copy->status, 'a half-edited copy must not go live');
        $this->assertSame(1, $copy->images()->count());
        $this->assertNotSame(
            $product->images()->first()->path,
            $copy->images()->first()->path,
            'the copy owns its own file, so deleting one product cannot blank the other',
        );
    }

    public function test_compare_price_must_beat_the_selling_price(): void
    {
        $this->actingAs($this->user)
            ->post('http://localhost/products', $this->payload([
                'price' => '400',
                'compare_at_price' => '300',
            ]))
            ->assertSessionHasErrors('compare_at_price');
    }

    public function test_a_merchant_cannot_touch_another_merchants_product(): void
    {
        $theirs = Product::factory()->create();

        $this->actingAs($this->user)
            ->get("http://localhost/products/{$theirs->id}/edit")
            ->assertForbidden();

        $this->actingAs($this->user)
            ->delete("http://localhost/products/{$theirs->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $theirs->id, 'deleted_at' => null]);
    }

    public function test_deleting_a_product_keeps_its_order_history(): void
    {
        $product = Product::factory()->for($this->store)->create(['name' => 'قميص قطن']);

        $order = $this->store->orders()->create([
            'number' => 1, 'customer_name' => 'محمود', 'customer_phone' => '01006262330',
            'address' => 'القاهرة', 'subtotal' => 399, 'total' => 399,
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'name' => $product->name,
            'unit_price' => 399, 'quantity' => 1, 'total' => 399,
        ]);

        $this->actingAs($this->user)->delete("http://localhost/products/{$product->id}");

        $item = $order->items()->firstOrFail();

        $this->assertSame('قميص قطن', $item->name, 'the order line keeps what was sold');
    }
}

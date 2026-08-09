<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::factory()->create();
        $this->store = Store::factory()->for($this->user)->create(['slug' => 'mahmoud']);
    }

    private function category(array $attributes = []): Category
    {
        return Category::create([
            'store_id' => $this->store->id,
            'name' => 'رجالي',
            'slug' => Category::uniqueSlug($this->store->id, $attributes['name'] ?? 'رجالي'),
            ...$attributes,
        ]);
    }

    public function test_a_merchant_can_create_a_category_with_an_image(): void
    {
        $this->actingAs($this->user)
            ->post('http://localhost/categories', [
                'name' => 'رجالي',
                'description' => 'كل حاجة للرجالة',
                'image' => UploadedFile::fake()->image('men.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $category = Category::firstOrFail();

        $this->assertSame('رجالي', $category->name);
        Storage::disk('public')->assertExists($category->image_path);
    }

    /**
     * Str::slug() strips Arabic entirely, so every Arabic category name would
     * otherwise collide on an empty slug and hit the unique index.
     */
    public function test_arabic_names_get_distinct_usable_slugs(): void
    {
        $first = $this->category(['name' => 'رجالي']);
        $second = $this->category(['name' => 'حريمي']);

        $this->assertNotSame('', $first->slug);
        $this->assertNotSame($first->slug, $second->slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $first->slug);
    }

    public function test_new_categories_are_appended_rather_than_reordering_the_menu(): void
    {
        $this->actingAs($this->user)->post('http://localhost/categories', ['name' => 'أول']);
        $this->actingAs($this->user)->post('http://localhost/categories', ['name' => 'تاني']);

        $orders = Category::orderBy('id')->pluck('sort_order')->all();

        $this->assertLessThan($orders[1], $orders[0]);
    }

    public function test_a_merchant_can_reorder_categories(): void
    {
        $a = $this->category(['name' => 'أ']);
        $b = $this->category(['name' => 'ب']);

        $this->actingAs($this->user)
            ->put('http://localhost/categories-order', ['ids' => [$b->id, $a->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $b->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
    }

    /** Deleting a section must never delete the things filed under it. */
    public function test_deleting_a_category_keeps_its_products(): void
    {
        $category = $this->category();
        $product = Product::factory()->for($this->store)->create();
        $category->products()->attach($product->id);

        $this->actingAs($this->user)
            ->delete('http://localhost/categories/' . $category->id)
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($category);
        $this->assertModelExists($product);
        $this->assertSame(0, $product->fresh()->categories()->count());
    }

    public function test_deleting_a_category_removes_its_image(): void
    {
        $this->actingAs($this->user)->post('http://localhost/categories', [
            'name' => 'رجالي',
            'image' => UploadedFile::fake()->image('men.jpg'),
        ]);

        $category = Category::firstOrFail();
        $path = $category->image_path;

        $this->actingAs($this->user)->delete('http://localhost/categories/' . $category->id);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_merchant_cannot_touch_another_stores_category(): void
    {
        $theirs = Store::factory()->create();
        $category = Category::create([
            'store_id' => $theirs->id, 'name' => 'تبعهم', 'slug' => 'theirs',
        ]);

        $this->actingAs($this->user)
            ->delete('http://localhost/categories/' . $category->id)
            ->assertForbidden();

        $this->assertModelExists($category);
    }

    // ── Product linking ─────────────────────────────────────────────────────

    public function test_a_product_can_be_filed_under_categories(): void
    {
        $men = $this->category(['name' => 'رجالي']);
        $shirts = $this->category(['name' => 'قمصان']);

        $this->actingAs($this->user)->post('http://localhost/products', [
            'name' => 'قميص',
            'price' => 300,
            'status' => 'active',
            'categories' => [$men->id, $shirts->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Product::firstOrFail()->categories()->count());
    }

    /**
     * A posted id from someone else's store would otherwise file this product
     * under a section its owner never created.
     */
    public function test_a_category_from_another_store_is_ignored(): void
    {
        $mine = $this->category(['name' => 'رجالي']);

        $theirs = Store::factory()->create();
        $foreign = Category::create(['store_id' => $theirs->id, 'name' => 'تبعهم', 'slug' => 'theirs']);

        $this->actingAs($this->user)->post('http://localhost/products', [
            'name' => 'قميص',
            'price' => 300,
            'status' => 'active',
            'categories' => [$mine->id, $foreign->id],
        ])->assertSessionHasNoErrors();

        $attached = Product::firstOrFail()->categories()->pluck('categories.id')->all();

        $this->assertSame([$mine->id], $attached);
    }

    /** Editing a product without touching categories must not unfile it. */
    public function test_omitting_categories_leaves_the_existing_ones_alone(): void
    {
        $category = $this->category();
        $product = Product::factory()->for($this->store)->create();
        $product->categories()->attach($category->id);

        $this->actingAs($this->user)->post('http://localhost/products/' . $product->id, [
            'name' => $product->name,
            'price' => $product->price,
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $product->fresh()->categories()->count());
    }

    public function test_sending_an_empty_list_clears_the_categories(): void
    {
        $category = $this->category();
        $product = Product::factory()->for($this->store)->create();
        $product->categories()->attach($category->id);

        $this->actingAs($this->user)->post('http://localhost/products/' . $product->id, [
            'name' => $product->name,
            'price' => $product->price,
            'status' => 'active',
            'categories' => [],
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $product->fresh()->categories()->count());
    }
}

<?php

namespace Tests\Feature\Dashboard;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One merchant must never reach another merchant's records.
 *
 * Every controller here already checks ownership. This file exists so that
 * stays true: the checks are single lines that a refactor can drop without
 * breaking a single other test, and the failure is silent — the endpoint keeps
 * working, just for the wrong person. Customer names, phone numbers and home
 * addresses sit behind these routes.
 *
 * Written as "the intruder is a real, logged-in merchant with their own shop",
 * because that is the actual threat. Anyone can sign up in a minute and start
 * incrementing ids.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $intruder;

    private Store $victim;

    protected function setUp(): void
    {
        parent::setUp();

        // A legitimate account with a shop of its own — not an anonymous
        // attacker, which the auth middleware would stop anyway.
        $this->intruder = User::factory()->create();
        Store::factory()->for($this->intruder)->create(['slug' => 'intruder']);

        $this->victim = Store::factory()->create(['slug' => 'victim']);
    }

    /** Anything that is not 200 means the door held. */
    private function assertBlocked(\Illuminate\Testing\TestResponse $response, string $what): void
    {
        $this->assertContains(
            $response->getStatusCode(),
            [403, 404],
            "{$what} — تسريب: الرد كان {$response->getStatusCode()}",
        );
    }

    // ── Products ────────────────────────────────────────────────────────

    public function test_a_merchant_cannot_touch_another_stores_product(): void
    {
        $product = Product::factory()->for($this->victim)->create();

        $this->actingAs($this->intruder);

        $this->assertBlocked($this->get("/products/{$product->id}/edit"), 'فتح منتج');
        $this->assertBlocked($this->post("/products/{$product->id}", ['name' => 'مسروق', 'price' => 1, 'status' => 'active']), 'تعديل منتج');
        $this->assertBlocked($this->post("/products/{$product->id}/duplicate"), 'نسخ منتج');
        $this->assertBlocked($this->delete("/products/{$product->id}"), 'حذف منتج');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => $product->name]);
    }

    /**
     * Categories are attached by id from the request body, which is the shape
     * that gets forgotten — there is no route parameter to notice.
     */
    public function test_a_product_cannot_be_filed_under_another_stores_category(): void
    {
        $theirs = Category::create([
            'store_id' => $this->victim->id, 'name' => 'قسم مش بتاعي', 'slug' => 'theirs',
        ]);

        $this->actingAs($this->intruder)->post('/products', [
            'name' => 'منتج عادي',
            'price' => 100,
            'status' => Product::STATUS_ACTIVE,
            'categories' => [$theirs->id],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('category_product', ['category_id' => $theirs->id]);
    }

    // ── Categories ──────────────────────────────────────────────────────

    public function test_a_merchant_cannot_touch_another_stores_category(): void
    {
        $category = Category::create([
            'store_id' => $this->victim->id, 'name' => 'أقسامهم', 'slug' => 'theirs',
        ]);

        $this->actingAs($this->intruder);

        $this->assertBlocked($this->post("/categories/{$category->id}", ['name' => 'مسروق']), 'تعديل قسم');
        $this->assertBlocked($this->delete("/categories/{$category->id}"), 'حذف قسم');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'أقسامهم']);
    }

    public function test_reordering_cannot_reach_another_stores_categories(): void
    {
        $category = Category::create([
            'store_id' => $this->victim->id, 'name' => 'قسمهم', 'slug' => 'theirs', 'sort_order' => 5,
        ]);

        // No route parameter here — the ids arrive in the body, so the only
        // thing standing between them and another shop is the query's scope.
        $this->actingAs($this->intruder)->put('/categories-order', ['ids' => [$category->id]]);

        $this->assertSame(5, $category->fresh()->sort_order);
    }

    // ── Orders — the ones that carry customer data ──────────────────────

    public function test_a_merchant_cannot_open_another_stores_order(): void
    {
        $order = Order::factory()->for($this->victim)->create(['customer_phone' => '01099999999']);

        $this->actingAs($this->intruder);

        $this->assertBlocked($this->get("/orders/{$order->id}"), 'فتح طلب');
        $this->assertBlocked($this->patch("/orders/{$order->id}", ['customer_name' => 'مسروق']), 'تعديل طلب');
        $this->assertBlocked($this->patch("/orders/{$order->id}/status", ['status' => Order::STATUS_CANCELLED]), 'تغيير حالة طلب');

        $this->assertSame('01099999999', $order->fresh()->customer_phone);
    }

    public function test_bulk_status_cannot_reach_another_stores_orders(): void
    {
        $order = Order::factory()->for($this->victim)->create(['status' => Order::STATUS_PENDING]);

        $this->actingAs($this->intruder)->patch('/orders-bulk/status', [
            'ids' => [$order->id],
            'status' => Order::STATUS_CANCELLED,
        ]);

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    /**
     * The waybill is a printable page holding a customer's full name, phone
     * and street address. Its ids arrive in a query string.
     */
    public function test_waybills_never_print_another_stores_customer(): void
    {
        $order = Order::factory()->for($this->victim)->create(['customer_name' => 'سارة عبد الله']);

        $this->actingAs($this->intruder)->get("/orders/waybills?ids={$order->id}")
            ->assertDontSee('سارة عبد الله', escape: false);
    }

    public function test_the_order_export_only_contains_the_merchants_own_orders(): void
    {
        Order::factory()->for($this->victim)->create(['customer_name' => 'عميل مش بتاعهم']);

        $response = $this->actingAs($this->intruder)->get('/orders/export');

        $body = $response->streamedContent() ?: $response->getContent();
        $this->assertStringNotContainsString('عميل مش بتاعهم', $body);
    }

    // ── Domains ─────────────────────────────────────────────────────────

    public function test_a_merchant_cannot_touch_another_stores_domain(): void
    {
        $domain = StoreDomain::factory()->for($this->victim)->create(['domain' => 'victim.com']);

        $this->actingAs($this->intruder);

        $this->assertBlocked($this->post("/settings/domains/{$domain->id}/verify"), 'فحص دومين');
        $this->assertBlocked($this->post("/settings/domains/{$domain->id}/primary"), 'تعيين دومين أساسي');
        $this->assertBlocked($this->delete("/settings/domains/{$domain->id}"), 'حذف دومين');

        $this->assertDatabaseHas('store_domains', ['id' => $domain->id]);
    }

    // ── The builder ─────────────────────────────────────────────────────

    /**
     * The builder takes no store id at all — it always resolves the signed-in
     * merchant's own shop. This proves that stays true, since a `{store}`
     * parameter is exactly the kind of thing a later feature adds.
     */
    public function test_the_builder_only_ever_writes_the_merchants_own_pages(): void
    {
        $this->actingAs($this->intruder)->put('/builder/home', [
            'sections' => [[
                'id' => 'aaaaaaaaaaaa', 'type' => 'rich_text', 'visible' => true,
                'settings' => ['heading' => 'كتبت في متجر غيري', 'body' => '', 'align' => 'right', 'width' => 'narrow'],
            ]],
        ]);

        $this->assertDatabaseMissing('store_pages', ['store_id' => $this->victim->id]);
    }

    public function test_the_product_picker_never_lists_another_stores_products(): void
    {
        $theirs = Product::factory()->for($this->victim)->create(['name' => 'منتج مش بتاعهم']);

        $this->actingAs($this->intruder)->get("/builder/products?ids[]={$theirs->id}")
            ->assertOk()
            ->assertJsonMissing(['name' => 'منتج مش بتاعهم']);
    }
}

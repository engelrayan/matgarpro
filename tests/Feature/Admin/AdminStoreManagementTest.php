<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\BillingPlan;
use App\Models\Store;
use App\Models\StoreWalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The four things an operator may do to a store, and the trail each leaves.
 */
class AdminStoreManagementTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->super()->create();
    }

    private function acting()
    {
        return $this->actingAs($this->admin, 'admin');
    }

    public function test_suspending_a_store_requires_a_reason(): void
    {
        $store = Store::factory()->create(['status' => Store::STATUS_ACTIVE]);

        $this->acting()
            ->patch("/admin/stores/{$store->id}/status", ['status' => Store::STATUS_SUSPENDED])
            ->assertSessionHasErrors('reason');

        $this->assertSame(Store::STATUS_ACTIVE, $store->fresh()->status);
    }

    public function test_suspending_a_store_records_who_did_it_and_why(): void
    {
        $store = Store::factory()->create(['status' => Store::STATUS_ACTIVE]);

        $this->acting()->patch("/admin/stores/{$store->id}/status", [
            'status' => Store::STATUS_SUSPENDED,
            'reason' => 'منتجات مقلدة',
        ])->assertSessionHasNoErrors();

        $store->refresh();
        $this->assertSame(Store::STATUS_SUSPENDED, $store->status);
        $this->assertSame('منتجات مقلدة', $store->suspension_reason);
        $this->assertFalse($store->canAcceptOrders());

        $log = AdminActivityLog::where('action', 'store.status_changed')->firstOrFail();
        $this->assertSame($this->admin->id, $log->admin_id);
        $this->assertStringContainsString('منتجات مقلدة', $log->summary);
        $this->assertSame(Store::STATUS_ACTIVE, $log->changes['status']['from']);
        $this->assertSame(Store::STATUS_SUSPENDED, $log->changes['status']['to']);
    }

    public function test_reactivating_clears_the_old_suspension_reason(): void
    {
        $store = Store::factory()->create([
            'status' => Store::STATUS_SUSPENDED,
            'suspension_reason' => 'سبب قديم',
        ]);

        $this->acting()->patch("/admin/stores/{$store->id}/status", ['status' => Store::STATUS_ACTIVE]);

        $this->assertNull($store->fresh()->suspension_reason);
    }

    public function test_an_override_beats_the_plan_price(): void
    {
        $plan = BillingPlan::factory()->create(['price_per_order' => 2.00]);
        $store = Store::factory()->create(['billing_plan_id' => $plan->id]);

        $this->acting()->patch("/admin/stores/{$store->id}/billing", [
            'billing_plan_id' => $plan->id,
            'price_per_order_override' => 0.5,
            'billing_status' => 'active',
        ])->assertSessionHasNoErrors();

        $store->refresh();
        $this->assertSame(0.5, $store->pricePerOrder());
        $this->assertSame('override', $store->priceSource());
    }

    public function test_clearing_the_override_falls_back_to_the_plan_not_to_zero(): void
    {
        $plan = BillingPlan::factory()->create(['price_per_order' => 2.00]);
        $store = Store::factory()->create([
            'billing_plan_id' => $plan->id,
            'price_per_order_override' => 0.5,
        ]);

        $this->acting()->patch("/admin/stores/{$store->id}/billing", [
            'billing_plan_id' => $plan->id,
            'price_per_order_override' => null,
            'billing_status' => 'active',
        ])->assertSessionHasNoErrors();

        $store->refresh();
        $this->assertNull($store->price_per_order_override);
        $this->assertSame(2.0, $store->pricePerOrder());
        $this->assertSame('plan', $store->priceSource());
    }

    public function test_a_zero_override_means_free_and_is_not_treated_as_empty(): void
    {
        $plan = BillingPlan::factory()->create(['price_per_order' => 2.00]);
        $store = Store::factory()->create(['billing_plan_id' => $plan->id]);

        $this->acting()->patch("/admin/stores/{$store->id}/billing", [
            'billing_plan_id' => $plan->id,
            'price_per_order_override' => 0,
            'billing_status' => 'active',
        ])->assertSessionHasNoErrors();

        $store->refresh();
        $this->assertSame(0.0, $store->pricePerOrder());
        $this->assertSame('override', $store->priceSource());
    }

    public function test_crediting_a_wallet_writes_a_ledger_row_and_moves_the_balance(): void
    {
        $store = Store::factory()->create();

        $this->acting()->post("/admin/stores/{$store->id}/wallet", [
            'direction' => 'credit',
            'amount' => 150,
            'note' => 'تحويل بنكي #4471',
        ])->assertSessionHasNoErrors();

        $store->refresh();
        $this->assertSame('150.00', $store->balance);

        $transaction = StoreWalletTransaction::where('store_id', $store->id)->firstOrFail();
        $this->assertSame(StoreWalletTransaction::TYPE_ADJUSTMENT, $transaction->type);
        $this->assertSame('150.00', $transaction->amount);
        $this->assertSame('تحويل بنكي #4471', $transaction->description);
        // `created_by` is a FK to users; an operator is recorded in meta.
        $this->assertNull($transaction->created_by);
        $this->assertSame($this->admin->id, $transaction->meta['admin_id']);

        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'store.wallet_adjusted']);
    }

    public function test_debiting_a_wallet_can_take_it_negative(): void
    {
        $store = Store::factory()->create();

        $this->acting()->post("/admin/stores/{$store->id}/wallet", [
            'direction' => 'debit',
            'amount' => 40,
            'note' => 'تصحيح قيد مكرر',
        ])->assertSessionHasNoErrors();

        $this->assertSame('-40.00', $store->fresh()->balance);
    }

    public function test_a_wallet_movement_needs_a_note(): void
    {
        $store = Store::factory()->create();

        $this->acting()->post("/admin/stores/{$store->id}/wallet", [
            'direction' => 'credit',
            'amount' => 50,
        ])->assertSessionHasErrors('note');

        $this->assertSame('0.00', $store->fresh()->balance);
    }

    public function test_the_theme_can_be_set_on_a_store_and_is_logged(): void
    {
        $store = Store::factory()->create();

        $this->acting()->patch("/admin/stores/{$store->id}/theme", ['theme' => 'noir'])
            ->assertSessionHasNoErrors();

        $this->assertSame('noir', $store->fresh()->settings['theme']);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'store.theme_changed']);
    }

    public function test_an_unknown_theme_is_refused(): void
    {
        $store = Store::factory()->create();

        $this->acting()->patch("/admin/stores/{$store->id}/theme", ['theme' => 'nope'])
            ->assertSessionHasErrors('theme');
    }

    public function test_the_panel_has_no_route_into_a_merchants_orders(): void
    {
        // Not a hypothetical: customer names, phones and addresses live there,
        // and the panel is deliberately built without a way to open them.
        $this->assertFalse(
            collect(app('router')->getRoutes())->contains(
                fn ($route) => str_starts_with($route->uri(), 'admin/') && str_contains($route->uri(), 'order'),
            ),
        );
    }
}

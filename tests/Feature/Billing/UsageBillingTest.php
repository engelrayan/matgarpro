<?php

namespace Tests\Feature\Billing;

use App\Models\BillingPlan;
use App\Models\Store;
use App\Models\StoreUsageEvent;
use App\Models\StoreWalletTransaction;
use App\Services\Billing\UsageBiller;
use App\Services\Billing\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageBillingTest extends TestCase
{
    use RefreshDatabase;

    private UsageBiller $biller;

    private WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->biller = $this->app->make(UsageBiller::class);
        $this->wallet = $this->app->make(WalletService::class);
    }

    private function storeOnPlan(float $pricePerOrder, float $balance = 100): Store
    {
        $plan = BillingPlan::factory()->create(['price_per_order' => $pricePerOrder]);
        $store = Store::factory()->create(['billing_plan_id' => $plan->id]);

        if ($balance != 0) {
            $this->wallet->credit($store, $balance, description: 'شحن رصيد');
        }

        return $store->refresh();
    }

    public function test_an_order_deducts_the_plan_price(): void
    {
        $store = $this->storeOnPlan(1.00);
        $order = BillingPlan::factory()->create(); // stands in for an Order model

        $event = $this->biller->chargeForOrder($store, $order);

        $this->assertSame('1.00', $event->unit_price);
        $this->assertSame('plan', $event->price_source);
        $this->assertSame('99.00', $store->refresh()->balance);
        $this->assertNotNull($event->wallet_transaction_id);
    }

    public function test_a_store_override_beats_the_plan(): void
    {
        $store = $this->storeOnPlan(1.00);
        $store->update(['price_per_order_override' => 0.50]);

        $event = $this->biller->chargeForOrder($store->refresh(), BillingPlan::factory()->create());

        $this->assertSame('0.50', $event->unit_price);
        $this->assertSame('override', $event->price_source);
    }

    public function test_a_zero_override_makes_a_store_free_without_removing_its_plan(): void
    {
        $store = $this->storeOnPlan(1.00);
        $store->update(['price_per_order_override' => 0]);

        $event = $this->biller->chargeForOrder($store->refresh(), BillingPlan::factory()->create());

        $this->assertSame('0.00', $event->unit_price);
        $this->assertSame('100.00', $store->refresh()->balance);
        // The usage row still exists — free stores keep a complete history.
        $this->assertNull($event->wallet_transaction_id);
        $this->assertSame(1, StoreUsageEvent::count());
    }

    public function test_charging_the_same_order_twice_only_takes_money_once(): void
    {
        $store = $this->storeOnPlan(1.00);
        $order = BillingPlan::factory()->create();

        $first = $this->biller->chargeForOrder($store, $order);
        $second = $this->biller->chargeForOrder($store->refresh(), $order);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('99.00', $store->refresh()->balance);
        $this->assertSame(1, StoreUsageEvent::count());
    }

    /**
     * The rule that protects historical invoices: a price change must never
     * reach backwards into what a store was already charged.
     */
    public function test_repricing_a_store_does_not_rewrite_past_charges(): void
    {
        $store = $this->storeOnPlan(1.00);
        $old = $this->biller->chargeForOrder($store, BillingPlan::factory()->create());

        $store->update(['price_per_order_override' => 5.00]);
        $new = $this->biller->chargeForOrder($store->refresh(), BillingPlan::factory()->create());

        $this->assertSame('1.00', $old->refresh()->unit_price);
        $this->assertSame('5.00', $new->unit_price);
    }

    public function test_the_wallet_ledger_and_the_cached_balance_agree(): void
    {
        $store = $this->storeOnPlan(1.00, balance: 50);

        foreach (range(1, 5) as $i) {
            $this->biller->chargeForOrder($store->refresh(), BillingPlan::factory()->create());
        }

        $this->assertSame(45.0, $this->wallet->ledgerBalance($store));
        $this->assertSame('45.00', $store->refresh()->balance);
        $this->assertSame(45.0, (float) StoreWalletTransaction::latest('id')->first()->balance_after);
    }

    public function test_a_store_stops_taking_orders_once_it_passes_the_overdraft_floor(): void
    {
        $store = $this->storeOnPlan(1.00, balance: 0);

        $this->assertTrue($store->canAcceptOrders(), 'a small buffer keeps the store selling');

        $this->wallet->debit($store, 30, description: 'استهلاك');

        $this->assertFalse($store->refresh()->canAcceptOrders());
    }

    public function test_a_free_store_is_never_blocked_by_its_balance(): void
    {
        $store = $this->storeOnPlan(0, balance: 0);

        $this->wallet->debit($store, 500, description: 'رصيد سالب قديم');

        $this->assertTrue($store->refresh()->canAcceptOrders());
    }
}

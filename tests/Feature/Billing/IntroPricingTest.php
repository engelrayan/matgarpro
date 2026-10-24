<?php

namespace Tests\Feature\Billing;

use App\Models\BillingPlan;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreUsageEvent;
use App\Models\User;
use App\Services\Billing\UsageBiller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What an order costs a merchant.
 *
 *                    without WhatsApp confirmation   with one
 *   first 3 months   0.50                            1.00
 *   after            0.75                            1.25
 *
 * The plan carries the "after" price; the first three months take a quarter
 * off it; a confirmation adds half a pound when one is actually sent. These
 * tests are that table written down.
 */
class IntroPricingTest extends TestCase
{
    use RefreshDatabase;

    private UsageBiller $biller;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'billing.intro_months' => 3,
            'billing.intro_discount' => 0.25,
            'billing.whatsapp_confirmation_fee' => 0.50,
        ]);

        $this->biller = $this->app->make(UsageBiller::class);
    }

    private function standardPlan(float $price = 0.75): BillingPlan
    {
        return BillingPlan::factory()->create(['price_per_order' => $price]);
    }

    private function order(Store $store): Order
    {
        return Order::factory()->for($store)->create();
    }

    // ── The table ───────────────────────────────────────────────────────────

    public function test_the_first_three_months_cost_half_a_pound_and_a_pound_with_a_confirmation(): void
    {
        $store = Store::factory()->inIntroPeriod()->create(['billing_plan_id' => $this->standardPlan()->id]);

        $this->assertSame(0.50, $store->pricePerOrder());
        $this->assertSame(1.00, $store->pricePerOrder() + $store->confirmationPrice());
        $this->assertSame('intro', $store->priceSource());
    }

    public function test_after_them_a_quarter_is_added_to_each(): void
    {
        $store = Store::factory()->create([
            'billing_plan_id' => $this->standardPlan()->id,
            'trial_ends_at' => now()->subSecond(),
        ]);

        $this->assertSame(0.75, $store->pricePerOrder());
        $this->assertSame(1.25, $store->pricePerOrder() + $store->confirmationPrice());
        $this->assertSame('plan', $store->priceSource());
    }

    public function test_the_later_price_is_knowable_during_the_first_months(): void
    {
        $store = Store::factory()->inIntroPeriod()->create(['billing_plan_id' => $this->standardPlan()->id]);

        $this->assertSame(0.75, $store->basePricePerOrder());
    }

    // ── Charging ────────────────────────────────────────────────────────────

    public function test_an_order_is_charged_the_introductory_price(): void
    {
        $store = Store::factory()->inIntroPeriod()->create(['billing_plan_id' => $this->standardPlan()->id]);

        $event = $this->biller->chargeForOrder($store, $this->order($store));

        $this->assertSame('0.50', (string) $event->amount);
        $this->assertSame('intro', $event->price_source);
        $this->assertSame('-0.50', (string) $store->refresh()->balance);
    }

    public function test_a_confirmation_is_charged_once_per_order_however_often_it_is_sent(): void
    {
        $store = Store::factory()->inIntroPeriod()->create(['billing_plan_id' => $this->standardPlan()->id]);
        $order = $this->order($store);

        $this->biller->chargeForOrder($store, $order);
        $this->biller->chargeForConfirmation($store, $order);
        $this->biller->chargeForConfirmation($store, $order);

        $this->assertSame(1, StoreUsageEvent::where('type', StoreUsageEvent::TYPE_WHATSAPP_CONFIRMATION)->count());
        $this->assertSame('-1.00', (string) $store->refresh()->balance);
    }

    // ── Who wins ────────────────────────────────────────────────────────────

    /**
     * The introductory months are a promise made at signup, so an operator's
     * override gets the same quarter off rather than cancelling it.
     */
    public function test_an_override_is_discounted_too_and_takes_over_in_full_after(): void
    {
        $store = Store::factory()->inIntroPeriod()->create(['price_per_order_override' => 2.00]);

        $this->assertSame(1.75, $store->pricePerOrder());

        $store->update(['trial_ends_at' => now()->subSecond()]);

        $this->assertSame(2.00, $store->pricePerOrder());
        $this->assertSame('override', $store->priceSource());
    }

    /** The founding plan is free for life; a confirmation must not undo that. */
    public function test_a_free_store_pays_nothing_for_orders_or_confirmations(): void
    {
        $store = Store::factory()->inIntroPeriod()->create(['billing_plan_id' => $this->standardPlan(0)->id]);

        $this->assertSame(0.0, $store->pricePerOrder());
        $this->assertSame(0.0, $store->confirmationPrice());
        $this->assertTrue($store->canAcceptOrders());
    }

    /** A plan cheaper than the discount never turns into a credit. */
    public function test_the_discount_never_makes_an_order_negative(): void
    {
        $store = Store::factory()->inIntroPeriod()->create(['price_per_order_override' => 0.10]);

        $this->assertSame(0.0, $store->pricePerOrder());
    }

    // ── The clock ───────────────────────────────────────────────────────────

    /**
     * Built without the factory on purpose: the factory opts out of the
     * introductory months so billing tests measure the plan, and this is the
     * one place that has to prove the real default a merchant gets at signup.
     */
    public function test_a_new_store_opens_with_three_introductory_months(): void
    {
        $store = Store::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'متجر جديد',
            'slug' => 'store-' . uniqid(),
            'currency' => 'EGP',
            'status' => Store::STATUS_ACTIVE,
            'billing_status' => 'active',
        ]);

        $this->assertTrue($store->inIntroPeriod());
        $this->assertEqualsWithDelta(now()->addMonths(3)->timestamp, $store->trial_ends_at->timestamp, 5);
    }

    public function test_a_demo_store_gets_no_introductory_months(): void
    {
        $store = Store::factory()->create(['is_demo' => true]);

        $this->assertNull($store->trial_ends_at);
        $this->assertFalse($store->inIntroPeriod());
    }

    public function test_an_explicit_null_is_not_overwritten_with_a_default(): void
    {
        $this->assertNull(Store::factory()->create(['trial_ends_at' => null])->trial_ends_at);
    }

    public function test_the_countdown_rounds_up_to_whole_days_and_ends_at_null(): void
    {
        $this->assertSame(2, Store::factory()->create(['trial_ends_at' => now()->addHours(30)])->introDaysLeft());
        $this->assertNull(Store::factory()->create(['trial_ends_at' => now()->subDay()])->introDaysLeft());
    }

    // ── What the merchant is shown ──────────────────────────────────────────

    public function test_the_dashboard_shows_both_prices_now_and_after(): void
    {
        $store = Store::factory()->inIntroPeriod(40)->create(['billing_plan_id' => $this->standardPlan()->id]);

        $this->actingAs($store->user)
            ->get('http://localhost/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('billing.in_intro', true)
                ->where('billing.intro_days_left', 40)
                ->where('billing.price_per_order', 0.5)
                ->where('billing.price_with_confirmation', 1)
                ->where('billing.price_after_intro', 0.75)
                ->where('billing.price_after_intro_with_confirmation', 1.25));
    }
}

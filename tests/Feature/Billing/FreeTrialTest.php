<?php

namespace Tests\Feature\Billing;

use App\Models\BillingPlan;
use App\Models\Store;
use App\Models\User;
use App\Services\Billing\UsageBiller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three free months we advertise.
 *
 * The landing page and the register screen both promise "three months free,
 * then half a pound an order, no monthly subscription". Until this existed the
 * promise had nothing behind it — there was no trial clock at all, and the only
 * half-pound plan carried a monthly fee nothing ever charged. These tests are
 * the offer written down.
 */
class FreeTrialTest extends TestCase
{
    use RefreshDatabase;

    private UsageBiller $biller;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('billing.trial_months', 3);

        $this->biller = $this->app->make(UsageBiller::class);
    }

    private function paidPlan(float $price = 0.50): BillingPlan
    {
        return BillingPlan::factory()->create(['price_per_order' => $price]);
    }

    // ── The promise ─────────────────────────────────────────────────────────

    /**
     * Built without the factory on purpose: the factory opts out of the trial
     * so billing tests measure billing, and this is the one place that has to
     * prove the real default a merchant gets at signup.
     */
    public function test_a_new_store_opens_with_three_free_months(): void
    {
        $store = Store::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'متجر جديد',
            'slug' => 'store-' . uniqid(),
            'currency' => 'EGP',
            'status' => Store::STATUS_ACTIVE,
            'billing_status' => 'active',
        ]);

        $this->assertTrue($store->onTrial());
        $this->assertEqualsWithDelta(
            now()->addMonths(3)->timestamp,
            $store->trial_ends_at->timestamp,
            5,
        );
    }

    /** Registering through the real form has to produce the advertised offer. */
    public function test_registering_a_merchant_starts_the_trial(): void
    {
        $plan = BillingPlan::factory()->create(['price_per_order' => 0.50, 'is_default' => true]);

        $this->post('http://localhost/register', [
            'name' => 'محمود',
            'store_name' => 'متجر التجربة',
            'email' => 'trial@example.com',
            'password' => 'Password!2345',
            'password_confirmation' => 'Password!2345',
        ]);

        $store = Store::firstWhere('name', 'متجر التجربة');

        $this->assertNotNull($store, 'التسجيل المفروض يعمل متجر');
        $this->assertTrue($store->onTrial());
        $this->assertSame(0.0, $store->pricePerOrder());
        $this->assertSame(0.50, $store->priceAfterTrial());
        $this->assertSame($plan->id, $store->billing_plan_id);
    }

    public function test_orders_are_free_while_the_trial_runs(): void
    {
        $store = Store::factory()->onTrial()->create(['billing_plan_id' => $this->paidPlan()->id]);

        $event = $this->biller->chargeForOrder($store, $this->paidPlan());

        $this->assertSame('0.00', (string) $event->amount);
        $this->assertSame('trial', $event->price_source);
        // Free, but still recorded — usage history has to be complete, or the
        // day the trial ends leaves an unexplained hole in the store's numbers.
        $this->assertSame('0.00', (string) $store->refresh()->balance);
    }

    public function test_the_plan_price_takes_over_the_moment_the_trial_ends(): void
    {
        $store = Store::factory()->create([
            'billing_plan_id' => $this->paidPlan(0.50)->id,
            'trial_ends_at' => now()->subSecond(),
        ]);

        $this->assertFalse($store->onTrial());
        $this->assertSame(0.50, $store->pricePerOrder());
        $this->assertSame('plan', $store->priceSource());
    }

    /**
     * The advertised price has to be visible during the free months, otherwise
     * the first charge is the first time the merchant sees the number.
     */
    public function test_the_price_after_the_trial_is_knowable_during_it(): void
    {
        $store = Store::factory()->onTrial()->create(['billing_plan_id' => $this->paidPlan(0.50)->id]);

        $this->assertSame(0.0, $store->pricePerOrder());
        $this->assertSame(0.50, $store->priceAfterTrial());
    }

    // ── Who wins ────────────────────────────────────────────────────────────

    /**
     * A trial is a promise made at signup. An operator who needs to start
     * charging early ends the trial explicitly rather than pricing around it —
     * so the trial has to beat an override, not the other way round.
     */
    public function test_the_trial_beats_a_per_store_override(): void
    {
        $store = Store::factory()->onTrial()->create([
            'billing_plan_id' => $this->paidPlan(0.50)->id,
            'price_per_order_override' => 5.00,
        ]);

        $this->assertSame(0.0, $store->pricePerOrder());
        $this->assertSame('trial', $store->priceSource());
    }

    public function test_ending_a_trial_early_starts_the_override(): void
    {
        $store = Store::factory()->onTrial()->create(['price_per_order_override' => 5.00]);

        $store->update(['trial_ends_at' => now()->subSecond()]);

        $this->assertSame(5.00, $store->pricePerOrder());
        $this->assertSame('override', $store->priceSource());
    }

    // ── Edges that would cost money ─────────────────────────────────────────

    /** A showroom cannot take a real order, so a countdown on it means nothing. */
    public function test_a_demo_store_gets_no_trial(): void
    {
        $store = Store::factory()->create(['is_demo' => true]);

        $this->assertNull($store->trial_ends_at);
        $this->assertFalse($store->onTrial());
    }

    /**
     * `null` has to mean "no trial", not "give it the default one" — the
     * factory and the operator both rely on being able to say so explicitly.
     */
    public function test_an_explicit_null_is_not_overwritten_with_a_default(): void
    {
        $store = Store::factory()->create(['trial_ends_at' => null]);

        $this->assertNull($store->trial_ends_at);
    }

    public function test_a_store_on_trial_keeps_selling_on_an_empty_wallet(): void
    {
        $store = Store::factory()->onTrial()->create(['billing_plan_id' => $this->paidPlan(0.50)->id])->refresh();

        $this->assertSame('0.00', (string) $store->balance);
        $this->assertTrue($store->canAcceptOrders());
    }

    public function test_the_countdown_rounds_up_to_whole_days(): void
    {
        $store = Store::factory()->create(['trial_ends_at' => now()->addHours(30)]);

        $this->assertSame(2, $store->trialDaysLeft());
    }

    public function test_days_left_is_null_once_the_trial_is_over(): void
    {
        $store = Store::factory()->create(['trial_ends_at' => now()->subDay()]);

        $this->assertNull($store->trialDaysLeft());
    }

    // ── What the merchant is shown ──────────────────────────────────────────

    public function test_the_dashboard_shows_the_countdown_and_the_price_that_follows(): void
    {
        $store = Store::factory()->onTrial(40)->create(['billing_plan_id' => $this->paidPlan(0.50)->id]);

        $this->actingAs($store->user)
            ->get('http://localhost/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('billing.on_trial', true)
                ->where('billing.trial_days_left', 40)
                ->where('billing.price_per_order', 0.50));
    }
}

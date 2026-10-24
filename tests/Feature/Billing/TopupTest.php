<?php

namespace Tests\Feature\Billing;

use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\Store;
use App\Models\StoreTopupRequest;
use App\Models\StoreWalletTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A merchant topping up: pick an amount, transfer it by InstaPay, send the
 * receipt — and an operator confirms what actually arrived. Only that
 * confirmation moves money, once.
 */
class TopupTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'billing.topup.amounts' => [200, 500, 1000, 2000, 5000, 10000],
            'billing.topup.instapay_number' => '01006262330',
        ]);

        $this->merchant = User::factory()->create();
        $this->store = Store::factory()->for($this->merchant)->create(['name' => 'متجر سارة', 'slug' => 'sara']);
    }

    private function ask(int $amount = 1000): TestResponse
    {
        return $this->actingAs($this->merchant)->post('http://localhost/billing/topups', ['amount' => $amount]);
    }

    private function operator(): Admin
    {
        return Admin::factory()->create();
    }

    // ── The merchant ─────────────────────────────────────────────────────

    public function test_the_balance_page_opens_with_the_amounts_and_the_prices(): void
    {
        $this->actingAs($this->merchant)
            ->get('http://localhost/billing')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('billing/Index')
                ->where('amounts', [200, 500, 1000, 2000, 5000, 10000])
                ->where('instapay', '01006262330')
                ->where('pending', null)
                ->has('prices.with_confirmation'));
    }

    public function test_a_merchant_asks_for_one_of_the_amounts(): void
    {
        $this->ask(1000)->assertSessionHasNoErrors();

        $request = StoreTopupRequest::sole();
        $this->assertSame($this->store->id, $request->store_id);
        $this->assertSame('1000.00', (string) $request->amount);
        $this->assertSame(StoreTopupRequest::STATUS_PENDING, $request->status);

        // Asking moves no money.
        $this->assertSame('0.00', (string) $this->store->refresh()->balance);
    }

    public function test_an_amount_that_is_not_offered_is_refused(): void
    {
        $this->ask(750)->assertSessionHasErrors('amount');

        $this->assertSame(0, StoreTopupRequest::count());
    }

    public function test_one_request_waits_at_a_time(): void
    {
        $this->ask(1000)->assertSessionHasNoErrors();
        $this->ask(500)->assertSessionHasErrors('amount');

        $this->assertSame(1, StoreTopupRequest::count());
    }

    public function test_the_receipt_link_says_which_store_and_which_request(): void
    {
        $this->ask(1000);
        $id = StoreTopupRequest::sole()->id;

        $this->actingAs($this->merchant)
            ->get('http://localhost/billing')
            ->assertInertia(fn ($page) => $page
                ->where('pending.amount', 1000)
                ->where('pending.whatsapp_url', fn (string $url) => str_starts_with($url, 'https://wa.me/201006262330?text=')
                    && str_contains(rawurldecode($url), 'متجر سارة')
                    && str_contains(rawurldecode($url), "طلب رقم {$id}")));
    }

    public function test_a_merchant_cancels_their_own_request_and_no_one_elses(): void
    {
        $this->ask(1000);
        $mine = StoreTopupRequest::sole();

        $stranger = User::factory()->create();
        Store::factory()->for($stranger)->create();

        $this->actingAs($stranger)->delete("http://localhost/billing/topups/{$mine->id}")->assertNotFound();
        $this->assertTrue($mine->fresh()->isPending());

        $this->actingAs($this->merchant)->delete("http://localhost/billing/topups/{$mine->id}")->assertRedirect();
        $this->assertSame(StoreTopupRequest::STATUS_CANCELLED, $mine->fresh()->status);
    }

    public function test_a_guest_cannot_ask(): void
    {
        $this->post('http://localhost/billing/topups', ['amount' => 1000])->assertRedirect('/login');

        $this->assertSame(0, StoreTopupRequest::count());
    }

    // ── The operator ─────────────────────────────────────────────────────

    public function test_an_operator_sees_the_waiting_requests(): void
    {
        $this->ask(1000);

        $this->actingAs($this->operator(), 'admin')
            ->get('http://localhost/admin/topups')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/Topups')
                ->where('pendingCount', 1)
                ->where('requests.data.0.store.name', 'متجر سارة'));
    }

    public function test_confirming_credits_what_actually_arrived_once(): void
    {
        $this->store->update(['price_per_order_override' => 0.75]);
        $this->ask(1000);
        $request = StoreTopupRequest::sole();
        $operator = $this->operator();

        // The merchant picked 1,000 and sent 950.
        $this->actingAs($operator, 'admin')
            ->post("http://localhost/admin/topups/{$request->id}/confirm", ['amount' => 950])
            ->assertSessionHasNoErrors();

        // A second press is refused, not credited again.
        $this->actingAs($operator, 'admin')
            ->post("http://localhost/admin/topups/{$request->id}/confirm", ['amount' => 950])
            ->assertSessionHasErrors('topup');

        $request->refresh();
        $this->assertSame(StoreTopupRequest::STATUS_CONFIRMED, $request->status);
        $this->assertSame('950.00', (string) $request->confirmed_amount);
        $this->assertSame($operator->id, $request->reviewed_by);

        $this->assertSame('950.00', (string) $this->store->refresh()->balance);

        $transaction = StoreWalletTransaction::sole();
        $this->assertSame(StoreWalletTransaction::TYPE_TOPUP, $transaction->type);
        $this->assertSame($request->id, (int) $transaction->source_id);
        $this->assertSame($transaction->id, $request->wallet_transaction_id);

        $this->assertTrue(AdminActivityLog::where('action', 'store.topup_confirmed')->exists());
    }

    public function test_rejecting_moves_nothing_and_tells_the_merchant_why(): void
    {
        $this->ask(1000);
        $request = StoreTopupRequest::sole();

        $this->actingAs($this->operator(), 'admin')
            ->post("http://localhost/admin/topups/{$request->id}/reject", ['note' => 'مالقيناش تحويل بالمبلغ ده'])
            ->assertSessionHasNoErrors();

        $this->assertSame('0.00', (string) $this->store->refresh()->balance);
        $this->assertSame(0, StoreWalletTransaction::count());

        // The guard named: acting as the operator made `admin` the default,
        // and an unnamed actingAs would sign the merchant into that one.
        $this->actingAs($this->merchant, 'web')
            ->get('http://localhost/billing')
            ->assertInertia(fn ($page) => $page
                ->where('pending', null)
                ->where('requests.0.status', 'rejected')
                ->where('requests.0.note', 'مالقيناش تحويل بالمبلغ ده'));
    }

    public function test_a_rejection_needs_a_reason(): void
    {
        $this->ask(1000);

        $this->actingAs($this->operator(), 'admin')
            ->post('http://localhost/admin/topups/' . StoreTopupRequest::sole()->id . '/reject', ['note' => ''])
            ->assertSessionHasErrors('note');
    }

    public function test_a_cancelled_request_cannot_be_confirmed(): void
    {
        $this->ask(1000);
        $request = StoreTopupRequest::sole();
        $this->actingAs($this->merchant)->delete("http://localhost/billing/topups/{$request->id}");

        $this->actingAs($this->operator(), 'admin')
            ->post("http://localhost/admin/topups/{$request->id}/confirm", ['amount' => 1000])
            ->assertSessionHasErrors('topup');

        $this->assertSame('0.00', (string) $this->store->refresh()->balance);
    }

    public function test_a_merchant_cannot_reach_the_operator_screens(): void
    {
        $this->ask(1000);
        $request = StoreTopupRequest::sole();

        $this->actingAs($this->merchant)
            ->post("http://localhost/admin/topups/{$request->id}/confirm", ['amount' => 1000]);

        $this->assertTrue($request->fresh()->isPending());
        $this->assertSame('0.00', (string) $this->store->refresh()->balance);
    }
}

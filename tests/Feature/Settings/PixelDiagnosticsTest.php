<?php

namespace Tests\Feature\Settings;

use App\Models\Order;
use App\Models\Store;
use App\Models\StorePixel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PixelDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Nothing here may reach the real Graph API.
        Http::preventStrayRequests();

        $this->user = User::factory()->create();
        $this->store = Store::factory()->for($this->user)->create();
    }

    private function pixel(array $attributes = []): StorePixel
    {
        return $this->store->pixels()->create([
            'provider' => 'meta',
            'pixel_id' => '123456789012345',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    // ── Connection test ─────────────────────────────────────────────────────

    public function test_a_successful_test_reports_meta_accepted_the_event(): void
    {
        Http::fake(['*/events' => Http::response(['events_received' => 1], 200)]);

        $pixel = $this->pixel(['access_token' => 'EAAG-valid']);

        $this->actingAs($this->user)
            ->post('http://localhost/settings/pixels/' . $pixel->id . '/test')
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'شغّال'));

        // A working round-trip clears any stale failure.
        $this->assertNull($pixel->fresh()->last_error);
        $this->assertNotNull($pixel->fresh()->last_event_at);
    }

    /**
     * The case a format check can never catch: a well-formed token that Meta
     * has revoked. This is what merchants actually hit.
     */
    public function test_a_revoked_token_surfaces_metas_own_message(): void
    {
        Http::fake(['*/events' => Http::response([
            'error' => ['message' => 'Error validating access token', 'code' => 190],
        ], 400)]);

        $pixel = $this->pixel(['access_token' => 'EAAG-revoked']);

        $this->actingAs($this->user)
            ->post('http://localhost/settings/pixels/' . $pixel->id . '/test')
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Error validating access token'));

        $this->assertStringContainsString('access token', (string) $pixel->fresh()->last_error);
    }

    public function test_testing_without_a_token_explains_what_is_missing(): void
    {
        $pixel = $this->pixel();

        $this->actingAs($this->user)
            ->post('http://localhost/settings/pixels/' . $pixel->id . '/test')
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'توكن'));

        Http::assertNothingSent();
    }

    public function test_a_merchant_cannot_test_another_stores_pixel(): void
    {
        $theirs = Store::factory()->create()->pixels()->create([
            'provider' => 'meta', 'pixel_id' => '999999999999999',
            'access_token' => 'EAAG-theirs', 'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->post('http://localhost/settings/pixels/' . $theirs->id . '/test')
            ->assertForbidden();

        Http::assertNothingSent();
    }

    /** A test event must never be counted as a real conversion. */
    public function test_the_test_event_is_identifiable_and_carries_no_fake_customer(): void
    {
        Http::fake(['*/events' => Http::response(['events_received' => 1], 200)]);

        $pixel = $this->pixel(['access_token' => 'EAAG-valid']);

        $this->actingAs($this->user)->post('http://localhost/settings/pixels/' . $pixel->id . '/test');

        Http::assertSent(function ($request) {
            $event = $request->data()['data'][0];

            return str_starts_with($event['event_id'], 'matgarpro-test-')
                && $event['event_name'] === 'PageView'
                // No hashed email or phone: inventing a person would put a
                // fake customer into the merchant's audience.
                && ! isset($event['user_data']['em'])
                && ! isset($event['user_data']['ph']);
        });
    }

    // ── Match quality ───────────────────────────────────────────────────────

    private function order(array $attributes = []): Order
    {
        return Order::factory()->for($this->store)->create($attributes);
    }

    public function test_match_quality_is_unavailable_before_any_orders(): void
    {
        $this->actingAs($this->user)
            ->get('http://localhost/settings/pixels')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('match_quality.available', false));
    }

    public function test_a_store_collecting_email_scores_higher_than_one_that_does_not(): void
    {
        $this->order(['customer_email' => null, 'governorate' => null, 'number' => 1]);

        $without = $this->quality();

        Cache::flush();
        $this->store->orders()->delete();

        $this->order(['customer_email' => 'a@b.com', 'governorate' => 'القاهرة', 'number' => 2]);

        $this->assertGreaterThan($without['score'], $this->quality()['score']);
    }

    public function test_the_advice_points_at_the_setting_that_fixes_it(): void
    {
        $this->order(['customer_email' => null, 'number' => 1]);

        $email = collect($this->quality()['signals'])->firstWhere('label', 'البريد الإلكتروني');

        $this->assertSame(0, $email['coverage']);
        // Advice a merchant can act on, not a generic "improve match quality".
        $this->assertSame('/settings/checkout', $email['fix_url']);
    }

    public function test_coverage_is_measured_per_signal(): void
    {
        $this->order(['customer_email' => 'a@b.com', 'number' => 1]);
        $this->order(['customer_email' => null, 'number' => 2]);

        $email = collect($this->quality()['signals'])->firstWhere('label', 'البريد الإلكتروني');

        $this->assertSame(50, $email['coverage']);
    }

    /** @return array<string,mixed> */
    private function quality(): array
    {
        Cache::flush();

        $props = $this->actingAs($this->user)
            ->get('http://localhost/settings/pixels')
            ->viewData('page')['props'];

        return $props['match_quality'];
    }
}

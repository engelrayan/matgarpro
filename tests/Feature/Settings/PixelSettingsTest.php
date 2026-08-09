<?php

namespace Tests\Feature\Settings;

use App\Models\Store;
use App\Models\StorePixel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PixelSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

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

    /**
     * Every settings screen gets one of these.
     *
     * A missing controller import is invisible until somebody opens the page —
     * which is exactly how this one shipped broken.
     */
    public function test_the_page_renders(): void
    {
        $this->actingAs($this->user)
            ->get('http://localhost/settings/pixels')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('settings/Pixels'));
    }

    // ── Saving ids ──────────────────────────────────────────────────────────

    public function test_ids_are_saved_per_network(): void
    {
        $this->actingAs($this->user)
            ->put('http://localhost/settings/pixels', [
                'meta' => '111111111111111',
                'tiktok' => 'CQWERTY1234567890',
                'snapchat' => '333333333333333',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $this->store->pixels()->count());
        $this->assertSame('111111111111111', $this->store->pixels()->where('provider', 'meta')->value('pixel_id'));
        $this->assertSame('333333333333333', $this->store->pixels()->where('provider', 'snapchat')->value('pixel_id'));
    }

    public function test_several_ids_can_go_in_one_box_one_per_line(): void
    {
        $this->actingAs($this->user)
            ->put('http://localhost/settings/pixels', [
                'meta' => "111111111111111\n222222222222222\n\n333333333333333",
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $this->store->pixels()->where('provider', 'meta')->count());
    }

    /**
     * Merchants paste the whole Events Manager snippet more often than the bare
     * id. Pulling the digits out means that paste works instead of failing on
     * something that looks correct to them.
     */
    public function test_a_pasted_snippet_still_yields_the_id(): void
    {
        $this->actingAs($this->user)->put('http://localhost/settings/pixels', [
            'meta' => "fbq('init', '123456789012345');",
        ]);

        $this->assertSame('123456789012345', $this->store->pixels()->value('pixel_id'));
    }

    public function test_junk_lines_are_ignored(): void
    {
        $this->actingAs($this->user)->put('http://localhost/settings/pixels', [
            'meta' => "not-a-pixel\n123\n111111111111111",
        ]);

        // "123" is too short to be a pixel id; the first line has no digits.
        $this->assertSame(1, $this->store->pixels()->count());
    }

    public function test_the_same_id_twice_creates_one_pixel(): void
    {
        $this->actingAs($this->user)->put('http://localhost/settings/pixels', [
            'meta' => "111111111111111\n111111111111111",
        ]);

        $this->assertSame(1, $this->store->pixels()->count());
    }

    /**
     * Removing a line deactivates rather than deletes: the row carries the CAPI
     * token and the error history, and a merchant who deletes a line by mistake
     * should get it all back by pasting the id again.
     */
    public function test_removing_a_line_deactivates_but_keeps_the_token(): void
    {
        $this->pixel(['pixel_id' => '111111111111111', 'access_token' => 'EAAG-keep-me']);

        $this->actingAs($this->user)->put('http://localhost/settings/pixels', ['meta' => '']);

        $pixel = $this->store->pixels()->first();

        $this->assertFalse($pixel->is_active);
        $this->assertSame('EAAG-keep-me', $pixel->access_token);
    }

    public function test_pasting_a_removed_id_again_brings_it_back(): void
    {
        $this->pixel(['pixel_id' => '111111111111111', 'is_active' => false, 'access_token' => 'EAAG-keep-me']);

        $this->actingAs($this->user)->put('http://localhost/settings/pixels', ['meta' => '111111111111111']);

        $pixel = $this->store->pixels()->first();

        $this->assertTrue($pixel->is_active);
        $this->assertSame('EAAG-keep-me', $pixel->access_token);
        $this->assertSame(1, $this->store->pixels()->count());
    }

    // ── CAPI token ──────────────────────────────────────────────────────────

    /** A page that can display the token is a page that can leak it. */
    public function test_the_access_token_is_never_sent_to_the_browser(): void
    {
        $this->pixel(['access_token' => 'EAAG-super-secret-token']);

        $this->actingAs($this->user)
            ->get('http://localhost/settings/pixels')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('capi.0.has_token', true)
                ->missing('capi.0.access_token'))
            ->assertDontSee('EAAG-super-secret-token');
    }

    public function test_a_token_can_be_attached_to_a_pixel(): void
    {
        $pixel = $this->pixel();

        $this->actingAs($this->user)
            ->patch('http://localhost/settings/pixels/' . $pixel->id . '/token', [
                'access_token' => 'EAAG-token',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('EAAG-token', $pixel->fresh()->access_token);
    }

    public function test_a_merchant_cannot_attach_a_token_to_another_stores_pixel(): void
    {
        $theirs = Store::factory()->create()->pixels()->create([
            'provider' => 'meta', 'pixel_id' => '999999999999999', 'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->patch('http://localhost/settings/pixels/' . $theirs->id . '/token', [
                'access_token' => 'EAAG-stolen',
            ])
            ->assertForbidden();

        $this->assertNull($theirs->fresh()->access_token);
    }

    // ── Storefront rendering ────────────────────────────────────────────────

    public function test_each_network_renders_its_own_snippet(): void
    {
        $this->pixel(['provider' => 'meta', 'pixel_id' => '111111111111111']);
        $this->pixel(['provider' => 'tiktok', 'pixel_id' => '222222222222222']);
        $this->pixel(['provider' => 'snapchat', 'pixel_id' => '333333333333333']);

        $this->get('http://' . $this->store->platformHost())
            ->assertOk()
            ->assertSee('fbq(', false)
            ->assertSee('ttq.load', false)
            ->assertSee('snaptr(', false)
            ->assertSee('111111111111111')
            ->assertSee('222222222222222')
            ->assertSee('333333333333333');
    }

    /** A store with only TikTok must not emit Meta's snippet. */
    public function test_only_the_networks_in_use_are_rendered(): void
    {
        $this->pixel(['provider' => 'tiktok', 'pixel_id' => '222222222222222']);

        $this->get('http://' . $this->store->platformHost())
            ->assertOk()
            ->assertSee('ttq.load', false)
            ->assertDontSee('fbq(', false)
            ->assertDontSee('snaptr(', false);
    }

    public function test_a_disabled_pixel_does_not_reach_the_storefront(): void
    {
        $this->pixel(['pixel_id' => '111111111111111', 'is_active' => true]);
        $this->pixel(['pixel_id' => '222222222222222', 'is_active' => false]);

        $this->get('http://' . $this->store->platformHost())
            ->assertOk()
            ->assertSee('111111111111111')
            ->assertDontSee('222222222222222');
    }
}

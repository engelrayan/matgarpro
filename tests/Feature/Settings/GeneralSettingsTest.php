<?php

namespace Tests\Feature\Settings;

use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The consolidated account-and-domain screen.
 *
 * It renders four forms that each still post to their own endpoint, so the
 * thing worth testing is that the page hands every one of them what it needs —
 * and that folding four pages into one did not quietly break the old URLs,
 * which is where password-reset mails and merchants' bookmarks point.
 */
class GeneralSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function merchant(): User
    {
        $user = User::factory()->create();
        Store::factory()->for($user)->create(['slug' => 'mahmoud']);

        return $user;
    }

    public function test_it_renders_everything_the_four_forms_need(): void
    {
        $this->actingAs($this->merchant())->get('/settings/general')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/General')
                ->has('store.platform_host')
                ->has('domains')
                ->has('mustVerifyEmail'));
    }

    public function test_a_domains_ssl_state_reaches_the_page(): void
    {
        $user = $this->merchant();

        StoreDomain::factory()->for($user->currentStore())->create([
            'domain' => 'mahmoud.com',
            'status' => StoreDomain::STATUS_ACTIVE,
            'ssl_status' => StoreDomain::SSL_ISSUED,
        ]);

        $this->actingAs($user)->get('/settings/general')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('domains.0.is_secure', true)
                ->where('domains.0.ssl_status', StoreDomain::SSL_ISSUED));
    }

    public function test_it_needs_a_login(): void
    {
        $this->get('/settings/general')->assertRedirect('/login');
    }

    /**
     * The old pages are what a password-reset mail and a merchant's bookmarks
     * point at. Tidying a sidebar is not worth breaking either.
     */
    public function test_the_pages_it_replaced_still_answer(): void
    {
        $user = $this->merchant();

        foreach (['/settings/profile', '/settings/password', '/settings/domains'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }
}

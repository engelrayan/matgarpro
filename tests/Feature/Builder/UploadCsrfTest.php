<?php

namespace Tests\Feature\Builder;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The CSRF contract the builder's image upload (and the AI screens) rely on.
 *
 * Those requests are plain fetch() calls, not Inertia visits, and they sign
 * themselves from the XSRF-TOKEN cookie rather than the page's <meta> tag
 * (resources/js/lib/csrf.ts). The meta tag is written once per page load and
 * goes stale when a merchant signs in through Inertia, which used to make
 * their first upload fail with "CSRF token mismatch".
 *
 * The browser half of that cannot be tested here — the project has no JS test
 * runner. What can be is the server half the browser depends on: every
 * dashboard response hands out the current token in a cookie script can read,
 * and the upload accepts that cookie while refusing a stale token.
 *
 * Laravel skips CSRF checks in tests, so this file switches them back on.
 */
class UploadCsrfTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    protected function setUp(): void
    {
        parent::setUp();

        // The real middleware, minus its "running unit tests" exemption.
        $this->app->singleton(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        Storage::fake('public');

        $this->merchant = User::factory()->create();
        Store::factory()->for($this->merchant)->create();
    }

    public function test_every_dashboard_page_hands_out_the_current_token_in_a_readable_cookie(): void
    {
        $response = $this->actingAs($this->merchant)
            ->withSession(['_token' => 'current-session-token'])
            ->get('/builder/home')
            ->assertOk();

        $this->assertSame('current-session-token', $response->getCookie('XSRF-TOKEN')->getValue());
        // Script has to read it; an HttpOnly cookie would leave lib/csrf.ts
        // falling back to the stale meta tag.
        $this->assertFalse($response->getCookie('XSRF-TOKEN', decrypt: false)->isHttpOnly());
    }

    public function test_an_upload_signed_from_the_cookie_gets_through(): void
    {
        $cookie = $this->actingAs($this->merchant)
            ->withSession(['_token' => 'current-session-token'])
            ->get('/builder/home')
            ->getCookie('XSRF-TOKEN', decrypt: false)
            ->getValue();

        $this->actingAs($this->merchant)
            ->withSession(['_token' => 'current-session-token'])
            ->post('/builder/upload', ['image' => UploadedFile::fake()->image('banner.jpg')], [
                'X-XSRF-TOKEN' => $cookie,
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonStructure(['path', 'url']);
    }

    public function test_an_upload_signed_with_a_stale_token_is_refused(): void
    {
        // What the <meta> tag still held after an Inertia login rotated the
        // session's token.
        $this->actingAs($this->merchant)
            ->withSession(['_token' => 'current-session-token'])
            ->post('/builder/upload', ['image' => UploadedFile::fake()->image('banner.jpg')], [
                'X-CSRF-TOKEN' => 'token-from-before-login',
                'Accept' => 'application/json',
            ])
            ->assertStatus(419);

        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}

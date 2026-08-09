<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The wall between the two applications.
 *
 * Every test here is about one question: can anything other than a signed-in,
 * still-active operator reach the panel? If any of these ever go green in the
 * wrong direction, a merchant login is a platform login.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $attributes = []): Admin
    {
        return Admin::factory()->super()->create([
            'password' => Hash::make('correct-horse-battery-1!'),
            ...$attributes,
        ]);
    }

    public function test_a_guest_is_sent_to_the_admin_login_not_the_merchant_one(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/stores')->assertRedirect('/admin/login');
    }

    public function test_a_signed_in_merchant_cannot_reach_the_panel(): void
    {
        $merchant = User::factory()->create();

        // Authenticated on `web`, which says nothing about `admin`.
        $this->actingAs($merchant)->get('/admin')->assertRedirect('/admin/login');
        $this->actingAs($merchant)->get('/admin/stores')->assertRedirect('/admin/login');
    }

    public function test_an_operator_cannot_reach_the_merchant_dashboard(): void
    {
        $admin = $this->admin();

        /*
         | Signed in through the real login rather than actingAs(): actingAs()
         | also calls shouldUse($guard), which repoints the DEFAULT guard at
         | `admin` for the rest of the test. That would make `auth` on the
         | merchant routes resolve the operator and pass — an artefact of the
         | test helper that never happens in a browser, where the default guard
         | stays `web`.
         */
        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'correct-horse-battery-1!',
        ]);

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_an_operator_can_sign_in_and_reach_the_panel(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'correct-horse-battery-1!',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin, 'admin');
        // Signing in as an operator must not sign anyone in as a merchant.
        $this->assertGuest('web');

        $this->get('/admin')->assertOk();
    }

    public function test_signing_in_is_recorded_with_the_ip(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'correct-horse-battery-1!',
        ]);

        $log = AdminActivityLog::where('action', 'admin.logged_in')->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->admin_id);
        $this->assertNotNull($log->ip);
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_a_deactivated_account_cannot_sign_in(): void
    {
        $admin = $this->admin(['is_active' => false]);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'correct-horse-battery-1!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_deactivation_ends_an_open_session_on_the_next_request(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->get('/admin')->assertOk();

        $admin->forceFill(['is_active' => false])->save();

        $this->actingAs($admin, 'admin')->get('/admin')->assertRedirect('/admin/login');
        $this->assertGuest('admin');
    }

    public function test_a_wrong_password_is_rejected_and_leaves_no_session(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_repeated_failures_lock_the_account_out(): void
    {
        $admin = $this->admin();

        // Five is the per-account ceiling; the sixth must not even be tried.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong']);
        }

        $response = $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'correct-horse-battery-1!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    public function test_support_staff_cannot_reach_the_screens_that_change_the_rules(): void
    {
        $staff = Admin::factory()->create(); // staff by default

        $this->actingAs($staff, 'admin')->get('/admin/plans')->assertNotFound();
        $this->actingAs($staff, 'admin')->get('/admin/admins')->assertNotFound();

        // …but the day-to-day screens are theirs.
        $this->actingAs($staff, 'admin')->get('/admin/stores')->assertOk();
        $this->actingAs($staff, 'admin')->get('/admin/activity')->assertOk();
    }

    public function test_logging_out_clears_the_session(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->post('/admin/logout')->assertRedirect('/admin/login');

        $this->assertGuest('admin');
    }
}

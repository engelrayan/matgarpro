<?php

namespace Tests\Feature\AI;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The landing page's "بتبيع إيه؟" box: what a visitor types there is waiting
 * in the AI store builder once they have signed up.
 */
class StartWithAiTest extends TestCase
{
    use RefreshDatabase;

    private const BUSINESS = 'هدوم أطفال قطن من سنة لـ ٦ سنين';

    public function test_the_landing_page_asks_what_they_sell(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('action="'.route('start').'"', false)
            ->assertSee('name="business"', false);
    }

    public function test_a_visitor_is_sent_to_signup_which_shows_what_they_typed(): void
    {
        $this->post('/start', ['business' => self::BUSINESS])
            ->assertRedirect(route('register', absolute: false));

        $this->get('/register')
            ->assertInertia(fn ($page) => $page->where('aiStart', self::BUSINESS));
    }

    public function test_signup_ends_on_the_builder_with_the_description_filled_in(): void
    {
        $this->post('/start', ['business' => self::BUSINESS]);

        $this->post('/register', [
            'name' => 'منى',
            'store_name' => 'Mona Kids',
            'email' => 'mona@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('ai.store-builder', absolute: false));

        $this->get(route('ai.store-builder'))
            ->assertInertia(fn ($page) => $page
                ->component('ai/StoreBuilder')
                ->where('prefill', self::BUSINESS));

        // Once: coming back to the builder later starts from an empty form.
        $this->get(route('ai.store-builder'))
            ->assertInertia(fn ($page) => $page->where('prefill', null));
    }

    public function test_an_empty_box_still_takes_the_ai_route(): void
    {
        $this->post('/start', ['business' => '']);

        $this->get('/register')
            ->assertInertia(fn ($page) => $page->where('aiStart', ''));
    }

    public function test_a_plain_signup_still_ends_on_the_dashboard(): void
    {
        $this->get('/register')
            ->assertInertia(fn ($page) => $page->where('aiStart', null));
    }

    public function test_a_signed_in_merchant_goes_straight_to_the_builder(): void
    {
        $user = User::factory()->create();
        Store::factory()->for($user)->create();

        $this->actingAs($user)
            ->post('/start', ['business' => self::BUSINESS])
            ->assertRedirect(route('ai.store-builder', absolute: false));

        $this->actingAs($user)
            ->get(route('ai.store-builder'))
            ->assertInertia(fn ($page) => $page->where('prefill', self::BUSINESS));
    }

    public function test_the_description_is_capped(): void
    {
        $this->post('/start', ['business' => str_repeat('ا', 601)])
            ->assertSessionHasErrors('business');
    }
}

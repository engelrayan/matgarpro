<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Meta will not put the platform's WhatsApp number live without a public
 * privacy policy that says how to have data deleted.
 */
class PrivacyPageTest extends TestCase
{
    public function test_the_privacy_policy_is_public_and_says_how_to_delete_data(): void
    {
        config(['app.support_whatsapp' => '01006262330', 'app.support_email' => 'privacy@matgarpro.com']);

        $this->get('http://localhost/privacy')
            ->assertOk()
            ->assertSee('سياسة الخصوصية')
            ->assertSee('id="deletion"', false)
            ->assertSee('https://wa.me/201006262330', false)
            ->assertSee('privacy@matgarpro.com');
    }

    public function test_the_home_page_links_to_it(): void
    {
        $this->get('http://localhost/')->assertOk()->assertSee(route('privacy'), false);
    }
}

<?php

namespace Tests\Feature\Storefront;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The checkout honeypot must stay invisible without moving the page.
 *
 * It once sat at `left:-9999px`. The storefront is RTL, and in RTL a box
 * pushed to the left is inside the scrollable area: the product page became
 * ten thousand pixels wide and phones painted its first screen blank — on the
 * one page every ad sends traffic to.
 */
class HoneypotLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_honeypot_is_on_the_product_page_without_an_off_screen_offset(): void
    {
        $store = Store::factory()->create(['status' => Store::STATUS_ACTIVE]);
        $product = Product::factory()->for($store)->create(['status' => Product::STATUS_ACTIVE]);

        $html = $this->get($store->canonicalUrl() . '/p/' . $product->slug)
            ->assertOk()
            ->assertSee('name="confirm_url"', false)
            ->getContent();

        // The trap is still there for bots…
        $this->assertMatchesRegularExpression('/<div aria-hidden="true" style="[^"]*clip-path:inset\(50%\)/', $html);

        // …and nothing on the page is shoved off-screen to hide it.
        $this->assertDoesNotMatchRegularExpression('/\b(left|right)\s*:\s*-\d/', $html);
    }
}

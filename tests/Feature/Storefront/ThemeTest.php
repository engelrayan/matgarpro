<?php

namespace Tests\Feature\Storefront;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Storefront\ThemeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private ThemeResolver $themes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create(['slug' => 'mahmoud', 'name' => 'متجر محمود']);
        $this->themes = app(ThemeResolver::class);
    }

    private function home(): string
    {
        return 'http://' . $this->store->platformHost() . '/';
    }

    public function test_a_store_with_no_choice_gets_the_default_theme(): void
    {
        $this->assertSame(config('themes.default'), $this->themes->forStore($this->store)['key']);
    }

    /**
     * A theme removed from the platform must not break the stores that chose
     * it — an old store looking slightly different beats an unstyled one.
     */
    public function test_an_unknown_saved_theme_falls_back_instead_of_breaking(): void
    {
        $this->store->update(['settings' => ['theme' => 'theme-we-deleted']]);

        $theme = $this->themes->forStore($this->store->fresh());

        $this->assertSame(config('themes.default'), $theme['key']);
        $this->assertNotEmpty($theme['palette']);
    }

    /** Every shipped theme must define every token the layout paints with. */
    public function test_every_theme_defines_the_full_palette(): void
    {
        $required = array_keys(config('themes.themes.' . config('themes.default') . '.palette'));

        foreach ($this->themes->all() as $theme) {
            foreach ($required as $token) {
                $this->assertArrayHasKey($token, $theme['palette'], "{$theme['key']} is missing --{$token}");
            }

            $this->assertNotEmpty($theme['name'], "{$theme['key']} has no name");
            $this->assertNotEmpty($theme['description'], "{$theme['key']} has no description");
        }
    }

    /**
     * Tailwind composes these with opacity modifiers, which only works on raw
     * `H S% L%`. A hex value here renders as a broken colour everywhere.
     */
    public function test_palette_values_are_raw_hsl_triplets(): void
    {
        foreach ($this->themes->all() as $theme) {
            foreach ($theme['palette'] as $token => $value) {
                $this->assertMatchesRegularExpression(
                    '/^\d+(\.\d+)? \d+(\.\d+)?% \d+(\.\d+)?%$/',
                    $value,
                    "{$theme['key']}.{$token} is not an HSL triplet",
                );
            }
        }
    }

    public function test_the_storefront_renders_the_chosen_palette(): void
    {
        Product::factory()->for($this->store)->create();

        $this->store->update(['settings' => ['theme' => 'coral']]);

        $this->get($this->home())
            ->assertOk()
            ->assertSee('--primary:' . config('themes.themes.coral.palette.primary'), false)
            ->assertSee('data-layout="' . config('themes.themes.coral.layout') . '"', false);
    }

    public function test_a_merchant_can_switch_theme(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->for($user)->create();

        $this->actingAs($user)
            ->put('http://localhost/settings/themes', ['theme' => 'noir'])
            ->assertSessionHasNoErrors();

        $this->assertSame('noir', $store->fresh()->settings['theme']);
    }

    public function test_an_unknown_theme_is_rejected_on_save(): void
    {
        $user = User::factory()->create();
        Store::factory()->for($user)->create();

        $this->actingAs($user)
            ->put('http://localhost/settings/themes', ['theme' => 'not-a-theme'])
            ->assertSessionHasErrors('theme');
    }

    /** Switching the theme must not wipe the merchant's other settings. */
    public function test_switching_theme_preserves_other_settings(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->for($user)->create([
            'settings' => ['checkout_fields' => ['note' => ['enabled' => true]]],
        ]);

        $this->actingAs($user)->put('http://localhost/settings/themes', ['theme' => 'rose']);

        $settings = $store->fresh()->settings;

        $this->assertSame('rose', $settings['theme']);
        $this->assertTrue($settings['checkout_fields']['note']['enabled']);
    }

    // ── Storefront pages ────────────────────────────────────────────────────

    public function test_the_home_page_lists_active_products_only(): void
    {
        Product::factory()->for($this->store)->create(['name' => 'منشور', 'status' => Product::STATUS_ACTIVE]);
        Product::factory()->for($this->store)->create(['name' => 'مسودة', 'status' => Product::STATUS_DRAFT]);

        $this->get($this->home())->assertOk()->assertSee('منشور')->assertDontSee('مسودة');
    }

    /** An empty section on the storefront is a dead end for the customer. */
    public function test_empty_categories_are_not_shown(): void
    {
        Product::factory()->for($this->store)->create();

        $full = Category::create(['store_id' => $this->store->id, 'name' => 'رجالي', 'slug' => 'men']);
        Category::create(['store_id' => $this->store->id, 'name' => 'فاضي', 'slug' => 'empty']);

        $full->products()->attach(Product::factory()->for($this->store)->create()->id);

        $this->get($this->home())->assertOk()->assertSee('رجالي')->assertDontSee('فاضي');
    }

    public function test_a_category_page_lists_its_products(): void
    {
        $category = Category::create(['store_id' => $this->store->id, 'name' => 'رجالي', 'slug' => 'men']);

        $inside = Product::factory()->for($this->store)->create(['name' => 'قميص رجالي']);
        Product::factory()->for($this->store)->create(['name' => 'فستان']);

        $category->products()->attach($inside->id);

        $this->get('http://' . $this->store->platformHost() . '/c/men')
            ->assertOk()
            ->assertSee('قميص رجالي')
            ->assertDontSee('فستان');
    }

    public function test_an_inactive_category_is_not_reachable(): void
    {
        Category::create([
            'store_id' => $this->store->id, 'name' => 'مخفي', 'slug' => 'hidden', 'is_active' => false,
        ]);

        $this->get('http://' . $this->store->platformHost() . '/c/hidden')->assertNotFound();
    }

    /** One store's categories must never appear under another's hostname. */
    public function test_categories_are_scoped_to_their_store(): void
    {
        $other = Store::factory()->create(['slug' => 'other']);
        Category::create(['store_id' => $other->id, 'name' => 'تبع غيره', 'slug' => 'theirs']);

        $this->get('http://' . $this->store->platformHost() . '/c/theirs')->assertNotFound();
    }
}

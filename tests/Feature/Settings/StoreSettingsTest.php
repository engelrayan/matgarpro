<?php

namespace Tests\Feature\Settings;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::factory()->create();
        $this->store = Store::factory()->for($this->user)->create(['slug' => 'mahmoud']);
    }

    public function test_a_merchant_can_upload_a_logo(): void
    {
        $this->actingAs($this->user)
            ->post('http://localhost/settings/store', [
                'name' => 'متجر محمود',
                'logo' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertSessionHasNoErrors();

        $this->store->refresh();

        $this->assertNotNull($this->store->logo_path);
        Storage::disk('public')->assertExists($this->store->logo_path);
        $this->assertSame('متجر محمود', $this->store->name);
    }

    /** A replaced logo must not leave the old file behind forever. */
    public function test_replacing_a_logo_deletes_the_old_file(): void
    {
        $this->actingAs($this->user)->post('http://localhost/settings/store', [
            'name' => 'متجر محمود',
            'logo' => UploadedFile::fake()->image('first.png'),
        ]);

        $first = $this->store->fresh()->logo_path;

        $this->actingAs($this->user)->post('http://localhost/settings/store', [
            'name' => 'متجر محمود',
            'logo' => UploadedFile::fake()->image('second.png'),
        ]);

        $second = $this->store->fresh()->logo_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_a_merchant_can_remove_the_logo(): void
    {
        $this->actingAs($this->user)->post('http://localhost/settings/store', [
            'name' => 'متجر محمود',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $path = $this->store->fresh()->logo_path;

        $this->actingAs($this->user)->post('http://localhost/settings/store', [
            'name' => 'متجر محمود',
            'remove_logo' => true,
        ]);

        $this->assertNull($this->store->fresh()->logo_path);
        Storage::disk('public')->assertMissing($path);
    }

    // ── Order form ──────────────────────────────────────────────────────────

    public function test_the_default_form_is_short(): void
    {
        $enabled = array_keys($this->store->enabledCheckoutFields());

        $this->assertSame(
            ['customer_name', 'customer_phone', 'governorate', 'address'],
            $enabled,
            'a cash-on-delivery form should start with four fields, not eight',
        );
    }

    public function test_a_merchant_can_switch_a_field_on_and_rename_it(): void
    {
        $this->actingAs($this->user)
            ->put('http://localhost/settings/checkout', [
                'fields' => [
                    ['key' => 'customer_name', 'label' => 'اسمك الكامل', 'order' => 1, 'enabled' => true, 'required' => true],
                    ['key' => 'customer_phone', 'label' => 'رقم الموبايل', 'order' => 2, 'enabled' => true, 'required' => true],
                    ['key' => 'address', 'label' => 'العنوان', 'order' => 3, 'enabled' => true, 'required' => true],
                    ['key' => 'note', 'label' => 'أي ملاحظة؟', 'order' => 4, 'enabled' => true, 'required' => false],
                ],
            ])
            ->assertSessionHasNoErrors();

        $fields = $this->store->fresh()->checkoutFields();

        $this->assertSame('اسمك الكامل', $fields['customer_name']['label']);
        $this->assertTrue($fields['note']['enabled']);
        $this->assertFalse($fields['note']['required']);

        // Omitted means "unchanged", not "off". The settings screen always
        // posts every field, so a payload missing one is a partial call — and
        // wiping the merchant's form because of it would be the worse failure.
        $this->assertTrue($fields['governorate']['enabled']);
    }

    public function test_switching_a_field_off_requires_saying_so(): void
    {
        $this->actingAs($this->user)
            ->put('http://localhost/settings/checkout', [
                'fields' => [
                    ['key' => 'governorate', 'label' => 'المحافظة', 'order' => 3, 'enabled' => false, 'required' => false],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($this->store->fresh()->checkoutFields()['governorate']['enabled']);
    }

    /** A key we do not ship must not survive into the stored settings. */
    public function test_unknown_fields_are_ignored(): void
    {
        $this->actingAs($this->user)->put('http://localhost/settings/checkout', [
            'fields' => [
                ['key' => 'national_id', 'label' => 'الرقم القومي', 'order' => 1, 'enabled' => true, 'required' => true],
            ],
        ]);

        $this->assertArrayNotHasKey(
            'national_id',
            $this->store->fresh()->settings['checkout_fields'],
        );
    }

    /**
     * A cash-on-delivery order needs a name and a reachable phone. The client
     * must not be able to unlock them, however the request is crafted.
     */
    public function test_locked_fields_cannot_be_switched_off(): void
    {
        $this->actingAs($this->user)->put('http://localhost/settings/checkout', [
            'fields' => [
                ['key' => 'customer_name', 'label' => 'اسمك', 'order' => 1, 'enabled' => false, 'required' => false],
                ['key' => 'customer_phone', 'label' => 'موبايلك', 'order' => 2, 'enabled' => false, 'required' => false],
            ],
        ]);

        $fields = $this->store->fresh()->checkoutFields();

        foreach (['customer_name', 'customer_phone'] as $key) {
            $this->assertTrue($fields[$key]['enabled'], "{$key} must stay on");
            $this->assertTrue($fields[$key]['required'], "{$key} must stay required");
        }
    }

    public function test_the_storefront_renders_the_configured_form(): void
    {
        $this->store->update([
            'settings' => ['checkout_fields' => [
                'note' => ['label' => 'أي ملاحظة؟', 'enabled' => true, 'required' => false, 'order' => 9, 'placeholder' => ''],
                'governorate' => ['label' => 'المحافظة', 'enabled' => false, 'required' => false, 'order' => 3, 'placeholder' => ''],
            ]],
        ]);

        $product = Product::factory()->for($this->store)->create();

        $this->get('http://' . $this->store->platformHost() . '/p/' . $product->slug)
            ->assertOk()
            ->assertSee('أي ملاحظة؟')
            ->assertDontSee('اختار المحافظة');
    }

    /** A field the merchant switched off cannot be required by the server. */
    public function test_a_disabled_field_is_not_validated(): void
    {
        $this->store->update([
            'settings' => ['checkout_fields' => [
                'address' => ['label' => 'العنوان', 'enabled' => false, 'required' => false, 'order' => 5, 'placeholder' => ''],
            ]],
        ]);

        $product = Product::factory()->for($this->store)->create();

        $this->post('http://' . $this->store->platformHost() . '/checkout', [
            'product_id' => $product->id,
            'quantity' => 1,
            'customer_name' => 'محمود',
            'customer_phone' => '01006262330',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->store->orders()->count());
    }

    public function test_a_field_the_merchant_made_required_is_enforced(): void
    {
        $this->store->update([
            'settings' => ['checkout_fields' => [
                'customer_email' => ['label' => 'بريدك', 'enabled' => true, 'required' => true, 'order' => 7, 'placeholder' => ''],
            ]],
        ]);

        $product = Product::factory()->for($this->store)->create();

        $this->post('http://' . $this->store->platformHost() . '/checkout', [
            'product_id' => $product->id,
            'quantity' => 1,
            'customer_name' => 'محمود',
            'customer_phone' => '01006262330',
            'address' => 'القاهرة',
        ])->assertSessionHasErrors('customer_email');
    }
}

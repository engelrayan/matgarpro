<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\BillingPlan;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The plan editor, after the monthly-fee and included-orders fields were
 * removed for never having been enforced.
 *
 * The point of these is that the screen still saves a plan with the fields
 * gone — an operator editing pricing is the one person whose form must not
 * fail quietly.
 */
class AdminPlansTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->super()->create();
    }

    public function test_the_plans_screen_renders(): void
    {
        BillingPlan::factory()->create(['code' => 'standard', 'price_per_order' => 0.50]);

        $this->actingAs($this->admin(), 'admin')
            ->get('http://localhost/admin/plans')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Plans')
                ->where('plans.0.price_per_order', 0.50));
    }

    public function test_an_operator_can_create_a_plan_without_the_removed_fields(): void
    {
        // One active default has to exist at all times, or new stores open with
        // no plan — the controller refuses the save otherwise.
        BillingPlan::factory()->create(['code' => 'standard', 'is_default' => true]);

        $this->actingAs($this->admin(), 'admin')
            ->post('http://localhost/admin/plans', [
                'code' => 'seasonal',
                'name' => 'موسمي',
                'description' => 'عرض مؤقت',
                'price_per_order' => 0.25,
                'billable_event' => 'created',
                'is_default' => false,
                'is_public' => true,
                'is_active' => true,
                'sort_order' => 5,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(0.25, (float) BillingPlan::firstWhere('code', 'seasonal')->price_per_order);
    }

    /**
     * `Store::booted()` reads `BillingPlan::default()`, which takes the first
     * match — two defaults would make a new store's price depend on row order.
     */
    public function test_promoting_a_plan_to_default_demotes_the_old_one(): void
    {
        $old = BillingPlan::factory()->create(['code' => 'old', 'is_default' => true]);
        $new = BillingPlan::factory()->create(['code' => 'new', 'is_default' => false]);

        $this->actingAs($this->admin(), 'admin')
            ->patch("http://localhost/admin/plans/{$new->id}", [
                'code' => $new->code,
                'name' => $new->name,
                'description' => '',
                'price_per_order' => 0.50,
                'billable_event' => 'created',
                'is_default' => true,
                'is_public' => true,
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $this->assertTrue($new->refresh()->is_default);
        $this->assertFalse($old->refresh()->is_default);
        $this->assertSame($new->id, BillingPlan::default()->id);
    }

    /**
     * Retiring a plan must not repoint the stores already on it — their usage
     * history records the plan each charge was billed under.
     */
    public function test_the_seeder_moves_stores_off_retired_plans_onto_the_public_one(): void
    {
        $free = BillingPlan::factory()->create(['code' => 'free', 'price_per_order' => 0, 'is_default' => true]);
        $store = Store::factory()->create(['billing_plan_id' => $free->id]);

        $this->seed(\Database\Seeders\BillingPlanSeeder::class);

        $standard = BillingPlan::firstWhere('code', 'standard');

        $this->assertSame($standard->id, $store->refresh()->billing_plan_id);
        $this->assertSame(0.50, $store->priceAfterTrial());
        $this->assertFalse($free->refresh()->is_active);
        $this->assertTrue($standard->is_default);
    }
}

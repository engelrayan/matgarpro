<?php

namespace Database\Factories;

use App\Models\BillingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingPlan>
 */
class BillingPlanFactory extends Factory
{
    protected $model = BillingPlan::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'price_per_order' => 1.00,
            'billable_event' => 'created',
            'is_default' => false,
            'is_public' => true,
            'is_active' => true,
        ];
    }

    public function free(): static
    {
        return $this->state(['price_per_order' => 0]);
    }
}

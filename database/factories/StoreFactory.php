<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numerify('####'),
            'currency' => 'EGP',
            'status' => Store::STATUS_ACTIVE,
            'billing_status' => 'active',
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => Store::STATUS_DRAFT]);
    }

    public function suspended(): static
    {
        return $this->state(['status' => Store::STATUS_SUSPENDED]);
    }
}

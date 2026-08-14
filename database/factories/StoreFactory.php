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
            /*
             | Past the trial by default, so a billing test is testing billing.
             | A real store opens with three free months, and leaving that on
             | here would quietly price every charge in the suite at zero and
             | turn green into no evidence at all. Use `onTrial()` to test it.
             */
            'trial_ends_at' => null,
        ];
    }

    /** A store inside its free months. */
    public function onTrial(?int $daysLeft = null): static
    {
        return $this->state([
            'trial_ends_at' => now()->addDays($daysLeft ?? 90),
        ]);
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

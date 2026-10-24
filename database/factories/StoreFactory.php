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
             | Past the introductory months by default, so a billing test is
             | testing the plan price. A real store opens with three cheaper
             | months, and leaving that on here would quietly discount every
             | charge in the suite. Use `inIntroPeriod()` to test it.
             */
            'trial_ends_at' => null,
        ];
    }

    /** A store inside its introductory months. */
    public function inIntroPeriod(?int $daysLeft = null): static
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

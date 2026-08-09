<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\StoreDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreDomain>
 */
class StoreDomainFactory extends Factory
{
    protected $model = StoreDomain::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'domain' => fake()->unique()->domainName(),
            'is_primary' => false,
            'status' => StoreDomain::STATUS_PENDING,
            'verification_token' => StoreDomain::mintToken(),
        ];
    }

    public function active(): static
    {
        return $this->state([
            'status' => StoreDomain::STATUS_ACTIVE,
            'verified_at' => now(),
        ]);
    }

    public function primary(): static
    {
        return $this->state(['is_primary' => true]);
    }
}

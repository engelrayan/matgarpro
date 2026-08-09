<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 100, 2000);

        return [
            'store_id' => Store::factory(),
            // Sequential per store. `number` is unique with store_id, so a
            // random value here would collide as soon as a test makes a few.
            'number' => function (array $attributes) {
                return (int) Order::withTrashed()
                    ->where('store_id', $attributes['store_id'])
                    ->max('number') + 1;
            },
            'customer_name' => $this->faker->name(),
            'customer_phone' => '010' . $this->faker->numerify('########'),
            'governorate' => 'القاهرة',
            'address' => $this->faker->address(),
            'subtotal' => $subtotal,
            'shipping_amount' => 0,
            'discount_amount' => 0,
            'total' => $subtotal,
            'status' => Order::STATUS_PENDING,
            'payment_method' => 'cod',
        ];
    }

    public function delivered(): static
    {
        return $this->state(['status' => Order::STATUS_DELIVERED]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => Order::STATUS_CANCELLED]);
    }
}

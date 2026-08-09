<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $unitPrice = $this->faker->randomFloat(2, 50, 800);
        $quantity = $this->faker->numberBetween(1, 3);

        return [
            'order_id' => Order::factory(),
            'product_id' => null,
            'product_variant_id' => null,
            // The snapshot, not a relation read: an order line has to keep
            // saying what was sold even after the product is renamed or gone.
            'name' => $this->faker->words(2, true),
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'total' => $unitPrice * $quantity,
        ];
    }
}

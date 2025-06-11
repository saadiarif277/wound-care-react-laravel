<?php

namespace Database\Factories\Order;

use App\Models\Order\OrderItem;
use App\Models\Order\Order;
use App\Models\Order\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 5);
        $price = $this->faker->randomFloat(2, 50, 500);
        $totalAmount = $quantity * $price;
        
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'price' => $price,
            'unit' => 'per sq cm',
            'size' => $this->faker->randomElement(['2x2', '4x4', '5x5', '10x10']),
            'total_amount' => $totalAmount,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function withSpecificQuantity(int $quantity): static
    {
        return $this->state(function (array $attributes) use ($quantity) {
            return [
                'quantity' => $quantity,
                'total_amount' => $quantity * $attributes['price'],
            ];
        });
    }

    public function withSpecificPrice(float $price): static
    {
        return $this->state(function (array $attributes) use ($price) {
            return [
                'price' => $price,
                'total_amount' => $attributes['quantity'] * $price,
            ];
        });
    }
}
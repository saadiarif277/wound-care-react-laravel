<?php

namespace Database\Factories\Order;

use App\Models\Order\Product;
use App\Models\Order\Category;
use App\Models\Order\Manufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true) . ' Wound Dressing',
            'sku' => strtoupper($this->faker->bothify('??#####')),
            'q_code' => 'Q' . $this->faker->numberBetween(4100, 4999),
            'description' => $this->faker->paragraph(),
            'category' => $this->faker->randomElement(['wound_care', 'skin_substitutes', 'compression', 'negative_pressure']),
            'manufacturer' => $this->faker->company(),
            'hcpcs_code' => 'Q' . $this->faker->numberBetween(4100, 4999),
            'sizes' => json_encode([
                ['size' => '2x2', 'unit' => 'cm'],
                ['size' => '4x4', 'unit' => 'cm'],
                ['size' => '5x5', 'unit' => 'cm']
            ]),
            'billing_unit' => 'per sq cm',
            'units_per_box' => $this->faker->randomElement([1, 5, 10]),
            'national_asp_price' => $this->faker->randomFloat(2, 50, 500),
            'msc_price' => $this->faker->randomFloat(2, 40, 400),
            'requires_prior_auth' => $this->faker->boolean(30),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function woundCare(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'wound_care',
            'name' => $this->faker->randomElement(['Collagen', 'Alginate', 'Hydrogel', 'Foam']) . ' Wound Dressing',
        ]);
    }

    public function skinSubstitute(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'skin_substitutes',
            'name' => $this->faker->randomElement(['Dermal', 'Epidermal', 'Bilayer']) . ' Skin Substitute',
            'requires_prior_auth' => true,
        ]);
    }
}
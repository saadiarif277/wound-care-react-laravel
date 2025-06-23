<?php

namespace Database\Factories\Order;

use App\Models\Order\Manufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;

class ManufacturerFactory extends Factory
{
    protected $model = Manufacturer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'contact_email' => $this->faker->companyEmail(),
            'contact_phone' => $this->faker->phoneNumber(),
            'address' => [],
            'website' => $this->faker->url(),
            'is_active' => true,
            'notes' => null,
        ];
    }
}
<?php

namespace Database\Factories;

use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Manufacturer>
 */
class ManufacturerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $companies = ['MedTech', 'WoundCare', 'HealthSupply', 'MediPro', 'CareMax'];
        $name = fake()->randomElement($companies) . ' ' . fake()->randomElement(['Solutions', 'Industries', 'Medical', 'Corp']);
        
        return [
            'name' => $name,
            'code' => strtoupper(substr($name, 0, 3) . fake()->numerify('##')),
            'default_email' => fake()->companyEmail(),
            'website' => fake()->url(),
            'phone' => fake()->numerify('###-###-####'),
            'address' => [
                'line' => [fake()->streetAddress()],
                'city' => fake()->city(),
                'state' => fake()->stateAbbr(),
                'postalCode' => fake()->postcode(),
                'country' => 'USA',
            ],
            'active' => true,
            'requires_medical_review' => fake()->boolean(30),
            'requires_office_manager_approval' => fake()->boolean(50),
            'has_ivr_template' => true,
            'product_categories' => fake()->randomElements(['dressing', 'compression', 'debridement', 'offloading'], rand(1, 4)),
            'metadata' => [],
        ];
    }

    /**
     * Indicate that the manufacturer is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Indicate that the manufacturer requires medical review.
     */
    public function requiresMedicalReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_medical_review' => true,
        ]);
    }
}
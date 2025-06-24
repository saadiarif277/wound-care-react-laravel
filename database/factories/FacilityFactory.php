<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Facility>
 */
class FacilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->company() . ' ' . fake()->randomElement(['Center', 'Clinic', 'Facility', 'Unit']),
            'type' => fake()->randomElement(['outpatient', 'inpatient', 'emergency', 'rehabilitation']),
            'npi' => fake()->numerify('##########'),
            'address' => [
                'line' => [fake()->streetAddress()],
                'city' => fake()->city(),
                'state' => fake()->stateAbbr(),
                'postalCode' => fake()->postcode(),
                'country' => 'USA',
            ],
            'phone' => fake()->numerify('###-###-####'),
            'fax' => fake()->numerify('###-###-####'),
            'email' => fake()->companyEmail(),
            'office_manager_email' => fake()->safeEmail(),
            'active' => true,
            'metadata' => [],
        ];
    }

    /**
     * Indicate that the facility is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
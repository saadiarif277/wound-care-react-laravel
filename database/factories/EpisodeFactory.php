<?php

namespace Database\Factories;

use App\Models\Episode;
use App\Models\Organization;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Episode>
 */
class EpisodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['draft', 'pending_review', 'manufacturer_review', 'approved', 'completed', 'cancelled'];
        
        return [
            'patient_fhir_id' => 'Patient/' . fake()->uuid(),
            'practitioner_fhir_id' => 'Practitioner/' . fake()->uuid(),
            'organization_fhir_id' => 'Organization/' . fake()->uuid(),
            'episode_of_care_fhir_id' => 'EpisodeOfCare/' . fake()->uuid(),
            'patient_display' => strtoupper(fake()->lexify('??') . fake()->lexify('??') . fake()->numerify('###')),
            'status' => fake()->randomElement($statuses),
            'manufacturer_id' => Manufacturer::factory(),
            'organization_id' => Organization::factory(),
            'facility_id' => null, // Set in seeder or test
            'created_by' => User::factory(),
            'updated_by' => null,
            'approved_by' => null,
            'approved_at' => null,
            'cancelled_at' => null,
            'metadata' => [],
            'insurance_data' => [
                'primary' => [
                    'type' => 'medicare',
                    'policyNumber' => fake()->numerify('MED########'),
                    'subscriberId' => fake()->numerify('SUB########'),
                ],
            ],
        ];
    }

    /**
     * Indicate that the episode is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate that the episode is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'approved_by' => User::factory(),
            'approved_at' => now()->subDays(2),
        ]);
    }

    /**
     * Indicate that the episode is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }
}
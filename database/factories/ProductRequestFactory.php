<?php

namespace Database\Factories;

use App\Models\ProductRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductRequestFactory extends Factory
{
    protected $model = ProductRequest::class;

    public function definition(): array
    {
        return [
            'request_number' => 'PR-' . strtoupper($this->faker->bothify('####??##')),
            'provider_id' => User::factory(),
            'patient_fhir_id' => $this->faker->uuid(),
            'patient_display_id' => $this->generatePatientDisplayId(),
            'facility_id' => $this->faker->numberBetween(1, 10),
            'payer_name_submitted' => $this->faker->company(),
            'payer_id' => $this->faker->bothify('??####'),
            'expected_service_date' => $this->faker->dateTimeBetween('now', '+30 days'),
            'wound_type' => $this->faker->randomElement(['DFU', 'VLU', 'PU', 'TW', 'AU', 'OTHER']),
            'azure_order_checklist_fhir_id' => $this->faker->uuid(),
            'clinical_summary' => [
                'wound_location' => $this->faker->randomElement(['Left foot', 'Right leg', 'Sacral', 'Heel']),
                'wound_duration' => $this->faker->numberBetween(4, 52) . ' weeks',
                'conservative_care_duration' => $this->faker->numberBetween(4, 12) . ' weeks',
            ],
            'mac_validation_results' => null,
            'mac_validation_status' => $this->faker->randomElement(['not_checked', 'pending', 'passed', 'warning', 'failed']),
            'eligibility_results' => null,
            'eligibility_status' => $this->faker->randomElement(['not_checked', 'pending', 'eligible', 'not_eligible', 'needs_review']),
            'pre_auth_required_determination' => $this->faker->randomElement(['pending_determination', 'required', 'not_required', 'unknown']),
            'clinical_opportunities' => null,
            'order_status' => $this->faker->randomElement(['draft', 'submitted', 'processing', 'approved', 'rejected']),
            'step' => $this->faker->numberBetween(1, 6),
            'submitted_at' => null,
            'total_order_value' => $this->faker->randomFloat(2, 100, 5000),
            'acquiring_rep_id' => null,
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    private function generatePatientDisplayId(): string
    {
        $firstName = $this->faker->firstName();
        $lastName = $this->faker->lastName();
        $initials = substr($firstName, 0, 2) . substr($lastName, 0, 2);
        $number = str_pad($this->faker->numberBetween(1, 999), 3, '0', STR_PAD_LEFT);

        return $initials . $number; // Format: "JoSm001"
    }

    public function submitted(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'order_status' => 'submitted',
                'step' => 6,
                'submitted_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
                'mac_validation_status' => 'passed',
                'eligibility_status' => 'eligible',
            ];
        });
    }

    public function woundCare(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'wound_type' => $this->faker->randomElement(['DFU', 'VLU', 'PU']),
                'clinical_summary' => array_merge($attributes['clinical_summary'] ?? [], [
                    'specialty' => 'wound_care_specialty',
                    'wound_classification' => 'chronic',
                ]),
            ];
        });
    }

    public function pulmonologyWoundCare(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'wound_type' => $this->faker->randomElement(['DFU', 'PU', 'TW']),
                'clinical_summary' => array_merge($attributes['clinical_summary'] ?? [], [
                    'specialty' => 'pulmonology_wound_care',
                    'respiratory_condition' => $this->faker->randomElement(['COPD', 'Asthma', 'Sleep Apnea']),
                    'oxygen_therapy' => $this->faker->boolean(),
                ]),
            ];
        });
    }

    public function readyForValidation(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'step' => 4,
                'order_status' => 'processing',
                'clinical_summary' => array_merge($attributes['clinical_summary'] ?? [], [
                    'documentation_complete' => true,
                    'photos_uploaded' => true,
                    'measurements_recorded' => true,
                ]),
            ];
        });
    }
}

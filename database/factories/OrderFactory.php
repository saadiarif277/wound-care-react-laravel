<?php

namespace Database\Factories;

use App\Models\Order\Order;
use App\Models\User;
use App\Models\Fhir\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number' => 'ORD-' . strtoupper($this->faker->bothify('####??##')),
            'user_id' => User::factory(),
            'facility_id' => $this->faker->numberBetween(1, 10),
            'patient_first_name' => $this->faker->firstName(),
            'patient_last_name' => $this->faker->lastName(),
            'patient_dob' => $this->faker->date('Y-m-d', '-18 years'),
            'patient_gender' => $this->faker->randomElement(['male', 'female', 'other']),
            'patient_member_id' => $this->faker->bothify('??######'),
            'payer_name' => $this->faker->company(),
            'payer_id' => $this->faker->bothify('??####'),
            'diagnosis_codes' => $this->faker->randomElements(['E11.621', 'I70.202', 'L97.429'], 2),
            'procedure_codes' => $this->faker->randomElements(['97597', '97598', '15271'], 1),
            'expected_service_date' => $this->faker->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'wound_type' => $this->faker->randomElement(['DFU', 'VLU', 'PU', 'TW', 'AU', 'OTHER']),
            'wound_location' => $this->faker->randomElement(['Left foot', 'Right leg', 'Sacral', 'Heel']),
            'wound_size_length' => $this->faker->randomFloat(1, 1, 10),
            'wound_size_width' => $this->faker->randomFloat(1, 1, 8),
            'wound_size_depth' => $this->faker->randomFloat(1, 0.1, 5),
            'wound_duration_weeks' => $this->faker->numberBetween(4, 52),
            'total_amount' => $this->faker->randomFloat(2, 100, 5000),
            'specialty' => $this->faker->randomElement(['wound_care_specialty', 'pulmonology_wound_care']),
            'status' => $this->faker->randomElement(['draft', 'submitted', 'processing', 'approved']),
            'mac_validation_status' => $this->faker->randomElement(['not_checked', 'pending', 'passed', 'failed']),
            'eligibility_status' => $this->faker->randomElement(['not_checked', 'pending', 'eligible', 'not_eligible']),
'created_at' => $createdAt = $this->faker->dateTimeBetween('-30 days', 'now'),
'updated_at' => $this->faker->dateTimeBetween($createdAt, 'now'),
        ];
    }

    public function woundCare(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'specialty' => 'wound_care_specialty',
                'wound_type' => $this->faker->randomElement(['DFU', 'VLU', 'PU']),
            ];
        });
    }

    public function pulmonologyWoundCare(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'specialty' => 'pulmonology_wound_care',
                'wound_type' => $this->faker->randomElement(['DFU', 'PU', 'TW']),
            ];
        });
    }

    public function submitted(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'submitted',
                'mac_validation_status' => 'passed',
                'eligibility_status' => 'eligible',
            ];
        });
    }
}

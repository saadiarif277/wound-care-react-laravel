<?php

namespace Database\Factories;

use App\Models\PatientIVRStatus;
use App\Models\Order\Manufacturer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PatientIVRStatus>
 */
class PatientIVRStatusFactory extends Factory
{
    protected $model = PatientIVRStatus::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'patient_id' => 'Patient/' . $this->faker->uuid(),
            'manufacturer_id' => Manufacturer::factory(),
            'status' => $this->faker->randomElement([
                'ready_for_review',
                'ivr_sent',
                'ivr_verified',
                'sent_to_manufacturer',
                'tracking_added',
                'completed'
            ]),
            'ivr_status' => $this->faker->randomElement(['pending', 'verified', 'expired']),
            'verification_date' => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
            'expiration_date' => $this->faker->optional()->dateTimeBetween('now', '+6 months'),
            'frequency_days' => $this->faker->randomElement([30, 60, 90, 180, 365]),
            'created_by' => null, // Will be set to user ID when available
            'docuseal_submission_id' => $this->faker->optional()->uuid(),
            'docuseal_status' => $this->faker->optional()->randomElement(['pending', 'completed', 'signed']),
            'docuseal_completed_at' => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
            'docuseal_audit_log_url' => $this->faker->optional()->url(),
            'docuseal_signed_document_url' => $this->faker->optional()->url(),
            'docuseal_template_id' => $this->faker->optional()->uuid(),
            'docuseal_last_synced_at' => $this->faker->optional()->dateTimeBetween('-7 days', 'now'),
        ];
    }

    /**
     * Indicate that the episode is ready for review.
     */
    public function readyForReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ready_for_review',
            'ivr_status' => 'pending',
            'verification_date' => null,
            'expiration_date' => null,
        ]);
    }

    /**
     * Indicate that the IVR has been sent.
     */
    public function ivrSent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ivr_sent',
            'ivr_status' => 'pending',
            'verification_date' => now()->subDays(1),
            'expiration_date' => now()->addMonths(3),
        ]);
    }

    /**
     * Indicate that the IVR has been verified.
     */
    public function ivrVerified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ivr_verified',
            'ivr_status' => 'verified',
            'verification_date' => now()->subDays(2),
            'expiration_date' => now()->addMonths(3),
        ]);
    }

    /**
     * Indicate that the episode has been sent to manufacturer.
     */
    public function sentToManufacturer(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent_to_manufacturer',
            'ivr_status' => 'verified',
            'verification_date' => now()->subDays(3),
            'expiration_date' => now()->addMonths(3),
        ]);
    }

    /**
     * Indicate that tracking has been added.
     */
    public function trackingAdded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'tracking_added',
            'ivr_status' => 'verified',
            'verification_date' => now()->subDays(4),
            'expiration_date' => now()->addMonths(3),
        ]);
    }

    /**
     * Indicate that the episode is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'ivr_status' => 'verified',
            'verification_date' => now()->subDays(5),
            'expiration_date' => now()->addMonths(3),
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the IVR is expiring soon.
     */
    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'ivr_status' => 'verified',
            'expiration_date' => now()->addDays(15), // Expires in 15 days
        ]);
    }

    /**
     * Indicate that the IVR has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'ivr_status' => 'expired',
            'expiration_date' => now()->subDays(1), // Expired yesterday
        ]);
    }

    /**
     * Set a specific patient ID.
     */
    public function forPatient(string $patientId): static
    {
        return $this->state(fn (array $attributes) => [
            'patient_id' => $patientId,
        ]);
    }

    /**
     * Set a specific manufacturer.
     */
    public function forManufacturer($manufacturerId): static
    {
        return $this->state(fn (array $attributes) => [
            'manufacturer_id' => $manufacturerId,
        ]);
    }

    /**
     * Set frequency for testing.
     */
    public function withFrequency(string $frequency): static
    {
        $frequencyMap = [
            'weekly' => 7,
            'monthly' => 30,
            'quarterly' => 90,
            'yearly' => 365,
        ];

        return $this->state(fn (array $attributes) => [
            'frequency_days' => $frequencyMap[$frequency] ?? 90,
            'frequency' => $frequency, // For legacy compatibility
        ]);
    }
}

<?php

namespace App\Jobs\QuickRequest;

use App\Models\PatientManufacturerIVREpisode;
use App\Models\InsuranceVerification;
use App\Services\Insurance\InsuranceVerificationService;
use App\Services\FhirService;
use App\Notifications\InsuranceVerificationCompleted;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VerifyInsuranceEligibility implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 180; // 3 minutes

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private PatientManufacturerIVREpisode $episode,
        private array $insuranceData,
        private string $verificationType = 'primary'
    ) {
        $this->onQueue('insurance-verification');
    }

    /**
     * Execute the job.
     */
    public function handle(
        InsuranceVerificationService $verificationService,
        FhirService $fhirService
    ): void {
        Log::info('Starting insurance verification', [
            'episode_id' => $this->episode->id,
            'verification_type' => $this->verificationType,
        ]);

        try {
            // Get patient data from FHIR
            $patient = $fhirService->read('Patient', $this->episode->patient_fhir_id);

            // Prepare verification request
            $verificationRequest = $this->prepareVerificationRequest($patient);

            // Perform verification
            $result = $verificationService->verify($verificationRequest);

            // Create verification record
            $verification = InsuranceVerification::create([
                'episode_id' => $this->episode->id,
                'insurance_type' => $this->verificationType,
                'status' => $result['status'],
                'eligibility_status' => $result['eligibility_status'] ?? null,
                'coverage_details' => $result['coverage'] ?? [],
                'benefits' => $result['benefits'] ?? [],
                'copay_amount' => $result['copay'] ?? null,
                'deductible_remaining' => $result['deductible_remaining'] ?? null,
                'out_of_pocket_remaining' => $result['out_of_pocket_remaining'] ?? null,
                'verified_at' => now(),
                'response_data' => $result,
                'metadata' => [
                    'verification_id' => $result['verification_id'] ?? null,
                    'payor_response_time' => $result['response_time'] ?? null,
                ],
            ]);

            // Update Coverage resource in FHIR
            $this->updateFhirCoverage($fhirService, $result);

            // Check if all insurances are verified
            if ($this->allInsurancesVerified()) {
                // Update episode status if all verifications complete
                $this->episode->update([
                    'metadata' => array_merge($this->episode->metadata ?? [], [
                        'insurance_verification_completed' => now()->toIso8601String(),
                    ]),
                ]);

                // Notify relevant parties
                $this->episode->creator->notify(new InsuranceVerificationCompleted(
                    $this->episode,
                    $this->getVerificationSummary()
                ));
            }

            Log::info('Insurance verification completed', [
                'episode_id' => $this->episode->id,
                'verification_id' => $verification->id,
                'status' => $result['status'],
            ]);

        } catch (\Exception $e) {
            Log::error('Insurance verification failed', [
                'episode_id' => $this->episode->id,
                'verification_type' => $this->verificationType,
                'error' => $e->getMessage(),
            ]);

            // Create failed verification record
            InsuranceVerification::create([
                'episode_id' => $this->episode->id,
                'insurance_type' => $this->verificationType,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'metadata' => [
                    'attempted_at' => now()->toIso8601String(),
                    'attempts' => $this->attempts(),
                ],
            ]);

            throw $e;
        }
    }

    /**
     * Prepare verification request data
     */
    private function prepareVerificationRequest(array $patient): array
    {
        $insurance = $this->insuranceData;

        return [
            'member' => [
                'first_name' => $patient['name'][0]['given'][0] ?? '',
                'last_name' => $patient['name'][0]['family'] ?? '',
                'date_of_birth' => $patient['birthDate'] ?? '',
                'gender' => $patient['gender'] ?? '',
                'member_id' => $insurance['subscriberId'],
            ],
            'provider' => [
                'npi' => $this->episode->practitioner->npi ?? '',
                'tax_id' => $this->episode->facility->tax_id ?? '',
            ],
            'insurance' => [
                'payor_id' => $insurance['payorId'] ?? '',
                'payor_name' => $insurance['payorName'],
                'plan_name' => $insurance['planName'] ?? '',
                'group_number' => $insurance['groupNumber'] ?? '',
                'policy_number' => $insurance['policyNumber'],
            ],
            'service_types' => $this->getServiceTypes(),
            'date_of_service' => now()->toDateString(),
        ];
    }

    /**
     * Get relevant service types for wound care
     */
    private function getServiceTypes(): array
    {
        return [
            '18', // Durable Medical Equipment
            '12', // Diabetic Supplies and Equipment
            'AD', // Wound Care
        ];
    }

    /**
     * Update FHIR Coverage resource with verification results
     */
    private function updateFhirCoverage(FhirService $fhirService, array $result): void
    {
        try {
            // Get existing Coverage resource
            $coverageSearch = $fhirService->search('Coverage', [
                'beneficiary' => "Patient/{$this->episode->patient_fhir_id}",
                'type' => $this->verificationType,
            ]);

            if (empty($coverageSearch['entry'])) {
                return;
            }

            $coverage = $coverageSearch['entry'][0]['resource'];
            $coverageId = $coverage['id'];

            // Update with verification results
            $coverage['extension'] = $coverage['extension'] ?? [];
            $coverage['extension'][] = [
                'url' => 'http://mscwoundcare.com/fhir/StructureDefinition/verification-status',
                'valueCodeableConcept' => [
                    'coding' => [
                        [
                            'system' => 'http://mscwoundcare.com/CodeSystem/verification-status',
                            'code' => $result['status'],
                            'display' => ucfirst($result['status']),
                        ],
                    ],
                ],
            ];

            $coverage['extension'][] = [
                'url' => 'http://mscwoundcare.com/fhir/StructureDefinition/verification-date',
                'valueDateTime' => now()->toIso8601String(),
            ];

            $fhirService->update('Coverage', $coverageId, $coverage);

        } catch (\Exception $e) {
            Log::warning('Failed to update FHIR Coverage with verification results', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if all insurance verifications are complete
     */
    private function allInsurancesVerified(): bool
    {
        $requiredVerifications = ['primary'];

        if (!empty($this->episode->insurance_data['secondary'])) {
            $requiredVerifications[] = 'secondary';
        }

        if (!empty($this->episode->insurance_data['tertiary'])) {
            $requiredVerifications[] = 'tertiary';
        }

        $completedVerifications = InsuranceVerification::where('episode_id', $this->episode->id)
            ->whereIn('insurance_type', $requiredVerifications)
            ->where('status', '!=', 'failed')
            ->pluck('insurance_type')
            ->toArray();

        return count($completedVerifications) === count($requiredVerifications);
    }

    /**
     * Get verification summary for notifications
     */
    private function getVerificationSummary(): array
    {
        $verifications = InsuranceVerification::where('episode_id', $this->episode->id)
            ->get()
            ->mapWithKeys(function ($verification) {
                return [$verification->insurance_type => [
                    'status' => $verification->status,
                    'eligibility' => $verification->eligibility_status,
                    'copay' => $verification->copay_amount,
                    'deductible_remaining' => $verification->deductible_remaining,
                ]];
            })
            ->toArray();

        return $verifications;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Exception $exception): void
    {
        Log::error('Insurance verification job failed permanently', [
            'episode_id' => $this->episode->id,
            'verification_type' => $this->verificationType,
            'error' => $exception->getMessage(),
        ]);

        // Update episode metadata
        $this->episode->update([
            'metadata' => array_merge($this->episode->metadata ?? [], [
                'insurance_verification_failed' => [
                    $this->verificationType => [
                        'error' => $exception->getMessage(),
                        'failed_at' => now()->toIso8601String(),
                        'attempts' => $this->attempts(),
                    ],
                ],
            ]),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'insurance-verification',
            'episode:' . $this->episode->id,
            'type:' . $this->verificationType,
        ];
    }
}

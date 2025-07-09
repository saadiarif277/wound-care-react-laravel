<?php

namespace App\Jobs;

use App\Models\PatientManufacturerIVREpisode;
use App\Services\QuickRequest\Handlers\PatientHandler;
use App\Services\QuickRequest\Handlers\ProviderHandler;
use App\Services\QuickRequest\Handlers\ClinicalHandler;
use App\Services\QuickRequest\Handlers\InsuranceHandler;
use App\Logging\PhiSafeLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Exception;

class CreateEpisodeFhirResourcesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes
    public int $backoff = 60; // 1 minute

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $episodeId,
        public array $episodeData
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        PatientHandler $patientHandler,
        ProviderHandler $providerHandler,
        ClinicalHandler $clinicalHandler,
        InsuranceHandler $insuranceHandler,
        PhiSafeLogger $logger
    ): void {
        $logger->info('Starting async FHIR resource creation', [
            'episode_id' => $this->episodeId,
            'attempt' => $this->attempts()
        ]);

        try {
            $episode = PatientManufacturerIVREpisode::findOrFail($this->episodeId);
            
            // Skip if already processed
            if ($episode->patient_fhir_id || $episode->status !== PatientManufacturerIVREpisode::STATUS_PROCESSING_FHIR) {
                $logger->info('Episode already has FHIR resources or wrong status, skipping', [
                    'episode_id' => $this->episodeId,
                    'status' => $episode->status,
                    'has_patient_fhir_id' => !empty($episode->patient_fhir_id)
                ]);
                return;
            }

            DB::beginTransaction();

            // Transform request data for handlers
            $insuranceDataForHandler = $this->transformRequestDataForInsuranceHandler($this->episodeData);

            // Step 1: Create or find patient in FHIR
            $patientFhirId = $patientHandler->createOrUpdatePatient($this->episodeData['patient']);
            $logger->info('Patient FHIR resource created', ['patient_fhir_id' => $patientFhirId]);

            // Step 2: Create or find provider in FHIR
            $providerFhirId = $providerHandler->createOrUpdateProvider($this->episodeData['provider']);
            $logger->info('Provider FHIR resource created', ['provider_fhir_id' => $providerFhirId]);

            // Step 3: Create or find organization in FHIR
            $organizationData = $this->episodeData['organization'] ?? $this->episodeData['facility'];
            $organizationFhirId = $providerHandler->createOrUpdateOrganization($organizationData);
            $logger->info('Organization FHIR resource created', ['organization_fhir_id' => $organizationFhirId]);

            // Step 4: Create clinical resources
            $clinicalResources = $clinicalHandler->createClinicalResources([
                'patient_id' => $patientFhirId,
                'provider_id' => $providerFhirId,
                'organization_id' => $organizationFhirId,
                'clinical' => $this->episodeData['clinical']
            ]);
            $logger->info('Clinical FHIR resources created', ['clinical_resources' => array_keys($clinicalResources)]);

            // Step 5: Create insurance coverage(s)
            $coverageIds = [];
            if (!empty($insuranceDataForHandler)) {
                $coverageIds = $insuranceHandler->createMultipleCoverages($insuranceDataForHandler, $patientFhirId);
                $logger->info('Insurance coverage FHIR resources created', ['coverage_ids' => $coverageIds]);
            }

            $primaryCoverageId = $coverageIds['primary'] ?? array_values($coverageIds)[0] ?? null;

            // Update episode with FHIR IDs
            $episode->update([
                'patient_fhir_id' => $patientFhirId,
                'status' => PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW,
                'metadata' => array_merge($episode->metadata ?? [], [
                    'is_async_fhir' => false,
                    'fhir_completed_at' => now()->toISOString(),
                    'practitioner_fhir_id' => $providerFhirId,
                    'organization_fhir_id' => $organizationFhirId,
                    'episode_of_care_fhir_id' => $clinicalResources['episode_of_care_id'],
                    'condition_id' => $clinicalResources['condition_id'],
                    'coverage_ids' => $coverageIds,
                    'primary_coverage_id' => $primaryCoverageId,
                    'fhir_ids' => [
                        'patient_id' => $patientFhirId,
                        'practitioner_id' => $providerFhirId,
                        'organization_id' => $organizationFhirId,
                        'episode_of_care_id' => $clinicalResources['episode_of_care_id'],
                        'condition_id' => $clinicalResources['condition_id'],
                        'coverage_id' => $primaryCoverageId
                    ]
                ])
            ]);

            DB::commit();

            $logger->info('Async FHIR resource creation completed successfully', [
                'episode_id' => $this->episodeId,
                'patient_fhir_id' => $patientFhirId,
                'new_status' => $episode->status
            ]);

        } catch (Exception $e) {
            DB::rollBack();

            $logger->error('Async FHIR resource creation failed', [
                'episode_id' => $this->episodeId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mark episode as failed if we've exhausted retries
            if ($this->attempts() >= $this->tries) {
                try {
                    $episode = PatientManufacturerIVREpisode::find($this->episodeId);
                    if ($episode) {
                        $episode->update([
                            'status' => PatientManufacturerIVREpisode::STATUS_DRAFT, // Fallback to draft
                            'metadata' => array_merge($episode->metadata ?? [], [
                                'fhir_creation_failed' => true,
                                'fhir_error' => $e->getMessage(),
                                'failed_at' => now()->toISOString()
                            ])
                        ]);
                    }
                } catch (Exception $updateException) {
                    $logger->error('Failed to update episode status after FHIR failure', [
                        'episode_id' => $this->episodeId,
                        'error' => $updateException->getMessage()
                    ]);
                }
            }

            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        $logger = app(PhiSafeLogger::class);
        $logger->error('CreateEpisodeFhirResourcesJob permanently failed', [
            'episode_id' => $this->episodeId,
            'error' => $exception->getMessage()
        ]);
    }

    /**
     * Transform request data for insurance handler
     */
    private function transformRequestDataForInsuranceHandler(array $data): array
    {
        $insuranceData = [];

        // Check if insurance_data is already in the correct format
        if (!empty($data['insurance_data']) && is_array($data['insurance_data'])) {
            // If it's indexed array, return as is
            if (isset($data['insurance_data'][0])) {
                return $data['insurance_data'];
            }
            
            // If it has named keys, transform it
            $insurance = $data['insurance_data'];
            if (!empty($insurance['primary_name'])) {
                $insuranceData[] = [
                    'policy_type' => 'primary',
                    'payer_name' => $insurance['primary_name'],
                    'member_id' => $insurance['primary_member_id'] ?? null,
                    'type' => $insurance['primary_plan_type'] ?? null,
                ];
            }
            
            if (!empty($insurance['has_secondary_insurance']) && !empty($insurance['secondary_insurance_name'])) {
                $insuranceData[] = [
                    'policy_type' => 'secondary',
                    'payer_name' => $insurance['secondary_insurance_name'],
                    'member_id' => $insurance['secondary_member_id'] ?? null,
                    'type' => $insurance['secondary_plan_type'] ?? null,
                ];
            }
            
            return $insuranceData;
        }

        // Primary Insurance (from flat data structure)
        if (!empty($data['primary_insurance_name'])) {
            $insuranceData[] = [
                'policy_type' => 'primary',
                'payer_name' => $data['primary_insurance_name'],
                'member_id' => $data['primary_member_id'] ?? null,
                'type' => $data['primary_plan_type'] ?? null,
            ];
        }

        // Secondary Insurance (from flat data structure)
        if (!empty($data['has_secondary_insurance']) && !empty($data['secondary_insurance_name'])) {
            $insuranceData[] = [
                'policy_type' => 'secondary',
                'payer_name' => $data['secondary_insurance_name'],
                'member_id' => $data['secondary_member_id'] ?? null,
                'type' => $data['secondary_plan_type'] ?? null,
            ];
        }

        return $insuranceData;
    }
} 
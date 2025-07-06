<?php

namespace App\Services\QuickRequest;

use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\Order;
use App\Services\QuickRequest\Handlers\PatientHandler;
use App\Services\QuickRequest\Handlers\ProviderHandler;
use App\Services\QuickRequest\Handlers\ClinicalHandler;
use App\Services\QuickRequest\Handlers\InsuranceHandler;
use App\Services\QuickRequest\Handlers\OrderHandler;
use App\Services\QuickRequest\Handlers\NotificationHandler;
use App\Logging\PhiSafeLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class QuickRequestOrchestrator
{
    public function __construct(
        protected PatientHandler $patientHandler,
        protected ProviderHandler $providerHandler,
        protected ClinicalHandler $clinicalHandler,
        protected InsuranceHandler $insuranceHandler,
        protected OrderHandler $orderHandler,
        protected NotificationHandler $notificationHandler,
        protected PhiSafeLogger $logger
    ) {}

        /**
     * Start a new episode with initial order
     */
    public function startEpisode(array $data): PatientManufacturerIVREpisode
    {
        try {
            $this->logger->info('Starting new Quick Request episode');

            DB::beginTransaction();
            // Step 1: Create or find patient in FHIR
            $patientFhirId = $this->patientHandler->createOrUpdatePatient($data['patient']);

            // Step 2: Create or find provider in FHIR
            $providerFhirId = $this->providerHandler->createOrUpdateProvider($data['provider']);

            // Step 3: Create or find organization in FHIR (using organization data, not facility data)
            $organizationFhirId = $this->providerHandler->createOrUpdateOrganization($data['organization'] ?? $data['facility']);

            // Step 4: Create clinical resources (Condition, EpisodeOfCare)
            $clinicalResources = $this->clinicalHandler->createClinicalResources([
                'patient_id' => $patientFhirId,
                'provider_id' => $providerFhirId,
                'organization_id' => $organizationFhirId,
                'clinical' => $data['clinical']
            ]);

            // Step 5: Create insurance coverage(s) by transforming flat data from the request
            $coverageIds = [];
            $insuranceDataForHandler = $this->transformRequestDataForInsuranceHandler($data);

            if (!empty($insuranceDataForHandler)) {
                $this->logger->info('Insurance data found, creating coverages.', ['insurance_data' => $insuranceDataForHandler]);
                $coverageIds = $this->insuranceHandler->createMultipleCoverages($insuranceDataForHandler, $patientFhirId);
            } else {
                $this->logger->info('No insurance data provided, skipping coverage creation.');
            }

            // Get primary coverage ID for episode metadata
            $primaryCoverageId = $coverageIds['primary'] ?? array_values($coverageIds)[0] ?? null;

            // Step 6: Create local episode record
            $episode = PatientManufacturerIVREpisode::create([
                'patient_id' => $data['patient']['id'] ?? uniqid('patient-'),
                'patient_fhir_id' => $patientFhirId,
                'patient_display_id' => $data['patient']['display_id'] ?? null,
                'manufacturer_id' => $data['manufacturer_id'],
                'status' => PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW,
                'ivr_status' => PatientManufacturerIVREpisode::IVR_STATUS_NA,
                'created_by' => Auth::id(),
                'metadata' => [
                    'practitioner_fhir_id' => $providerFhirId,
                    'organization_fhir_id' => $organizationFhirId,
                    'episode_of_care_fhir_id' => $clinicalResources['episode_of_care_id'],
                    'condition_id' => $clinicalResources['condition_id'],
                    'coverage_ids' => $coverageIds,
                    'primary_coverage_id' => $primaryCoverageId,
                    'clinical_data' => $data['clinical'],
                    'provider_data' => $data['provider'],
                    'facility_data' => $data['facility'],
                    'organization_data' => $data['organization'] ?? null,
                    'insurance_data' => $insuranceDataForHandler, // Use the transformed data
                    'order_details' => $data['order_details'],
                    'created_by' => Auth::id()
                ]
            ]);

            // Step 7: Create initial order
            $this->logger->info('About to create initial order', [
                'episode_id' => $episode->id,
                'order_details_count' => count($data['order_details'] ?? []),
                'has_products' => isset($data['order_details']['products'])
            ]);
            
            $order = $this->orderHandler->createInitialOrder($episode, $data['order_details']);
            
            $this->logger->info('Order created, checking if saved', [
                'order_id' => $order->id ?? 'no_id',
                'order_exists' => $order->exists,
                'episode_id' => $episode->id
            ]);

            DB::commit();
            
            // Verify order was saved after commit
            $orderCount = Order::where('episode_id', $episode->id)->count();
            $this->logger->info('After commit - order count check', [
                'episode_id' => $episode->id,
                'order_count' => $orderCount,
                'order_id' => $order->id ?? 'no_id'
            ]);

            $this->logger->info('Quick Request episode created successfully', [
                'episode_id' => $episode->id,
                'status' => $episode->status
            ]);

            return $episode->load('orders');

        } catch (Exception $e) {
            DB::rollBack();

            $this->logger->error('Failed to create Quick Request episode', [
                'error' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'manufacturer_id' => $data['manufacturer_id'] ?? 'unknown',
                'step' => 'Unknown - check trace'
            ]);

            throw new \Exception('Failed to create Quick Request episode: ' . $e->getMessage());
        }
    }

    /**
     * Create a draft episode for IVR generation without persisting FHIR resources
     * This allows IVR generation before final submission
     */
    public function createDraftEpisode(array $data): PatientManufacturerIVREpisode
    {
        try {
            $this->logger->info('Creating draft episode for IVR generation');

            // Create minimal episode record without FHIR resources
            $episode = PatientManufacturerIVREpisode::create([
                'patient_id' => $data['patient']['id'] ?? uniqid('patient-draft-'),
                'patient_fhir_id' => null, // Will be populated during finalization
                'patient_display_id' => $data['patient']['display_id'] ?? null,
                'manufacturer_id' => $data['manufacturer_id'],
                'status' => PatientManufacturerIVREpisode::STATUS_DRAFT, // New draft status
                'ivr_status' => PatientManufacturerIVREpisode::IVR_STATUS_PENDING,
                'created_by' => Auth::id(),
                'metadata' => [
                    'is_draft' => true,
                    'patient_data' => $data['patient'] ?? [],
                    'clinical_data' => $data['clinical'] ?? [],
                    'provider_data' => $data['provider'] ?? [],
                    'facility_data' => $data['facility'] ?? [],
                    'organization_data' => $data['organization'] ?? [],
                    'insurance_data' => $this->transformRequestDataForInsuranceHandler($data),
                    'order_details' => $data['order_details'] ?? [],
                    'created_by' => Auth::id(),
                    'draft_created_at' => now()->toISOString()
                ]
            ]);

            $this->logger->info('Draft episode created successfully', [
                'episode_id' => $episode->id,
                'status' => $episode->status
            ]);

            return $episode;

        } catch (Exception $e) {
            $this->logger->error('Failed to create draft episode', [
                'error' => $e->getMessage(),
                'manufacturer_id' => $data['manufacturer_id'] ?? 'unknown'
            ]);

            throw new \Exception('Failed to create draft episode: ' . $e->getMessage());
        }
    }

    /**
     * Finalize a draft episode by creating FHIR resources and updating status
     */
    public function finalizeDraftEpisode(PatientManufacturerIVREpisode $episode, array $finalData): PatientManufacturerIVREpisode
    {
        try {
            $this->logger->info('Finalizing draft episode', ['episode_id' => $episode->id]);

            if ($episode->status !== PatientManufacturerIVREpisode::STATUS_DRAFT) {
                throw new Exception("Episode is not in draft status. Current status: {$episode->status}");
            }

            DB::beginTransaction();

            // Merge final data with draft metadata
            $mergedData = array_merge($episode->metadata, $finalData);

            // Extract the correct data structures for each handler
            $patientData = $mergedData['patient'] ?? [];
            $providerData = $mergedData['provider_data'] ?? [];
            $organizationData = $mergedData['organization_data'] ?? $mergedData['facility_data'] ?? [];
            $clinicalData = $mergedData['clinical_data'] ?? [];
            $insuranceData = $mergedData['insurance_data'] ?? [];

            // Step 1: Create or find patient in FHIR
            $patientFhirId = $this->patientHandler->createOrUpdatePatient($patientData);

            // Step 2: Create or find provider in FHIR
            $providerFhirId = $this->providerHandler->createOrUpdateProvider($providerData);

            // Step 3: Create or find organization in FHIR
            $organizationFhirId = $this->providerHandler->createOrUpdateOrganization($organizationData);

            // Step 4: Create clinical resources (Condition, EpisodeOfCare)
            $clinicalResources = $this->clinicalHandler->createClinicalResources([
                'patient_id' => $patientFhirId,
                'provider_id' => $providerFhirId,
                'organization_id' => $organizationFhirId,
                'clinical' => $clinicalData
            ]);

            // Step 5: Create insurance coverage(s)
            $coverageIds = [];
            if (!empty($insuranceData)) {
                $coverageIds = $this->insuranceHandler->createMultipleCoverages($insuranceData, $patientFhirId);
            }

            $primaryCoverageId = $coverageIds['primary'] ?? array_values($coverageIds)[0] ?? null;

            // Update episode with FHIR IDs and finalized status
            $episode->update([
                'patient_fhir_id' => $patientFhirId,
                'status' => PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW,
                'metadata' => array_merge($episode->metadata, [
                    'is_draft' => false,
                    'practitioner_fhir_id' => $providerFhirId,
                    'organization_fhir_id' => $organizationFhirId,
                    'episode_of_care_fhir_id' => $clinicalResources['episode_of_care_id'],
                    'condition_id' => $clinicalResources['condition_id'],
                    'coverage_ids' => $coverageIds,
                    'primary_coverage_id' => $primaryCoverageId,
                    'finalized_at' => now()->toISOString()
                ])
            ]);

            DB::commit();

            $this->logger->info('Draft episode finalized successfully', [
                'episode_id' => $episode->id,
                'new_status' => $episode->status
            ]);

            return $episode->fresh();

        } catch (Exception $e) {
            DB::rollBack();

            $this->logger->error('Failed to finalize draft episode', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to finalize draft episode: ' . $e->getMessage());
        }
    }

    /**
     * Transforms the flat request data into a nested structure for the InsuranceHandler.
     */
    private function transformRequestDataForInsuranceHandler(array $data): array
    {
        $insuranceData = [];

        // Check if insurance_data is already in the correct format
        if (!empty($data['insurance_data']) && is_array($data['insurance_data'])) {
            return $data['insurance_data'];
        }

        // Primary Insurance
        if (!empty($data['primary_insurance_name'])) {
            $insuranceData[] = [
                'policy_type' => 'primary',
                'payer_name' => $data['primary_insurance_name'],
                'member_id' => $data['primary_member_id'] ?? null,
                'type' => $data['primary_plan_type'] ?? null,
            ];
        }

        // Secondary Insurance
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

    /**
     * Add follow-up order to existing episode
     */
    public function addFollowUpOrder(PatientManufacturerIVREpisode $episode, array $orderData): Order
    {
        $this->logger->info('Adding follow-up order to episode', [
            'episode_id' => $episode->id
        ]);

        DB::beginTransaction();

        try {
            // Validate episode can accept new orders
            if (!in_array($episode->status, [PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW, PatientManufacturerIVREpisode::STATUS_IVR_SENT])) {
                throw new Exception("Episode is not accepting new orders. Current status: {$episode->status}");
            }

            // Create follow-up order
            $order = $this->orderHandler->createFollowUpOrder($episode, $orderData);

            // Update episode status if needed
            if ($episode->status === PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW) {
                $episode->update(['status' => PatientManufacturerIVREpisode::STATUS_IVR_SENT]);
            }

            DB::commit();

            $this->logger->info('Follow-up order added successfully', [
                'episode_id' => $episode->id,
                'order_id' => $order->id
            ]);

            return $order;

        } catch (Exception $e) {
            DB::rollBack();

                        $this->logger->error('Failed to add follow-up order', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to add follow-up order: ' . $e->getMessage());
        }
    }

    /**
     * Approve episode and notify manufacturer
     */
    public function approveEpisode(PatientManufacturerIVREpisode $episode): void
    {
        $this->logger->info('Approving episode', [
            'episode_id' => $episode->id
        ]);

        DB::beginTransaction();

        try {
            // Update episode status
            $episode->update([
                'status' => PatientManufacturerIVREpisode::STATUS_SENT_TO_MANUFACTURER,
                'metadata' => array_merge($episode->metadata ?? [], [
                    'reviewed_at' => now()->toIso8601String(),
                    'reviewed_by' => Auth::id()
                ])
            ]);

            // Update FHIR Task status
            $taskFhirId = $episode->metadata['task_fhir_id'] ?? null;
            if ($taskFhirId) {
                $this->clinicalHandler->updateTaskStatus(
                    $taskFhirId,
                    'completed',
                    'Approved for manufacturer review'
                );
            }

            // Send notification to manufacturer
            $this->notificationHandler->notifyManufacturerApproval($episode);

            DB::commit();

            $this->logger->info('Episode approved successfully', [
                'episode_id' => $episode->id,
                'new_status' => $episode->status
            ]);

        } catch (Exception $e) {
            DB::rollBack();

                        $this->logger->error('Failed to approve episode', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to approve episode: ' . $e->getMessage());
        }
    }

    /**
     * Complete episode after manufacturer approval
     */
    public function completeEpisode(PatientManufacturerIVREpisode $episode, array $manufacturerResponse): void
    {
        $this->logger->info('Completing episode', [
            'episode_id' => $episode->id
        ]);

        DB::beginTransaction();

        try {
            // Update episode with manufacturer response
            $episode->update([
                'status' => PatientManufacturerIVREpisode::STATUS_COMPLETED,
                'completed_at' => now(),
                'metadata' => array_merge($episode->metadata ?? [], [
                    'manufacturer_response' => $manufacturerResponse
                ])
            ]);

            // Update all pending orders
            $episode->orders()
                ->where('status', 'pending')
                ->update(['status' => 'approved']);

            // Update FHIR resources
            $episodeOfCareFhirId = $episode->metadata['episode_of_care_fhir_id'] ?? null;
            if ($episodeOfCareFhirId) {
                $this->clinicalHandler->completeEpisodeOfCare($episodeOfCareFhirId);
            }

            // Send completion notifications
            $this->notificationHandler->notifyEpisodeCompletion($episode);

            DB::commit();

            $this->logger->info('Episode completed successfully', [
                'episode_id' => $episode->id
            ]);

        } catch (Exception $e) {
            DB::rollBack();

                        $this->logger->error('Failed to complete episode', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to complete episode: ' . $e->getMessage());
        }
    }

    /**
     * Prepare comprehensive data for Docuseal integration using orchestrator's aggregated data
     */
    public function prepareDocusealData(PatientManufacturerIVREpisode $episode): array
    {
        try {
            $metadata = $episode->metadata ?? [];
            
            // Aggregate data from all sources stored in episode metadata
            $aggregatedData = [];
            
            // Patient data (from form)
            if (isset($metadata['patient_data'])) {
                $patientData = $metadata['patient_data'];
                $aggregatedData['patient_first_name'] = $patientData['first_name'] ?? '';
                $aggregatedData['patient_last_name'] = $patientData['last_name'] ?? '';
                $aggregatedData['patient_dob'] = $patientData['dob'] ?? '';
                $aggregatedData['patient_gender'] = $patientData['gender'] ?? '';
                $aggregatedData['patient_phone'] = $patientData['phone'] ?? '';
                $aggregatedData['patient_email'] = $patientData['email'] ?? '';
                $aggregatedData['patient_display_id'] = $patientData['display_id'] ?? '';
                
                // Create patient_name for DocuSeal compatibility
                $firstName = $patientData['first_name'] ?? '';
                $lastName = $patientData['last_name'] ?? '';
                $aggregatedData['patient_name'] = trim($firstName . ' ' . $lastName);
            }
            
            // Provider data (from provider profile)
            if (isset($metadata['provider_data'])) {
                $providerData = $metadata['provider_data'];
                $aggregatedData['provider_name'] = $providerData['name'] ?? '';
                $aggregatedData['provider_first_name'] = $providerData['first_name'] ?? '';
                $aggregatedData['provider_last_name'] = $providerData['last_name'] ?? '';
                $aggregatedData['provider_npi'] = $providerData['npi'] ?? '';
                $aggregatedData['provider_email'] = $providerData['email'] ?? '';
                $aggregatedData['provider_phone'] = $providerData['phone'] ?? '';
                $aggregatedData['provider_specialty'] = $providerData['specialty'] ?? '';
                $aggregatedData['provider_credentials'] = $providerData['credentials'] ?? '';
                $aggregatedData['provider_license_number'] = $providerData['license_number'] ?? '';
                $aggregatedData['provider_license_state'] = $providerData['license_state'] ?? '';
                $aggregatedData['provider_dea_number'] = $providerData['dea_number'] ?? '';
                $aggregatedData['provider_ptan'] = $providerData['ptan'] ?? '';
                $aggregatedData['provider_tax_id'] = $providerData['tax_id'] ?? '';
                $aggregatedData['practice_name'] = $providerData['practice_name'] ?? '';
                
                // Create physician aliases for DocuSeal compatibility
                $aggregatedData['physician_name'] = $providerData['name'] ?? '';
                $aggregatedData['physician_npi'] = $providerData['npi'] ?? '';
                $aggregatedData['physician_ptan'] = $providerData['ptan'] ?? '';
            }
            
            // Facility data (from selected facility)
            if (isset($metadata['facility_data'])) {
                $facilityData = $metadata['facility_data'];
                $aggregatedData['facility_name'] = $facilityData['name'] ?? '';
                $aggregatedData['facility_address'] = $facilityData['address'] ?? '';
                $aggregatedData['facility_address_line1'] = $facilityData['address_line1'] ?? '';
                $aggregatedData['facility_address_line2'] = $facilityData['address_line2'] ?? '';
                $aggregatedData['facility_city'] = $facilityData['city'] ?? '';
                $aggregatedData['facility_state'] = $facilityData['state'] ?? '';
                $aggregatedData['facility_zip_code'] = $facilityData['zip_code'] ?? '';
                $aggregatedData['facility_phone'] = $facilityData['phone'] ?? '';
                $aggregatedData['facility_fax'] = $facilityData['fax'] ?? '';
                $aggregatedData['facility_email'] = $facilityData['email'] ?? '';
                $aggregatedData['facility_npi'] = $facilityData['npi'] ?? '';
                $aggregatedData['facility_group_npi'] = $facilityData['group_npi'] ?? '';
                $aggregatedData['facility_ptan'] = $facilityData['ptan'] ?? '';
                $aggregatedData['facility_tax_id'] = $facilityData['tax_id'] ?? '';
                $aggregatedData['facility_type'] = $facilityData['facility_type'] ?? '';
                $aggregatedData['place_of_service'] = $facilityData['place_of_service'] ?? '';
            }
            
            // Organization data (from CurrentOrganization service)
            if (isset($metadata['organization_data'])) {
                $organizationData = $metadata['organization_data'];
                $aggregatedData['organization_name'] = $organizationData['name'] ?? '';
                $aggregatedData['organization_tax_id'] = $organizationData['tax_id'] ?? '';
                $aggregatedData['organization_address'] = $organizationData['address'] ?? '';
                $aggregatedData['organization_city'] = $organizationData['city'] ?? '';
                $aggregatedData['organization_state'] = $organizationData['state'] ?? '';
                $aggregatedData['organization_zip_code'] = $organizationData['zip_code'] ?? '';
                $aggregatedData['organization_phone'] = $organizationData['phone'] ?? '';
                $aggregatedData['organization_email'] = $organizationData['email'] ?? '';
                $aggregatedData['organization_type'] = $organizationData['type'] ?? '';
            }
            
            // Clinical data (from form)
            if (isset($metadata['clinical_data'])) {
                $clinicalData = $metadata['clinical_data'];
                $aggregatedData['wound_type'] = $clinicalData['wound_type'] ?? '';
                $aggregatedData['wound_location'] = $clinicalData['wound_location'] ?? '';
                $aggregatedData['wound_size_length'] = $clinicalData['wound_length'] ?? $clinicalData['wound_size_length'] ?? '';
                $aggregatedData['wound_size_width'] = $clinicalData['wound_width'] ?? $clinicalData['wound_size_width'] ?? '';
                $aggregatedData['wound_size_depth'] = $clinicalData['wound_depth'] ?? $clinicalData['wound_size_depth'] ?? '';
                
                // Copy all clinical fields that might be needed
                $aggregatedData['graft_size_requested'] = $clinicalData['graft_size_requested'] ?? '';
                $aggregatedData['icd10_code_1'] = $clinicalData['icd10_code_1'] ?? '';
                $aggregatedData['cpt_code_1'] = $clinicalData['cpt_code_1'] ?? '';
                $aggregatedData['failed_conservative_treatment'] = $clinicalData['failed_conservative_treatment'] ?? '';
                $aggregatedData['information_accurate'] = $clinicalData['information_accurate'] ?? '';
                $aggregatedData['medical_necessity_established'] = $clinicalData['medical_necessity_established'] ?? '';
                $aggregatedData['maintain_documentation'] = $clinicalData['maintain_documentation'] ?? '';
                $aggregatedData['wound_duration_weeks'] = $clinicalData['wound_duration_weeks'] ?? '';
                $aggregatedData['primary_diagnosis_code'] = $clinicalData['primary_diagnosis_code'] ?? '';
                
                // Calculate wound size total if not present
                if (!empty($aggregatedData['wound_size_length']) && !empty($aggregatedData['wound_size_width'])) {
                    $aggregatedData['wound_size_total'] = floatval($aggregatedData['wound_size_length']) * floatval($aggregatedData['wound_size_width']);
                }
                
                // Add procedure_date (required field) - default to today if not provided
                $aggregatedData['procedure_date'] = $clinicalData['procedure_date'] ?? now()->format('Y-m-d');
            }
            
            // Insurance data (from form)
            if (isset($metadata['insurance_data'])) {
                $insuranceData = $metadata['insurance_data'];
                foreach ($insuranceData as $index => $policy) {
                    $policyType = $policy['policy_type'] ?? '';
                    if ($policyType === 'primary') {
                        $aggregatedData['primary_insurance_name'] = $policy['payer_name'] ?? '';
                        $aggregatedData['primary_member_id'] = $policy['member_id'] ?? '';
                    } elseif ($policyType === 'secondary') {
                        $aggregatedData['secondary_insurance_name'] = $policy['payer_name'] ?? '';
                        $aggregatedData['secondary_member_id'] = $policy['member_id'] ?? '';
                    }
                }
            }
            
            // Order details
            if (isset($metadata['order_details'])) {
                $orderData = $metadata['order_details'];
                $aggregatedData['expected_service_date'] = $orderData['expected_service_date'] ?? '';
                $aggregatedData['place_of_service'] = $orderData['place_of_service'] ?? $aggregatedData['place_of_service'] ?? '';
            }
            
            // Add episode-specific data
            $aggregatedData['episode_id'] = $episode->id;
            $aggregatedData['patient_id'] = $episode->patient_id;
            $aggregatedData['manufacturer_id'] = $episode->manufacturer_id;
            $aggregatedData['created_by'] = $episode->created_by;
            
            $this->logger->info('Prepared comprehensive Docuseal data from orchestrator', [
                'episode_id' => $episode->id,
                'data_fields_count' => count($aggregatedData),
                'has_provider_data' => !empty($aggregatedData['provider_name']),
                'has_facility_data' => !empty($aggregatedData['facility_name']),
                'has_organization_data' => !empty($aggregatedData['organization_name']),
                'has_patient_data' => !empty($aggregatedData['patient_first_name']),
                'has_clinical_data' => !empty($aggregatedData['wound_type']),
                'has_insurance_data' => !empty($aggregatedData['primary_insurance_name']),
            ]);
            
            return $aggregatedData;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to prepare Docuseal data from orchestrator', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}

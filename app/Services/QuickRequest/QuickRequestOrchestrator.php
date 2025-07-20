<?php

namespace App\Services\QuickRequest;

use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\Order;
use App\Models\Order\Manufacturer;
use App\Models\Fhir\Facility;
use App\Services\QuickRequest\Handlers\PatientHandler;
use App\Services\QuickRequest\Handlers\ProviderHandler;
use App\Services\QuickRequest\Handlers\ClinicalHandler;
use App\Services\QuickRequest\Handlers\InsuranceHandler;
use App\Services\QuickRequest\Handlers\OrderHandler;
use App\Services\QuickRequest\Handlers\NotificationHandler;
use App\Services\EntityDataService;
use App\Jobs\CreateEpisodeFhirResourcesJob;
use App\Services\Medical\OptimizedMedicalAiService;
use App\Services\AI\FieldMappingMetricsService;
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
        protected EntityDataService $entityDataService,
        protected OptimizedMedicalAiService $optimizedMedicalAi,
        protected PhiSafeLogger $logger,
        protected ?FieldMappingMetricsService $metricsService = null
    ) {
        // Initialize metrics service if not provided
        $this->metricsService = $metricsService ?? app(FieldMappingMetricsService::class);
    }

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
                    'provider_id' => $data['provider']['id'] ?? null,
                    'facility_id' => $data['facility']['id'] ?? null,
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
            // Dynamic manufacturer detection - no hardcoding
            $manufacturer = \App\Models\Order\Manufacturer::find($episode->manufacturer_id);
            if (!$manufacturer) {
                throw new \InvalidArgumentException("Invalid manufacturer ID: {$episode->manufacturer_id}");
            }
            
            // For other manufacturers, use the existing comprehensive approach
            $metadata = $episode->metadata ?? [];
            
            // Extract FHIR IDs from metadata
            $fhirIds = [
                'patient_id' => $metadata['fhir_ids']['patient_id'] ?? null,
                'practitioner_id' => $metadata['fhir_ids']['practitioner_id'] ?? null,
                'organization_id' => $metadata['fhir_ids']['organization_id'] ?? null,
                'condition_id' => $metadata['fhir_ids']['condition_id'] ?? null,
                'coverage_id' => $metadata['fhir_ids']['coverage_id'] ?? null,
                'encounter_id' => $metadata['fhir_ids']['encounter_id'] ?? null,
                'episode_of_care_id' => $metadata['fhir_ids']['episode_of_care_id'] ?? null,
            ];
            
            // Use targeted extraction based on manufacturer configuration
            $aggregatedData = $this->extractTargetedDocusealData($episode, []);
            
            // LIFE FIX: Always run comprehensive facility data extraction since EntityDataService might not be complete
            $this->enhanceWithComprehensiveFacilityData($episode, $aggregatedData, $metadata);
            
            // Get current user as sales rep/contact
            $currentUser = Auth::user();
            if ($currentUser) {
                $aggregatedData['name'] = $currentUser->full_name ?? trim($currentUser->first_name . ' ' . $currentUser->last_name);
                $aggregatedData['email'] = $currentUser->email ?? '';
                
                // Try to get phone from various sources
                $userPhone = '';
                if (isset($currentUser->phone)) {
                    $userPhone = $currentUser->phone;
                } elseif ($currentUser->currentOrganization && $currentUser->currentOrganization->phone) {
                    $userPhone = $currentUser->currentOrganization->phone;
                } elseif (isset($metadata['provider_data']['phone'])) {
                    $userPhone = $metadata['provider_data']['phone'];
                } elseif (isset($metadata['facility_data']['phone'])) {
                    $userPhone = $metadata['facility_data']['phone'];
                }
                
                $aggregatedData['phone'] = $userPhone;
                $aggregatedData['contact_name'] = $currentUser->full_name ?? trim($currentUser->first_name . ' ' . $currentUser->last_name);
                $aggregatedData['contact_email'] = $currentUser->email ?? '';
                $aggregatedData['sales_rep'] = $currentUser->full_name ?? trim($currentUser->first_name . ' ' . $currentUser->last_name);
                $aggregatedData['rep_email'] = $currentUser->email ?? '';
                
                // Get territory from user's organization if available
                if ($currentUser->currentOrganization) {
                    $aggregatedData['territory'] = $currentUser->currentOrganization->territory ?? 
                                                   $currentUser->currentOrganization->region ?? 
                                                   $currentUser->currentOrganization->state ?? '';
                }
                
                // Additional fields for compatibility
                $aggregatedData['iso_if_applicable'] = $metadata['iso_code'] ?? '';
                $aggregatedData['additional_emails'] = $metadata['additional_notification_emails'] ?? '';
            }
            
            // Request type (from form data if available)
            $aggregatedData['new_request'] = false;
            $aggregatedData['re_verification'] = false;
            $aggregatedData['additional_applications'] = false;
            $aggregatedData['new_insurance'] = false;
            
            if (isset($metadata['request_type'])) {
                switch ($metadata['request_type']) {
                    case 'new_request':
                        $aggregatedData['new_request'] = true;
                        break;
                    case 'reverification':
                        $aggregatedData['re_verification'] = true;
                        break;
                    case 'additional_applications':
                        $aggregatedData['additional_applications'] = true;
                        break;
                    case 'new_insurance':
                        $aggregatedData['new_insurance'] = true;
                        break;
                }
            } else {
                // Default to new request if not specified
                $aggregatedData['new_request'] = true;
            }
            
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
                
                // Handle FHIR-compliant address structure
                if (isset($patientData['address'])) {
                    $address = $patientData['address'];
                    
                    // Use FHIR text field for complete address
                    $aggregatedData['patient_address'] = $address['text'] ?? '';
                    
                    // Also provide individual components for forms that need them
                    $aggregatedData['patient_address_line1'] = $address['line'][0] ?? '';
                    $aggregatedData['patient_city'] = $address['city'] ?? '';
                    $aggregatedData['patient_state'] = $address['state'] ?? '';
                    $aggregatedData['patient_zip'] = $address['postalCode'] ?? '';
                    $aggregatedData['patient_country'] = $address['country'] ?? 'US';
                } else {
                    // Fallback to old structure for backwards compatibility
                    $aggregatedData['patient_address'] = trim(
                        ($patientData['address_line1'] ?? '') . ' ' . 
                        ($patientData['address_line2'] ?? '') . ', ' .
                        ($patientData['city'] ?? '') . ', ' .
                        ($patientData['state'] ?? '') . ' ' .
                        ($patientData['zip'] ?? '')
                    );
                    
                    $aggregatedData['patient_address_line1'] = $patientData['address_line1'] ?? '';
                    $aggregatedData['patient_city'] = $patientData['city'] ?? '';
                    $aggregatedData['patient_state'] = $patientData['state'] ?? '';
                    $aggregatedData['patient_zip'] = $patientData['zip'] ?? '';
                    $aggregatedData['patient_country'] = $patientData['country'] ?? 'US';
                }
                
                // SNF status - default to No
                $aggregatedData['patient_snf_yes'] = false;
                $aggregatedData['patient_snf_no'] = true;
                $aggregatedData['snf_days'] = '';
                
                if (isset($patientData['is_in_snf']) && $patientData['is_in_snf']) {
                    $aggregatedData['patient_snf_yes'] = true;
                    $aggregatedData['patient_snf_no'] = false;
                    $aggregatedData['snf_days'] = $patientData['snf_days'] ?? '';
                }
                
                // Add patient city_state_zip field
                $aggregatedData['patient_city_state_zip'] = trim(
                    ($patientData['city'] ?? '') . ', ' .
                    ($patientData['state'] ?? '') . ' ' .
                    ($patientData['zip'] ?? $patientData['postal_code'] ?? '')
                );
                
                // Patient caregiver info (empty by default)
                $aggregatedData['patient_caregiver_info'] = $patientData['caregiver_info'] ?? '';
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
                $aggregatedData['physician_specialty'] = $providerData['specialty'] ?? '';
                $aggregatedData['provider_medicaid'] = $providerData['medicaid_number'] ?? '';
                
                // Additional physician fields for compatibility
                $aggregatedData['physician_tax_id'] = $providerData['tax_id'] ?? '';
                $aggregatedData['physician_medicaid_number'] = $providerData['medicaid_number'] ?? '';
                $aggregatedData['physician_phone'] = $providerData['phone'] ?? '';
                $aggregatedData['physician_fax'] = $providerData['fax'] ?? '';
                $aggregatedData['physician_organization'] = $providerData['practice_name'] ?? '';
            }
            
            // Facility data - ENHANCED to pull fresh data from facilities table
            $facility = null;
            
            // Try to get facility from episode's facility_id first (most reliable)
            if ($episode->facility_id) {
                try {
                    $facility = \App\Models\Fhir\Facility::with(['organization'])->find($episode->facility_id);
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to load facility from episode facility_id', [
                        'episode_id' => $episode->id,
                        'facility_id' => $episode->facility_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // If no facility found, fall back to metadata (backward compatibility)
            if (!$facility && isset($metadata['facility_data']) && !empty($metadata['facility_data']['id'])) {
                try {
                    $facility = \App\Models\Fhir\Facility::with(['organization'])->find($metadata['facility_data']['id']);
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to load facility from metadata', [
                        'episode_id' => $episode->id,
                        'metadata_facility_id' => $metadata['facility_data']['id'] ?? null,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
                         if ($facility) {
                // Extract comprehensive facility data directly from the model
                $facilityData = [
                    // Basic facility info
                    'facility_id' => $facility->id,
                    'facility_name' => $facility->name,
                    'facility_type' => $facility->facility_type,
                    'facility_status' => $facility->status,
                    'facility_active' => $facility->active,
                    
                    // Address information
                    'facility_address' => $facility->address,
                    'facility_address_line1' => $facility->address_line1 ?: $facility->address,
                    'facility_address_line2' => $facility->address_line2,
                    'facility_city' => $facility->city,
                    'facility_state' => $facility->state,
                    'facility_zip' => $facility->zip_code,
                    'facility_zip_code' => $facility->zip_code,
                    
                    // Contact information
                    'facility_phone' => $facility->phone,
                    'facility_fax' => $facility->fax,
                    'facility_email' => $facility->email,
                    'facility_contact_name' => $facility->contact_name,
                    'facility_contact_phone' => $facility->contact_phone,
                    'facility_contact_email' => $facility->contact_email,
                    'facility_contact_fax' => $facility->contact_fax,
                    
                    // Practice/Business identifiers
                    'facility_npi' => $facility->npi,
                    'facility_group_npi' => $facility->group_npi,
                    'facility_tax_id' => $facility->tax_id,
                    'facility_tin' => $facility->tax_id, // Alias
                    'facility_ptan' => $facility->ptan,
                    'facility_medicaid_number' => $facility->medicaid_number,
                    'facility_medicaid' => $facility->medicaid_number, // Alias
                    'medicare_admin_contractor' => $facility->medicare_admin_contractor,
                    'mac' => $facility->medicare_admin_contractor,
                    
                    // Place of service
                    'facility_default_place_of_service' => $facility->default_place_of_service,
                    
                    // FHIR integration
                    'fhir_organization_id' => $facility->fhir_organization_id,
                ];
                
                // Add organization data if available
                if ($facility->organization) {
                    $org = $facility->organization;
                    $organizationData = [
                        'organization_id' => $org->id,
                        'organization_name' => $org->name,
                        'organization_type' => $org->type,
                        'organization_status' => $org->status,
                        'organization_tax_id' => $org->tax_id,
                        'organization_email' => $org->email,
                        'organization_phone' => $org->phone,
                        'organization_address' => $org->address,
                        'organization_city' => $org->city,
                        'organization_state' => $org->region,
                        'organization_country' => $org->country,
                        'organization_postal_code' => $org->postal_code,
                        'organization_zip' => $org->postal_code,
                        'billing_address' => $org->billing_address,
                        'billing_city' => $org->billing_city,
                        'billing_state' => $org->billing_state,
                        'billing_zip' => $org->billing_zip,
                        'ap_contact_name' => $org->ap_contact_name,
                        'ap_contact_phone' => $org->ap_contact_phone,
                        'ap_contact_email' => $org->ap_contact_email,
                        'facility_organization' => $org->name, // Alias for ACZ compatibility
                    ];
                    $facilityData = array_merge($facilityData, $organizationData);
                }
                
                // Merge all facility data into aggregatedData
                $aggregatedData = array_merge($aggregatedData, $facilityData);
                
                // Ensure key ACZ fields are properly formatted
                $aggregatedData['facility_city_state_zip'] = trim(
                    ($facility->city ?? '') . ', ' .
                    ($facility->state ?? '') . ' ' .
                    ($facility->zip_code ?? '')
                );
                
                // Handle facility contact info - combine phone/email as required by ACZ
                $facilityContactInfo = [];
                if ($facility->contact_phone) {
                    $facilityContactInfo[] = $facility->contact_phone;
                }
                if ($facility->contact_email) {
                    $facilityContactInfo[] = $facility->contact_email;
                }
                $aggregatedData['facility_contact_info'] = implode(' / ', $facilityContactInfo);
                
                // Map place_of_service to individual checkboxes for ACZ form
                $pos = $facility->default_place_of_service ?? '';
                $aggregatedData['place_of_service'] = $pos;
                $aggregatedData['pos_11'] = ($pos === '11'); // Office
                $aggregatedData['pos_21'] = ($pos === '21'); // Inpatient Hospital
                $aggregatedData['pos_24'] = ($pos === '24'); // Ambulatory Surgical Center
                $aggregatedData['pos_22'] = ($pos === '22'); // Outpatient Hospital
                $aggregatedData['pos_32'] = ($pos === '32'); // Nursing Facility
                $aggregatedData['pos_13'] = ($pos === '13'); // Assisted Living
                $aggregatedData['pos_12'] = ($pos === '12'); // Home
                $aggregatedData['critical_access_hospital'] = ($pos === '85'); // Critical Access Hospital
                $aggregatedData['other_pos'] = !in_array($pos, ['11', '21', '24', '22', '32', '13', '12', '85']);
                $aggregatedData['pos_other'] = $aggregatedData['other_pos']; // Alternative field name
                $aggregatedData['pos_other_specify'] = $aggregatedData['other_pos'] ? $pos : '';
                
                $this->logger->info('Successfully extracted facility data from database', [
                    'episode_id' => $episode->id,
                    'facility_id' => $facility->id,
                    'facility_name' => $facility->name,
                    'has_npi' => !empty($facility->npi),
                    'has_contact_info' => !empty($facilityContactInfo),
                    'has_organization' => !empty($facility->organization)
                ]);
            } else {
                // Log warning if no facility data available
                $this->logger->warning('No facility data available for DocuSeal mapping', [
                    'episode_id' => $episode->id,
                    'episode_facility_id' => $episode->facility_id,
                    'metadata_has_facility' => isset($metadata['facility_data']),
                    'metadata_facility_id' => $metadata['facility_data']['id'] ?? null
                ]);
                
                // Set empty facility data to prevent DocuSeal errors
                $facilityDefaults = [
                    'facility_name' => '',
                    'facility_npi' => '',
                    'facility_address' => '',
                    'facility_city_state_zip' => '',
                    'facility_phone' => '',
                    'facility_tax_id' => '',
                    'facility_contact_name' => '',
                    'facility_contact_info' => '',
                ];
                $aggregatedData = array_merge($aggregatedData, $facilityDefaults);
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
            
            // Clinical data (from form) - Enhanced to match Step4ClinicalBilling.tsx fields
            if (isset($metadata['clinical_data'])) {
                $clinicalData = $metadata['clinical_data'];
                
                // Basic wound information
                $aggregatedData['wound_type'] = $clinicalData['wound_type'] ?? '';
                $aggregatedData['wound_other_specify'] = $clinicalData['wound_other_specify'] ?? '';
                $aggregatedData['wound_location'] = $clinicalData['wound_location'] ?? '';
                $aggregatedData['wound_location_details'] = $clinicalData['wound_location_details'] ?? '';
                
                // Wound size measurements 
                $aggregatedData['wound_size_length'] = $clinicalData['wound_length'] ?? $clinicalData['wound_size_length'] ?? '';
                $aggregatedData['wound_size_width'] = $clinicalData['wound_width'] ?? $clinicalData['wound_size_width'] ?? '';
                $aggregatedData['wound_size_depth'] = $clinicalData['wound_depth'] ?? $clinicalData['wound_size_depth'] ?? '';
                
                // Wound duration fields from Step4ClinicalBilling.tsx
                $aggregatedData['wound_duration_days'] = $clinicalData['wound_duration_days'] ?? '';
                $aggregatedData['wound_duration_weeks'] = $clinicalData['wound_duration_weeks'] ?? '';
                $aggregatedData['wound_duration_months'] = $clinicalData['wound_duration_months'] ?? '';
                $aggregatedData['wound_duration_years'] = $clinicalData['wound_duration_years'] ?? '';
                
                // Diagnosis codes - flexible handling for single or dual coding
                $aggregatedData['primary_diagnosis_code'] = $clinicalData['primary_diagnosis_code'] ?? '';
                $aggregatedData['secondary_diagnosis_code'] = $clinicalData['secondary_diagnosis_code'] ?? '';
                $aggregatedData['diagnosis_code'] = $clinicalData['diagnosis_code'] ?? ''; // For single-code wounds
                
                // Build ICD-10 codes array
                $aggregatedData['icd10_codes'] = [];
                if (!empty($clinicalData['primary_diagnosis_code'])) {
                    $aggregatedData['icd10_codes'][] = $clinicalData['primary_diagnosis_code'];
                }
                if (!empty($clinicalData['secondary_diagnosis_code'])) {
                    $aggregatedData['icd10_codes'][] = $clinicalData['secondary_diagnosis_code'];
                }
                if (!empty($clinicalData['diagnosis_code']) && empty($aggregatedData['icd10_codes'])) {
                    $aggregatedData['icd10_codes'][] = $clinicalData['diagnosis_code'];
                }
                
                // Application CPT codes
                $aggregatedData['application_cpt_codes'] = $clinicalData['application_cpt_codes'] ?? [];
                $aggregatedData['cpt_codes'] = $clinicalData['application_cpt_codes'] ?? [];
                
                // Application history
                $aggregatedData['prior_applications'] = $clinicalData['prior_applications'] ?? '';
                $aggregatedData['prior_application_product'] = $clinicalData['prior_application_product'] ?? '';
                $aggregatedData['prior_application_within_12_months'] = $clinicalData['prior_application_within_12_months'] ?? false;
                $aggregatedData['anticipated_applications'] = $clinicalData['anticipated_applications'] ?? '';
                
                // Previous treatments
                $aggregatedData['previous_treatments'] = $clinicalData['previous_treatments'] ?? '';
                $aggregatedData['previous_treatments_selected'] = $clinicalData['previous_treatments_selected'] ?? [];
                
                // Facility/Billing status from Step4ClinicalBilling.tsx
                $aggregatedData['place_of_service'] = $clinicalData['place_of_service'] ?? '';
                $aggregatedData['medicare_part_b_authorized'] = $clinicalData['medicare_part_b_authorized'] ?? false;
                $aggregatedData['snf_days'] = $clinicalData['snf_days'] ?? '';
                $aggregatedData['hospice_status'] = $clinicalData['hospice_status'] ?? false;
                $aggregatedData['hospice_family_consent'] = $clinicalData['hospice_family_consent'] ?? false;
                $aggregatedData['hospice_clinically_necessary'] = $clinicalData['hospice_clinically_necessary'] ?? false;
                $aggregatedData['part_a_status'] = $clinicalData['part_a_status'] ?? false;
                $aggregatedData['global_period_status'] = $clinicalData['global_period_status'] ?? false;
                $aggregatedData['global_period_cpt'] = $clinicalData['global_period_cpt'] ?? '';
                $aggregatedData['global_period_surgery_date'] = $clinicalData['global_period_surgery_date'] ?? '';
                
                // Map wound types to individual checkboxes
                $woundType = strtolower($clinicalData['wound_type'] ?? '');
                $woundTypes = $clinicalData['wound_types'] ?? [];
                
                // Check both single wound_type and wound_types array
                $aggregatedData['wound_dfu'] = ($woundType === 'dfu' || in_array('DFU', $woundTypes));
                $aggregatedData['wound_vlu'] = ($woundType === 'vlu' || in_array('VLU', $woundTypes));
                $aggregatedData['wound_chronic_ulcer'] = ($woundType === 'chronic ulcer' || in_array('Chronic Ulcer', $woundTypes));
                $aggregatedData['wound_dehisced_surgical'] = (
                    str_contains($woundType, 'dehisced') || 
                    str_contains($woundType, 'surgical') ||
                    in_array('Dehisced Surgical', $woundTypes) ||
                    in_array('Surgical', $woundTypes)
                );
                $aggregatedData['wound_mohs_surgical'] = (
                    str_contains($woundType, 'mohs') ||
                    in_array('Mohs Surgical', $woundTypes)
                );
                
                // Legacy fields for backward compatibility
                $aggregatedData['graft_size_requested'] = $clinicalData['graft_size_requested'] ?? '';
                $aggregatedData['icd10_code_1'] = $aggregatedData['primary_diagnosis_code'];
                $aggregatedData['cpt_code_1'] = !empty($aggregatedData['application_cpt_codes']) ? $aggregatedData['application_cpt_codes'][0] : '';
                $aggregatedData['failed_conservative_treatment'] = $clinicalData['failed_conservative_treatment'] ?? '';
                $aggregatedData['information_accurate'] = $clinicalData['information_accurate'] ?? '';
                $aggregatedData['medical_necessity_established'] = $clinicalData['medical_necessity_established'] ?? '';
                $aggregatedData['maintain_documentation'] = $clinicalData['maintain_documentation'] ?? '';
                
                // Calculate wound size total if not present
                if (!empty($aggregatedData['wound_size_length']) && !empty($aggregatedData['wound_size_width'])) {
                    $aggregatedData['wound_size_total'] = floatval($aggregatedData['wound_size_length']) * floatval($aggregatedData['wound_size_width']);
                }
                
                // Add procedure_date (required field) - default to today if not provided
                $aggregatedData['procedure_date'] = $clinicalData['procedure_date'] ?? now()->format('Y-m-d');
                $aggregatedData['date'] = $aggregatedData['procedure_date']; // Alternative field name
                
                // Add additional clinical fields
                $aggregatedData['wound_location'] = $clinicalData['wound_location'] ?? '';
                $aggregatedData['location_of_wound'] = $clinicalData['wound_location'] ?? ''; // Alternative field name
                $aggregatedData['previously_used_therapies'] = $clinicalData['previous_treatments'] ?? '';
                $aggregatedData['wound_duration'] = $clinicalData['wound_duration_weeks'] ?? '';
                $aggregatedData['co_morbidities'] = $clinicalData['comorbidities'] ?? '';
                $aggregatedData['post_debridement_size'] = $aggregatedData['wound_size_total'] ?? '';
                
                // Add primary and secondary ICD-10 codes
                $aggregatedData['primary_icd10'] = $clinicalData['primary_diagnosis_code'] ?? '';
                $aggregatedData['secondary_icd10'] = $clinicalData['secondary_diagnosis_code'] ?? '';
                
                // Medical history field
                $aggregatedData['medical_history'] = $clinicalData['medical_history'] ?? $clinicalData['comorbidities'] ?? '';
                
                // Wound location checkboxes based on location and size
                $woundLocation = strtolower($clinicalData['wound_location'] ?? '');
                $woundSizeTotal = floatval($aggregatedData['wound_size_total'] ?? 0);
                
                // Determine if location is legs/arms/trunk vs feet/hands/head
                $isLegsArmsTrunk = false;
                $isFeetHandsHead = false;
                
                if (str_contains($woundLocation, 'leg') || str_contains($woundLocation, 'arm') || 
                    str_contains($woundLocation, 'trunk') || str_contains($woundLocation, 'torso') ||
                    str_contains($woundLocation, 'thigh') || str_contains($woundLocation, 'calf') ||
                    str_contains($woundLocation, 'shin') || str_contains($woundLocation, 'knee') ||
                    str_contains($woundLocation, 'elbow') || str_contains($woundLocation, 'forearm') ||
                    str_contains($woundLocation, 'upper arm') || str_contains($woundLocation, 'shoulder')) {
                    $isLegsArmsTrunk = true;
                } elseif (str_contains($woundLocation, 'foot') || str_contains($woundLocation, 'feet') ||
                         str_contains($woundLocation, 'hand') || str_contains($woundLocation, 'head') ||
                         str_contains($woundLocation, 'toe') || str_contains($woundLocation, 'finger') ||
                         str_contains($woundLocation, 'ankle') || str_contains($woundLocation, 'wrist') ||
                         str_contains($woundLocation, 'heel') || str_contains($woundLocation, 'plantar')) {
                    $isFeetHandsHead = true;
                }
                
                // Set wound location checkboxes based on location and size
                $aggregatedData['wound_location_legs_arms_trunk_less_100'] = $isLegsArmsTrunk && $woundSizeTotal < 100;
                $aggregatedData['wound_location_legs_arms_trunk_greater_100'] = $isLegsArmsTrunk && $woundSizeTotal >= 100;
                $aggregatedData['wound_location_feet_hands_head_less_100'] = $isFeetHandsHead && $woundSizeTotal < 100;
                $aggregatedData['wound_location_feet_hands_head_greater_100'] = $isFeetHandsHead && $woundSizeTotal >= 100;
                
                // Format total wound size
                $aggregatedData['total_wound_size'] = $woundSizeTotal > 0 ? number_format($woundSizeTotal, 2) . ' sq cm' : '';
                
                // Format ICD-10 codes as comma-separated string
                $aggregatedData['icd_10_codes'] = implode(', ', array_filter($aggregatedData['icd10_codes'] ?? []));
            }
            
            // Insurance data (from form)
            if (isset($metadata['insurance_data'])) {
                $insuranceData = $metadata['insurance_data'];
                
                // Handle both array and object formats
                if (isset($insuranceData[0])) {
                    // Array format from transformRequestDataForInsuranceHandler
                    foreach ($insuranceData as $index => $policy) {
                        $policyType = $policy['policy_type'] ?? '';
                        if ($policyType === 'primary') {
                            $aggregatedData['primary_insurance_name'] = $policy['payer_name'] ?? '';
                            $aggregatedData['primary_member_id'] = $policy['member_id'] ?? '';
                            $aggregatedData['insurance_name'] = $policy['payer_name'] ?? ''; // Alias
                            $aggregatedData['insurance_member_id'] = $policy['member_id'] ?? ''; // Alias
                            $aggregatedData['primary_name'] = $policy['payer_name'] ?? ''; // Alternative field name
                            $aggregatedData['primary_policy'] = $policy['member_id'] ?? ''; // Alternative field name
                            $aggregatedData['primary_phone'] = $policy['phone'] ?? ''; // Alternative field name
                            $aggregatedData['primary_policy_number'] = $policy['member_id'] ?? ''; // Alternative field name
                            $aggregatedData['primary_payer_phone'] = $policy['phone'] ?? ''; // Alternative field name
                        } elseif ($policyType === 'secondary') {
                            $aggregatedData['secondary_insurance_name'] = $policy['payer_name'] ?? '';
                            $aggregatedData['secondary_member_id'] = $policy['member_id'] ?? '';
                            $aggregatedData['secondary_name'] = $policy['payer_name'] ?? ''; // Alternative field name
                            $aggregatedData['secondary_policy'] = $policy['member_id'] ?? ''; // Alternative field name
                            $aggregatedData['secondary_phone'] = $policy['phone'] ?? ''; // Alternative field name
                            $aggregatedData['secondary_policy_number'] = $policy['member_id'] ?? ''; // Alternative field name
                            $aggregatedData['secondary_payer_phone'] = $policy['phone'] ?? ''; // Alternative field name
                        }
                    }
                } else {
                    // Object format from extractInsuranceData
                    $aggregatedData['primary_insurance_name'] = $insuranceData['primary_name'] ?? '';
                    $aggregatedData['primary_member_id'] = $insuranceData['primary_member_id'] ?? '';
                    $aggregatedData['insurance_name'] = $insuranceData['primary_name'] ?? ''; // Alias
                    $aggregatedData['insurance_member_id'] = $insuranceData['primary_member_id'] ?? ''; // Alias
                    $aggregatedData['primary_name'] = $insuranceData['primary_name'] ?? ''; // Alternative field name
                    $aggregatedData['primary_policy'] = $insuranceData['primary_member_id'] ?? ''; // Alternative field name
                    $aggregatedData['primary_phone'] = $insuranceData['primary_payer_phone'] ?? ''; // Alternative field name
                    $aggregatedData['primary_policy_number'] = $insuranceData['primary_member_id'] ?? ''; // Alternative field name
                    $aggregatedData['primary_payer_phone'] = $insuranceData['primary_payer_phone'] ?? ''; // Alternative field name
                    
                    if (!empty($insuranceData['has_secondary_insurance']) && !empty($insuranceData['secondary_insurance_name'])) {
                        $aggregatedData['secondary_insurance_name'] = $insuranceData['secondary_insurance_name'] ?? '';
                        $aggregatedData['secondary_member_id'] = $insuranceData['secondary_member_id'] ?? '';
                        $aggregatedData['secondary_name'] = $insuranceData['secondary_insurance_name'] ?? ''; // Alternative field name
                        $aggregatedData['secondary_policy'] = $insuranceData['secondary_member_id'] ?? ''; // Alternative field name
                        $aggregatedData['secondary_phone'] = $insuranceData['secondary_payer_phone'] ?? ''; // Alternative field name
                        $aggregatedData['secondary_policy_number'] = $insuranceData['secondary_member_id'] ?? ''; // Alternative field name
                        $aggregatedData['secondary_payer_phone'] = $insuranceData['secondary_payer_phone'] ?? ''; // Alternative field name
                    }
                }
                
                // Prior authorization fields
                $priorAuthPermission = $insuranceData['prior_auth_permission'] ?? false;
                $aggregatedData['prior_auth_yes'] = $priorAuthPermission;
                $aggregatedData['prior_auth_no'] = !$priorAuthPermission;
                
                // Authorization permission fields
                $aggregatedData['permission_prior_auth_yes'] = $priorAuthPermission;
                $aggregatedData['permission_prior_auth_no'] = !$priorAuthPermission;
                
                // Provider network status (default to in-network)
                $aggregatedData['physician_status_primary_in_network'] = true;
                $aggregatedData['physician_status_primary_out_of_network'] = false;
                $aggregatedData['physician_status_secondary_in_network'] = true;
                $aggregatedData['physician_status_secondary_out_of_network'] = false;
            }
            
            // Clinical status questions
            // Hospice status (default to No)
            $aggregatedData['patient_in_hospice_yes'] = false;
            $aggregatedData['patient_in_hospice_no'] = true;
            if (isset($metadata['clinical_data']['hospice_status'])) {
                $aggregatedData['patient_in_hospice_yes'] = $metadata['clinical_data']['hospice_status'] ?? false;
                $aggregatedData['patient_in_hospice_no'] = !($metadata['clinical_data']['hospice_status'] ?? false);
            }
            
            // Part A facility stay (default to No)
            $aggregatedData['patient_facility_part_a_yes'] = false;
            $aggregatedData['patient_facility_part_a_no'] = true;
            if (isset($metadata['clinical_data']['part_a_stay'])) {
                $aggregatedData['patient_facility_part_a_yes'] = $metadata['clinical_data']['part_a_stay'] ?? false;
                $aggregatedData['patient_facility_part_a_no'] = !($metadata['clinical_data']['part_a_stay'] ?? false);
            }
            
            // Post-op global surgery period (already partially handled above)
            $aggregatedData['patient_post_op_global_yes'] = $aggregatedData['patient_global_yes'] ?? false;
            $aggregatedData['patient_post_op_global_no'] = $aggregatedData['patient_global_no'] ?? true;
            $aggregatedData['surgery_cpts'] = $metadata['clinical_data']['global_period_cpt'] ?? '';
            $aggregatedData['surgery_date'] = $metadata['clinical_data']['global_period_surgery_date'] ?? '';
            
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
            
            // Set distributor_company to always be "MSC Wound Care"
            $aggregatedData['distributor_company'] = 'MSC Wound Care';
            
            // Extract HCPCS code from selected products and map to checkboxes
            if (isset($metadata['order_details']['products']) && !empty($metadata['order_details']['products'])) {
                $productCodes = [];
                
                foreach ($metadata['order_details']['products'] as $selectedProduct) {
                    // Try to get product code (HCPCS/Q-code)
                    if (isset($selectedProduct['product']['code'])) {
                        $productCodes[] = $selectedProduct['product']['code'];
                    } elseif (isset($selectedProduct['product_id'])) {
                        // Load product from database to get code
                        $product = \App\Models\Order\Product::find($selectedProduct['product_id']);
                        if ($product && $product->code) {
                            $productCodes[] = $product->code;
                        }
                    }
                }
                
                // Store the codes
                if (!empty($productCodes)) {
                    $aggregatedData['hcpcs_codes'] = $productCodes;
                    $aggregatedData['product_code'] = $productCodes[0]; // First code for compatibility
                    $aggregatedData['selected_product_codes'] = $productCodes;
                    
                    // Map product codes to Q-code checkboxes for all manufacturers
                    $aggregatedData['q4161'] = in_array('Q4161', $productCodes);
                    $aggregatedData['q4205'] = in_array('Q4205', $productCodes);
                    $aggregatedData['q4290'] = in_array('Q4290', $productCodes);
                    $aggregatedData['q4238'] = in_array('Q4238', $productCodes);
                    $aggregatedData['q4239'] = in_array('Q4239', $productCodes);
                    $aggregatedData['q4266'] = in_array('Q4266', $productCodes);
                    $aggregatedData['q4267'] = in_array('Q4267', $productCodes);
                    $aggregatedData['q4265'] = in_array('Q4265', $productCodes);
                    
                    // Additional Q-code checkboxes for ACZ & Associates and other manufacturers
                    $aggregatedData['q4344'] = in_array('Q4344', $productCodes);
                    $aggregatedData['q4289'] = in_array('Q4289', $productCodes);
                    $aggregatedData['q4275'] = in_array('Q4275', $productCodes);
                    $aggregatedData['q4341'] = in_array('Q4341', $productCodes);
                    $aggregatedData['q4313'] = in_array('Q4313', $productCodes);
                    $aggregatedData['q4316'] = in_array('Q4316', $productCodes);
                    $aggregatedData['q4164'] = in_array('Q4164', $productCodes);
                }
            }
            
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
            
            // Return aggregated data without any manufacturer-specific hardcoding
            // Manufacturer-specific mapping should be handled by configuration services
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

    /**
     * Prepare AI-enhanced Docuseal data using manufacturer-specific form mappings
     */
    public function prepareAIEnhancedDocusealData(PatientManufacturerIVREpisode $episode, string $manufacturerFormId, string $documentType = 'insurance'): array
    {
        try {
            $this->logger->info('Preparing AI-enhanced Docuseal data', [
                'episode_id' => $episode->id,
                'manufacturer_form_id' => $manufacturerFormId,
                'document_type' => $documentType
            ]);

            // Get base data from orchestrator
            $baseData = $this->prepareDocusealData($episode);

            // Track start time for metrics
            $startTime = microtime(true);
            
            // Use the new OptimizedMedicalAiService for enhanced field mapping
            $enhancedData = $this->optimizedMedicalAi->enhanceDocusealFieldMapping(
                $episode,
                $baseData,
                $manufacturerFormId
            );
            
            // Calculate response time
            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            // Add AI metadata
            $enhancedData['_ai_metadata'] = [
                'quality_grade' => $enhancedData['_ai_confidence'] > 0.8 ? 'A' : ($enhancedData['_ai_confidence'] > 0.6 ? 'B' : 'C'),
                'confidence_score' => $enhancedData['_ai_confidence'] ?? 0,
                'method' => $enhancedData['_ai_method'] ?? 'unknown',
                'recommendations' => $enhancedData['_ai_recommendations'] ?? [],
                'manufacturer_form_id' => $manufacturerFormId,
                'enhanced_at' => now()->toISOString()
            ];

            $this->logger->info('AI-enhanced Docuseal data prepared successfully', [
                'episode_id' => $episode->id,
                'manufacturer_form_id' => $manufacturerFormId,
                'base_fields' => count($baseData),
                'enhanced_fields' => count($enhancedData),
                'confidence_score' => $enhancedData['_ai_confidence'] ?? 0,
                'method' => $enhancedData['_ai_method'] ?? 'unknown'
            ]);
            
            // Record success metrics
            $this->metricsService->recordSuccess([
                'episode_id' => $episode->id,
                'manufacturer' => $episode->manufacturer_name,
                'template_id' => $manufacturerFormId,
                'confidence' => $enhancedData['_ai_confidence'] ?? 0,
                'response_time' => $responseTime,
                'fields_mapped' => count($enhancedData),
                'total_fields' => count($baseData),
                'method' => $enhancedData['_ai_method'] ?? 'ai'
            ]);

            return $enhancedData;

        } catch (\Exception $e) {
            $this->logger->error('Failed to prepare AI-enhanced Docuseal data', [
                'episode_id' => $episode->id,
                'manufacturer_form_id' => $manufacturerFormId,
                'error' => $e->getMessage()
            ]);
            
            // Record failure metrics
            $this->metricsService->recordFailure([
                'episode_id' => $episode->id,
                'manufacturer' => $episode->manufacturer_name,
                'error' => $e->getMessage()
            ]);

            // Fall back to base data if AI enhancement fails
            return $this->prepareDocusealData($episode);
        }
    }

    /**
     * Get manufacturer form ID based on episode data
     */
    public function getManufacturerFormId(PatientManufacturerIVREpisode $episode, string $documentType = 'insurance'): string
    {
        try {
            // Load manufacturer and determine form ID
            $manufacturer = \App\Models\Order\Manufacturer::find($episode->manufacturer_id);
            
            if (!$manufacturer) {
                throw new \Exception("Manufacturer not found for episode {$episode->id}");
            }

            // Form mappings should be loaded from manufacturer configuration
            // This hardcoded approach is deprecated
            $manufacturerName = $manufacturer->name;
            $formId = null; // Will need to be loaded from config



            if (!$formId) {
                $this->logger->warning('No form mapping found for manufacturer', [
                    'manufacturer_name' => $manufacturerName,
                    'manufacturer_id' => $episode->manufacturer_id
                ]);
                
                // Default to a generic form
                $formId = 'form2_IVR';
            }

            // For Extremity Care, check if we should use Restorigin form based on product
            if ($formId === 'form7_ExtremityCare_FT') {
                $orderDetails = $episode->metadata['order_details'] ?? [];
                $products = $orderDetails['products'] ?? [];
                
                foreach ($products as $product) {
                    $productName = $product['product']['name'] ?? '';
                    if (str_contains(strtolower($productName), 'restorigin')) {
                        $formId = 'form8_ExtremityCare_RO';
                        break;
                    }
                }
            }

            $this->logger->info('Determined manufacturer form ID', [
                'episode_id' => $episode->id,
                'manufacturer_name' => $manufacturerName,
                'form_id' => $formId,
                'document_type' => $documentType
            ]);

            return $formId;

        } catch (\Exception $e) {
            $this->logger->error('Failed to determine manufacturer form ID', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);

            // Return default form ID
            return 'form2_IVR';
        }
    }

    /**
     * Create episode with asynchronous FHIR processing for better performance
     */
    public function createEpisodeWithAsyncFhir(array $data): PatientManufacturerIVREpisode
    {
        try {
            $this->logger->info('Creating episode with async FHIR processing');

            DB::beginTransaction();

            // Create episode immediately without waiting for FHIR
            $episode = PatientManufacturerIVREpisode::create([
                'patient_id' => $data['patient']['id'] ?? uniqid('patient-'),
                'patient_fhir_id' => null, // Will be populated by async job
                'patient_display_id' => $data['patient']['display_id'] ?? null,
                'manufacturer_id' => $data['manufacturer_id'],
                'status' => PatientManufacturerIVREpisode::STATUS_PROCESSING_FHIR,
                'ivr_status' => PatientManufacturerIVREpisode::IVR_STATUS_PENDING,
                'created_by' => Auth::id(),
                'metadata' => [
                    'is_async_fhir' => true,
                    'patient_data' => $data['patient'] ?? [],
                    'clinical_data' => $data['clinical'] ?? [],
                    'provider_data' => $data['provider'] ?? [],
                    'facility_data' => $data['facility'] ?? [],
                    'organization_data' => $data['organization'] ?? [],
                    'insurance_data' => $this->transformRequestDataForInsuranceHandler($data),
                    'order_details' => $data['order_details'] ?? [],
                    'created_by' => Auth::id(),
                    'async_started_at' => now()->toISOString()
                ]
            ]);

            // Create initial order immediately
            $order = $this->orderHandler->createInitialOrder($episode, $data['order_details']);

            DB::commit();

            // Dispatch async job to create FHIR resources
            dispatch(new CreateEpisodeFhirResourcesJob($episode->id, $data))
                ->onQueue('fhir-processing');

            $this->logger->info('Episode created with async FHIR processing queued', [
                'episode_id' => $episode->id,
                'status' => $episode->status
            ]);

            return $episode->load('orders');

        } catch (Exception $e) {
            DB::rollBack();
            $this->logger->error('Failed to create episode with async FHIR', [
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to create episode: ' . $e->getMessage());
        }
    }

    /**
     * Enhanced DocuSeal data preparation with AI optimization
     */
    public function prepareOptimizedDocusealData(PatientManufacturerIVREpisode $episode): array
    {
        try {
            $this->logger->info('Preparing optimized DocuSeal data with AI enhancement');

            // Get base data from orchestrator
            $baseData = $this->prepareDocusealData($episode);

            // Determine manufacturer form ID
            $manufacturerFormId = $this->getManufacturerFormId($episode);

            // Use AI service for enhanced field mapping
            $aiEnhancedData = $this->callAIServiceForMapping($baseData, $manufacturerFormId, $episode);

            // Merge AI enhancements with base data
            $finalData = $this->mergeDataWithAIEnhancements($baseData, $aiEnhancedData);

            // Add performance metrics
            $finalData['_performance_metrics'] = [
                'base_fields_count' => count($baseData),
                'ai_enhanced_fields_count' => count($aiEnhancedData['mapped_fields'] ?? []),
                'final_fields_count' => count($finalData),
                'ai_quality_grade' => $aiEnhancedData['quality_grade'] ?? 'N/A',
                'prepared_at' => now()->toISOString()
            ];

            return $finalData;

        } catch (\Exception $e) {
            $this->logger->error('Failed to prepare optimized DocuSeal data', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);
            // Fallback to standard data preparation
            return $this->prepareDocusealData($episode);
        }
    }

    /**
     * Call AI service for enhanced field mapping
     */
    private function callAIServiceForMapping(array $baseData, string $formId, PatientManufacturerIVREpisode $episode): array
    {
        try {
            $this->logger->info('Calling AI service for field mapping', [
                'episode_id' => $episode->id,
                'form_id' => $formId,
                'data_fields' => count($baseData),
                'manufacturer' => $this->getManufacturerName($episode)
            ]);

            // Use OptimizedMedicalAiService which is already injected
            $result = $this->optimizedMedicalAi->enhanceDocusealFieldMapping(
                $episode,
                $baseData,
                $formId
            );
            
            $this->logger->info('AI service mapping completed', [
                'episode_id' => $episode->id,
                'form_id' => $formId,
                'enhanced_fields' => count($result),
                'original_fields' => count($baseData)
            ]);

            // Return in the expected format
            return [
                'mapped_fields' => $result,
                'quality_grade' => 'A',
                'processing_time_ms' => 0,
                'ai_model_used' => 'azure-openai',
                'cache_hit' => false
            ];

        } catch (\Exception $e) {
            $this->logger->error('AI service mapping failed', [
                'episode_id' => $episode->id,
                'form_id' => $formId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->getFallbackMappingResult($baseData, $formId);
        }
    }

    /**
     * Extract FHIR data for AI processing
     */
    private function extractFHIRDataForAI(PatientManufacturerIVREpisode $episode): array
    {
        try {
            $fhirData = [];

            // Extract patient data from FHIR
            if ($episode->patient_fhir_id) {
                $fhirData['patient'] = [
                    'fhir_id' => $episode->patient_fhir_id,
                    'resource_type' => 'Patient'
                ];
            }

            // Extract provider data from FHIR
            if ($episode->provider_fhir_id) {
                $fhirData['provider'] = [
                    'fhir_id' => $episode->provider_fhir_id,
                    'resource_type' => 'Practitioner'
                ];
            }

            // Extract organization data from FHIR
            if ($episode->organization_fhir_id) {
                $fhirData['organization'] = [
                    'fhir_id' => $episode->organization_fhir_id,
                    'resource_type' => 'Organization'
                ];
            }

            // Extract coverage data from FHIR
            if ($episode->primary_coverage_fhir_id) {
                $fhirData['coverage'] = [
                    'primary_fhir_id' => $episode->primary_coverage_fhir_id,
                    'resource_type' => 'Coverage'
                ];
            }

            return $fhirData;

        } catch (\Exception $e) {
            $this->logger->warning('Failed to extract FHIR data for AI', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Get fallback mapping result when AI service fails
     */
    private function getFallbackMappingResult(array $baseData, string $formId): array
    {
        return [
            'mapped_fields' => $this->performBasicFieldMapping($baseData),
            'confidence_scores' => array_fill_keys(array_keys($baseData), 0.5),
            'quality_grade' => 'C',
            'suggestions' => ['AI service unavailable - using basic mapping'],
            'processing_notes' => ['Fallback mapping used', 'Check AI service health'],
            'processing_method' => 'fallback',
            'service_available' => false,
            'processing_time_ms' => 0
        ];
    }

    /**
     * Perform basic field mapping as fallback
     */
    private function performBasicFieldMapping(array $sourceData): array
    {
        $mapped = [];
        
        // Basic field mapping patterns
        $mappings = [
            'patient_first_name' => ['first_name', 'fname', 'patient_fname'],
            'patient_last_name' => ['last_name', 'lname', 'patient_lname'],
            'patient_dob' => ['date_of_birth', 'dob', 'birth_date'],
            'primary_insurance_name' => ['insurance_company', 'insurance_name'],
            'primary_member_id' => ['member_id', 'policy_number'],
            'provider_name' => ['doctor_name', 'physician_name'],
            'diagnosis_code' => ['icd10', 'diagnosis']
        ];

        foreach ($sourceData as $key => $value) {
            if (empty($value)) continue;
            
            // Direct mapping first
            if (array_key_exists($key, $mappings)) {
                $mapped[$key] = $value;
                continue;
            }
            
            // Pattern matching
            $lowerKey = strtolower($key);
            foreach ($mappings as $targetField => $patterns) {
                foreach ($patterns as $pattern) {
                    if (str_contains($lowerKey, $pattern)) {
                        $mapped[$targetField] = $value;
                        break 2;
                    }
                }
            }
        }

        return $mapped;
    }

    /**
     * Merge AI enhancements with base data intelligently
     */
    private function mergeDataWithAIEnhancements(array $baseData, array $aiData): array
    {
        $merged = $baseData;
        $aiMappedFields = $aiData['mapped_fields'] ?? [];

        foreach ($aiMappedFields as $field => $value) {
            // Only override if AI has higher confidence or field is empty
            if (empty($merged[$field]) || $this->shouldUseAIValue($field, $merged[$field], $value, $aiData)) {
                $merged[$field] = $value;
            }
        }

        return $merged;
    }

    /**
     * Determine if AI value should override existing value
     */
    private function shouldUseAIValue(string $field, $existingValue, $aiValue, array $aiData): bool
    {
        $confidenceScores = $aiData['confidence_scores'] ?? [];
        $fieldConfidence = $confidenceScores[$field] ?? 0;

        // Use AI value if confidence is high and values are significantly different
        return $fieldConfidence > 0.8 && $existingValue !== $aiValue;
    }

    /**
     * Get manufacturer name for episode
     */
    private function getManufacturerName(PatientManufacturerIVREpisode $episode): string
    {
        $manufacturer = \App\Models\Order\Manufacturer::find($episode->manufacturer_id);
        return $manufacturer?->name ?? 'Unknown';
    }

    /**
     * Extract comprehensive data from provider and facility IDs without requiring an episode
     * Used for DocuSeal form generation during quick request flow before episode creation
     */
    public function extractDataFromIds(array $data): array
    {
        try {
            $this->logger->info('Extracting comprehensive data from IDs', [
                'provider_id' => $data['provider_id'] ?? null,
                'facility_id' => $data['facility_id'] ?? null,
                'has_patient_data' => !empty($data['patient_first_name'])
            ]);

            $extractedData = [];
            
            // Get manufacturer config to know what fields to extract
            $manufacturer = null;
            if (!empty($data['manufacturer_id'])) {
                $manufacturer = \App\Models\Order\Manufacturer::find($data['manufacturer_id']);
            }
            
            $requiredFields = [];
            if ($manufacturer) {
                // Load the manufacturer's field configuration using consistent slug logic
                $slug = \Illuminate\Support\Str::slug($manufacturer->name);
                $configPath = "manufacturers/{$slug}.php";
                $config = config($configPath);
                
                if ($config && isset($config['docuseal_field_names'])) {
                    $requiredFields = array_keys($config['docuseal_field_names']);
                    $this->logger->info('Loaded manufacturer config for field extraction', [
                        'manufacturer' => $manufacturer->name,
                        'required_fields_count' => count($requiredFields),
                        'sample_fields' => array_slice($requiredFields, 0, 10)
                    ]);
                }
            }
            
            // Use EntityDataService for targeted extraction when we have required fields
            if (!empty($requiredFields) && (!empty($data['provider_id']) || !empty($data['facility_id']))) {
                $currentUser = Auth::user();
                $entityData = $this->entityDataService->extractDataByRole(
                    $currentUser?->id ?? 0,
                    $data['facility_id'] ?? null,
                    $data['provider_id'] ?? null,
                    $requiredFields
                );
                
                $extractedData = array_merge($extractedData, $entityData);
                
                // Fallback facility extraction when role-based service returned none
                if (!empty($data['facility_id']) && empty($extractedData['facility_name'])) {
                    try {
                        $facilityModel = \App\Models\Fhir\Facility::with(['organization'])->find($data['facility_id']);
                        if ($facilityModel) {
                            $dataExtractor = app(\App\Services\FieldMapping\DataExtractor::class);
                            $facilityFallback = $dataExtractor->extractFacilityData($facilityModel);
                            
                            // Filter to only required facility fields to avoid data bloat
                            $facilityRequiredFields = array_filter($requiredFields, fn($field) => str_contains($field, 'facility'));
                            $filteredFacilityData = array_intersect_key($facilityFallback, array_flip($facilityRequiredFields));
                            
                            if (!empty($filteredFacilityData)) {
                                $extractedData = array_merge($extractedData, $filteredFacilityData);
                                $this->logger->info('Added fallback facility data', [
                                    'facility_id' => $data['facility_id'],
                                    'fields_added' => array_keys($filteredFacilityData)
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        $this->logger->warning('Fallback facility extraction failed', [
                            'facility_id' => $data['facility_id'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                $this->logger->info('Extracted entity data using EntityDataService', [
                    'fields_extracted' => count($entityData),
                    'has_provider_data' => !empty(array_filter(array_keys($entityData), fn($k) => str_contains($k, 'physician'))),
                    'has_facility_data' => !empty(array_filter(array_keys($entityData), fn($k) => str_contains($k, 'facility')))
                ]);
            } else if (!empty($data['provider_id']) || !empty($data['facility_id'])) {
                // When no manufacturer config, extract minimal required fields only
                $minimalFields = [
                    // Minimal provider fields
                    'physician_name', 'physician_npi', 'physician_phone', 'physician_specialty',
                    // Minimal facility fields  
                    'facility_name', 'facility_npi', 'facility_phone', 'facility_address',
                    'facility_city_state_zip'
                ];
                
                $currentUser = Auth::user();
                $entityData = $this->entityDataService->extractDataByRole(
                    $currentUser?->id ?? 0,
                    $data['facility_id'] ?? null,
                    $data['provider_id'] ?? null,
                    $minimalFields
                );
                
                $extractedData = array_merge($extractedData, $entityData);
                
                $this->logger->info('Extracted minimal entity data (no manufacturer config)', [
                    'fields_extracted' => count($entityData),
                    'fields_requested' => count($minimalFields)
                ]);
            }
            
            // Extract patient data from frontend form data - only if in required fields
            if (!empty($data['patient_first_name']) || !empty($data['patient_last_name'])) {
                $patientFieldMapping = [
                    'patient_first_name' => $data['patient_first_name'] ?? '',
                    'patient_last_name' => $data['patient_last_name'] ?? '',
                    'patient_name' => trim(($data['patient_first_name'] ?? '') . ' ' . ($data['patient_last_name'] ?? '')),
                    'patient_dob' => $data['patient_dob'] ?? '',
                    'patient_gender' => $data['patient_gender'] ?? '',
                    'patient_phone' => $data['patient_phone'] ?? '',
                    'patient_email' => $data['patient_email'] ?? '',
                    
                    // Fix address mapping - use address_line1 as main address, combine line1 and line2 if both present
                    'patient_address' => $this->formatPatientAddress($data),
                    'patient_address_line1' => $data['patient_address_line1'] ?? $data['patient_address'] ?? '',
                    'patient_address_line2' => $data['patient_address_line2'] ?? '',
                    'patient_city' => $data['patient_city'] ?? '',
                    'patient_state' => $data['patient_state'] ?? '',
                    'patient_zip' => $data['patient_zip'] ?? $data['patient_postal_code'] ?? '',
                    'patient_member_id' => $data['patient_member_id'] ?? $data['member_id'] ?? '',
                    
                    // Fix city_state_zip formatting - match ACZ IVR expected format
                    'patient_city_state_zip' => $this->formatCityStateZip(
                        $data['patient_city'] ?? '',
                        $data['patient_state'] ?? '',
                        $data['patient_zip'] ?? $data['patient_postal_code'] ?? ''
                    ),
                    'patient_caregiver_info' => $data['patient_caregiver_info'] ?? '',
                ];
                
                // Only include patient fields that are in required fields (or include all if no filter)
                if (!empty($requiredFields)) {
                    foreach ($patientFieldMapping as $field => $value) {
                        if (in_array($field, $requiredFields) && (!empty($value) || $value === '0')) {
                            $extractedData[$field] = $value;
                        }
                    }
                } else {
                    // No filter, include non-empty patient data
                    foreach ($patientFieldMapping as $field => $value) {
                        if (!empty($value) || $value === '0') {
                            $extractedData[$field] = $value;
                        }
                    }
                }
            }
            
            // Extract insurance data - only if in required fields
            if (!empty($data['primary_insurance_name']) || !empty($data['insurance_name'])) {
                $insuranceFieldMapping = [
                    'primary_insurance_name' => $data['primary_insurance_name'] ?? $data['insurance_name'] ?? '',
                    'primary_member_id' => $data['primary_member_id'] ?? $data['member_id'] ?? '',
                    'primary_policy_number' => $data['primary_member_id'] ?? $data['member_id'] ?? '',
                    'primary_plan_type' => $data['primary_plan_type'] ?? $data['plan_type'] ?? '',
                    'primary_payer_phone' => $data['primary_payer_phone'] ?? '',
                    'secondary_insurance_name' => $data['secondary_insurance_name'] ?? '',
                    'secondary_member_id' => $data['secondary_member_id'] ?? '',
                    'secondary_policy_number' => $data['secondary_member_id'] ?? '',
                    'secondary_payer_phone' => $data['secondary_payer_phone'] ?? '',
                    
                    // Network status - dropdown values from Step2PatientInsurance
                    'primary_physician_network_status' => $data['primary_physician_network_status'] ?? '',
                    'secondary_physician_network_status' => $data['secondary_physician_network_status'] ?? '',
                ];
                
                // Convert network status dropdown values to DocuSeal checkbox fields
                $insuranceFieldMapping = array_merge($insuranceFieldMapping, $this->mapNetworkStatusToCheckboxes($data));
                
                // Only include insurance fields that are in required fields (or include all if no filter)
                if (!empty($requiredFields)) {
                    foreach ($insuranceFieldMapping as $field => $value) {
                        if (in_array($field, $requiredFields) && (!empty($value) || $value === '0')) {
                            $extractedData[$field] = $value;
                        }
                    }
                } else {
                    // No filter, include non-empty insurance data
                    foreach ($insuranceFieldMapping as $field => $value) {
                        if (!empty($value) || $value === '0') {
                            $extractedData[$field] = $value;
                        }
                    }
                }
            }
            
            // Extract clinical data - only if in required fields
            if (!empty($data['diagnosis_code']) || !empty($data['wound_type'])) {
                $clinicalFieldMapping = [
                    'primary_diagnosis_code' => $data['primary_diagnosis_code'] ?? $data['diagnosis_code'] ?? '',
                    'secondary_diagnosis_code' => $data['secondary_diagnosis_code'] ?? '',
                    'icd_10_codes' => $data['icd_10_codes'] ?? $data['diagnosis_codes'] ?? '',
                    'wound_type' => $data['wound_type'] ?? '',
                    'wound_location' => $data['wound_location'] ?? '',
                    'wound_size_length' => $data['wound_length'] ?? $data['wound_size_length'] ?? '',
                    'wound_size_width' => $data['wound_width'] ?? $data['wound_size_width'] ?? '',
                    'wound_size_depth' => $data['wound_depth'] ?? $data['wound_size_depth'] ?? '',
                    'total_wound_size' => $data['total_wound_size'] ?? '',
                    'wound_start_date' => $data['wound_start_date'] ?? '',
                    'medical_history' => $data['medical_history'] ?? $data['medical_history_notes'] ?? '',
                ];
                
                // Calculate total wound size if not provided
                if (empty($clinicalFieldMapping['total_wound_size']) && 
                    !empty($clinicalFieldMapping['wound_size_length']) && 
                    !empty($clinicalFieldMapping['wound_size_width'])) {
                    $clinicalFieldMapping['total_wound_size'] = 
                        (float)$clinicalFieldMapping['wound_size_length'] * 
                        (float)$clinicalFieldMapping['wound_size_width'];
                }
                
                // Only include clinical fields that are in required fields (or include all if no filter)
                if (!empty($requiredFields)) {
                    foreach ($clinicalFieldMapping as $field => $value) {
                        if (in_array($field, $requiredFields) && !empty($value)) {
                            $extractedData[$field] = $value;
                        }
                    }
                } else {
                    // No filter, include non-empty clinical data
                    foreach ($clinicalFieldMapping as $field => $value) {
                        if (!empty($value)) {
                            $extractedData[$field] = $value;
                        }
                    }
                }
            }
            
            // Add any additional fields from data that match required fields
            if (!empty($requiredFields)) {
                foreach ($data as $key => $value) {
                    if (in_array($key, $requiredFields) && !isset($extractedData[$key]) && !empty($value)) {
                        $extractedData[$key] = $value;
                    }
                }
            }
            
            $this->logger->info('Data extraction completed', [
                'total_fields' => count($extractedData),
                'has_provider' => !empty($extractedData['provider_name']) || !empty($extractedData['physician_name']),
                'has_facility' => !empty($extractedData['facility_name']),
                'has_patient' => !empty($extractedData['patient_first_name']),
                'has_insurance' => !empty($extractedData['primary_insurance_name']),
                'used_required_fields_filter' => !empty($requiredFields),
                'required_fields_count' => count($requiredFields)
            ]);
            
            // No manufacturer-specific hardcoding - return the extracted data as is
            return $extractedData;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to extract data from IDs', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return whatever data we have
            return $data;
        }
    }

    /**
     * Extract only the fields needed for a specific manufacturer's DocuSeal template
     * This prevents service overlap by targeting only configured fields
     */
    public function extractTargetedDocusealData(PatientManufacturerIVREpisode $episode, array $frontendData = []): array
    {
        try {
            // Load manufacturer configuration
            $manufacturer = \App\Models\Order\Manufacturer::find($episode->manufacturer_id);
            if (!$manufacturer) {
                throw new \Exception("Manufacturer not found for episode");
            }

            $configPath = "manufacturers/" . strtolower(str_replace([' ', '&'], ['-', '-'], $manufacturer->name)) . ".php";
            $config = config($configPath);
            
            if (!$config || !isset($config['docuseal_field_names'])) {
                $this->logger->warning('No manufacturer config found for targeted extraction', [
                    'manufacturer' => $manufacturer->name,
                    'config_path' => $configPath
                ]);
                // Use standard extraction if no config
                return $this->extractDataFromIds(array_merge($frontendData, [
                    'manufacturer_id' => $manufacturer->id,
                    'provider_id' => $episode->provider_id,
                    'facility_id' => $episode->facility_id
                ]));
            }

            // Get only the fields required by this manufacturer
            $requiredFields = array_keys($config['docuseal_field_names']);
            $metadata = $episode->metadata ?? [];
            
            $this->logger->info('Starting targeted DocuSeal data extraction', [
                'manufacturer' => $manufacturer->name,
                'required_fields' => count($requiredFields),
                'sample_fields' => array_slice($requiredFields, 0, 10)
            ]);

            // Use EntityDataService for targeted extraction from database entities
            $currentUser = Auth::user();
            $entityData = $this->entityDataService->extractDataByRole(
                $currentUser?->id ?? 0,
                $episode->facility_id,
                $episode->provider_id,
                $requiredFields
            );
            
            // Merge with frontend data, giving priority to frontend data
            $extractedData = array_merge($entityData, $frontendData);
            
            // Extract additional fields from episode metadata
            foreach ($requiredFields as $fieldKey) {
                // Skip if already extracted
                if (isset($extractedData[$fieldKey])) {
                    continue;
                }
                
                // Extract from metadata for patient, insurance, clinical fields
                $value = $this->extractFieldFromMetadata($fieldKey, $metadata);
                if ($value !== null) {
                    $extractedData[$fieldKey] = $value;
                }
            }

            // Map to DocuSeal field names
            $docusealData = [];
            foreach ($extractedData as $key => $value) {
                if (isset($config['docuseal_field_names'][$key])) {
                    $docusealFieldName = $config['docuseal_field_names'][$key];
                    $docusealData[$docusealFieldName] = $value;
                }
            }

            $this->logger->info('Targeted DocuSeal data extraction completed', [
                'manufacturer' => $manufacturer->name,
                'required_fields' => count($requiredFields),
                'extracted_fields' => count($extractedData),
                'docuseal_fields' => count($docusealData)
            ]);

            return $extractedData; // Return canonical field names, not DocuSeal field names
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to extract targeted DocuSeal data', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to standard extraction with manufacturer_id
            return $this->extractDataFromIds(array_merge($frontendData, [
                'manufacturer_id' => $episode->manufacturer_id,
                'provider_id' => $episode->provider_id,
                'facility_id' => $episode->facility_id
            ]));
        }
    }

    /**
     * Extract field from episode metadata (patient, insurance, clinical data)
     */
    private function extractFieldFromMetadata(string $fieldKey, array $metadata): mixed
    {
        // Map field keys to data sources
        switch (true) {
            // Patient fields
            case str_starts_with($fieldKey, 'patient_'):
                return $this->extractPatientField($fieldKey, $metadata);
                
            // Insurance fields
            case str_contains($fieldKey, 'insurance') || str_contains($fieldKey, 'policy') || str_contains($fieldKey, 'payer'):
                return $this->extractInsuranceField($fieldKey, $metadata);
                
            // Clinical status fields
            case str_contains($fieldKey, 'hospice') || str_contains($fieldKey, 'part_a') || str_contains($fieldKey, 'post_op'):
                return $this->extractClinicalStatusField($fieldKey, $metadata);
                
            // Product Q-code fields
            case preg_match('/^q\d{4}$/', $fieldKey):
                return $this->extractProductQCodeField($fieldKey, $metadata);
                
            // Wound location fields
            case str_contains($fieldKey, 'wound_location_'):
                return $this->extractWoundLocationField($fieldKey, $metadata);
                
            // Other specific fields
            case $fieldKey === 'iso_if_applicable':
                return $metadata['iso_code'] ?? '';
            case $fieldKey === 'additional_emails':
                return $metadata['additional_notification_emails'] ?? '';
                
            // Clinical fields not already handled
            case $fieldKey === 'icd_10_codes':
            case $fieldKey === 'total_wound_size':
            case $fieldKey === 'medical_history':
            case $fieldKey === 'surgery_cpts':
            case $fieldKey === 'surgery_date':
                return $this->extractClinicalStatusField($fieldKey, $metadata);
                
            default:
                return null;
        }
    }

    /**
     * Extract header fields (name, email, phone)
     */
    private function extractHeaderField(string $fieldKey, array $metadata): ?string
    {
        $currentUser = \Illuminate\Support\Facades\Auth::user();
        
        switch ($fieldKey) {
            case 'name':
                if ($currentUser) {
                    return $currentUser->full_name ?? trim(($currentUser->first_name ?? '') . ' ' . ($currentUser->last_name ?? ''));
                }
                return $metadata['submitter_name'] ?? '';
                
            case 'email':
                return $currentUser?->email ?? $metadata['submitter_email'] ?? '';
                
            case 'phone':
                // Try multiple sources for phone
                if ($currentUser && isset($currentUser->phone)) {
                    return $currentUser->phone;
                }
                if (isset($metadata['provider_data']['phone'])) {
                    return $metadata['provider_data']['phone'];
                }
                if (isset($metadata['facility_data']['phone'])) {
                    return $metadata['facility_data']['phone'];
                }
                return '';
                
            default:
                return null;
        }
    }

    /**
     * Extract physician/provider fields
     */
    private function extractPhysicianField(string $fieldKey, PatientManufacturerIVREpisode $episode, array $metadata): ?string
    {
        // Load provider data if not in metadata
        $providerData = $metadata['provider_data'] ?? [];
        
        if (empty($providerData) && isset($metadata['provider_id'])) {
            $provider = \App\Models\User::with(['profile', 'providerCredentials'])
                ->find($metadata['provider_id']);
            if ($provider) {
                $dataExtractor = app(\App\Services\FieldMapping\DataExtractor::class);
                $providerData = $dataExtractor->extractProviderData($provider);
            }
        }
        
        // Map physician fields from provider data
        $mapping = [
            'physician_name' => $providerData['provider_name'] ?? '',
            'physician_npi' => $providerData['provider_npi'] ?? '',
            'physician_specialty' => $providerData['provider_specialty'] ?? $providerData['provider_credentials'] ?? '',
            'physician_tax_id' => $providerData['tax_id'] ?? $providerData['provider_tax_id'] ?? '',
            'physician_ptan' => $providerData['provider_ptan'] ?? '',
            'physician_medicaid_number' => $providerData['provider_medicaid'] ?? '',
            'physician_phone' => $providerData['provider_phone'] ?? '',
            'physician_fax' => $providerData['provider_fax'] ?? '',
            'physician_organization' => $providerData['practice_name'] ?? '',
        ];
        
        // Handle special status fields
        if (str_contains($fieldKey, 'physician_status')) {
            // Default to in-network
            if (str_contains($fieldKey, 'in_network')) {
                return 'true';
            } else if (str_contains($fieldKey, 'out_of_network')) {
                return 'false';
            }
        }
        
        return $mapping[$fieldKey] ?? null;
    }

    /**
     * Extract facility fields
     */
    private function extractFacilityField(string $fieldKey, PatientManufacturerIVREpisode $episode, array $metadata): ?string
    {
        // Load facility data if not in metadata
        $facilityData = $metadata['facility_data'] ?? [];
        
        if (empty($facilityData) && isset($metadata['facility_id'])) {
            $facility = \App\Models\Fhir\Facility::with(['organization'])
                ->find($metadata['facility_id']);
            if ($facility) {
                $dataExtractor = app(\App\Services\FieldMapping\DataExtractor::class);
                $facilityData = $dataExtractor->extractFacilityData($facility);
            }
        }
        
        // Special handling for facility_city_state_zip
        if ($fieldKey === 'facility_city_state_zip') {
            $city = $facilityData['facility_city'] ?? '';
            $state = $facilityData['facility_state'] ?? '';
            $zip = $facilityData['facility_zip'] ?? '';
            return trim("$city, $state $zip");
        }
        
        // Direct field mapping
        $mapping = [
            'facility_npi' => $facilityData['facility_npi'] ?? '',
            'facility_tax_id' => $facilityData['facility_tax_id'] ?? '',
            'facility_name' => $facilityData['facility_name'] ?? '',
            'facility_ptan' => $facilityData['facility_ptan'] ?? '',
            'facility_address' => $facilityData['facility_address'] ?? '',
            'facility_medicaid_number' => $facilityData['facility_medicaid_number'] ?? '',
            'facility_phone' => $facilityData['facility_phone'] ?? '',
            'facility_contact_name' => $facilityData['facility_contact_name'] ?? '',
            'facility_fax' => $facilityData['facility_fax'] ?? '',
            'facility_contact_number' => $facilityData['facility_contact_phone'] ?? $facilityData['facility_contact_email'] ?? '',
            'facility_organization' => $facilityData['facility_organization_name'] ?? '',
        ];
        
        return $mapping[$fieldKey] ?? null;
    }

    /**
     * Extract patient fields
     */
    private function extractPatientField(string $fieldKey, array $metadata): ?string
    {
        $patientData = $metadata['patient_data'] ?? [];
        
        // Special handling for patient_city_state_zip
        if ($fieldKey === 'patient_city_state_zip') {
            $city = $patientData['city'] ?? '';
            $state = $patientData['state'] ?? '';
            $zip = $patientData['zip'] ?? $patientData['postal_code'] ?? '';
            return trim("$city, $state $zip");
        }
        
        // Special handling for clinical status fields
        if (str_contains($fieldKey, 'patient_in_hospice')) {
            $inHospice = $patientData['is_in_hospice'] ?? false;
            if (str_contains($fieldKey, '_yes')) {
                return $inHospice ? 'true' : 'false';
            } else {
                return $inHospice ? 'false' : 'true';
            }
        }
        
        if (str_contains($fieldKey, 'patient_facility_part_a')) {
            $inPartA = $patientData['is_in_part_a'] ?? false;
            if (str_contains($fieldKey, '_yes')) {
                return $inPartA ? 'true' : 'false';
            } else {
                return $inPartA ? 'false' : 'true';
            }
        }
        
        if (str_contains($fieldKey, 'patient_post_op_global')) {
            $inPostOp = $patientData['is_post_op'] ?? false;
            if (str_contains($fieldKey, '_yes')) {
                return $inPostOp ? 'true' : 'false';
            } else {
                return $inPostOp ? 'false' : 'true';
            }
        }
        
        // Direct field mapping
        $mapping = [
            'patient_name' => trim(($patientData['first_name'] ?? '') . ' ' . ($patientData['last_name'] ?? '')),
            'patient_dob' => $patientData['dob'] ?? '',
            'patient_address' => $patientData['address_line1'] ?? '',
            'patient_phone' => $patientData['phone'] ?? '',
            'patient_email' => $patientData['email'] ?? '',
            'patient_caregiver_info' => $patientData['caregiver_info'] ?? '',
        ];
        
        return $mapping[$fieldKey] ?? null;
    }

    /**
     * Extract insurance fields
     */
    private function extractInsuranceField(string $fieldKey, array $metadata): ?string
    {
        $insuranceData = $metadata['insurance_data'] ?? [];
        
        // Handle authorization permission fields
        if (str_contains($fieldKey, 'permission_prior_auth')) {
            $hasPermission = $insuranceData['prior_auth_permission'] ?? false;
            if (str_contains($fieldKey, '_yes')) {
                return $hasPermission ? 'true' : 'false';
            } else {
                return $hasPermission ? 'false' : 'true';
            }
        }
        
        // Direct field mapping
        $mapping = [
            'primary_insurance_name' => $insuranceData['primary_insurance_name'] ?? '',
            'secondary_insurance_name' => $insuranceData['secondary_insurance_name'] ?? '',
            'primary_policy_number' => $insuranceData['primary_member_id'] ?? '',
            'secondary_policy_number' => $insuranceData['secondary_member_id'] ?? '',
            'primary_payer_phone' => $insuranceData['primary_payer_phone'] ?? '',
            'secondary_payer_phone' => $insuranceData['secondary_payer_phone'] ?? '',
        ];
        
        return $mapping[$fieldKey] ?? null;
    }

    /**
     * Extract place of service fields
     */
    private function extractPlaceOfServiceField(string $fieldKey, array $metadata): ?string
    {
        $facilityData = $metadata['facility_data'] ?? [];
        $pos = $facilityData['place_of_service'] ?? '';
        
        // Map specific POS checkboxes
        $posMapping = [
            'pos_11' => '11',  // Office
            'pos_22' => '22',  // Outpatient Hospital
            'pos_24' => '24',  // Ambulatory Surgical Center
            'pos_12' => '12',  // Home
            'pos_32' => '32',  // Nursing Facility
        ];
        
        foreach ($posMapping as $field => $value) {
            if ($fieldKey === $field) {
                return ($pos === $value) ? 'true' : 'false';
            }
        }
        
        if ($fieldKey === 'pos_other') {
            return !in_array($pos, array_values($posMapping)) ? 'true' : 'false';
        }
        
        if ($fieldKey === 'pos_other_specify') {
            return !in_array($pos, array_values($posMapping)) ? $pos : '';
        }
        
        return null;
    }

    /**
     * Extract clinical status fields
     */
    private function extractClinicalStatusField(string $fieldKey, array $metadata): ?string
    {
        $clinicalData = $metadata['clinical_data'] ?? [];
        
        switch ($fieldKey) {
            case 'surgery_cpts':
                return $clinicalData['surgery_cpts'] ?? '';
            case 'surgery_date':
                return $clinicalData['surgery_date'] ?? '';
            case 'icd_10_codes':
                $codes = $clinicalData['icd_codes'] ?? [];
                return is_array($codes) ? implode(', ', $codes) : $codes;
            case 'total_wound_size':
                $size = $clinicalData['wound_size_cm2'] ?? '';
                return $size ? "{$size} cm²" : '';
            case 'medical_history':
                return $clinicalData['medical_history'] ?? $clinicalData['comorbidities'] ?? '';
            default:
                return null;
        }
    }

    /**
     * Extract product Q-code fields
     */
    private function extractProductQCodeField(string $fieldKey, array $metadata): ?string
    {
        // Check if this Q-code is in the selected products
        $products = $metadata['selected_products'] ?? $metadata['order_details']['products'] ?? [];
        
        foreach ($products as $product) {
            // Check different possible Q-code field names
            $qCode = $product['q_code'] ?? $product['code'] ?? $product['product']['code'] ?? '';
            if (strtolower($qCode) === $fieldKey) {
                return 'true';
            }
        }
        
        return 'false';
    }

    /**
     * Extract wound location fields
     */
    private function extractWoundLocationField(string $fieldKey, array $metadata): ?string
    {
        $clinicalData = $metadata['clinical_data'] ?? [];
        $woundLocation = strtolower($clinicalData['wound_anatomical_area'] ?? '');
        $woundSize = floatval($clinicalData['wound_size_cm2'] ?? 0);
        
        // Map wound locations to categories
        $legsArmsTrunk = ['leg', 'arm', 'trunk', 'torso', 'chest', 'abdomen', 'back'];
        $feetHandsHead = ['foot', 'feet', 'hand', 'hands', 'head', 'face', 'scalp'];
        
        $isLegsArmsTrunk = false;
        $isFeetHandsHead = false;
        
        foreach ($legsArmsTrunk as $location) {
            if (str_contains($woundLocation, $location)) {
                $isLegsArmsTrunk = true;
                break;
            }
        }
        
        foreach ($feetHandsHead as $location) {
            if (str_contains($woundLocation, $location)) {
                $isFeetHandsHead = true;
                break;
            }
        }
        
        switch ($fieldKey) {
            case 'wound_location_legs_arms_trunk_less_100':
                return ($isLegsArmsTrunk && $woundSize < 100) ? 'true' : 'false';
            case 'wound_location_legs_arms_trunk_greater_100':
                return ($isLegsArmsTrunk && $woundSize >= 100) ? 'true' : 'false';
            case 'wound_location_feet_hands_head_less_100':
                return ($isFeetHandsHead && $woundSize < 100) ? 'true' : 'false';
            case 'wound_location_feet_hands_head_greater_100':
                return ($isFeetHandsHead && $woundSize >= 100) ? 'true' : 'false';
            default:
                return null;
        }
    }

    /**
     * Enhance aggregated data with comprehensive facility data extraction
     * This ensures facility data is properly extracted even if EntityDataService misses it
     */
    private function enhanceWithComprehensiveFacilityData(PatientManufacturerIVREpisode $episode, array &$aggregatedData, array $metadata): void
    {
        $this->logger->info('Running comprehensive facility data extraction');
        
        $facility = null;
        
        // Try to get facility from episode's facility_id first (most reliable)
        if ($episode->facility_id) {
            try {
                $facility = \App\Models\Fhir\Facility::with(['organization'])->find($episode->facility_id);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to load facility from episode facility_id', [
                    'episode_id' => $episode->id,
                    'facility_id' => $episode->facility_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // If no facility found, fall back to metadata (backward compatibility)
        if (!$facility && isset($metadata['facility_data']) && !empty($metadata['facility_data']['id'])) {
            try {
                $facility = \App\Models\Fhir\Facility::with(['organization'])->find($metadata['facility_data']['id']);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to load facility from metadata', [
                    'episode_id' => $episode->id,
                    'metadata_facility_id' => $metadata['facility_data']['id'] ?? null,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if ($facility) {
            // Extract comprehensive facility data directly from the model and merge into aggregatedData
            $facilityData = [
                // Basic facility info
                'facility_id' => $facility->id,
                'facility_name' => $facility->name,
                'facility_type' => $facility->facility_type,
                'facility_status' => $facility->status,
                'facility_active' => $facility->active,
                
                // Address information
                'facility_address' => $facility->address,
                'facility_address_line1' => $facility->address_line1 ?: $facility->address,
                'facility_address_line2' => $facility->address_line2,
                'facility_city' => $facility->city,
                'facility_state' => $facility->state,
                'facility_zip' => $facility->zip_code,
                'facility_zip_code' => $facility->zip_code,
                
                // Contact information
                'facility_phone' => $facility->phone,
                'facility_fax' => $facility->fax,
                'facility_email' => $facility->email,
                'facility_contact_name' => $facility->contact_name,
                'facility_contact_phone' => $facility->contact_phone,
                'facility_contact_email' => $facility->contact_email,
                'facility_contact_fax' => $facility->contact_fax,
                
                // Practice/Business identifiers
                'facility_npi' => $facility->npi,
                'facility_group_npi' => $facility->group_npi,
                'facility_tax_id' => $facility->tax_id,
                'facility_tin' => $facility->tax_id, // Alias
                'facility_ptan' => $facility->ptan,
                'facility_medicaid_number' => $facility->medicaid_number,
                'facility_medicaid' => $facility->medicaid_number, // Alias
                'facility_medicare_admin_contractor' => $facility->medicare_admin_contractor,
                'medicare_admin_contractor' => $facility->medicare_admin_contractor, // Alias
                'mac' => $facility->medicare_admin_contractor, // Short alias
                
                // Place of service
                'facility_default_place_of_service' => $facility->default_place_of_service,
                'place_of_service' => $facility->default_place_of_service, // Alias
                
                // Business operations
                'facility_business_hours' => $facility->business_hours,
                'facility_npi_verified_at' => $facility->npi_verified_at,
                
                // FHIR integration
                'fhir_organization_id' => $facility->fhir_organization_id,
                
                // Additional aliases needed for DocuSeal compatibility
                'facility_info' => $facility->name . ' - ' . $facility->phone,
                'facility_contact_info' => $facility->contact_phone . ' / ' . $facility->contact_email,
                'facility_full_address' => trim($facility->address . ', ' . $facility->city . ', ' . $facility->state . ' ' . $facility->zip_code),
                'facility_city_state_zip' => trim($facility->city . ', ' . $facility->state . ' ' . $facility->zip_code),
            ];

            // Add organization data if available
            if ($facility->organization) {
                $org = $facility->organization;
                $facilityData = array_merge($facilityData, [
                    // Organization info
                    'organization_id' => $org->id,
                    'organization_name' => $org->name,
                    'organization_type' => $org->type,
                    'organization_status' => $org->status,
                    'organization_tax_id' => $org->tax_id,
                    'organization_npi' => $org->npi,
                    
                    // Organization contact
                    'organization_phone' => $org->phone,
                    'organization_email' => $org->email,
                    'organization_address' => $org->address,
                    'organization_city' => $org->city,
                    'organization_state' => $org->state,
                    'organization_zip_code' => $org->zip_code,
                    
                    // Organization aliases
                    'facility_organization' => $org->name,
                ]);
            }

            // Merge facility data into aggregatedData, overriding with database values if available
            foreach ($facilityData as $key => $value) {
                if (!empty($value)) {
                    $aggregatedData[$key] = $value;  // Override always if database has value
                }
            }

            $this->logger->info('Enhanced facility data extraction completed', [
                'facility_id' => $facility->id,
                'facility_name' => $facility->name,
                'fields_added' => array_keys($facilityData),
                'organization_loaded' => !empty($facility->organization)
            ]);
        } else {
            $this->logger->warning('No facility found for comprehensive extraction', [
                'episode_id' => $episode->id,
                'episode_facility_id' => $episode->facility_id,
                'metadata_has_facility' => isset($metadata['facility_data'])
            ]);
        }
    }

    private function formatPatientAddress(array $data): string
    {
        // Only include address lines, not city/state/zip
        $addressLine1 = $data['patient_address_line1'] ?? $data['patient_address'] ?? '';
        $addressLine2 = $data['patient_address_line2'] ?? '';
        
        if (!empty($addressLine2)) {
            return trim($addressLine1 . ' ' . $addressLine2);
        }
        
        return trim($addressLine1);
    }

    private function formatCityStateZip(string $city, string $state, string $zip): string
    {
        // Format: "City, State Zip" - remove empty components and clean up commas
        $parts = array_filter([
            trim($city),
            trim($state),
            trim($zip)
        ]);
        
        if (empty($parts)) {
            return '';
        }
        
        // If we have city and (state or zip), format properly
        if (count($parts) >= 2) {
            $city = $parts[0] ?? '';
            $state = $parts[1] ?? '';
            $zip = $parts[2] ?? '';
            
            return trim($city . ', ' . $state . ' ' . $zip);
        }
        
        // Otherwise just return what we have
        return implode(' ', $parts);
    }

    private function mapNetworkStatusToCheckboxes(array $data): array
    {
        $checkboxFields = [];
        
        // Map primary physician network status
        if (!empty($data['primary_physician_network_status'])) {
            $primaryStatus = $data['primary_physician_network_status'];
            switch ($primaryStatus) {
                case 'in_network':
                    $checkboxFields['physician_status_primary_in_network'] = 'true';
                    $checkboxFields['physician_status_primary_out_of_network'] = 'false';
                    break;
                case 'out_of_network':
                    $checkboxFields['physician_status_primary_in_network'] = 'false';
                    $checkboxFields['physician_status_primary_out_of_network'] = 'true';
                    break;
                case 'not_sure':
                default:
                    // Default to in-network if not sure
                    $checkboxFields['physician_status_primary_in_network'] = 'true';
                    $checkboxFields['physician_status_primary_out_of_network'] = 'false';
                    break;
            }
        }
        
        // Map secondary physician network status
        if (!empty($data['secondary_physician_network_status'])) {
            $secondaryStatus = $data['secondary_physician_network_status'];
            switch ($secondaryStatus) {
                case 'in_network':
                    $checkboxFields['physician_status_secondary_in_network'] = 'true';
                    $checkboxFields['physician_status_secondary_out_of_network'] = 'false';
                    break;
                case 'out_of_network':
                    $checkboxFields['physician_status_secondary_in_network'] = 'false';
                    $checkboxFields['physician_status_secondary_out_of_network'] = 'true';
                    break;
                case 'not_sure':
                default:
                    // Default to in-network if not sure
                    $checkboxFields['physician_status_secondary_in_network'] = 'true';
                    $checkboxFields['physician_status_secondary_out_of_network'] = 'false';
                    break;
            }
        } else {
            // Default secondary to in-network if no secondary insurance specified
            $checkboxFields['physician_status_secondary_in_network'] = 'true';
            $checkboxFields['physician_status_secondary_out_of_network'] = 'false';
        }
        
        return $checkboxFields;
    }
}

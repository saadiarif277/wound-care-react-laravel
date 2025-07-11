<?php

namespace App\Services\QuickRequest;

use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\Order;
use App\Models\Order\Manufacturer;
use App\Services\QuickRequest\Handlers\PatientHandler;
use App\Services\QuickRequest\Handlers\ProviderHandler;
use App\Services\QuickRequest\Handlers\ClinicalHandler;
use App\Services\QuickRequest\Handlers\InsuranceHandler;
use App\Services\QuickRequest\Handlers\OrderHandler;
use App\Services\QuickRequest\Handlers\NotificationHandler;
use App\Services\FhirToIvrFieldMapper;
use App\Jobs\CreateEpisodeFhirResourcesJob;
use App\Services\Medical\OptimizedMedicalAiService;
use App\Services\AI\FieldMappingMetricsService;
use App\Logging\PhiSafeLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;
use App\Services\CanonicalFieldService;
use App\Services\FhirService;
use Illuminate\Support\Facades\Log;

class QuickRequestOrchestrator
{
    public function __construct(
        protected PatientHandler $patientHandler,
        protected ProviderHandler $providerHandler,
        protected ClinicalHandler $clinicalHandler,
        protected InsuranceHandler $insuranceHandler,
        protected OrderHandler $orderHandler,
        protected NotificationHandler $notificationHandler,
        protected FhirToIvrFieldMapper $fhirMapper,
        protected OptimizedMedicalAiService $optimizedMedicalAi,
        protected PhiSafeLogger $logger,
        protected ?FieldMappingMetricsService $metricsService = null,
        protected ?CanonicalFieldService $canonicalFieldService = null
    ) {
        // Initialize services if not provided
        $this->metricsService = $metricsService ?? app(FieldMappingMetricsService::class);
        $this->canonicalFieldService = $canonicalFieldService ?? app(CanonicalFieldService::class);
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
            
            // Link practitioner to patient if not already linked
            $fhirService = app(FhirService::class);
            $patient = $fhirService->getPatientById($patientFhirId);
            if ($patient && empty($patient['generalPractitioner'])) {
                $patient['generalPractitioner'] = [
                    [
                        'reference' => "Practitioner/{$providerFhirId}"
                    ]
                ];
                $fhirService->updatePatient($patientFhirId, $patient);
                Log::debug('Linked practitioner to patient', ['patient_id' => $patientFhirId, 'practitioner_id' => $providerFhirId]);
            }
            
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
            
            // Use FhirToIvrFieldMapper to extract comprehensive data
            $aggregatedData = $this->fhirMapper->extractDataFromFhir($fhirIds, $metadata);
            
            // Insurance fallback from metadata if not in FHIR data
            if (empty($aggregatedData['primary_insurance_name']) && isset($metadata['insurance_data'])) {
                Log::debug('Using insurance fallback from metadata', ['episode_id' => $episode->id]);
                $insuranceData = $metadata['insurance_data'];
                
                // Handle both array and object formats
                if (isset($insuranceData[0])) {  // Array format
                    foreach ($insuranceData as $policy) {
                        $policyType = $policy['policy_type'] ?? '';
                        if ($policyType === 'primary') {
                            $aggregatedData['primary_insurance_name'] = $policy['payer_name'] ?? '';
                            $aggregatedData['primary_member_id'] = $policy['member_id'] ?? '';
                            $aggregatedData['insurance_name'] = $policy['payer_name'] ?? '';
                            $aggregatedData['insurance_member_id'] = $policy['member_id'] ?? '';
                        } elseif ($policyType === 'secondary') {
                            $aggregatedData['secondary_insurance_name'] = $policy['payer_name'] ?? '';
                            $aggregatedData['secondary_member_id'] = $policy['member_id'] ?? '';
                        }
                    }
                } else {  // Object format
                    $aggregatedData['primary_insurance_name'] = $insuranceData['primary_name'] ?? '';
                    $aggregatedData['primary_member_id'] = $insuranceData['primary_member_id'] ?? '';
                    $aggregatedData['insurance_name'] = $insuranceData['primary_name'] ?? '';
                    $aggregatedData['insurance_member_id'] = $insuranceData['primary_member_id'] ?? '';
                    if (!empty($insuranceData['has_secondary_insurance'])) {
                        $aggregatedData['secondary_insurance_name'] = $insuranceData['secondary_insurance_name'] ?? '';
                        $aggregatedData['secondary_member_id'] = $insuranceData['secondary_member_id'] ?? '';
                    }
                }
            }
            
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
            }
            
            // Provider data (from provider profile)
            if (isset($metadata['provider_data'])) {
                $providerData = $metadata['provider_data'];
                
                // Use CanonicalFieldService for mapping provider fields
                $aggregatedData['provider_name'] = $this->canonicalFieldService->getFieldValue('provider', 'provider_name', $providerData) ?? $providerData['name'] ?? '';
                $aggregatedData['provider_first_name'] = $this->canonicalFieldService->getFieldValue('provider', 'provider_first_name', $providerData) ?? $providerData['first_name'] ?? '';
                $aggregatedData['provider_last_name'] = $this->canonicalFieldService->getFieldValue('provider', 'provider_last_name', $providerData) ?? $providerData['last_name'] ?? '';
                $aggregatedData['provider_npi'] = $this->canonicalFieldService->getFieldValue('provider', 'provider_npi', $providerData) ?? $providerData['npi'] ?? '';
                $aggregatedData['provider_email'] = $this->canonicalFieldService->getFieldValue('provider', 'provider_email', $providerData) ?? $providerData['email'] ?? '';
                $aggregatedData['provider_phone'] = $this->canonicalFieldService->getFieldValue('provider', 'provider_phone', $providerData) ?? $providerData['phone'] ?? '';
                $aggregatedData['provider_specialty'] = $this->canonicalFieldService->getFieldValue('provider', 'provider_specialty', $providerData) ?? $providerData['specialty'] ?? '';
                $aggregatedData['provider_credentials'] = $this->canonicalFieldService->getFieldValue('provider', 'provider_credentials', $providerData) ?? $providerData['credentials'] ?? '';
                $aggregatedData['provider_license_number'] = $this->canonicalFieldService->getFieldValue('provider', 'provider_license_number', $providerData) ?? $providerData['license_number'] ?? '';
                $aggregatedData['provider_license_state'] = $this->canonicalFieldService->getFieldValue('provider', 'provider_license_state', $providerData) ?? $providerData['license_state'] ?? '';
                $aggregatedData['provider_dea_number'] = $this->canonicalFieldService->getFieldValue('provider', 'provider_dea_number', $providerData) ?? $providerData['dea_number'] ?? '';
                $aggregatedData['provider_ptan'] = $this->canonicalFieldService->getFieldValue('provider', 'provider_ptan', $providerData) ?? $providerData['ptan'] ?? '';
                $aggregatedData['provider_tax_id'] = $this->canonicalFieldService->getFieldValue('provider', 'provider_tax_id', $providerData) ?? $providerData['tax_id'] ?? '';
                $aggregatedData['practice_name'] = $this->canonicalFieldService->getFieldValue('provider', 'practice_name', $providerData) ?? $providerData['practice_name'] ?? '';
                
                // Create physician aliases for DocuSeal compatibility
                $aggregatedData['physician_name'] = $aggregatedData['provider_name'];
                $aggregatedData['physician_npi'] = $aggregatedData['provider_npi'];
                $aggregatedData['physician_ptan'] = $aggregatedData['provider_ptan'];
                $aggregatedData['physician_specialty'] = $aggregatedData['provider_specialty'];
                $aggregatedData['provider_medicaid'] = $this->canonicalFieldService->getFieldValue('provider', 'provider_medicaid', $providerData) ?? $providerData['medicaid_number'] ?? '';
            }
            
            // Facility data (from selected facility)
            if (isset($metadata['facility_data'])) {
                $facilityData = $metadata['facility_data'];
                
                // Use CanonicalFieldService for mapping facility fields
                $aggregatedData['facility_name'] = $this->canonicalFieldService->getFieldValue('facility', 'facility_name', $facilityData) ?? $facilityData['name'] ?? '';
                $aggregatedData['facility_address'] = $this->canonicalFieldService->getFieldValue('facility', 'facility_address', $facilityData) ?? $facilityData['address'] ?? '';
                $aggregatedData['facility_address_line1'] = $this->canonicalFieldService->getFieldValue('facility', 'facility_address_line1', $facilityData) ?? $facilityData['address_line1'] ?? '';
                $aggregatedData['facility_address_line2'] = $this->canonicalFieldService->getFieldValue('facility', 'facility_address_line2', $facilityData) ?? $facilityData['address_line2'] ?? '';
                $aggregatedData['facility_city'] = $this->canonicalFieldService->getFieldValue('facility', 'facility_city', $facilityData) ?? $facilityData['city'] ?? '';
                $aggregatedData['facility_state'] = $this->canonicalFieldService->getFieldValue('facility', 'facility_state', $facilityData) ?? $facilityData['state'] ?? '';
                $aggregatedData['facility_zip_code'] = $this->canonicalFieldService->getFieldValue('facility', 'facility_zip_code', $facilityData) ?? $facilityData['zip_code'] ?? '';
                $aggregatedData['facility_phone'] = $this->canonicalFieldService->getFieldValue('facility', 'facility_phone', $facilityData) ?? $facilityData['phone'] ?? '';
                $aggregatedData['facility_fax'] = $this->canonicalFieldService->getFieldValue('facility', 'facility_fax', $facilityData) ?? $facilityData['fax'] ?? '';
                $aggregatedData['facility_email'] = $this->canonicalFieldService->getFieldValue('facility', 'facility_email', $facilityData) ?? $facilityData['email'] ?? '';
                $aggregatedData['facility_npi'] = $this->canonicalFieldService->getFieldValue('facility', 'facility_npi', $facilityData) ?? $facilityData['npi'] ?? '';
                $aggregatedData['facility_group_npi'] = $this->canonicalFieldService->getFieldValue('facility', 'facility_group_npi', $facilityData) ?? $facilityData['group_npi'] ?? '';
                $aggregatedData['facility_ptan'] = $this->canonicalFieldService->getFieldValue('facility', 'facility_ptan', $facilityData) ?? $facilityData['ptan'] ?? '';
                $aggregatedData['facility_tax_id'] = $this->canonicalFieldService->getFieldValue('facility', 'facility_tax_id', $facilityData) ?? $facilityData['tax_id'] ?? '';
                $aggregatedData['facility_type'] = $this->canonicalFieldService->getFieldValue('facility', 'facility_type', $facilityData) ?? $facilityData['facility_type'] ?? '';
                $aggregatedData['place_of_service'] = $this->canonicalFieldService->getFieldValue('facility', 'place_of_service', $facilityData) ?? $facilityData['place_of_service'] ?? '';
                $aggregatedData['facility_medicaid'] = $this->canonicalFieldService->getFieldValue('facility', 'facility_medicaid', $facilityData) ?? $facilityData['medicaid_number'] ?? '';
                
                // Add city_state_zip field for BioWound
                $aggregatedData['city_state_zip'] = trim(
                    ($facilityData['city'] ?? '') . ', ' .
                    ($facilityData['state'] ?? '') . ' ' .
                    ($facilityData['zip_code'] ?? '')
                );
                
                // Map place_of_service to individual checkboxes
                $pos = $facilityData['place_of_service'] ?? '';
                $aggregatedData['pos_11'] = ($pos === '11'); // Office
                $aggregatedData['pos_21'] = ($pos === '21'); // Inpatient Hospital
                $aggregatedData['pos_24'] = ($pos === '24'); // Ambulatory Surgical Center
                $aggregatedData['pos_22'] = ($pos === '22'); // Outpatient Hospital
                $aggregatedData['pos_32'] = ($pos === '32'); // Nursing Facility
                $aggregatedData['pos_13'] = ($pos === '13'); // Assisted Living
                $aggregatedData['pos_12'] = ($pos === '12'); // Home
                $aggregatedData['critical_access_hospital'] = ($pos === '85'); // Critical Access Hospital
                $aggregatedData['other_pos'] = !in_array($pos, ['11', '21', '24', '22', '32', '13', '12', '85']);
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
                
                // Global period status
                $aggregatedData['patient_global_yes'] = $clinicalData['global_period_status'] ?? false;
                $aggregatedData['patient_global_no'] = !($clinicalData['global_period_status'] ?? false);
                
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
                $aggregatedData['secondary_diagnosis_code'] = $clinicalData['secondary_diagnosis_code'] ?? '';
                
                // Add missing diagnosis codes
                $aggregatedData['icd10_codes'] = [];
                if (!empty($clinicalData['primary_diagnosis_code'])) {
                    $aggregatedData['icd10_codes'][] = $clinicalData['primary_diagnosis_code'];
                }
                if (!empty($clinicalData['secondary_diagnosis_code'])) {
                    $aggregatedData['icd10_codes'][] = $clinicalData['secondary_diagnosis_code'];
                }
                
                // Add CPT codes from application_cpt_codes array
                $aggregatedData['application_cpt_codes'] = $clinicalData['application_cpt_codes'] ?? [];
                $aggregatedData['cpt_codes'] = $clinicalData['application_cpt_codes'] ?? [];
                
                // Add post-op status fields
                $aggregatedData['global_period_status'] = $clinicalData['global_period_status'] ?? false;
                $aggregatedData['global_period_cpt'] = $clinicalData['global_period_cpt'] ?? '';
                $aggregatedData['global_period_surgery_date'] = $clinicalData['global_period_surgery_date'] ?? '';
                
                // Calculate wound size total if not present
                if (!empty($aggregatedData['wound_size_length']) && !empty($aggregatedData['wound_size_width'])) {
                    $aggregatedData['wound_size_total'] = floatval($aggregatedData['wound_size_length']) * floatval($aggregatedData['wound_size_width']);
                }
                
                // Add procedure_date (required field) - default to today if not provided
                $aggregatedData['procedure_date'] = $clinicalData['procedure_date'] ?? now()->format('Y-m-d');
                $aggregatedData['date'] = $aggregatedData['procedure_date']; // BioWound field alias
                
                // Add additional clinical fields for BioWound
                $aggregatedData['wound_location'] = $clinicalData['wound_location'] ?? '';
                $aggregatedData['location_of_wound'] = $clinicalData['wound_location'] ?? ''; // BioWound field alias
                $aggregatedData['previously_used_therapies'] = $clinicalData['previous_treatments'] ?? '';
                $aggregatedData['wound_duration'] = $clinicalData['wound_duration_weeks'] ?? '';
                $aggregatedData['co_morbidities'] = $clinicalData['comorbidities'] ?? '';
                $aggregatedData['post_debridement_size'] = $aggregatedData['wound_size_total'] ?? '';
                
                // Add primary and secondary ICD-10 codes for BioWound
                $aggregatedData['primary_icd10'] = $clinicalData['primary_diagnosis_code'] ?? '';
                $aggregatedData['secondary_icd10'] = $clinicalData['secondary_diagnosis_code'] ?? '';
            }
            
            // Insurance data (from form)
            if (empty($aggregatedData['primary_insurance_name']) && isset($metadata['insurance_data'])) {
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
                            $aggregatedData['primary_name'] = $policy['payer_name'] ?? ''; // BioWound field name
                            $aggregatedData['primary_policy'] = $policy['member_id'] ?? ''; // BioWound field name
                            $aggregatedData['primary_phone'] = $policy['phone'] ?? ''; // BioWound field name
                        } elseif ($policyType === 'secondary') {
                            $aggregatedData['secondary_insurance_name'] = $policy['payer_name'] ?? '';
                            $aggregatedData['secondary_member_id'] = $policy['member_id'] ?? '';
                            $aggregatedData['secondary_name'] = $policy['payer_name'] ?? ''; // BioWound field name
                            $aggregatedData['secondary_policy'] = $policy['member_id'] ?? ''; // BioWound field name
                            $aggregatedData['secondary_phone'] = $policy['phone'] ?? ''; // BioWound field name
                        }
                    }
                } else {
                    // Object format from extractInsuranceData
                    $aggregatedData['primary_insurance_name'] = $insuranceData['primary_name'] ?? '';
                    $aggregatedData['primary_member_id'] = $insuranceData['primary_member_id'] ?? '';
                    $aggregatedData['insurance_name'] = $insuranceData['primary_name'] ?? ''; // Alias
                    $aggregatedData['insurance_member_id'] = $insuranceData['primary_member_id'] ?? ''; // Alias
                    $aggregatedData['primary_name'] = $insuranceData['primary_name'] ?? ''; // BioWound field name
                    $aggregatedData['primary_policy'] = $insuranceData['primary_member_id'] ?? ''; // BioWound field name
                    $aggregatedData['primary_phone'] = $insuranceData['primary_payer_phone'] ?? ''; // BioWound field name
                    
                    if (!empty($insuranceData['has_secondary_insurance']) && !empty($insuranceData['secondary_insurance_name'])) {
                        $aggregatedData['secondary_insurance_name'] = $insuranceData['secondary_insurance_name'] ?? '';
                        $aggregatedData['secondary_member_id'] = $insuranceData['secondary_member_id'] ?? '';
                        $aggregatedData['secondary_name'] = $insuranceData['secondary_insurance_name'] ?? ''; // BioWound field name
                        $aggregatedData['secondary_policy'] = $insuranceData['secondary_member_id'] ?? ''; // BioWound field name
                        $aggregatedData['secondary_phone'] = $insuranceData['secondary_payer_phone'] ?? ''; // BioWound field name
                    }
                }
                
                // Prior authorization fields
                $priorAuthPermission = $insuranceData['prior_auth_permission'] ?? false;
                $aggregatedData['prior_auth_yes'] = $priorAuthPermission;
                $aggregatedData['prior_auth_no'] = !$priorAuthPermission;
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
                    
                    // Map product codes to Q-code checkboxes for BioWound
                    $aggregatedData['q4161'] = in_array('Q4161', $productCodes);
                    $aggregatedData['q4205'] = in_array('Q4205', $productCodes);
                    $aggregatedData['q4290'] = in_array('Q4290', $productCodes);
                    $aggregatedData['q4238'] = in_array('Q4238', $productCodes);
                    $aggregatedData['q4239'] = in_array('Q4239', $productCodes);
                    $aggregatedData['q4266'] = in_array('Q4266', $productCodes);
                    $aggregatedData['q4267'] = in_array('Q4267', $productCodes);
                    $aggregatedData['q4265'] = in_array('Q4265', $productCodes);
                }
            }
            
            // Additional manufacturer-specific checkbox mapping for Celularity
            if ($episode->manufacturer_name === 'Celularity') {
                $productNames = [];
                foreach ($metadata['order_details']['products'] ?? [] as $selectedProduct) {
                    if (isset($selectedProduct['product']['name'])) {
                        $productNames[] = $selectedProduct['product']['name'];
                    }
                }
                $aggregatedData['biovance'] = in_array('Biovance', $productNames);
                $aggregatedData['biovance_3l'] = in_array('Biovance 3L', $productNames);
                $aggregatedData['interfyl'] = in_array('Interfyl', $productNames);
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

            // Map manufacturer names to form IDs based on our JSON mappings
            $formMappings = [
                'ACZ Associates' => 'form1_ACZ',
                'BioWound Solutions' => 'form4_BioWound', 
                'Advanced Solution Health' => 'form5_AdvancedSolution',
                'MedLife Solutions' => 'form6_MedLife',
                'Extremity Care' => 'form7_ExtremityCare_FT', // Default to FT, could be RO based on product
                'ImbedBio' => 'form6_ImbedBio'
            ];

            // Get the form ID based on manufacturer name
            $manufacturerName = $manufacturer->name;
            $formId = $formMappings[$manufacturerName] ?? null;

            if (!$formId) {
                // Try partial name matching
                foreach ($formMappings as $name => $id) {
                    if (str_contains(strtolower($manufacturerName), strtolower($name))) {
                        $formId = $id;
                        break;
                    }
                }
            }

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

            // Explicitly add practitioner data if available
            if (isset($baseData['practitioner_fhir_id'])) {
                $practitioner = app(FhirService::class)->getPractitionerById($baseData['practitioner_fhir_id']);
                if ($practitioner) {
                    $preFillData = array_merge($baseData, [
                        'physician_name' => $practitioner['name'][0]['text'] ?? '',
                        'physician_npi' => collect($practitioner['identifier'] ?? [])->firstWhere('system', 'http://hl7.org/fhir/sid/us-npi')['value'] ?? '',
                        'physician_address' => $practitioner['address'][0]['text'] ?? '',
                    ]);
                }
            }
            
            $docusealTemplateId = $this->getManufacturerFormId($episode);

            // Use AI service for enhanced field mapping
            $aiEnhancedData = $this->callAIServiceForMapping($baseData, $docusealTemplateId, $episode);

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
}

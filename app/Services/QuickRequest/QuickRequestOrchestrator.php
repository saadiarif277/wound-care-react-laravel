<?php

namespace App\Services\QuickRequest;

use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\Order;
use App\Services\UnifiedFieldMappingService;
use App\Services\DocusealService;
use App\Services\DataExtractionService;
use App\Services\FieldMapping\DataExtractor;
use App\Services\QuickRequest\Handlers\PatientHandler;
use App\Services\QuickRequest\Handlers\ProviderHandler;
use App\Services\QuickRequest\Handlers\ClinicalHandler;
use App\Services\QuickRequest\Handlers\InsuranceHandler;
use App\Services\QuickRequest\Handlers\OrderHandler;
use App\Services\QuickRequest\Handlers\NotificationHandler;
use App\Logging\PhiSafeLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * QuickRequestOrchestratorV2 - A clean coordinator for Quick Request workflow
 * 
 * Responsibilities:
 * - Coordinate between services
 * - Manage transaction boundaries
 * - Handle workflow state transitions
 * - NO field mapping logic
 * - NO data transformation
 * - NO business rules
 */
class QuickRequestOrchestrator
{
    public function __construct(
        protected PatientHandler $patientHandler,
        protected ProviderHandler $providerHandler,
        protected ClinicalHandler $clinicalHandler,
        protected InsuranceHandler $insuranceHandler,
        protected OrderHandler $orderHandler,
        protected NotificationHandler $notificationHandler,
        protected UnifiedFieldMappingService $fieldMappingService,
        protected DocusealService $docusealService,
        protected DataExtractionService $dataExtractionService,
        protected DataExtractor $dataExtractor,
        protected PhiSafeLogger $logger
    ) {}

    /**
     * Start a new Quick Request episode
     */
    public function startEpisode(array $requestData): PatientManufacturerIVREpisode
    {
        $this->logger->info('Starting new Quick Request episode');

        try {
            DB::beginTransaction();

            // Step 1: Create FHIR resources through handlers
            $fhirIds = $this->createFhirResources($requestData);

            // Step 2: Create episode record
            $episode = $this->createEpisodeRecord($requestData, $fhirIds);

            // Step 3: Create initial order if products provided
            if (!empty($requestData['order_details']['products'])) {
                $this->orderHandler->createInitialOrder($episode, $requestData['order_details']);
            }

            // Step 4: Send notifications
            $this->notificationHandler->notifyEpisodeCreated($episode);

            DB::commit();

            $this->logger->info('Episode created successfully', [
                'episode_id' => $episode->id,
                'status' => $episode->status
            ]);

            return $episode;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logger->error('Failed to create episode', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create a draft episode for IVR generation
     */
    public function createDraftEpisode(array $requestData): PatientManufacturerIVREpisode
    {
        $this->logger->info('Creating draft episode for IVR generation');

        $episode = PatientManufacturerIVREpisode::create([
            'patient_id' => $requestData['patient']['id'] ?? uniqid('patient-draft-'),
            'manufacturer_id' => $requestData['manufacturer_id'],
            'status' => PatientManufacturerIVREpisode::STATUS_DRAFT,
            'ivr_status' => PatientManufacturerIVREpisode::IVR_STATUS_PENDING,
            'created_by' => Auth::id(),
            'metadata' => [
                'is_draft' => true,
                'request_data' => $requestData,
                'created_at' => now()->toISOString()
            ]
        ]);

        return $episode;
    }

    /**
     * Prepare data for DocuSeal integration
     * This is where we delegate to the field mapping service
     */
    public function prepareDocusealData(PatientManufacturerIVREpisode $episode, array $additionalData = []): array
    {
        try {
            // Get manufacturer info
            $manufacturer = $episode->manufacturer;
            if (!$manufacturer) {
                throw new \InvalidArgumentException("Episode has no manufacturer");
            }

            // Extract raw data from the episode
            $rawData = $this->extractEpisodeData($episode, $additionalData);

            // Delegate ALL field mapping to UnifiedFieldMappingService
            $mappingResult = $this->fieldMappingService->mapEpisodeToDocuSeal(
                $episode->id,
                $manufacturer->name,
                $manufacturer->docuseal_template_id ?? '',
                $rawData,
                Auth::user()?->email,
                true // Use dynamic mapping if available
            );

            // Return the mapped data
            return $mappingResult['data'] ?? [];

        } catch (\Exception $e) {
            $this->logger->error('Failed to prepare DocuSeal data', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Extract raw data from episode without any mapping
     */
    protected function extractEpisodeData(PatientManufacturerIVREpisode $episode, array $additionalData): array
    {
        $metadata = $episode->metadata ?? [];
        
        // If this is a draft, use the stored request data
        if ($metadata['is_draft'] ?? false) {
            $requestData = $metadata['request_data'] ?? [];
            
            // Merge all data sources for draft
            $rawData = array_merge(
                $requestData,
                $additionalData,
                [
                    'episode_id' => $episode->id,
                    'manufacturer_id' => $episode->manufacturer_id,
                    'patient_id' => $episode->patient_id,
                    'facility_id' => $episode->facility_id,
                    'provider_id' => $metadata['provider_id'] ?? null,
                ]
            );
            
            // Use DataExtractionService for non-episode data
            if (!empty($rawData['provider_id']) || !empty($rawData['facility_id'])) {
                $context = [
                    'provider_id' => $rawData['provider_id'] ?? null,
                    'facility_id' => $rawData['facility_id'] ?? null,
                    'episode_id' => $episode->id,
                ];
                
                $extractedData = $this->dataExtractionService->extractData($context);
                $rawData = array_merge($rawData, $extractedData);
            }
            
            return $rawData;
        }
        
        // For non-draft episodes with FHIR data, use DataExtractor
        // which properly extracts FHIR patient names and other FHIR resources
        try {
            $extractedData = $this->dataExtractor->extractEpisodeData($episode->id);
            
            // Merge with additional data
            return array_merge($extractedData, $additionalData);
            
        } catch (\Exception $e) {
            $this->logger->warning('Failed to extract episode data with DataExtractor, falling back', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to basic extraction if DataExtractor fails
            return array_merge(
                [
                    'episode_id' => $episode->id,
                    'manufacturer_id' => $episode->manufacturer_id,
                    'patient_id' => $episode->patient_id,
                    'facility_id' => $episode->facility_id,
                    'provider_id' => $metadata['provider_id'] ?? null,
                ],
                $additionalData
            );
        }
    }

    /**
     * Create FHIR resources through appropriate handlers
     */
    protected function createFhirResources(array $requestData): array
    {
        $fhirIds = [];

        // Patient
        if (!empty($requestData['patient'])) {
            $fhirIds['patient_id'] = $this->patientHandler->createOrUpdatePatient($requestData['patient']);
        }

        // Provider
        if (!empty($requestData['provider'])) {
            $fhirIds['practitioner_id'] = $this->providerHandler->createOrUpdateProvider($requestData['provider']);
        }

        // Organization
        if (!empty($requestData['organization']) || !empty($requestData['facility'])) {
            $orgData = $requestData['organization'] ?? $requestData['facility'];
            $fhirIds['organization_id'] = $this->providerHandler->createOrUpdateOrganization($orgData);
        }

        // Clinical resources
        if (!empty($requestData['clinical']) && !empty($fhirIds['patient_id'])) {
            $clinicalResources = $this->clinicalHandler->createClinicalResources([
                'patient_id' => $fhirIds['patient_id'],
                'provider_id' => $fhirIds['practitioner_id'] ?? null,
                'organization_id' => $fhirIds['organization_id'] ?? null,
                'clinical' => $requestData['clinical']
            ]);
            
            $fhirIds = array_merge($fhirIds, $clinicalResources);
        }

        // Insurance
        if (!empty($requestData['insurance']) && !empty($fhirIds['patient_id'])) {
            $coverageIds = $this->insuranceHandler->createMultipleCoverages(
                $requestData['insurance'],
                $fhirIds['patient_id']
            );
            
            $fhirIds['coverage_ids'] = $coverageIds;
            $fhirIds['primary_coverage_id'] = $coverageIds['primary'] ?? null;
        }

        return $fhirIds;
    }

    /**
     * Create episode record with minimal data
     */
    protected function createEpisodeRecord(array $requestData, array $fhirIds): PatientManufacturerIVREpisode
    {
        return PatientManufacturerIVREpisode::create([
            'patient_id' => $requestData['patient']['id'] ?? uniqid('patient-'),
            'patient_fhir_id' => $fhirIds['patient_id'] ?? null,
            'patient_display_id' => $requestData['patient']['display_id'] ?? null,
            'manufacturer_id' => $requestData['manufacturer_id'],
            'facility_id' => $requestData['facility']['id'] ?? null,
            'status' => PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW,
            'ivr_status' => PatientManufacturerIVREpisode::IVR_STATUS_NA,
            'created_by' => Auth::id(),
            'metadata' => [
                'fhir_ids' => $fhirIds,
                'provider_id' => $requestData['provider']['id'] ?? null,
                'created_at' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Finalize a draft episode
     */
    public function finalizeDraftEpisode(PatientManufacturerIVREpisode $episode, array $finalData): PatientManufacturerIVREpisode
    {
        if ($episode->status !== PatientManufacturerIVREpisode::STATUS_DRAFT) {
            throw new \InvalidArgumentException("Episode is not in draft status");
        }

        try {
            DB::beginTransaction();

            // Create FHIR resources
            $fhirIds = $this->createFhirResources($finalData);

            // Update episode with FHIR IDs and final status
            $episode->update([
                'patient_fhir_id' => $fhirIds['patient_id'] ?? null,
                'status' => PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW,
                'metadata' => array_merge($episode->metadata ?? [], [
                    'fhir_ids' => $fhirIds,
                    'finalized_at' => now()->toISOString()
                ])
            ]);

            // Create order if needed
            if (!empty($finalData['order_details']['products'])) {
                $this->orderHandler->createInitialOrder($episode, $finalData['order_details']);
            }

            DB::commit();

            return $episode->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Complete an episode with manufacturer response
     */
    public function completeEpisode(PatientManufacturerIVREpisode $episode, array $manufacturerResponse): void
    {
        try {
            DB::beginTransaction();

            $episode->update([
                'status' => PatientManufacturerIVREpisode::STATUS_COMPLETED,
                'ivr_status' => PatientManufacturerIVREpisode::IVR_STATUS_VERIFIED,
                'completed_at' => now(),
                'metadata' => array_merge($episode->metadata ?? [], [
                    'manufacturer_response' => $manufacturerResponse,
                    'completed_by' => Auth::id()
                ])
            ]);

            // Update associated orders
            $episode->orders()->update([
                'status' => Order::STATUS_CONFIRMED_BY_MANUFACTURER,
                'confirmed_at' => now(),
                'confirmed_by' => Auth::id()
            ]);

            // Send notifications
            $this->notificationHandler->notifyEpisodeCompletion($episode);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
} 
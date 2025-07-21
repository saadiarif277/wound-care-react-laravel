<?php

namespace App\Http\Controllers\QuickRequest;

use App\Http\Controllers\Controller;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\Manufacturer;
use App\Services\DocusealService;
use App\Services\QuickRequest\QuickRequestOrchestrator;
use App\Services\DataExtractionService;
use App\Services\FieldMapping\DataExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * DocusealController - Handles DocuSeal integration for Quick Request workflow
 * 
 * This controller is responsible for all DocuSeal operations within the 
 * Quick Request flow, using the new clean architecture with:
 * - Manufacturer configs as single source of truth
 * - EntityDataService for role-based data extraction
 * - Targeted field extraction (only configured fields)
 */
class DocusealController extends Controller
{
    public function __construct(
        protected DocusealService $docusealService,
        protected QuickRequestOrchestrator $orchestrator,
        protected DataExtractionService $dataExtractionService,
        protected DataExtractor $dataExtractor
    ) {}

    /**
     * Generate submission slug for DocuSeal form embedding
     * This is the main entry point for creating DocuSeal submissions in Quick Request flow
     */
    public function generateSubmissionSlug(Request $request): JsonResponse
    {
        try {
            Log::info('DocuSeal generateSubmissionSlug called', [
                'request_keys' => array_keys($request->all()),
                'has_episode_id' => $request->has('episode_id'),
                'episode_id_value' => $request->input('episode_id', 'not_present'),
                'full_request' => $request->all()
            ]);
            
            $validationRules = [
                'user_email' => 'required|email',
                'integration_email' => 'nullable|email',
                'prefill_data' => 'required|array',
                'manufacturerId' => 'required|integer',
                'templateId' => 'nullable|string',
                'productCode' => 'nullable|string',
                'documentType' => 'nullable|string|in:IVR,OrderForm',
                'episode_id' => 'nullable|integer',
            ];
            
            // Validate the request
            $validated = $request->validate($validationRules);
            
            // Create data array with all fields, using null for missing nullable fields
            $data = [
                'user_email' => $validated['user_email'],
                'integration_email' => $validated['integration_email'] ?? null,
                'prefill_data' => $validated['prefill_data'],
                'manufacturerId' => $validated['manufacturerId'],
                'templateId' => $validated['templateId'] ?? null,
                'productCode' => $validated['productCode'] ?? null,
                'documentType' => $validated['documentType'] ?? null,
                'episode_id' => $validated['episode_id'] ?? null,
            ];

            // Find the manufacturer
            $manufacturer = Manufacturer::find($data['manufacturerId']);
            if (!$manufacturer) {
                throw new \Exception('Manufacturer not found');
            }

            // Determine template ID
            $templateId = $this->determineTemplateId($data, $manufacturer);
            if (!$templateId) {
                throw new \Exception('No DocuSeal template found for this manufacturer');
            }

            // Extract data based on whether we have an episode or not
            try {
                $extractedData = $this->extractDataForDocuseal($data, $manufacturer);
            } catch (\Exception $e) {
                Log::error('Error in extractDataForDocuseal', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
            
            // DEBUG: Log extracted data
            Log::warning('DEBUG - DocuSeal extraction results', [
                'manufacturer' => $manufacturer->name,
                'extracted_field_count' => count($extractedData),
                'extracted_keys' => array_keys($extractedData),
                'patient_fields' => array_filter(array_keys($extractedData), fn($k) => str_contains($k, 'patient')),
                'provider_fields' => array_filter(array_keys($extractedData), fn($k) => str_contains($k, 'provider') || str_contains($k, 'physician')),
                'facility_fields' => array_filter(array_keys($extractedData), fn($k) => str_contains($k, 'facility') || str_contains($k, 'practice'))
            ]);

            // Create DocuSeal submission
            $result = $this->docusealService->createSubmissionForQuickRequest(
                $templateId,
                $data['integration_email'] ?? 'limitless@mscwoundcare.com',
                $data['user_email'],
                'Quick Request User',
                $extractedData,
                isset($data['episode_id']) ? $data['episode_id'] : null
            );

            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'Failed to create DocuSeal submission');
            }

            $submission = $result['data'];

            Log::info('DocuSeal submission created successfully', [
                'template_id' => $templateId,
                'submission_id' => $submission['submission_id'] ?? null,
                'manufacturer' => $manufacturer->name,
                'fields_mapped' => count($extractedData),
                'episode_id' => $data['episode_id'] ?? null
            ]);

            return response()->json([
                'success' => true,
                'slug' => $submission['slug'] ?? null,
                'submission_id' => $submission['submission_id'] ?? null,
                'template_id' => $templateId,
                'integration_type' => (isset($data['episode_id']) && $data['episode_id']) ? 'episode_enhanced' : 'standard',
                'fields_mapped' => count($extractedData),
                'template_name' => $manufacturer->name . ' ' . ($data['documentType'] ?? 'IVR') . ' Form',
                'manufacturer' => $manufacturer->name,
                'ai_mapping_used' => $result['ai_mapping_used'] ?? false,
                'ai_confidence' => $result['ai_confidence'] ?? 0.0,
                'mapping_method' => $result['mapping_method'] ?? 'config_based'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate DocuSeal submission slug', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'manufacturer_id' => $data['manufacturerId'] ?? null,
                'user_id' => Auth::id(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'error' => 'Failed to create form submission',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create episode for DocuSeal workflow
     * This creates a draft episode that can be finalized after DocuSeal completion
     */
    public function createEpisode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'formData' => 'required|array',
            'manufacturerId' => 'required|integer',
            'selected_products' => 'nullable|array',
        ]);

        try {
            // Prepare episode data
            $episodeData = [
                'patient' => $this->extractPatientData($validated['formData']),
                'provider' => ['id' => $validated['formData']['provider_id'] ?? null],
                'facility' => ['id' => $validated['formData']['facility_id'] ?? null],
                'clinical' => $this->extractClinicalData($validated['formData']),
                'insurance' => $this->extractInsuranceData($validated['formData']),
                'order_details' => [
                    'products' => $validated['selected_products'] ?? [],
                ],
                'manufacturer_id' => $validated['manufacturerId'],
            ];

            // Create draft episode
            $episode = $this->orchestrator->createDraftEpisode($episodeData);

            Log::info('Draft episode created for DocuSeal', [
                'episode_id' => $episode->id,
                'manufacturer_id' => $validated['manufacturerId'],
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'episode_id' => $episode->id,
                'manufacturer_id' => $validated['manufacturerId']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create episode for DocuSeal', [
                'error' => $e->getMessage(),
                'manufacturer_id' => $validated['manufacturerId'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create episode: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Finalize episode after DocuSeal completion
     */
    public function finalizeEpisode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'episode_id' => 'required|integer',
            'submission_id' => 'required|string',
            'completion_data' => 'nullable|array',
        ]);

        try {
            $episode = PatientManufacturerIVREpisode::findOrFail($validated['episode_id']);

            // Update episode with DocuSeal submission info
            $metadata = $episode->metadata ?? [];
            $metadata['docuseal_submission_id'] = $validated['submission_id'];
            $metadata['docuseal_completed_at'] = now()->toISOString();
            
            if (!empty($validated['completion_data'])) {
                $metadata['docuseal_completion_data'] = $validated['completion_data'];
            }

            $episode->update([
                'metadata' => $metadata,
                'ivr_status' => PatientManufacturerIVREpisode::IVR_STATUS_VERIFIED
            ]);

            // If episode was a draft, finalize it
            if ($episode->status === PatientManufacturerIVREpisode::STATUS_DRAFT) {
                $finalData = array_merge($episode->metadata, $validated['completion_data'] ?? []);
                $episode = $this->orchestrator->finalizeDraftEpisode($episode, $finalData);
            }

            Log::info('Episode finalized after DocuSeal completion', [
                'episode_id' => $episode->id,
                'submission_id' => $validated['submission_id'],
            ]);

            return response()->json([
                'success' => true,
                'episode_id' => $episode->id,
                'status' => $episode->status,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to finalize episode', [
                'episode_id' => $validated['episode_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to finalize episode: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Private helper methods

    /**
     * Determine the template ID to use
     */
    private function determineTemplateId(array $data, Manufacturer $manufacturer): ?string
    {
        // Use provided template ID if available
        if (!empty($data['templateId'])) {
            return $data['templateId'];
        }

        // Otherwise, get from manufacturer config using UnifiedFieldMappingService
        $documentType = $data['documentType'] ?? 'IVR';
        
        try {
            $mappingService = app(\App\Services\UnifiedFieldMappingService::class);
            $manufacturerConfig = $mappingService->getManufacturerConfig($manufacturer->name, $documentType);
            
            if ($manufacturerConfig && isset($manufacturerConfig['docuseal_template_id'])) {
                return (string) $manufacturerConfig['docuseal_template_id'];
            }
            
            Log::warning('No template ID found in manufacturer config', [
                'manufacturer' => $manufacturer->name,
                'document_type' => $documentType
            ]);
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('Failed to get template ID from config', [
                'manufacturer' => $manufacturer->name, 
                'document_type' => $documentType,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Extract data for DocuSeal using our clean architecture
     */
    private function extractDataForDocuseal(array $data, Manufacturer $manufacturer): array
    {
        // Filter out manufacturer_fields from prefill_data - these are test fields
        if (isset($data['prefill_data']['manufacturer_fields'])) {
            Log::warning('Removing test manufacturer_fields from DocuSeal data', [
                'manufacturer_fields_keys' => array_keys($data['prefill_data']['manufacturer_fields']),
                'manufacturer_fields_count' => count($data['prefill_data']['manufacturer_fields'])
            ]);
            unset($data['prefill_data']['manufacturer_fields']);
        }
        
        // Log incoming data for debugging
        Log::info('DocuSeal data extraction starting', [
            'has_episode_id' => isset($data['episode_id']) && !empty($data['episode_id']),
            'has_facility_id' => isset($data['prefill_data']['facility_id']),
            'has_provider_id' => isset($data['prefill_data']['provider_id']),
            'manufacturer' => $manufacturer->name,
            'prefill_keys' => array_keys($data['prefill_data'] ?? []),
            'facility_id_value' => $data['prefill_data']['facility_id'] ?? 'not set',
            'provider_id_value' => $data['prefill_data']['provider_id'] ?? 'not set',
            'prefill_data_sample' => array_slice($data['prefill_data'] ?? [], 0, 10, true)
        ]);

        if (isset($data['episode_id']) && !empty($data['episode_id'])) {
            // We have an episode - use targeted extraction
            $episode = PatientManufacturerIVREpisode::find($data['episode_id']);
            if (!$episode) {
                throw new \Exception('Episode not found');
            }

            // Use DataExtractor for episode-based extraction
            $extractedData = $this->dataExtractor->extractEpisodeData($episode->id);
            
            Log::info('Used targeted extraction for episode', [
                'episode_id' => $episode->id,
                'extracted_fields' => count($extractedData),
            ]);
            
            return $extractedData;
        } else {
            // No episode - extract data from IDs
            // Pass manufacturer_id in the prefill_data so the orchestrator can filter properly
            $dataWithManufacturer = $data['prefill_data'];
            $dataWithManufacturer['manufacturer_id'] = $manufacturer->id;
            
            // Use DataExtractionService for ID-based extraction
            $context = [
                'provider_id' => $dataWithManufacturer['provider_id'] ?? null,
                'facility_id' => $dataWithManufacturer['facility_id'] ?? null,
                'manufacturer_id' => $manufacturer->id,
            ];
            
            // Add all form data to context for direct field extraction
            $context = array_merge($context, $dataWithManufacturer);
            
            Log::info('Extracting data for DocuSeal with context', [
                'provider_id' => $context['provider_id'],
                'facility_id' => $context['facility_id'],
                'manufacturer_id' => $context['manufacturer_id'],
                'has_episode' => false,
                'form_data_keys' => array_keys($dataWithManufacturer)
            ]);
            
            $extractedData = $this->dataExtractionService->extractData($context);
            
            // Log extraction results
            Log::info('Data extraction completed', [
                'extracted_fields_count' => count($extractedData),
                'has_facility_data' => isset($extractedData['facility_name']),
                'facility_name' => $extractedData['facility_name'] ?? 'not extracted',
                'has_provider_data' => isset($extractedData['provider_name']),
                'provider_name' => $extractedData['provider_name'] ?? 'not extracted'
            ]);
            
            Log::info('Used ID-based extraction (no episode)', [
                'manufacturer_id' => $manufacturer->id,
                'manufacturer_name' => $manufacturer->name,
                'extracted_fields' => count($extractedData),
                'sample_fields' => array_slice(array_keys($extractedData), 0, 10)
            ]);
            
            return $extractedData;
        }
    }

    /**
     * Extract patient data from form
     */
    private function extractPatientData(array $formData): array
    {
        return [
            'first_name' => $formData['patient_first_name'] ?? '',
            'last_name' => $formData['patient_last_name'] ?? '',
            'dob' => $formData['patient_dob'] ?? '',
            'gender' => $formData['patient_gender'] ?? 'unknown',
            'phone' => $formData['patient_phone'] ?? '',
            'email' => $formData['patient_email'] ?? '',
            'address_line1' => $formData['patient_address_line1'] ?? '',
            'city' => $formData['patient_city'] ?? '',
            'state' => $formData['patient_state'] ?? '',
            'zip' => $formData['patient_zip'] ?? '',
        ];
    }

    /**
     * Extract clinical data from form
     */
    private function extractClinicalData(array $formData): array
    {
        return [
            'wound_type' => $formData['wound_type'] ?? '',
            'wound_location' => $formData['wound_location'] ?? '',
            'wound_size_length' => $formData['wound_size_length'] ?? null,
            'wound_size_width' => $formData['wound_size_width'] ?? null,
            'wound_size_depth' => $formData['wound_size_depth'] ?? null,
            'primary_diagnosis_code' => $formData['primary_diagnosis_code'] ?? '',
            'wound_duration_weeks' => $formData['wound_duration_weeks'] ?? null,
        ];
    }

    /**
     * Extract insurance data from form
     */
    private function extractInsuranceData(array $formData): array
    {
        $insuranceData = [];

        if (!empty($formData['primary_insurance_name'])) {
            $insuranceData[] = [
                'policy_type' => 'primary',
                'payer_name' => $formData['primary_insurance_name'],
                'member_id' => $formData['primary_member_id'] ?? null,
            ];
        }

        if (!empty($formData['secondary_insurance_name'])) {
            $insuranceData[] = [
                'policy_type' => 'secondary',
                'payer_name' => $formData['secondary_insurance_name'],
                'member_id' => $formData['secondary_member_id'] ?? null,
            ];
        }

        return $insuranceData;
    }
}

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

    /**
     * Create DocuSeal submission for Quick Request workflow
     * POST /api/v1/quick-request/docuseal/test-mapping
     *
     * This endpoint creates actual DocuSeal submissions for the new
     * DocusealEmbed component, following clean architecture principles
     */
    public function testFieldMapping(Request $request): JsonResponse
    {
        try {
            // Validate incoming request
            $validated = $request->validate([
                'manufacturerId' => 'required|integer|exists:manufacturers,id',
                'templateId' => 'nullable|string',
                'productCode' => 'required|string',
                'documentType' => 'required|string|in:IVR,OrderForm',
                'formData' => 'required|array',
                'episode_id' => 'nullable|integer',
                'use_enhanced_mapping' => 'nullable|boolean' // New parameter for enhanced mapping
            ]);

            // Extract data
            $manufacturer = Manufacturer::find($validated['manufacturerId']);
            $episodeId = $validated['episode_id'] ?? null;
            $useEnhancedMapping = $validated['use_enhanced_mapping'] ?? false;

            // Determine template ID using the improved lookup method
            $templateId = $validated['templateId'] ?? $this->determineTemplateId([
                'templateId' => $validated['templateId'] ?? null,
                'documentType' => $validated['documentType']
            ], $manufacturer);

            if (!$templateId) {
                return response()->json([
                    'success' => false,
                    'error' => 'No DocuSeal template configured for this manufacturer'
                ], 422);
            }

            // Prepare data for DocuSeal submission
            $submissionData = [
                'user_email' => $validated['formData']['patient_email'] ?? 'noreply@mscwoundcare.com',
                'integration_email' => 'limitless@mscwoundcare.com',
                'prefill_data' => $validated['formData'],
                'manufacturerId' => $validated['manufacturerId'],
                'templateId' => $templateId,
                'productCode' => $validated['productCode'],
                'documentType' => $validated['documentType'],
                'episode_id' => $episodeId
            ];

            // Extract and transform data using enhanced or standard services
            if ($useEnhancedMapping) {
                $extractedData = $this->extractDataWithEnhancedMapping($submissionData, $manufacturer);
                Log::info('Using enhanced field mapping for test', [
                    'manufacturer' => $manufacturer->name,
                    'template_id' => $templateId,
                    'enhanced_mapping' => true
                ]);
            } else {
                $extractedData = $this->extractDataForDocuseal($submissionData, $manufacturer);
                Log::info('Using standard field mapping for test', [
                    'manufacturer' => $manufacturer->name,
                    'template_id' => $templateId,
                    'enhanced_mapping' => false
                ]);
            }

            // Log extracted data for debugging
            Log::info('DocuSeal field extraction complete', [
                'manufacturer' => $manufacturer->name,
                'template_id' => $templateId,
                'extracted_field_count' => count($extractedData),
                'extracted_fields' => array_keys($extractedData),
            ]);

            // Create DocuSeal submission using the service
            $result = $this->docusealService->createSubmissionForQuickRequest(
                $templateId,
                $submissionData['integration_email'],
                $submissionData['user_email'],
                $validated['formData']['patient_first_name'] . ' ' . $validated['formData']['patient_last_name'],
                $extractedData,
                $episodeId
            );

            // Calculate field coverage analytics
            if (isset($result['data']['field_analytics'])) {
                $analytics = $result['data']['field_analytics'];
                Log::info('DocuSeal field coverage analytics', [
                    'manufacturer' => $manufacturer->name,
                    'template_id' => $templateId,
                    'total_fields' => $analytics['total_fields'] ?? 0,
                    'mapped_fields' => $analytics['mapped_fields'] ?? 0,
                    'coverage_percentage' => $analytics['coverage_percentage'] ?? 0,
                    'missing_fields' => $analytics['missing_fields'] ?? []
                ]);
            }

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to create DocuSeal submission',
                    'message' => $result['message'] ?? null
                ], 422);
            }

            $submission = $result['data'];

            // Log successful submission
            Log::info('DocuSeal submission created via Quick Request', [
                'submission_id' => $submission['submission_id'] ?? null,
                'template_id' => $templateId,
                'manufacturer' => $manufacturer->name,
                'document_type' => $validated['documentType'],
                'episode_id' => $episodeId
            ]);

            // Return response matching what DocusealEmbed expects
            return response()->json([
                'success' => true,
                'slug' => $submission['slug'] ?? null,
                'submission_id' => $submission['submission_id'] ?? null,
                'template_id' => $templateId,
                'manufacturer' => $manufacturer->name,
                'document_type' => $validated['documentType'],
                'ai_mapping_used' => $result['ai_mapping_used'] ?? false,
                'ai_confidence' => $result['ai_confidence'] ?? 0.0,
                'mapping_method' => $result['mapping_method'] ?? 'config_based',
                'enhanced_mapping_used' => $useEnhancedMapping,
                'field_coverage' => [
                    'total_fields' => count($extractedData),
                    'enhanced_fields' => $useEnhancedMapping ? count(array_diff(array_keys($extractedData), ['basic_fields'])) : 0,
                    'mapping_type' => $useEnhancedMapping ? 'enhanced' : 'standard'
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create DocuSeal submission', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['formData'])
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create submission',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug template fields for troubleshooting
     */
    public function debugTemplateFields(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'templateId' => 'required|string'
            ]);

            $result = $this->docusealService->debugTemplateFields($validated['templateId']);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
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
                Log::info('Found template ID in manufacturer config', [
                    'manufacturer' => $manufacturer->name,
                    'template_id' => $manufacturerConfig['docuseal_template_id'],
                    'document_type' => $documentType
                ]);
                return (string) $manufacturerConfig['docuseal_template_id'];
            }

            Log::warning('No template ID found in manufacturer config, checking database', [
                'manufacturer' => $manufacturer->name,
                'document_type' => $documentType
            ]);

            // Fallback to database lookup
            $template = \App\Models\Docuseal\DocusealTemplate::where('manufacturer_id', $manufacturer->id)
                ->where('document_type', $documentType)
                ->where('is_active', true)
                ->where('is_default', true)
                ->first();

            if (!$template) {
                // Try without is_default constraint
                $template = \App\Models\Docuseal\DocusealTemplate::where('manufacturer_id', $manufacturer->id)
                    ->where('document_type', $documentType)
                    ->where('is_active', true)
                    ->first();
            }

            if ($template) {
                Log::info('Found template ID in database', [
                    'manufacturer' => $manufacturer->name,
                    'template_id' => $template->docuseal_template_id,
                    'document_type' => $documentType,
                    'is_default' => $template->is_default
                ]);
                return (string) $template->docuseal_template_id;
            }

            Log::error('No template ID found in config or database', [
                'manufacturer' => $manufacturer->name,
                'document_type' => $documentType,
                'manufacturer_id' => $manufacturer->id
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to get template ID from config or database', [
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

            // Ensure patient data is properly extracted from both flat and nested structures
            if (isset($dataWithManufacturer['patient']) && is_array($dataWithManufacturer['patient'])) {
                // Extract nested patient data and flatten it
                $patientData = $dataWithManufacturer['patient'];
                foreach ($patientData as $key => $value) {
                    $context['patient_' . $key] = $value;
                }
            }

            // Ensure patient_name is computed if we have first and last names
            if (!isset($context['patient_name']) &&
                (isset($context['patient_first_name']) || isset($context['patient_last_name']))) {
                $context['patient_name'] = trim(
                    ($context['patient_first_name'] ?? '') . ' ' .
                    ($context['patient_last_name'] ?? '')
                );
            }

            // Handle place_of_service field specially
            if (isset($context['place_of_service'])) {
                // If it's an array, find the selected value
                if (is_array($context['place_of_service'])) {
                    foreach ($context['place_of_service'] as $key => $value) {
                        if ($value === true || $value === 'true' || $value === 1 || $value === '1') {
                            // Extract the number from pos_XX format
                            if (preg_match('/pos[_\s]*(\d+)/i', $key, $matches)) {
                                $context['place_of_service'] = $matches[1];
                            } else {
                                $context['place_of_service'] = $key;
                            }
                            break;
                        }
                    }
                }
            }

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
     * Enhanced data extraction with comprehensive field mapping
     * This method uses the enhanced ACZ configuration and similar patterns for other manufacturers
     */
    private function extractDataWithEnhancedMapping(array $data, Manufacturer $manufacturer): array
    {
        Log::info('Starting enhanced field mapping extraction', [
            'manufacturer' => $manufacturer->name,
            'has_episode_id' => isset($data['episode_id']) && !empty($data['episode_id']),
            'document_type' => $data['documentType'] ?? 'IVR'
        ]);

        // Get base extracted data first
        $baseData = $this->extractDataForDocuseal($data, $manufacturer);

        // Apply enhanced field mapping based on manufacturer
        $enhancedData = $this->applyEnhancedFieldMapping($baseData, $manufacturer, $data);

        Log::info('Enhanced field mapping completed', [
            'manufacturer' => $manufacturer->name,
            'base_fields_count' => count($baseData),
            'enhanced_fields_count' => count($enhancedData),
            'additional_fields' => array_diff(array_keys($enhancedData), array_keys($baseData))
        ]);

        return $enhancedData;
    }

    /**
     * Apply enhanced field mapping rules for specific manufacturers
     */
    private function applyEnhancedFieldMapping(array $baseData, Manufacturer $manufacturer, array $context): array
    {
        $enhancedData = $baseData;

        // ACZ & Associates enhanced mapping
        if (str_contains(strtolower($manufacturer->name), 'acz')) {
            $enhancedData = $this->applyACZEnhancedMapping($enhancedData, $context);
        }

        // Add more manufacturer-specific enhanced mappings here
        // Example: if (str_contains(strtolower($manufacturer->name), 'biowound')) {
        //     $enhancedData = $this->applyBioWoundEnhancedMapping($enhancedData, $context);
        // }

        return $enhancedData;
    }

    /**
     * Apply ACZ & Associates specific enhanced field mapping
     */
    private function applyACZEnhancedMapping(array $data, array $context): array
    {
        $enhancedData = $data;
        $formData = $context['prefill_data'] ?? [];

        Log::info('Applying ACZ enhanced field mapping', [
            'input_fields_count' => count($data),
            'form_data_keys' => array_keys($formData)
        ]);

        // Product Q Code - Enhanced mapping
        if (!isset($enhancedData['product_q_code']) || empty($enhancedData['product_q_code'])) {
            if (isset($formData['selected_products']) && is_array($formData['selected_products']) && !empty($formData['selected_products'])) {
                $firstProduct = $formData['selected_products'][0];
                if (isset($firstProduct['product']['q_code'])) {
                    $enhancedData['product_q_code'] = $firstProduct['product']['q_code'];
                } elseif (isset($firstProduct['product']['code'])) {
                    $enhancedData['product_q_code'] = $firstProduct['product']['code'];
                }
            }
            // Fallback to default
            if (!isset($enhancedData['product_q_code']) || empty($enhancedData['product_q_code'])) {
                $enhancedData['product_q_code'] = 'Q4316';
            }
        }

        // Sales Representative - Enhanced mapping
        if (!isset($enhancedData['sales_rep']) || empty($enhancedData['sales_rep'])) {
            $enhancedData['sales_rep'] = $this->getSalesRepFromMultipleSources($formData, $data);
        }

        // ISO if applicable
        if (!isset($enhancedData['iso_if_applicable']) || empty($enhancedData['iso_if_applicable'])) {
            $enhancedData['iso_if_applicable'] = $formData['iso_number'] ??
                                               $formData['iso_if_applicable'] ??
                                               $formData['iso_id'] ?? '';
        }

        // Additional Emails for Notification
        if (!isset($enhancedData['additional_emails']) || empty($enhancedData['additional_emails'])) {
            $enhancedData['additional_emails'] = $formData['additional_emails'] ??
                                               $formData['additional_notification_emails'] ??
                                               $formData['notification_emails'] ?? '';
        }

        // Physician Information - Enhanced mapping
        $enhancedData = $this->enhancePhysicianData($enhancedData, $formData, $data);

        // Facility Information - Enhanced mapping
        $enhancedData = $this->enhanceFacilityData($enhancedData, $formData, $data);

        // Patient Information - Enhanced mapping
        $enhancedData = $this->enhancePatientData($enhancedData, $formData, $data);

        // Insurance Information - Enhanced mapping
        $enhancedData = $this->enhanceInsuranceData($enhancedData, $formData, $data);

        // Clinical Information - Enhanced mapping
        $enhancedData = $this->enhanceClinicalData($enhancedData, $formData, $data);

        // Network Status - Enhanced mapping with smart defaults
        $enhancedData = $this->enhanceNetworkStatusData($enhancedData, $formData, $data);

        // Map provider status from Step 1 to network status fields
        $enhancedData = $this->mapProviderStatusToNetworkStatus($enhancedData, $formData, $data);

        // Authorization Questions - Enhanced mapping with smart defaults
        $enhancedData = $this->enhanceAuthorizationData($enhancedData, $formData, $data);

        // Place of Service - Enhanced mapping
        $enhancedData = $this->enhancePlaceOfServiceData($enhancedData, $formData, $data);

        // Conditional Surgery Fields - Enhanced mapping
        $enhancedData = $this->enhanceConditionalSurgeryData($enhancedData, $formData, $data);

        // Convert field names to match DocuSeal template exactly
        $enhancedData = $this->convertToDocuSealFieldNames($enhancedData);

        Log::info('ACZ enhanced field mapping applied', [
            'enhanced_fields_count' => count($enhancedData),
            'key_enhanced_fields' => array_intersect(array_keys($enhancedData), [
                'product_q_code', 'sales_rep', 'physician_name', 'facility_name',
                'patient_name', 'primary_insurance_name', 'place_of_service'
            ])
        ]);

        return $enhancedData;
    }

    /**
     * Get sales representative from multiple sources
     */
    private function getSalesRepFromMultipleSources(array $formData, array $data): string
    {
        $sources = [
            $formData['sales_rep_name'] ?? null,
            $formData['sales_rep'] ?? null,
            $formData['organization_sales_rep_name'] ?? null,
            $data['provider_name'] ?? null,
            $data['physician_name'] ?? null,
            Auth::user()?->name ?? null,
            'MSC Wound Care'
        ];

        foreach ($sources as $source) {
            if (!empty($source)) {
                return $source;
            }
        }

        return 'MSC Wound Care';
    }

    /**
     * Enhance physician data with comprehensive mapping
     */
    private function enhancePhysicianData(array $data, array $formData, array $baseData): array
    {
        $enhanced = $data;

        // Physician Name
        if (!isset($enhanced['physician_name']) || empty($enhanced['physician_name'])) {
            $enhanced['physician_name'] = $formData['physician_name'] ??
                                        $formData['provider_name'] ??
                                        $baseData['provider_name'] ??
                                        'Dr. Provider';
        }

        // Physician NPI
        if (!isset($enhanced['physician_npi']) || empty($enhanced['physician_npi'])) {
            $enhanced['physician_npi'] = $formData['physician_npi'] ??
                                       $formData['provider_npi'] ??
                                       $baseData['provider_npi'] ?? '';
        }

        // Physician Specialty
        if (!isset($enhanced['physician_specialty']) || empty($enhanced['physician_specialty'])) {
            $enhanced['physician_specialty'] = $formData['physician_specialty'] ??
                                             $formData['provider_specialty'] ??
                                             $baseData['provider_specialty'] ??
                                             'Wound Care';
        }

        // Physician Phone
        if (!isset($enhanced['physician_phone']) || empty($enhanced['physician_phone'])) {
            $enhanced['physician_phone'] = $formData['physician_phone'] ??
                                         $formData['provider_phone'] ??
                                         $baseData['provider_phone'] ?? '';
        }

        // Physician Organization
        if (!isset($enhanced['physician_organization']) || empty($enhanced['physician_organization'])) {
            $enhanced['physician_organization'] = $formData['physician_organization'] ??
                                                $formData['organization_name'] ??
                                                $baseData['organization_name'] ??
                                                'MSC Wound Care';
        }

        return $enhanced;
    }

    /**
     * Enhance facility data with comprehensive mapping
     */
    private function enhanceFacilityData(array $data, array $formData, array $baseData): array
    {
        $enhanced = $data;

        // Facility Name
        if (!isset($enhanced['facility_name']) || empty($enhanced['facility_name'])) {
            $enhanced['facility_name'] = $formData['facility_name'] ??
                                       $formData['organization_name'] ??
                                       $baseData['facility_name'] ??
                                       'MSC Wound Care Facility';
        }

        // Facility Address
        if (!isset($enhanced['facility_address']) || empty($enhanced['facility_address'])) {
            $enhanced['facility_address'] = $formData['facility_address'] ??
                                          $formData['facility_address_line1'] ??
                                          $baseData['facility_address'] ?? '';
        }

        // Facility City, State, Zip
        if (!isset($enhanced['facility_city_state_zip']) || empty($enhanced['facility_city_state_zip'])) {
            $city = $formData['facility_city'] ?? $baseData['facility_city'] ?? '';
            $state = $formData['facility_state'] ?? $baseData['facility_state'] ?? '';
            $zip = $formData['facility_zip'] ?? $baseData['facility_zip'] ?? '';

            if ($city && $state && $zip) {
                $enhanced['facility_city_state_zip'] = "$city, $state $zip";
            }
        }

        // Facility Phone
        if (!isset($enhanced['facility_phone']) || empty($enhanced['facility_phone'])) {
            $enhanced['facility_phone'] = $formData['facility_phone'] ??
                                        $baseData['facility_phone'] ?? '';
        }

        // Facility NPI
        if (!isset($enhanced['facility_npi']) || empty($enhanced['facility_npi'])) {
            $enhanced['facility_npi'] = $formData['facility_npi'] ??
                                      $formData['organization_npi'] ??
                                      $baseData['facility_npi'] ?? '';
        }

        // Facility Tax ID
        if (!isset($enhanced['facility_tax_id']) || empty($enhanced['facility_tax_id'])) {
            $enhanced['facility_tax_id'] = $formData['facility_tax_id'] ??
                                         $formData['organization_tax_id'] ??
                                         $baseData['facility_tax_id'] ?? '';
        }

        // Facility PTAN
        if (!isset($enhanced['facility_ptan']) || empty($enhanced['facility_ptan'])) {
            $enhanced['facility_ptan'] = $formData['facility_ptan'] ??
                                       $formData['organization_ptan'] ??
                                       $baseData['facility_ptan'] ?? '';
        }

        // Facility Medicaid
        if (!isset($enhanced['facility_medicaid']) || empty($enhanced['facility_medicaid'])) {
            $enhanced['facility_medicaid'] = $formData['facility_medicaid'] ??
                                           $formData['organization_medicaid'] ??
                                           $baseData['facility_medicaid'] ?? '';
        }

        // Facility Contact Name
        if (!isset($enhanced['facility_contact_name']) || empty($enhanced['facility_contact_name'])) {
            $enhanced['facility_contact_name'] = $formData['facility_contact_name'] ??
                                               $formData['organization_contact_name'] ??
                                               $baseData['facility_contact_name'] ?? '';
        }

        // Facility Fax
        if (!isset($enhanced['facility_fax']) || empty($enhanced['facility_fax'])) {
            $enhanced['facility_fax'] = $formData['facility_fax'] ??
                                      $formData['organization_fax'] ??
                                      $baseData['facility_fax'] ?? '';
        }

        // Facility Contact Info (Phone/Email)
        if (!isset($enhanced['facility_contact_info']) || empty($enhanced['facility_contact_info'])) {
            $contactPhone = $formData['facility_contact_phone'] ?? $formData['organization_contact_phone'] ?? '';
            $contactEmail = $formData['facility_contact_email'] ?? $formData['organization_contact_email'] ?? '';

            if ($contactPhone && $contactEmail) {
                $enhanced['facility_contact_info'] = "$contactPhone / $contactEmail";
            } elseif ($contactPhone) {
                $enhanced['facility_contact_info'] = $contactPhone;
            } elseif ($contactEmail) {
                $enhanced['facility_contact_info'] = $contactEmail;
            }
        }

        // Facility Organization
        if (!isset($enhanced['facility_organization']) || empty($enhanced['facility_organization'])) {
            $enhanced['facility_organization'] = $formData['facility_organization'] ??
                                               $formData['organization_name'] ??
                                               $baseData['facility_organization'] ?? '';
        }

        return $enhanced;
    }

    /**
     * Enhance patient data with comprehensive mapping
     */
    private function enhancePatientData(array $data, array $formData, array $baseData): array
    {
        $enhanced = $data;

        // Patient Name - Enhanced computation
        if (!isset($enhanced['patient_name']) || empty($enhanced['patient_name'])) {
            $firstName = $formData['patient_first_name'] ?? $formData['fhir_patient_first_name'] ?? '';
            $lastName = $formData['patient_last_name'] ?? $formData['fhir_patient_last_name'] ?? '';

            if ($firstName && $lastName) {
                $enhanced['patient_name'] = "$firstName $lastName";
            } elseif (isset($formData['patient_name'])) {
                $enhanced['patient_name'] = $formData['patient_name'];
            } else {
                $enhanced['patient_name'] = 'Patient Name';
            }
        }

        // Patient DOB
        if (!isset($enhanced['patient_dob']) || empty($enhanced['patient_dob'])) {
            $enhanced['patient_dob'] = $formData['patient_dob'] ??
                                     $formData['fhir_patient_birth_date'] ??
                                     $formData['patient_birth_date'] ?? '';
        }

        // Patient Address
        if (!isset($enhanced['patient_address']) || empty($enhanced['patient_address'])) {
            $enhanced['patient_address'] = $formData['patient_address'] ??
                                         $formData['fhir_patient_address_line1'] ??
                                         $formData['patient_address_line1'] ?? '';
        }

        // Patient Phone
        if (!isset($enhanced['patient_phone']) || empty($enhanced['patient_phone'])) {
            $enhanced['patient_phone'] = $formData['patient_phone'] ??
                                       $formData['fhir_patient_phone'] ??
                                       $formData['patient_phone_number'] ?? '';
        }

        // Patient Email
        if (!isset($enhanced['patient_email']) || empty($enhanced['patient_email'])) {
            $enhanced['patient_email'] = $formData['patient_email'] ??
                                       $formData['fhir_patient_email'] ??
                                       $formData['patient_email_address'] ?? '';
        }

        // Patient City, State, Zip
        if (!isset($enhanced['patient_city_state_zip']) || empty($enhanced['patient_city_state_zip'])) {
            $city = $formData['patient_city'] ?? $formData['fhir_patient_city'] ?? '';
            $state = $formData['patient_state'] ?? $formData['fhir_patient_state'] ?? '';
            $zip = $formData['patient_zip'] ?? $formData['fhir_patient_zip'] ?? '';

            if ($city && $state && $zip) {
                $enhanced['patient_city_state_zip'] = "$city, $state $zip";
            }
        }

        // Patient Caregiver Info
        if (!isset($enhanced['patient_caregiver_info']) || empty($enhanced['patient_caregiver_info'])) {
            $caregiverName = $formData['patient_caregiver_name'] ?? $formData['caregiver_name'] ?? '';
            $caregiverPhone = $formData['patient_caregiver_phone'] ?? $formData['caregiver_phone'] ?? '';
            $caregiverRelationship = $formData['patient_caregiver_relationship'] ?? $formData['caregiver_relationship'] ?? '';

            if ($caregiverName && $caregiverPhone) {
                $enhanced['patient_caregiver_info'] = "$caregiverName ($caregiverRelationship) - $caregiverPhone";
            } elseif ($caregiverName) {
                $enhanced['patient_caregiver_info'] = "$caregiverName ($caregiverRelationship)";
            }
        }

        return $enhanced;
    }

    /**
     * Enhance insurance data with comprehensive mapping
     */
    private function enhanceInsuranceData(array $data, array $formData, array $baseData): array
    {
        $enhanced = $data;

        // Primary Insurance Name
        if (!isset($enhanced['primary_insurance_name']) || empty($enhanced['primary_insurance_name'])) {
            $enhanced['primary_insurance_name'] = $formData['primary_insurance_name'] ??
                                                $formData['primary_insurance'] ??
                                                $formData['primary_payer_name'] ?? '';
        }

        // Primary Policy Number
        if (!isset($enhanced['primary_policy_number']) || empty($enhanced['primary_policy_number'])) {
            $enhanced['primary_policy_number'] = $formData['primary_policy_number'] ??
                                               $formData['fhir_coverage_subscriber_id'] ??
                                               $formData['primary_member_id'] ??
                                               $formData['primary_subscriber_id'] ?? '';
        }

        // Secondary Insurance Name
        if (!isset($enhanced['secondary_insurance_name']) || empty($enhanced['secondary_insurance_name'])) {
            $enhanced['secondary_insurance_name'] = $formData['secondary_insurance_name'] ??
                                                  $formData['secondary_payer_name'] ??
                                                  $formData['secondary_insurance'] ?? '';
        }

        // Secondary Policy Number
        if (!isset($enhanced['secondary_policy_number']) || empty($enhanced['secondary_policy_number'])) {
            $enhanced['secondary_policy_number'] = $formData['secondary_policy_number'] ??
                                                 $formData['secondary_member_id'] ??
                                                 $formData['secondary_subscriber_id'] ?? '';
        }

        // Primary Payer Phone
        if (!isset($enhanced['primary_payer_phone']) || empty($enhanced['primary_payer_phone'])) {
            $enhanced['primary_payer_phone'] = $formData['primary_payer_phone'] ??
                                             $formData['primary_insurance_phone'] ??
                                             $formData['primary_insurance_contact'] ?? '';
        }

        // Secondary Payer Phone
        if (!isset($enhanced['secondary_payer_phone']) || empty($enhanced['secondary_payer_phone'])) {
            $enhanced['secondary_payer_phone'] = $formData['secondary_payer_phone'] ??
                                               $formData['secondary_insurance_phone'] ??
                                               $formData['secondary_insurance_contact'] ?? '';
        }

        return $enhanced;
    }

    /**
     * Enhance clinical data with comprehensive mapping
     */
    private function enhanceClinicalData(array $data, array $formData, array $baseData): array
    {
        $enhanced = $data;

        // Place of Service - Enhanced with smart defaults
        if (!isset($enhanced['place_of_service']) || empty($enhanced['place_of_service'])) {
            $pos = $formData['place_of_service'] ?? '';
            if ($pos) {
                // Ensure it has POS prefix
                if (!str_starts_with(strtoupper($pos), 'POS ')) {
                    $enhanced['place_of_service'] = "POS $pos";
                } else {
                    $enhanced['place_of_service'] = $pos;
                }
            } else {
                $enhanced['place_of_service'] = 'POS 11';
            }
        }

        // ICD-10 Codes
        if (!isset($enhanced['icd_10_codes']) || empty($enhanced['icd_10_codes'])) {
            $primary = $formData['primary_diagnosis_code'] ?? $formData['diagnosis_code'] ?? '';
            $secondary = $formData['secondary_diagnosis_code'] ?? '';

            if ($primary && $secondary) {
                $enhanced['icd_10_codes'] = "$primary, $secondary";
            } elseif ($primary) {
                $enhanced['icd_10_codes'] = $primary;
            } else {
                $enhanced['icd_10_codes'] = 'L97.9';
            }
        }

        // Total Wound Size
        if (!isset($enhanced['total_wound_size']) || empty($enhanced['total_wound_size'])) {
            $enhanced['total_wound_size'] = $formData['wound_size_total'] ??
                                          $formData['calculated_wound_area'] ??
                                          $formData['total_wound_size'] ??
                                          $formData['wound_area'] ??
                                          '25 sq cm';
        }

        // Medical History
        if (!isset($enhanced['medical_history']) || empty($enhanced['medical_history'])) {
            $enhanced['medical_history'] = $formData['medical_history'] ??
                                         $formData['clinical_notes'] ??
                                         $formData['patient_medical_history'] ??
                                         $formData['diagnosis_notes'] ??
                                         'Patient presents with wound requiring treatment.';
        }

        // Location of Wound
        if (!isset($enhanced['location_of_wound']) || empty($enhanced['location_of_wound'])) {
            $enhanced['location_of_wound'] = $formData['wound_location'] ??
                                           $formData['location_of_wound'] ??
                                           $formData['wound_site'] ??
                                           $formData['anatomical_location'] ??
                                           'Lower extremity';
        }

        return $enhanced;
    }

    /**
     * Enhance network status data with smart defaults
     */
    private function enhanceNetworkStatusData(array $data, array $formData, array $baseData): array
    {
        $enhanced = $data;

        // Physician Status With Primary
        if (!isset($enhanced['physician_status_primary']) || empty($enhanced['physician_status_primary'])) {
            $status = $formData['primary_physician_network_status'] ?? '';
            if ($status === 'in_network') {
                $enhanced['physician_status_primary'] = 'In-Network';
            } elseif ($status === 'out_of_network') {
                $enhanced['physician_status_primary'] = 'Out-of-Network';
            } else {
                $enhanced['physician_status_primary'] = 'In-Network'; // Default
            }
        }

        // Physician Status With Secondary
        if (!isset($enhanced['physician_status_secondary']) || empty($enhanced['physician_status_secondary'])) {
            $status = $formData['secondary_physician_network_status'] ?? '';
            if ($status === 'in_network') {
                $enhanced['physician_status_secondary'] = 'In-Network';
            } elseif ($status === 'out_of_network') {
                $enhanced['physician_status_secondary'] = 'Out-of-Network';
            } else {
                $enhanced['physician_status_secondary'] = 'In-Network'; // Default
            }
        }

        return $enhanced;
    }

    /**
     * Enhance authorization data with smart defaults
     */
    private function enhanceAuthorizationData(array $data, array $formData, array $baseData): array
    {
        $enhanced = $data;

        // Permission To Initiate And Follow Up On Prior Auth
        if (!isset($enhanced['permission_prior_auth']) || empty($enhanced['permission_prior_auth'])) {
            $permission = $formData['prior_auth_permission'] ?? '';
            if ($permission === true || $permission === 'true') {
                $enhanced['permission_prior_auth'] = 'Yes';
            } elseif ($permission === false || $permission === 'false') {
                $enhanced['permission_prior_auth'] = 'No';
            } else {
                $enhanced['permission_prior_auth'] = 'Yes'; // Default
            }
        }

        // Is The Patient Currently in Hospice
        if (!isset($enhanced['patient_in_hospice']) || empty($enhanced['patient_in_hospice'])) {
            $hospice = $formData['hospice_status'] ?? '';
            if ($hospice === true || $hospice === 'true') {
                $enhanced['patient_in_hospice'] = 'Yes';
            } elseif ($hospice === false || $hospice === 'false') {
                $enhanced['patient_in_hospice'] = 'No';
            } else {
                $enhanced['patient_in_hospice'] = 'No'; // Default
            }
        }

        // Is The Patient In A Facility Under Part A Stay
        if (!isset($enhanced['patient_part_a_stay']) || empty($enhanced['patient_part_a_stay'])) {
            $partA = $formData['part_a_status'] ?? '';
            if ($partA === true || $partA === 'true') {
                $enhanced['patient_part_a_stay'] = 'Yes';
            } elseif ($partA === false || $partA === 'false') {
                $enhanced['patient_part_a_stay'] = 'No';
            } else {
                $enhanced['patient_part_a_stay'] = 'No'; // Default
            }
        }

        // Is The Patient Under Post-Op Global Surgery Period
        if (!isset($enhanced['patient_global_surgery']) || empty($enhanced['patient_global_surgery'])) {
            $global = $formData['global_period_status'] ?? '';
            if ($global === true || $global === 'true') {
                $enhanced['patient_global_surgery'] = 'Yes';
            } elseif ($global === false || $global === 'false') {
                $enhanced['patient_global_surgery'] = 'No';
            } else {
                $enhanced['patient_global_surgery'] = 'No'; // Default
            }
        }

        return $enhanced;
    }

    /**
     * Enhance place of service data with comprehensive mapping
     */
    private function enhancePlaceOfServiceData(array $data, array $formData, array $baseData): array
    {
        $enhanced = $data;

        // Place of Service - Radio field mapping
        if (!isset($enhanced['place_of_service']) || empty($enhanced['place_of_service'])) {
            $placeOfService = $formData['place_of_service'] ??
                             $formData['pos'] ??
                             $formData['service_location'] ??
                             '';

            // Map common place of service values to DocuSeal options
            switch (strtolower($placeOfService)) {
                case 'office':
                case 'pos 11':
                case '11':
                    $enhanced['place_of_service'] = 'POS 11';
                    break;
                case 'outpatient hospital':
                case 'pos 22':
                case '22':
                    $enhanced['place_of_service'] = 'POS 22';
                    break;
                case 'ambulatory surgical center':
                case 'pos 24':
                case '24':
                    $enhanced['place_of_service'] = 'POS 24';
                    break;
                case 'home':
                case 'pos 12':
                case '12':
                    $enhanced['place_of_service'] = 'POS 12';
                    break;
                case 'nursing facility':
                case 'pos 32':
                case '32':
                    $enhanced['place_of_service'] = 'POS 32';
                    break;
                default:
                    $enhanced['place_of_service'] = 'POS 11'; // Default to office
                    break;
            }

            Log::info('Mapped place of service', [
                'input' => $placeOfService,
                'mapped' => $enhanced['place_of_service']
            ]);
        }

        // POS Other Specify - Conditional field
        if (!isset($enhanced['pos_other_specify']) || empty($enhanced['pos_other_specify'])) {
            if ($enhanced['place_of_service'] === 'Other') {
                $enhanced['pos_other_specify'] = $formData['pos_other_specify'] ??
                                               $formData['place_of_service_other'] ??
                                               $formData['service_location_other'] ?? '';
            }
        }

        return $enhanced;
    }

    /**
     * Enhance conditional surgery data with comprehensive mapping
     */
    private function enhanceConditionalSurgeryData(array $data, array $formData, array $baseData): array
    {
        $enhanced = $data;

        // Surgery CPTs - Conditional field (only if global surgery is Yes)
        if (!isset($enhanced['surgery_cpts']) || empty($enhanced['surgery_cpts'])) {
            if (($enhanced['patient_global_surgery'] ?? '') === 'Yes') {
                $enhanced['surgery_cpts'] = $formData['surgery_cpts'] ??
                                          $formData['surgery_codes'] ??
                                          $formData['global_surgery_cpts'] ??
                                          $formData['post_op_cpts'] ?? '';
            }
        }

        // Surgery Date - Conditional field (only if global surgery is Yes)
        if (!isset($enhanced['surgery_date']) || empty($enhanced['surgery_date'])) {
            if (($enhanced['patient_global_surgery'] ?? '') === 'Yes') {
                $enhanced['surgery_date'] = $formData['surgery_date'] ??
                                          $formData['global_surgery_date'] ??
                                          $formData['post_op_date'] ?? '';
            }
        }

        return $enhanced;
    }

    /**
     * Map provider status from Step 1 to network status fields
     */
    private function mapProviderStatusToNetworkStatus(array $data, array $formData, array $baseData): array
    {
        $enhanced = $data;

        // Map provider status to network status fields
        // This maps the provider status from Step 1 to the DocuSeal network status fields

        // Physician Status With Primary
        if (!isset($enhanced['physician_status_primary']) || empty($enhanced['physician_status_primary'])) {
            // Check for provider status in form data
            $providerStatus = $formData['provider_status'] ??
                             $formData['primary_physician_network_status'] ??
                             $formData['network_status'] ??
                             '';

            if ($providerStatus === 'in_network' || $providerStatus === 'in-network') {
                $enhanced['physician_status_primary'] = 'In-Network';
            } elseif ($providerStatus === 'out_of_network' || $providerStatus === 'out-of-network') {
                $enhanced['physician_status_primary'] = 'Out-of-Network';
            } else {
                // Default to In-Network if no status found
                $enhanced['physician_status_primary'] = 'In-Network';
            }

            Log::info('Mapped provider status to network status', [
                'provider_status' => $providerStatus,
                'mapped_status' => $enhanced['physician_status_primary']
            ]);
        }

        // Physician Status With Secondary
        if (!isset($enhanced['physician_status_secondary']) || empty($enhanced['physician_status_secondary'])) {
            // Check for secondary provider status
            $secondaryStatus = $formData['secondary_provider_status'] ??
                              $formData['secondary_physician_network_status'] ??
                              '';

            if ($secondaryStatus === 'in_network' || $secondaryStatus === 'in-network') {
                $enhanced['physician_status_secondary'] = 'In-Network';
            } elseif ($secondaryStatus === 'out_of_network' || $secondaryStatus === 'out-of-network') {
                $enhanced['physician_status_secondary'] = 'Out-of-Network';
            } else {
                // Default to In-Network if no status found
                $enhanced['physician_status_secondary'] = 'In-Network';
            }

            Log::info('Mapped secondary provider status to network status', [
                'secondary_provider_status' => $secondaryStatus,
                'mapped_status' => $enhanced['physician_status_secondary']
            ]);
        }

        return $enhanced;
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

    /**
     * Convert internal field names to exact DocuSeal field names
     */
    private function convertToDocuSealFieldNames(array $data): array
    {
        $docuSealData = [];

        // ACZ DocuSeal field name mapping
        $fieldMapping = [
            // Product Selection
            'product_q_code' => 'Product Q Code',

            // Representative Information
            'sales_rep' => 'Sales Rep',
            'iso_if_applicable' => 'ISO if applicable',
            'additional_emails' => 'Additional Emails for Notification',

            // Physician Information
            'physician_name' => 'Physician Name',
            'physician_npi' => 'Physician NPI',
            'physician_specialty' => 'Physician Specialty',
            'physician_tax_id' => 'Physician Tax ID',
            'physician_ptan' => 'Physician PTAN',
            'physician_medicaid' => 'Physician Medicaid #',
            'physician_phone' => 'Physician Phone #',
            'physician_fax' => 'Physician Fax #',
            'physician_organization' => 'Physician Organization',

            // Facility Information
            'facility_npi' => 'Facility NPI',
            'facility_tax_id' => 'Facility Tax ID',
            'facility_name' => 'Facility Name',
            'facility_ptan' => 'Facility PTAN',
            'facility_address' => 'Facility Address',
            'facility_medicaid' => 'Facility Medicaid #',
            'facility_city_state_zip' => 'Facility City, State, Zip',
            'facility_phone' => 'Facility Phone #',
            'facility_contact_name' => 'Facility Contact Name',
            'facility_fax' => 'Facility Fax #',
            'facility_contact_info' => 'Facility Contact Phone # / Facility Contact Email',
            'facility_organization' => 'Facility Organization',

            // Place of Service
            'place_of_service' => 'Place of Service',
            'pos_other_specify' => 'POS Other Specify',

            // Patient Information
            'patient_name' => 'Patient Name',
            'patient_dob' => 'Patient DOB',
            'patient_address' => 'Patient Address',
            'patient_city_state_zip' => 'Patient City, State, Zip',
            'patient_phone' => 'Patient Phone #',
            'patient_email' => 'Patient Email',
            'patient_caregiver_info' => 'Patient Caregiver Info',

            // Insurance Information
            'primary_insurance_name' => 'Primary Insurance Name',
            'secondary_insurance_name' => 'Secondary Insurance Name',
            'primary_policy_number' => 'Primary Policy Number',
            'secondary_policy_number' => 'Secondary Policy Number',
            'primary_payer_phone' => 'Primary Payer Phone #',
            'secondary_payer_phone' => 'Secondary Payer Phone #',

            // Network Status - Radio fields
            'physician_status_primary' => 'Physician Status With Primary',
            'physician_status_secondary' => 'Physician Status With Secondary',

            // Authorization Questions - Radio fields
            'permission_prior_auth' => 'Permission To Initiate And Follow Up On Prior Auth?',
            'patient_in_hospice' => 'Is The Patient Currently in Hospice?',
            'patient_part_a_stay' => 'Is The Patient In A Facility Under Part A Stay?',
            'patient_global_surgery' => 'Is The Patient Under Post-Op Global Surgery Period?',

            // Conditional Surgery Fields
            'surgery_cpts' => 'If Yes, List Surgery CPTs',
            'surgery_date' => 'Surgery Date',

            // Location and Clinical
            'location_of_wound' => 'Location of Wound',
            'icd_10_codes' => 'ICD-10 Codes',
            'total_wound_size' => 'Total Wound Size',
            'medical_history' => 'Medical History',
        ];

        // Convert internal field names to DocuSeal field names
        foreach ($data as $internalField => $value) {
            if (isset($fieldMapping[$internalField])) {
                $docuSealFieldName = $fieldMapping[$internalField];
                $docuSealData[$docuSealFieldName] = $value;

                Log::info('Converted field name for DocuSeal', [
                    'internal_field' => $internalField,
                    'docuseal_field' => $docuSealFieldName,
                    'value' => $value
                ]);
            } else {
                // Keep original field name if no mapping found
                $docuSealData[$internalField] = $value;
            }
        }

        Log::info('Field name conversion completed', [
            'original_fields' => count($data),
            'converted_fields' => count($docuSealData),
            'mapped_fields' => array_intersect(array_keys($docuSealData), array_values($fieldMapping))
        ]);

        return $docuSealData;
    }
}

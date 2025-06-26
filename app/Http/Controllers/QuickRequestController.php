<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuickRequest\CreateRequest;
use App\Http\Requests\QuickRequest\StoreRequest;
use App\Models\Order\Product;
use App\Models\Order\ProductRequest;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Fhir\Facility;
use App\Models\User;
use App\Services\QuickRequestService;
use App\Services\QuickRequest\QuickRequestOrchestrator;
use App\Services\PatientService;
use App\Services\PayerService;
use App\Services\PhiAuditService;
use App\Services\CurrentOrganization;
use App\Services\DocuSealService;
use App\Models\Docuseal\DocusealTemplate;
use App\Jobs\QuickRequest\ProcessEpisodeCreation;
use App\Jobs\QuickRequest\VerifyInsuranceEligibility;
use App\Jobs\QuickRequest\SendManufacturerNotification;
use App\Jobs\QuickRequest\GenerateDocuSealPdf;
use App\Jobs\QuickRequest\CreateApprovalTask;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Http;
use App\Models\Episode;
use App\Services\FhirDocuSealIntegrationService;

/**
 * QuickRequestController
 *
 * Handles the complete Quick Request workflow for wound care product orders.
 * Integrates with FHIR services, DocuSeal, and manufacturer notifications.
 */
final class QuickRequestController extends Controller
{
    public function __construct(
        protected QuickRequestService $quickRequestService,
        protected QuickRequestOrchestrator $orchestrator,
        protected PatientService $patientService,
        protected PayerService $payerService,
        protected CurrentOrganization $currentOrganization,
        protected DocuSealService $docuSealService
    ) {}

    /**
     * Display the quick request form
     */
    public function create(): Response
    {
        $user = $this->loadUserWithRelations();
        $currentOrg = $user->organizations->first();

        if ($currentOrg) {
            $this->currentOrganization->setId($currentOrg->id);
        }

        return Inertia::render('QuickRequest/CreateNew', [
            'facilities' => $this->getFacilitiesForUser($user, $currentOrg),
            'providers' => $this->getProvidersForUser($user, $currentOrg),
            'products' => $this->getActiveProducts(),
            'woundTypes' => $this->getWoundTypes(),
            'insuranceCarriers' => $this->getInsuranceCarriers(),
            'diagnosisCodes' => $this->getDiagnosisCodes(),
            'currentUser' => $this->getCurrentUserData($user, $currentOrg),
            'providerProducts' => [],
        ]);
    }

    /**
     * Store a new quick request
     */
    public function store(StoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            // Create episode using orchestrator
            $episode = $this->orchestrator->startEpisode([
                'patient' => $this->extractPatientData($validated),
                'provider' => $this->extractProviderData($validated),
                'facility' => $this->extractFacilityData($validated),
                'clinical' => $this->extractClinicalData($validated),
                'insurance' => $this->extractInsuranceData($validated),
                'order_details' => $this->extractOrderData($validated),
                'manufacturer_id' => $this->getManufacturerIdFromProducts($validated['selected_products']),
            ]);

            // Create product request for backward compatibility
            $productRequest = $this->createProductRequest($validated, $episode);

            // Handle file uploads
            // Handle file uploads (using base Request instance to satisfy static analysis)
            $this->handleFileUploads($request, $productRequest, $episode);

            // Save product request
            $productRequest->save();

            // Dispatch background jobs
            $this->dispatchQuickRequestJobs($episode, $productRequest, $validated);

            DB::commit();

            Log::info('Quick request submitted successfully', [
                'episode_id' => $episode->id,
                'product_request_id' => $productRequest->id,
                'user_id' => Auth::id(),
            ]);

            return redirect()->route('admin.episodes.show', $episode->id)
                ->with('success', 'Order submitted successfully! Your order is now being processed.')
                ->with('episode_id', $episode->id);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to submit quick request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to submit order: ' . $e->getMessage()]);
        }
    }

    /**
     * Create episode for DocuSeal integration
     */
    public function createEpisodeForDocuSeal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|string',
            'patient_fhir_id' => 'required|string',
            'patient_display_id' => 'required|string',
            'manufacturer_id' => 'nullable|exists:manufacturers,id',
            'selected_product_id' => 'nullable|exists:products,id',
            'form_data' => 'required|array',
        ]);

        try {
            // Determine manufacturer from product if not provided
            if (!$validated['manufacturer_id'] && $validated['selected_product_id']) {
                $product = Product::find($validated['selected_product_id']);
                if ($product && $product->manufacturer_id) {
                    $validated['manufacturer_id'] = $product->manufacturer_id;
                }
            }

            if (!$validated['manufacturer_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to determine manufacturer. Please ensure a product is selected.',
                ], 422);
            }

            // Find or create episode
            $episode = PatientManufacturerIVREpisode::firstOrCreate([
                'patient_fhir_id' => $validated['patient_fhir_id'],
                'manufacturer_id' => $validated['manufacturer_id'],
            ], [
                'patient_id' => $validated['patient_id'],
                'patient_display_id' => $validated['patient_display_id'],
                'status' => PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW,
                'metadata' => [
                    'facility_id' => $validated['form_data']['facility_id'] ?? null,
                    'provider_id' => Auth::id(),
                    'created_from' => 'quick_request',
                    'form_data' => $validated['form_data']
                ]
            ]);

            return response()->json([
                'success' => true,
                'episode_id' => $episode->id,
                'manufacturer_id' => $validated['manufacturer_id']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create episode for DocuSeal', [
                'error' => $e->getMessage(),
                'patient_display_id' => $validated['patient_display_id'],
                'manufacturer_id' => $validated['manufacturer_id'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create episode: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate JWT token for DocuSeal builder
     */
    public function generateBuilderToken(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'user_email' => 'required|email',
                'integration_email' => 'nullable|email',
                'template_id' => 'nullable|string',
                'template_name' => 'nullable|string',
                'document_urls' => 'nullable|array',
                'prefill_data' => 'nullable|array',
                'manufacturerId' => 'nullable|integer',
                'productCode' => 'nullable|string',
            ]);

            $apiKey = config('docuseal.api_key');
            if (!$apiKey) {
                throw new \Exception('DocuSeal API key not configured');
            }

            // Get manufacturer ID from request
            $manufacturerId = $data['manufacturerId'] ?? $this->getManufacturerIdFromPrefillData($data['prefill_data'] ?? []);

            // Map fields if we have prefill data and manufacturer
            $mappedFields = [];
            if (!empty($data['prefill_data']) && $manufacturerId) {
                try {
                    $template = $this->getTemplateForManufacturer($manufacturerId);
                    if ($template) {
                        $mappedFields = $this->docuSealService->mapFieldsUsingTemplate(
                            $data['prefill_data'],
                            $template
                        );

                        Log::info('DocuSeal fields mapped for JWT', [
                            'manufacturer_id' => $manufacturerId,
                            'template_id' => $template->docuseal_template_id,
                            'original_fields' => count($data['prefill_data']),
                            'mapped_fields' => count($mappedFields),
                            'sample_fields' => array_slice($mappedFields, 0, 3)
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Could not map fields for DocuSeal', [
                        'error' => $e->getMessage(),
                        'manufacturer_id' => $manufacturerId
                    ]);
                    // Continue without mapped fields
                }
            }

            $payload = [
                'user_email' => $data['user_email'],
                'integration_email' => $data['integration_email'] ?? $data['user_email'],
                'iat' => time(),
                'exp' => time() + (60 * 60), // 1 hour expiration
            ];

            if (!empty($data['template_id'])) {
                $payload['template_id'] = strval($data['template_id']);
            }

            if (!empty($data['template_name'])) {
                $payload['name'] = $data['template_name'];
            }

            if (!empty($data['document_urls'])) {
                $payload['document_urls'] = $data['document_urls'];
            }

            // Add mapped fields if available, otherwise use raw data
            if (!empty($mappedFields)) {
                $payload['fields'] = $mappedFields;
            } elseif (!empty($data['prefill_data'])) {
                // Fallback to raw data if mapping failed
                $payload['fields'] = $data['prefill_data'];
            }

            // Generate JWT token
            $jwtToken = $this->generateJwtToken($payload, $apiKey);

            return response()->json([
                'success' => true,
                'jwt_token' => $jwtToken,
                'token' => $jwtToken, // Alias for compatibility
                'jwt' => $jwtToken, // Another alias
                'user_email' => $data['user_email'],
                'integration_email' => $data['integration_email'] ?? $data['user_email'],
                'template_id' => $data['template_id'] ?? null,
                'template_name' => $data['template_name'] ?? 'MSC Wound Care IVR Form',
                'expires_at' => date('Y-m-d H:i:s', time() + (60 * 60)),
                'mapped_fields_count' => count($mappedFields)
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating DocuSeal builder token', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate builder token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create final DocuSeal submission
     */
    public function createFinalSubmission(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'template_type' => 'required|string',
                'use_builder' => 'boolean',
                'prefill_data' => 'required|array',
            ]);

            $prefillData = $data['prefill_data'];

            // Determine manufacturer and template
            $manufacturerId = $this->getManufacturerIdFromPrefillData($prefillData);
            $template = $this->getTemplateForManufacturer($manufacturerId);

            if (!$template) {
                throw new \Exception("No DocuSeal template found for manufacturer ID: {$manufacturerId}");
            }

            // Use builder mode if requested
            if ($data['use_builder'] ?? false) {
                return $this->generateBuilderToken(new Request([
                    'user_email' => 'limitless@mscwoundcare.com',
                    'integration_email' => $prefillData['admin@mscwound.com'] ?? 'limitless@mscwoundcare.com',
                    'template_id' => $template->docuseal_template_id,
                    'template_name' => "{$template->manufacturer->name} IVR Form",
                    'prefill_data' => $prefillData
                ]));
            }

            // Create direct submission
            $submissionData = [
                'template_id' => $template->docuseal_template_id,
                'send_email' => false,
                'submitters' => [
                    [
                        'role' => 'Patient',
                        'email' => config('docuseal.account_email', 'limitless@mscwoundcare.com'),
                        'name' => ($prefillData['patient_first_name'] ?? '') . ' ' . ($prefillData['patient_last_name'] ?? ''),
                        'values' => $this->formatPrefillValues($prefillData)
                    ]
                ]
            ];

            $response = $this->docuSealService->createSubmission($submissionData);

            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Failed to create submission');
            }

            return response()->json([
                'success' => true,
                'submission_id' => $response['submission_id'],
                'embed_url' => "https://api.docuseal.com/s/{$response['submission_id']}",
                'template_id' => $template->docuseal_template_id,
                'manufacturer' => $template->manufacturer->name,
                'manufacturer_id' => $manufacturerId
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating final submission', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create submission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate DocuSeal form token for filling existing templates.
     */
    public function generateFormToken(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'user_email' => 'required|email',
                'integration_email' => 'nullable|email',
                'prefill_data' => 'nullable|array',
                'manufacturerId' => 'nullable|integer',
                'productCode' => 'nullable|string',
            ]);

            $apiKey = config('docuseal.api_key');
            if (!$apiKey) {
                throw new \Exception('DocuSeal API key not configured');
            }

            // Get manufacturer ID from request
            $manufacturerId = $data['manufacturerId'] ?? $this->getManufacturerIdFromPrefillData($data['prefill_data'] ?? []);

            // Cast to integer if it's a string
            if (is_string($manufacturerId) && is_numeric($manufacturerId)) {
                $manufacturerId = (int) $manufacturerId;
            }

            if (!$manufacturerId) {
                throw new \Exception('Manufacturer ID is required');
            }

            // Get the template
            $template = $this->getTemplateForManufacturer($manufacturerId);
            if (!$template) {
                throw new \Exception("No DocuSeal template found for manufacturer ID: {$manufacturerId}");
            }

            // Map fields if we have prefill data
            $mappedFields = [];
            if (!empty($data['prefill_data'])) {
                try {
                    $mappedFields = $this->docuSealService->mapFieldsUsingTemplate(
                        $data['prefill_data'],
                        $template
                    );

                    Log::info('DocuSeal fields mapped for form', [
                        'manufacturer_id' => $manufacturerId,
                        'template_id' => $template->docuseal_template_id,
                        'original_fields' => count($data['prefill_data']),
                        'mapped_fields' => count($mappedFields),
                        'sample_fields' => array_slice($mappedFields, 0, 3)
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Could not map fields for DocuSeal form', [
                        'error' => $e->getMessage(),
                        'manufacturer_id' => $manufacturerId
                    ]);
                    // Continue without mapped fields
                }
            }

            // Build JWT payload for form component
            $payload = [
                'user_email' => $data['user_email'],
                'template_id' => $template->docuseal_template_id,
                'submitter' => [
                    'email' => $data['integration_email'] ?? $data['user_email'],
                    'fields' => $mappedFields // Pre-filled fields
                ],
                'external_id' => 'form_' . uniqid(),
                'iat' => time(),
                'exp' => time() + (60 * 60), // 1 hour expiration
            ];

            Log::info('Generating DocuSeal form token', [
                'template_id' => $template->docuseal_template_id,
                'has_fields' => !empty($mappedFields),
                'field_count' => count($mappedFields),
                'submitter_email' => $payload['submitter']['email']
            ]);

            // Generate JWT token
            $jwtToken = $this->generateJwtToken($payload, $apiKey);

            return response()->json([
                'success' => true,
                'jwt_token' => $jwtToken,
                'token' => $jwtToken, // Alias for compatibility
                'jwt' => $jwtToken, // Another alias
                'template_id' => $template->docuseal_template_id,
                'template_name' => $template->template_name,
                'manufacturer' => $template->manufacturer->name,
                'expires_at' => date('Y-m-d H:i:s', time() + (60 * 60)),
                'mapped_fields_count' => count($mappedFields)
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating DocuSeal form token', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate form token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate DocuSeal submission slug with enhanced FHIR integration
     */
    public function generateSubmissionSlug(Request $request): JsonResponse
    {
        // Log the start of the process with all incoming data
        Log::info('🔵 DocuSeal submission generation STARTED', [
            'request_data' => $request->all(),
            'user_id' => Auth::id(),
            'timestamp' => now()->toISOString(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        try {
            $data = $request->validate([
                'user_email' => 'required|email',
                'integration_email' => 'nullable|email',
                'prefill_data' => 'nullable|array',
                'manufacturerId' => 'nullable|integer',
                'productCode' => 'nullable|string',
                'episode_id' => 'nullable|integer', // Add episode ID for FHIR integration
            ]);

            Log::info('✅ Request validation PASSED', [
                'form_data_keys' => array_keys($data['prefill_data'] ?? []),
                'form_data_count' => count($data['prefill_data'] ?? []),
                'manufacturer_id' => $data['manufacturerId'] ?? 'not_provided',
                'product_code' => $data['productCode'] ?? 'not_provided',
                'episode_id' => $data['episode_id'] ?? 'not_provided',
                'sample_form_data' => array_slice($data['prefill_data'] ?? [], 0, 10)
            ]);

            $apiKey = config('docuseal.api_key');
            if (!$apiKey) {
                throw new \Exception('DocuSeal API key not configured');
            }

            // Get manufacturer ID from request
            $manufacturerId = $data['manufacturerId'] ?? $this->getManufacturerIdFromPrefillData($data['prefill_data'] ?? []);

            // Cast to integer if it's a string
            if (is_string($manufacturerId) && is_numeric($manufacturerId)) {
                $manufacturerId = (int) $manufacturerId;
            }

            if (!$manufacturerId) {
                throw new \Exception('Manufacturer ID is required');
            }

            // Check if we have an episode for FHIR integration
            $episode = null;
            if (!empty($data['episode_id'])) {
                $episode = Episode::find($data['episode_id']);
                Log::info('🔍 Episode found for FHIR integration', [
                    'episode_id' => $episode->id,
                    'patient_fhir_id' => $episode->patient_fhir_id,
                    'has_fhir_ids' => !empty($episode->metadata['fhir_ids'] ?? [])
                ]);
            }

            // Use FHIR-DocuSeal integration service if episode is available
            if ($episode) {
                Log::info('🎯 Using FHIR-DocuSeal Integration Service', [
                    'episode_id' => $episode->id,
                    'manufacturer_id' => $manufacturerId,
                    'has_patient_fhir_id' => !empty($episode->patient_fhir_id)
                ]);

                $fhirIntegrationService = app(\App\Services\FhirDocuSealIntegrationService::class);
                $result = $fhirIntegrationService->createProviderOrderSubmission($episode, $data['prefill_data'] ?? []);

                if ($result['success']) {
                    Log::info('✅ FHIR-DocuSeal integration SUCCESSFUL', [
                        'episode_id' => $episode->id,
                        'submission_id' => $result['submission_id'],
                        'slug' => $result['slug'],
                        'fhir_data_used' => $result['fhir_data_used'] ?? 0,
                        'fields_mapped' => $result['fields_mapped'] ?? 0
                    ]);

                    return response()->json([
                        'success' => true,
                        'slug' => $result['slug'],
                        'submission_id' => $result['submission_id'],
                        'template_id' => $result['template_id'],
                        'embed_url' => $result['embed_url'],
                        'fields_mapped' => $result['fields_mapped'],
                        'fhir_data_used' => $result['fhir_data_used'],
                        'integration_type' => 'fhir_enhanced'
                    ]);
                } else {
                    Log::warning('⚠️ FHIR integration failed, falling back to standard method', [
                        'episode_id' => $episode->id,
                        'error' => $result['error']
                    ]);
                    // Fall through to standard method
                }
            }

            // Standard DocuSeal integration (fallback or when no episode)
            Log::info('📋 Using standard DocuSeal integration', [
                'manufacturer_id' => $manufacturerId,
                'has_episode' => !is_null($episode),
                'fallback_reason' => $episode ? 'fhir_integration_failed' : 'no_episode_provided'
            ]);

            // Step 4: Template Resolution (CRITICAL STEP)
            Log::info('🔍 Starting template resolution', [
                'manufacturer_id' => $manufacturerId,
                'product_code' => $data['productCode'] ?? 'none',
                'lookup_key' => $manufacturerId . '_' . ($data['productCode'] ?? 'none')
            ]);

            $template = $this->getTemplateForManufacturer($manufacturerId);

            if (!$template) {
                Log::error('❌ TEMPLATE NOT FOUND', [
                    'manufacturer_id' => $manufacturerId,
                    'product_code' => $data['productCode'] ?? 'none',
                    'available_templates' => \App\Models\Docuseal\DocusealTemplate::where('manufacturer_id', $manufacturerId)
                        ->pluck('template_name', 'id')->toArray(),
                    'all_manufacturer_templates' => \App\Models\Docuseal\DocusealTemplate::pluck('template_name', 'manufacturer_id')->toArray()
                ]);
                throw new \Exception("No DocuSeal template found for manufacturer ID: {$manufacturerId}");
            }

            Log::info('✅ Template FOUND', [
                'template_id' => $template->id,
                'docuseal_template_id' => $template->docuseal_template_id,
                'template_name' => $template->template_name,
                'manufacturer_name' => $template->manufacturer->name ?? 'unknown',
                'field_mappings_count' => is_array($template->field_mappings) ? count($template->field_mappings) : 0
            ]);

            // Step 5: Field Mapping (127 fields issue)
            $mappedFields = [];
            if (!empty($data['prefill_data'])) {
                Log::info('🗂️ Starting field mapping', [
                    'input_fields_count' => count($data['prefill_data']),
                    'template_mappings_available' => !empty($template->field_mappings),
                    'first_10_input_fields' => array_slice(array_keys($data['prefill_data']), 0, 10),
                    'template_field_mappings' => is_array($template->field_mappings) ? array_keys($template->field_mappings) : []
                ]);

                try {
                    $mappedFields = $this->docuSealService->mapFieldsUsingTemplate(
                        $data['prefill_data'],
                        $template
                    );

                    Log::info('✅ Field mapping SUCCESSFUL', [
                        'manufacturer_id' => $manufacturerId,
                        'template_id' => $template->docuseal_template_id,
                        'original_fields' => count($data['prefill_data']),
                        'mapped_fields' => count($mappedFields),
                        'sample_mapped_fields' => array_slice($mappedFields, 0, 5),
                        'mapping_success_rate' => round((count($mappedFields) / count($data['prefill_data'])) * 100, 2) . '%'
                    ]);
                } catch (\Exception $e) {
                    Log::error('❌ Field mapping FAILED', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'manufacturer_id' => $manufacturerId,
                        'template_id' => $template->docuseal_template_id,
                        'input_field_count' => count($data['prefill_data'])
                    ]);
                    // Continue without mapped fields
                }
            } else {
                Log::info('ℹ️ No prefill data provided, skipping field mapping');
            }

            // Step 6: Role Resolution (CRITICAL - This was the original issue!)
            Log::info('🎭 Resolving template role', [
                'template_id' => $template->docuseal_template_id,
                'api_key_length' => strlen($apiKey)
            ]);

            $templateRole = $this->getTemplateRole($template->docuseal_template_id, $apiKey);

            Log::info('✅ Template role resolved', [
                'role' => $templateRole,
                'template_id' => $template->docuseal_template_id
            ]);

            // Step 7: Create submission data
            $submissionData = [
                'template_id' => (int) $template->docuseal_template_id,
                'send_email' => false, // Don't send email, we're embedding
                'submitters' => [
                    [
                        'email' => $data['integration_email'] ?? $data['user_email'],
                        'role' => $templateRole, // Use the actual role from template
                        'fields' => $mappedFields // Pre-filled fields
                    ]
                ]
            ];

            Log::info('📤 Sending DocuSeal API request', [
                'template_id' => $submissionData['template_id'],
                'submitter_email' => $submissionData['submitters'][0]['email'],
                'submitter_role' => $submissionData['submitters'][0]['role'],
                'fields_count' => count($submissionData['submitters'][0]['fields']),
                'api_endpoint' => 'https://api.docuseal.com/submissions'
            ]);

            // Make API call to DocuSeal (using correct authentication header)
            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey,  // DocuSeal uses X-Auth-Token, not X-API-Key
                'Content-Type' => 'application/json'
            ])->timeout(config('docuseal.timeout', 30))
            ->post('https://api.docuseal.com/submissions', $submissionData);

            if (!$response->successful()) {
                $statusCode = $response->status();
                $responseBody = $response->body();

                Log::error('DocuSeal API error', [
                    'status' => $statusCode,
                    'body' => $responseBody,
                    'submission_data' => $submissionData
                ]);

                // Enhanced error handling for specific HTTP status codes
                $errorMessage = 'Unknown error';
                if ($statusCode === 401) {
                    $errorMessage = 'Authentication failed - check API key and permissions';
                } elseif ($statusCode === 404) {
                    $errorMessage = 'Template not found - verify template ID exists in DocuSeal';
                } elseif ($statusCode === 422) {
                    $responseData = $response->json();
                    $errorMessage = $responseData['error'] ?? 'Validation failed - check role name and field mappings';
                } else {
                    $responseData = $response->json();
                    $errorMessage = $responseData['error'] ?? $responseBody;
                }

                throw new \Exception("DocuSeal API error ({$statusCode}): {$errorMessage}");
            }

            $submissionResponse = $response->json();

            Log::info('🔍 DocuSeal API Response Analysis', [
                'response_structure' => array_keys($submissionResponse),
                'is_array' => is_array($submissionResponse),
                'response_type' => gettype($submissionResponse),
                'response_sample' => array_slice($submissionResponse, 0, 3, true) // First 3 keys for safety
            ]);

            // Handle different DocuSeal response formats
            $submitters = [];
            $slug = null;
            $submissionId = null;

            // Format 1: Response contains 'submitters' array
            if (isset($submissionResponse['submitters']) && is_array($submissionResponse['submitters'])) {
                $submitters = $submissionResponse['submitters'];
                $slug = $submitters[0]['slug'] ?? null;
                $submissionId = $submissionResponse['id'] ?? $submitters[0]['submission_id'] ?? null;

                Log::info('✅ Using submitters array format', [
                    'submitters_count' => count($submitters),
                    'first_submitter_keys' => array_keys($submitters[0] ?? [])
                ]);
            }
            // Format 2: Response IS an array of submitters (direct format)
            elseif (is_array($submissionResponse) && isset($submissionResponse[0]['slug'])) {
                $submitters = $submissionResponse;
                $slug = $submitters[0]['slug'] ?? null;
                $submissionId = $submitters[0]['submission_id'] ?? $submitters[0]['id'] ?? null;

                Log::info('✅ Using direct submitters array format', [
                    'submitters_count' => count($submitters),
                    'first_submitter_keys' => array_keys($submitters[0] ?? [])
                ]);
            }
            // Format 3: Single submission object
            elseif (isset($submissionResponse['slug'])) {
                $slug = $submissionResponse['slug'];
                $submissionId = $submissionResponse['id'] ?? $submissionResponse['submission_id'] ?? null;
                $submitters = [$submissionResponse]; // Wrap in array for consistency

                Log::info('✅ Using single submission format', [
                    'submission_keys' => array_keys($submissionResponse)
                ]);
            }

            // Final validation
            if (empty($slug)) {
                Log::error('❌ No slug found in any format', [
                    'full_response' => $submissionResponse,
                    'response_keys' => array_keys($submissionResponse)
                ]);
                throw new \Exception('No slug returned from DocuSeal API. Response format: ' . json_encode(array_keys($submissionResponse)));
            }

            if (empty($submitters)) {
                Log::error('❌ No submitters found in any format', [
                    'full_response' => $submissionResponse
                ]);
                throw new \Exception('No submitters returned from DocuSeal API. Response: ' . json_encode($submissionResponse));
            }

            Log::info('DocuSeal submission created successfully', [
                'template_id' => $template->docuseal_template_id,
                'submission_id' => $submissionResponse['id'] ?? null,
                'slug' => $slug,
                'submitter_email' => $submitters[0]['email'] ?? null,
                'mapped_fields_count' => count($mappedFields),
                'integration_type' => 'standard'
            ]);

            return response()->json([
                'success' => true,
                'slug' => $slug,
                'submission_id' => $submissionResponse['id'] ?? null,
                'template_id' => $template->docuseal_template_id,
                'template_name' => $template->template_name,
                'manufacturer' => $template->manufacturer->name,
                'mapped_fields_count' => count($mappedFields),
                'embed_url' => "https://docuseal.com/s/{$slug}",
                'integration_type' => 'standard'
            ]);

        } catch (\Exception $e) {
            Log::error('❌ DocuSeal submission generation FAILED', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'user_id' => Auth::id(),
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate submission slug: ' . $e->getMessage(),
                'debug_info' => [
                    'error_type' => get_class($e),
                    'error_line' => $e->getLine(),
                    'error_file' => basename($e->getFile())
                ]
            ], 500);
        }
    }

    /**
     * Debug endpoint for DocuSeal integration issues
     */
    public function debugDocuSealIntegration(Request $request): JsonResponse
    {
        try {
            $manufacturerId = $request->get('manufacturerId', 32); // Default to your Amnio AMP manufacturer
            $productCode = $request->get('productCode', 'Q4250');

            $debugInfo = [
                'timestamp' => now()->toISOString(),
                'system_info' => [
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'user_id' => Auth::id()
                ],
                'configuration' => [
                    'api_key_configured' => !empty(config('docuseal.api_key')),
                    'api_key_length' => strlen(config('docuseal.api_key') ?? ''),
                    'api_url' => config('docuseal.api_url', 'https://api.docuseal.com'),
                    'timeout' => config('docuseal.timeout', 30)
                ],
                'manufacturer_info' => [],
                'template_info' => [],
                'api_connectivity' => []
            ];

            // Test manufacturer lookup
            $manufacturer = \App\Models\Order\Manufacturer::find($manufacturerId);
            if ($manufacturer) {
                $debugInfo['manufacturer_info'] = [
                    'found' => true,
                    'id' => $manufacturer->id,
                    'name' => $manufacturer->name,
                    'templates_count' => $manufacturer->docusealTemplates()->count(),
                    'templates' => $manufacturer->docusealTemplates()->pluck('template_name', 'id')->toArray()
                ];
            } else {
                $debugInfo['manufacturer_info'] = [
                    'found' => false,
                    'searched_id' => $manufacturerId,
                    'available_manufacturers' => \App\Models\Order\Manufacturer::pluck('name', 'id')->toArray()
                ];
            }

            // Test template lookup
            $template = $this->getTemplateForManufacturer($manufacturerId);
            if ($template) {
                $debugInfo['template_info'] = [
                    'found' => true,
                    'id' => $template->id,
                    'docuseal_template_id' => $template->docuseal_template_id,
                    'template_name' => $template->template_name,
                    'manufacturer_name' => $template->manufacturer->name ?? 'unknown',
                    'field_mappings_count' => is_array($template->field_mappings) ? count($template->field_mappings) : 0,
                    'field_mappings' => $template->field_mappings
                ];
            } else {
                $debugInfo['template_info'] = [
                    'found' => false,
                    'searched_manufacturer_id' => $manufacturerId,
                    'all_templates' => \App\Models\Docuseal\DocusealTemplate::with('manufacturer')
                        ->get()
                        ->map(fn($t) => [
                            'id' => $t->id,
                            'name' => $t->template_name,
                            'manufacturer' => $t->manufacturer->name ?? 'unknown',
                            'docuseal_id' => $t->docuseal_template_id
                        ])
                        ->toArray()
                ];
            }

            // Test API connectivity
            $apiKey = config('docuseal.api_key');
            if ($apiKey) {
                try {
                    $apiResponse = \Illuminate\Support\Facades\Http::withHeaders([
                        'X-Auth-Token' => $apiKey,
                    ])->timeout(30)->get('https://api.docuseal.com/templates');

                    $debugInfo['api_connectivity'] = [
                        'reachable' => true,
                        'status_code' => $apiResponse->status(),
                        'authenticated' => $apiResponse->successful(),
                        'templates_count' => $apiResponse->successful() ? count($apiResponse->json()) : 0,
                        'response_time_ms' => round(microtime(true) * 1000) - round($_SERVER['REQUEST_TIME_FLOAT'] * 1000)
                    ];

                    if ($template && $apiResponse->successful()) {
                        // Test role extraction
                        $roleResponse = \Illuminate\Support\Facades\Http::withHeaders([
                            'X-Auth-Token' => $apiKey,
                        ])->timeout(30)->get("https://api.docuseal.com/templates/{$template->docuseal_template_id}");

                        if ($roleResponse->successful()) {
                            $templateData = $roleResponse->json();
                            $extractedRole = $this->extractRoleFromTemplate($templateData);

                            $debugInfo['template_info']['role_extraction'] = [
                                'success' => !empty($extractedRole),
                                'extracted_role' => $extractedRole,
                                'template_structure' => array_keys($templateData),
                                'raw_template_data' => $templateData
                            ];
                        }
                    }

                } catch (\Exception $e) {
                    $debugInfo['api_connectivity'] = [
                        'reachable' => false,
                        'error' => $e->getMessage(),
                        'error_type' => get_class($e)
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'debug_info' => $debugInfo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Load user with necessary relationships
     */
    private function loadUserWithRelations(): User
    {
        return Auth::user()->load([
            'roles',
            'providerProfile',
            'providerCredentials',
            'organizations' => fn($q) => $q->where('organization_users.is_active', true),
            'facilities'
        ]);
    }

    /**
     * Get facilities for user
     */
    private function getFacilitiesForUser(User $user, $currentOrg): \Illuminate\Support\Collection
    {
        $userFacilities = $user->facilities->map(function($facility) {
            return [
                'id' => $facility->id,
                'name' => $facility->name,
                'address' => $facility->full_address,
                'source' => 'user_relationship'
            ];
        });

        if ($userFacilities->count() > 0) {
            return $userFacilities;
        }

        return Facility::withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class)
            ->where('active', true)
            ->take(10)
            ->get()
            ->map(function($facility) {
                return [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'address' => $facility->full_address ?? 'No address',
                    'organization_id' => $facility->organization_id,
                    'source' => 'all_facilities'
                ];
            });
    }

    /**
     * Get providers for user
     */
    private function getProvidersForUser(User $user, $currentOrg): array
    {
        $providers = [];
        $userRole = $user->getPrimaryRole()?->slug ?? $user->roles->first()?->slug;

        if ($userRole === 'provider') {
            $providers[] = [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'credentials' => $user->providerProfile?->credentials ?? $user->providerCredentials->pluck('credential_number')->implode(', ') ?? null,
                'npi' => $user->npi_number ?? $user->providerCredentials->where('credential_type', 'npi_number')->first()?->credential_number ?? null,
            ];
        }

        if ($currentOrg) {
            $orgProviders = User::whereHas('organizations', function($q) use ($currentOrg) {
                    $q->where('organizations.id', $currentOrg->id);
                })
                ->whereHas('roles', function($q) {
                    $q->where('slug', 'provider');
                })
                ->where('id', '!=', $user->id)
                ->get(['id', 'first_name', 'last_name', 'npi_number'])
                ->map(function($provider) {
                    return [
                        'id' => $provider->id,
                        'name' => $provider->first_name . ' ' . $provider->last_name,
                        'credentials' => $provider->providerProfile?->credentials ?? $provider->provider_credentials ?? null,
                        'npi' => $provider->npi_number,
                    ];
                })
                ->toArray();

            $providers = array_merge($providers, $orgProviders);
        }

        return $providers;
    }

    /**
     * Get active products
     */
    private function getActiveProducts(): \Illuminate\Support\Collection
    {
        return Product::where('is_active', true)
            ->get()
            ->map(function($product) {
                $sizes = $product->available_sizes;
                if (is_string($sizes)) {
                    $sizes = json_decode($sizes, true) ?? [];
                } elseif (!is_array($sizes)) {
                    $sizes = [];
                }

                return [
                    'id' => $product->id,
                    'code' => $product->q_code,
                    'name' => $product->name,
                    'manufacturer' => $product->manufacturer,
                    'manufacturer_id' => $product->manufacturer_id,
                    'available_sizes' => $sizes,
                    'price_per_sq_cm' => $product->price_per_sq_cm ?? 0,
                ];
            });
    }

    /**
     * Get wound types
     */
    private function getWoundTypes(): array
    {
        return DB::table('wound_types')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('display_name', 'code')
            ->toArray();
    }

    /**
     * Get insurance carriers
     */
    private function getInsuranceCarriers(): array
    {
        return $this->payerService->getAllPayers()
            ->pluck('name')
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Get diagnosis codes
     */
    private function getDiagnosisCodes(): array
    {
        return DB::table('diagnosis_codes')
            ->where('is_active', true)
            ->select(['code', 'description', 'category'])
            ->orderBy('category')
            ->orderBy('code')
            ->get()
            ->groupBy('category')
            ->map(function ($group) {
                return $group->map(function ($item) {
                    return [
                        'code' => $item->code,
                        'description' => $item->description
                    ];
                })->values()->toArray();
            })
            ->toArray();
    }

    /**
     * Get current user data
     */
    private function getCurrentUserData(User $user, $currentOrg): array
    {
        return [
            'id' => $user->id,
            'name' => $user->first_name . ' ' . $user->last_name,
            'npi' => $user->npi_number ?? $user->providerCredentials->where('credential_type', 'npi_number')->first()?->credential_number ?? null,
            'role' => $user->getPrimaryRole()?->slug ?? $user->roles->first()?->slug,
            'organization' => $currentOrg ? [
                'id' => $currentOrg->id,
                'name' => $currentOrg->name,
                'address' => $currentOrg->billing_address,
                'phone' => $currentOrg->phone,
            ] : null,
        ];
    }

    /**
     * Extract patient data from validated request
     */
    private function extractPatientData(array $validated): array
    {
        return [
            'first_name' => $validated['patient_first_name'],
            'last_name' => $validated['patient_last_name'],
            'date_of_birth' => $validated['patient_dob'],
            'gender' => $validated['patient_gender'] ?? 'unknown',
            'member_id' => $validated['patient_member_id'],
            'address_line1' => $validated['patient_address_line1'],
            'address_line2' => $validated['patient_address_line2'],
            'city' => $validated['patient_city'],
            'state' => $validated['patient_state'],
            'zip' => $validated['patient_zip'],
            'phone' => $validated['patient_phone'],
            'email' => $validated['patient_email'] ?? null,
        ];
    }

    /**
     * Extract provider data from validated request
     */
    private function extractProviderData(array $validated): array
    {
        $provider = User::find($validated['provider_id']);

        return [
            'id' => $provider->id,
            'first_name' => $provider->first_name,
            'last_name' => $provider->last_name,
            'name' => $provider->first_name . ' ' . $provider->last_name,
            'npi' => $provider->npi_number ?? $provider->providerCredentials->where('credential_type', 'npi_number')->first()?->credential_number ?? null,
            'credentials' => $provider->providerProfile?->credentials ?? $provider->providerCredentials->pluck('credential_number')->implode(', ') ?? null,
            'email' => $provider->email,
        ];
    }

    /**
     * Extract facility data from validated request
     */
    private function extractFacilityData(array $validated): array
    {
        $facility = Facility::find($validated['facility_id']);

        return [
            'id' => $facility->id,
            'name' => $facility->name,
            'address' => $facility->full_address,
            'phone' => $facility->phone ?? '',
            'npi' => $facility->npi ?? '',
        ];
    }

    /**
     * Extract clinical data from validated request
     */
    private function extractClinicalData(array $validated): array
    {
        return [
            'wound_type' => $validated['wound_type'],
            'wound_location' => $validated['wound_location'],
            'wound_length' => $validated['wound_size_length'],
            'wound_width' => $validated['wound_size_width'],
            'wound_depth' => $validated['wound_size_depth'] ?? null,
            'diagnosis_code' => $validated['primary_diagnosis_code'] ?? $validated['diagnosis_code'] ?? null,
            'diagnosis_description' => '', // Would need to look this up
            'previous_treatments' => $validated['previous_treatments'] ?? null,
            'clinical_notes' => $validated['clinical_notes'] ?? '',
            'onset_date' => $this->calculateOnsetDate($validated),
        ];
    }

    /**
     * Extract insurance data from validated request
     */
    private function extractInsuranceData(array $validated): array
    {
        return [
            'payer_name' => $validated['primary_insurance_name'],
            'member_id' => $validated['primary_member_id'],
            'type' => $validated['primary_plan_type'],
            'type_display' => $validated['primary_plan_type'],
            'has_secondary' => $validated['has_secondary_insurance'] ?? false,
            'secondary_payer_name' => $validated['secondary_insurance_name'] ?? null,
            'secondary_member_id' => $validated['secondary_member_id'] ?? null,
        ];
    }

    /**
     * Extract order data from validated request
     */
    private function extractOrderData(array $validated): array
    {
        return [
            'products' => $validated['selected_products'],
            'expected_service_date' => $validated['expected_service_date'],
            'shipping_speed' => $validated['shipping_speed'],
            'place_of_service' => $validated['place_of_service'],
            'cpt_codes' => $validated['application_cpt_codes'],
        ];
    }

    /**
     * Get manufacturer ID from selected products
     */
    private function getManufacturerIdFromProducts(array $selectedProducts): ?int
    {
        if (empty($selectedProducts)) {
            return null;
        }

        $product = Product::find($selectedProducts[0]['product_id']);
        return $product?->manufacturer_id;
    }

    /**
     * Get manufacturer ID from prefill data
     */
    private function getManufacturerIdFromPrefillData(array $prefillData): ?int
    {
        if (!empty($prefillData['manufacturer_id'])) {
            $manufacturerId = $prefillData['manufacturer_id'];
            // Cast to integer if it's a string
            if (is_string($manufacturerId) && is_numeric($manufacturerId)) {
                return (int) $manufacturerId;
            }
            return is_int($manufacturerId) ? $manufacturerId : null;
        }

        if (!empty($prefillData['selected_products'])) {
            $selectedProducts = $prefillData['selected_products'];
            if (!empty($selectedProducts[0]['product_id'])) {
                $product = Product::find($selectedProducts[0]['product_id']);
                return $product?->manufacturer_id;
            }
        }

        return null;
    }

    /**
     * Get template for manufacturer
     */
    private function getTemplateForManufacturer(?int $manufacturerId): ?DocusealTemplate
    {
        if (!$manufacturerId) {
            return null;
        }

        return DocusealTemplate::getDefaultTemplateForManufacturer($manufacturerId, 'IVR');
    }

    /**
     * Create product request for backward compatibility
     */
    private function createProductRequest(array $validated, PatientManufacturerIVREpisode $episode): ProductRequest
    {
        $firstProduct = Product::find($validated['selected_products'][0]['product_id']);

        $productRequest = new ProductRequest();
        $productRequest->id = Str::uuid();
        $productRequest->request_number = $this->generateRequestNumber();
        $productRequest->requester_id = Auth::id();
        $productRequest->provider_id = $validated['provider_id'];
        $productRequest->facility_id = $validated['facility_id'];
        $productRequest->request_type = $validated['request_type'];
        $productRequest->order_status = 'ready_for_review';
        $productRequest->submission_type = 'quick_request';
        $productRequest->patient_fhir_id = $episode->patient_fhir_id;
        $productRequest->payer_name = $validated['primary_insurance_name'];
        $productRequest->payer_id = $validated['primary_member_id'];
        $productRequest->insurance_type = $validated['primary_plan_type'];
        $productRequest->expected_service_date = $validated['expected_service_date'];
        $productRequest->wound_type = $validated['wound_type'];
        $productRequest->place_of_service = $validated['place_of_service'];
        $productRequest->product_id = $firstProduct->id;
        $productRequest->product_name = $firstProduct->name;
        $productRequest->product_code = $firstProduct->q_code;
        $productRequest->manufacturer = $firstProduct->manufacturer;
        $productRequest->size = $validated['selected_products'][0]['size'] ?? '';
        $productRequest->quantity = $validated['selected_products'][0]['quantity'];
        $productRequest->metadata = $this->buildProductRequestMetadata($validated);

        return $productRequest;
    }

    /**
     * Build metadata for product request
     */
    private function buildProductRequestMetadata(array $validated): array
    {
        return [
            'products' => $validated['selected_products'],
            'clinical_info' => [
                'wound_type' => $validated['wound_type'],
                'wound_location' => $validated['wound_location'],
                'wound_measurements' => [
                    'length' => $validated['wound_size_length'],
                    'width' => $validated['wound_size_width'],
                    'depth' => $validated['wound_size_depth'] ?? null,
                ],
                'previous_treatments' => $validated['previous_treatments'] ?? null,
                'cpt_codes' => $validated['application_cpt_codes'],
            ],
            'insurance_info' => [
                'primary' => [
                    'name' => $validated['primary_insurance_name'],
                    'member_id' => $validated['primary_member_id'],
                    'plan_type' => $validated['primary_plan_type'],
                ],
                'has_secondary' => $validated['has_secondary_insurance'] ?? false,
            ],
            'shipping_speed' => $validated['shipping_speed'],
            'created_via' => 'quick_request_controller',
            'created_by' => Auth::id(),
            'created_at' => now(),
        ];
    }

    /**
     * Handle file uploads
     */
    private function handleFileUploads(\Illuminate\Http\Request $request, ProductRequest $productRequest, PatientManufacturerIVREpisode $episode): void
    {
        $documentMetadata = [];
        $documentTypes = [
            'insurance_card_front' => 'phi/insurance-cards/',
            'insurance_card_back' => 'phi/insurance-cards/',
            'face_sheet' => 'phi/face-sheets/',
            'clinical_notes' => 'phi/clinical-notes/',
            'wound_photo' => 'phi/wound-photos/',
        ];

        foreach ($documentTypes as $fieldName => $storagePath) {
            if ($request->hasFile($fieldName)) {
                $path = $request->file($fieldName)->store($storagePath . date('Y/m'), 's3-encrypted');
                $documentMetadata[$fieldName] = [
                    'path' => $path,
                    'uploaded_at' => now(),
                    'size' => $request->file($fieldName)->getSize(),
                    'mime_type' => $request->file($fieldName)->getMimeType()
                ];

                PhiAuditService::logCreation('Document', $path, [
                    'document_type' => $fieldName,
                    'patient_fhir_id' => $episode->patient_fhir_id,
                    'product_request_id' => $productRequest->id
                ]);
            }
        }

        if (!empty($documentMetadata)) {
            $metadata = $productRequest->metadata;
            $metadata['documents'] = $documentMetadata;
            $productRequest->metadata = $metadata;
        }
    }

    /**
     * Dispatch background jobs
     */
    private function dispatchQuickRequestJobs(PatientManufacturerIVREpisode $episode, ProductRequest $productRequest, array $validated): void
    {
        // Insurance eligibility verification
        VerifyInsuranceEligibility::dispatch($episode, [
            'payer_name' => $validated['primary_insurance_name'],
            'member_id' => $validated['primary_member_id'],
            'plan_type' => $validated['primary_plan_type'],
        ]);

        // Generate DocuSeal PDF
        GenerateDocuSealPdf::dispatch($episode, [
            'manufacturer_id' => $this->getManufacturerIdFromProducts($validated['selected_products']),
            'form_data' => $validated,
        ]);

        // Send manufacturer notification
        SendManufacturerNotification::dispatch($episode, $productRequest);

        // Create approval task
        CreateApprovalTask::dispatch($episode, [
            'assigned_to' => 'admin',
            'priority' => 'normal',
            'due_date' => now()->addBusinessDays(2),
        ]);
    }

    /**
     * Generate JWT token
     */
    private function generateJwtToken(array $payload, string $apiKey): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);

        $headerEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $payloadEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, $apiKey, true);
        $signatureEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
    }

    /**
     * Format prefill values for DocuSeal
     */
    private function formatPrefillValues(array $prefillData): array
    {
        return [
            'patient_first_name' => $prefillData['patient_first_name'] ?? '',
            'patient_last_name' => $prefillData['patient_last_name'] ?? '',
            'patient_dob' => $prefillData['patient_dob'] ?? '',
            'patient_phone' => $prefillData['patient_phone'] ?? '',
            'patient_email' => $prefillData['patient_email'] ?? '',
            'wound_type' => $prefillData['wound_type'] ?? '',
            'wound_location' => $prefillData['wound_location'] ?? '',
            'wound_size_length' => $prefillData['wound_size_length'] ?? '',
            'wound_size_width' => $prefillData['wound_size_width'] ?? '',
            'primary_insurance_name' => $prefillData['primary_insurance_name'] ?? '',
            'primary_member_id' => $prefillData['primary_member_id'] ?? '',
            'provider_name' => $prefillData['provider_name'] ?? '',
            'provider_npi' => $prefillData['provider_npi'] ?? '',
            'facility_name' => $prefillData['facility_name'] ?? '',
        ];
    }

    /**
     * Calculate onset date from wound duration fields
     */
    private function calculateOnsetDate(array $validated): string
    {
        $days = ($validated['wound_duration_days'] ?? 0);
        $weeks = ($validated['wound_duration_weeks'] ?? 0) * 7;
        $months = ($validated['wound_duration_months'] ?? 0) * 30;
        $years = ($validated['wound_duration_years'] ?? 0) * 365;

        $totalDays = $days + $weeks + $months + $years;

        if ($totalDays > 0) {
            return Carbon::now()->subDays($totalDays)->format('Y-m-d');
        }

        return Carbon::now()->format('Y-m-d');
    }

    /**
     * Generate unique request number
     */
    private function generateRequestNumber(): string
    {
        $prefix = 'QR';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(4));

        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Get the correct role name from DocuSeal template
     */
    private function getTemplateRole(string $templateId, string $apiKey): string
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey,
            ])->timeout(30)->get("https://api.docuseal.com/templates/{$templateId}");

            if ($response->successful()) {
                $templateData = $response->json();
                $role = $this->extractRoleFromTemplate($templateData);

                if ($role) {
                    Log::info('DocuSeal template role found', [
                        'template_id' => $templateId,
                        'role' => $role
                    ]);
                    return $role;
                }
            }

            Log::warning('Could not determine template role, falling back to common defaults', [
                'template_id' => $templateId,
                'response_status' => $response->status(),
                'response_body' => $response->body()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching template role from DocuSeal', [
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);
        }

        // Fallback to common role names based on the error message
        // Since the error specifically mentioned "First Party", that's likely the correct role
        return 'First Party';
    }

    /**
     * Extract role from template data using multiple methods
     */
    private function extractRoleFromTemplate(array $templateData): ?string
    {
        // Method 1: Check submitter_roles array
        if (isset($templateData['submitter_roles']) && is_array($templateData['submitter_roles'])) {
            foreach ($templateData['submitter_roles'] as $role) {
                if (is_string($role)) {
                    return $role;
                } elseif (is_array($role) && isset($role['name'])) {
                    return $role['name'];
                }
            }
        }

        // Method 2: Check schema for role information
        $schema = $templateData['schema'] ?? [];
        if (!empty($schema) && is_array($schema)) {
            foreach ($schema as $item) {
                if (isset($item['role'])) {
                    return $item['role'];
                }
            }
        }

        // Method 3: Check fields for role information
        $fields = $templateData['fields'] ?? [];
        if (!empty($fields) && is_array($fields)) {
            foreach ($fields as $field) {
                if (isset($field['submitter']) && is_string($field['submitter'])) {
                    return $field['submitter'];
                }
                if (isset($field['role']) && is_string($field['role'])) {
                    return $field['role'];
                }
            }
        }

        // Method 4: Check roles array directly
        if (isset($templateData['roles']) && is_array($templateData['roles']) && !empty($templateData['roles'])) {
            $firstRole = $templateData['roles'][0];
            if (is_string($firstRole)) {
                return $firstRole;
            } elseif (is_array($firstRole) && isset($firstRole['name'])) {
                return $firstRole['name'];
            }
        }

        // Method 5: Check submitters array
        if (isset($templateData['submitters']) && is_array($templateData['submitters']) && !empty($templateData['submitters'])) {
            $firstSubmitter = $templateData['submitters'][0];
            if (is_array($firstSubmitter) && isset($firstSubmitter['role'])) {
                return $firstSubmitter['role'];
            }
        }

        return null;
    }
}

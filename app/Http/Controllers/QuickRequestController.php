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
use App\Services\UnifiedFieldMappingService;

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
        protected DocuSealService $docuSealService,
        protected UnifiedFieldMappingService $fieldMappingService
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

        // Determine if we should filter products by provider
        $providerId = null;
        $userRole = $user->getPrimaryRole()?->slug ?? $user->roles->first()?->slug;
        $roleRestrictions = $this->getRoleRestrictions($userRole);
        
        if ($userRole === 'provider') {
            $providerId = $user->id;
        }
        // Office managers don't see products initially - they select a provider first
        
        return Inertia::render('QuickRequest/CreateNew', [
            'facilities' => $this->getFacilitiesForUser($user, $currentOrg),
            'providers' => $this->getProvidersForUser($user, $currentOrg),
            'products' => $userRole === 'office-manager' ? [] : $this->getActiveProducts($providerId),
            'woundTypes' => $this->getWoundTypes(),
            'insuranceCarriers' => $this->getInsuranceCarriers(),
            'diagnosisCodes' => $this->getDiagnosisCodes(),
            'currentUser' => $this->getCurrentUserData($user, $currentOrg),
            'providerProducts' => [],
            'roleRestrictions' => $roleRestrictions,
        ]);
    }

    /**
     * Display the order review page
     */
    public function reviewOrder(Request $request): Response|RedirectResponse
    {
        // Get form data from session or request
        $formData = $request->session()->get('quick_request_form_data', []);
        $validatedEpisodeData = $request->session()->get('validated_episode_data', []);

        // If no form data, redirect back to create
        if (empty($formData)) {
            return redirect()->route('quick-requests.create')
                ->with('error', 'No form data found. Please complete the form first.');
        }

        // Load necessary data for the review page
        $user = $this->loadUserWithRelations();
        $currentOrg = $user->organizations->first();
        
        // Determine if we should filter products by provider
        $providerId = null;
        $userRole = $user->getPrimaryRole()?->slug ?? $user->roles->first()?->slug;
        $roleRestrictions = $this->getRoleRestrictions($userRole);
        
        if ($userRole === 'provider') {
            $providerId = $user->id;
        }

        return Inertia::render('QuickRequest/Orders/Index', [
            'formData' => $formData,
            'validatedEpisodeData' => $validatedEpisodeData,
            'facilities' => $this->getFacilitiesForUser($user, $currentOrg),
            'providers' => $this->getProvidersForUser($user, $currentOrg),
            'products' => $userRole === 'office-manager' ? [] : $this->getActiveProducts($providerId),
            'currentUser' => $this->getCurrentUserData($user, $currentOrg),
            'roleRestrictions' => $roleRestrictions,
        ]);
    }

    /**
     * Submit the order after review
     */
    public function submitOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'formData' => 'required|array',
            'episodeData' => 'sometimes|array',
            'adminNote' => 'sometimes|string|max:1000'
        ]);

        DB::beginTransaction();

        try {
            $formData = $validated['formData'];
            $episodeData = $validated['episodeData'] ?? [];
            $adminNote = $validated['adminNote'] ?? '';

            // Create episode using orchestrator
            $episode = $this->orchestrator->startEpisode([
                'patient' => $this->extractPatientData($formData),
                'provider' => $this->extractProviderData($formData),
                'facility' => $this->extractFacilityData($formData),
                'clinical' => $this->extractClinicalData($formData),
                'insurance' => $this->extractInsuranceData($formData),
                'order_details' => $this->extractOrderData($formData),
                'manufacturer_id' => $this->getManufacturerIdFromProducts($formData['selected_products']),
            ]);

            // Create product request for backward compatibility
            $productRequest = $this->createProductRequest($formData, $episode);

            // Add admin note if provided
            if (!empty($adminNote)) {
                $metadata = $productRequest->metadata ?? [];
                $metadata['admin_note'] = $adminNote;
                $metadata['admin_note_added_at'] = now()->toIso8601String();
                $productRequest->metadata = $metadata;
            }

            // Save product request
            $productRequest->save();

            // Dispatch background jobs
            $this->dispatchQuickRequestJobs($episode, $productRequest, $formData);

            // Clear session data
            $request->session()->forget(['quick_request_form_data', 'validated_episode_data']);

            DB::commit();

            Log::info('Quick request order submitted successfully', [
                'episode_id' => $episode->id,
                'product_request_id' => $productRequest->id,
                'user_id' => Auth::id(),
                'has_admin_note' => !empty($adminNote)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order submitted successfully! Your order is now being processed.',
                'episode_id' => $episode->id,
                'order_id' => $productRequest->id,
                'reference_number' => $productRequest->request_number
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to submit quick request order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit order: ' . $e->getMessage()
            ], 500);
        }
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

            // Load authenticated user's profile data
            $userProfileData = $this->loadUserProfileDataForDocuSeal();

            // Merge user profile data with prefill data
            if (!empty($userProfileData)) {
                $data['prefill_data'] = array_merge($userProfileData, $data['prefill_data'] ?? []);
            }

            // Map fields if we have prefill data and manufacturer
            $mappedFields = [];
            if (!empty($data['prefill_data']) && $manufacturerId) {
                try {
                    $template = $this->getTemplateForManufacturer($manufacturerId);
                    if ($template) {
                        $mappedFields = $this->docuSealService->mapFieldsWithAI(
                            $data['prefill_data'],
                            $template
                        );

                        Log::info('DocuSeal fields mapped for JWT', [
                            'manufacturer_id' => $manufacturerId,
                            'template_id' => $template->docuseal_template_id,
                            'original_fields' => count($data['prefill_data']),
                            'mapped_fields' => count($mappedFields),
                            'sample_fields' => array_slice($mappedFields, 0, 3),
                            'user_profile_fields_included' => count($userProfileData)
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

            // Load authenticated user's profile data
            $userProfileData = $this->loadUserProfileDataForDocuSeal();

            // Merge user profile data with prefill data
            if (!empty($userProfileData)) {
                $prefillData = array_merge($userProfileData, $prefillData);
            }

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
                'embed_url' => "https://docuseal.com/s/{$response['submission_id']}",
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

            // Load authenticated user's profile data
            $userProfileData = $this->loadUserProfileDataForDocuSeal();

            // Merge user profile data with prefill data
            if (!empty($userProfileData)) {
                $data['prefill_data'] = array_merge($userProfileData, $data['prefill_data'] ?? []);
            }

            // Map fields if we have prefill data
            $mappedFields = [];
            if (!empty($data['prefill_data'])) {
                try {
                    $mappedFields = $this->docuSealService->mapFieldsWithAI(
                        $data['prefill_data'],
                        $template
                    );

                    Log::info('DocuSeal fields mapped for form', [
                        'manufacturer_id' => $manufacturerId,
                        'template_id' => $template->docuseal_template_id,
                        'original_fields' => count($data['prefill_data']),
                        'mapped_fields' => count($mappedFields),
                        'sample_fields' => array_slice($mappedFields, 0, 3),
                        'user_profile_fields_included' => count($userProfileData)
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
            // Enhanced validation with better error messages
            $data = $request->validate([
                'user_email' => 'required|email',
                'integration_email' => 'nullable|email',
                'prefill_data' => 'nullable|array',
                'manufacturerId' => 'nullable|numeric', // Accept both string and integer
                'templateId' => 'nullable|string',
                'productCode' => 'nullable|string',
                'documentType' => 'nullable|string|in:IVR,OrderForm',
                'episode_id' => 'nullable|integer',
            ]);

            Log::info('✅ Request validation PASSED', [
                'form_data_keys' => array_keys($data['prefill_data'] ?? []),
                'form_data_count' => count($data['prefill_data'] ?? []),
                'manufacturer_id' => $data['manufacturerId'] ?? 'not_provided',
                'product_code' => $data['productCode'] ?? 'not_provided',
                'document_type' => $data['documentType'] ?? 'IVR',
                'episode_id' => $data['episode_id'] ?? 'not_provided',
                'sample_form_data' => array_slice($data['prefill_data'] ?? [], 0, 10)
            ]);

            // Step 1: Validate DocuSeal Configuration
            $apiKey = config('docuseal.api_key');
            $apiUrl = config('docuseal.api_url', 'https://api.docuseal.com');

            if (!$apiKey) {
                throw new \Exception('DocuSeal API key not configured. Please check DOCUSEAL_API_KEY environment variable.');
            }

            if (strlen($apiKey) < 10) {
                throw new \Exception('DocuSeal API key appears invalid (too short). Please verify your API key.');
            }

            Log::info('✅ DocuSeal configuration validated', [
                'api_url' => $apiUrl,
                'api_key_length' => strlen($apiKey),
                'api_key_prefix' => substr($apiKey, 0, 8) . '...'
            ]);

            // Step 2: Manufacturer ID Resolution
            $manufacturerId = $data['manufacturerId'] ?? $this->getManufacturerIdFromPrefillData($data['prefill_data'] ?? []);

            // Cast to integer if it's a string or numeric
            if (is_numeric($manufacturerId)) {
                $manufacturerId = (int) $manufacturerId;
            }

            if (!$manufacturerId) {
                $availableManufacturers = \App\Models\Order\Manufacturer::pluck('name', 'id')->toArray();
                throw new \Exception('Manufacturer ID is required. Available manufacturers: ' . implode(', ', array_values($availableManufacturers)));
            }

            // Verify manufacturer exists
            $manufacturer = \App\Models\Order\Manufacturer::find($manufacturerId);
            if (!$manufacturer) {
                $availableManufacturers = \App\Models\Order\Manufacturer::pluck('name', 'id')->toArray();
                throw new \Exception("Manufacturer ID {$manufacturerId} not found. Available manufacturers: " . implode(', ', array_values($availableManufacturers)));
            }

            Log::info('✅ Manufacturer resolved', [
                'manufacturer_id' => $manufacturerId,
                'manufacturer_name' => $manufacturer->name
            ]);

            // Step 3: Episode and FHIR Integration (if available)
            $episode = null;
            if (!empty($data['episode_id'])) {
                $episode = Episode::find($data['episode_id']);
                if ($episode) {
                    Log::info('🔍 Episode found for FHIR integration', [
                        'episode_id' => $episode->id,
                        'patient_fhir_id' => $episode->patient_fhir_id,
                        'has_fhir_ids' => !empty($episode->metadata['fhir_ids'] ?? [])
                    ]);
                } else {
                    Log::warning('⚠️ Episode ID provided but not found', [
                        'episode_id' => $data['episode_id']
                    ]);
                }
            }

            // Step 3a: Load authenticated user's profile data
            // Extract facility_id from prefill_data if available
            $facilityId = $data['prefill_data']['facility_id'] ?? null;
            $userProfileData = $this->loadUserProfileDataForDocuSeal($facilityId);

            // Merge user profile data with prefill data (prefill data takes precedence)
            if (!empty($userProfileData)) {
                $data['prefill_data'] = array_merge($userProfileData, $data['prefill_data'] ?? []);

                Log::info('✅ User profile data loaded for DocuSeal', [
                    'user_id' => Auth::id(),
                    'profile_fields_added' => array_keys($userProfileData),
                    'has_provider_data' => !empty($userProfileData['provider_name']),
                    'has_facility_data' => !empty($userProfileData['facility_name']),
                    'has_organization_data' => !empty($userProfileData['organization_name'])
                ]);
            }

            // Use FHIR-DocuSeal integration service if episode is available
            if ($episode) {
                try {
                    Log::info('🎯 Attempting FHIR-DocuSeal Integration', [
                        'episode_id' => $episode->id,
                        'manufacturer_id' => $manufacturerId
                    ]);

                    $fhirIntegrationService = app(FhirDocuSealIntegrationService::class);
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
                            'error' => $result['error'] ?? 'Unknown error'
                        ]);
                        // Fall through to standard method
                    }
                } catch (\Exception $e) {
                    Log::warning('⚠️ FHIR integration threw exception, falling back to standard method', [
                        'episode_id' => $episode->id,
                        'error' => $e->getMessage()
                    ]);
                    // Fall through to standard method
                }
            }

            // Step 4: Template Resolution (CRITICAL STEP)
            $documentType = $data['documentType'] ?? 'IVR';
            Log::info('🔍 Starting template resolution', [
                'manufacturer_id' => $manufacturerId,
                'template_id' => $data['templateId'] ?? null,
                'document_type' => $documentType,
                'product_code' => $data['productCode'] ?? 'none'
            ]);

            // If templateId is provided directly, use it
            if (!empty($data['templateId'])) {
                $template = DocusealTemplate::where('docuseal_template_id', $data['templateId'])
                    ->where('is_active', true)
                    ->first();
                    
                if ($template) {
                    Log::info('✅ Template found by direct ID', [
                        'docuseal_template_id' => $data['templateId']
                    ]);
                }
            } else {
                // Fallback to manufacturer lookup
                $template = $this->getTemplateForManufacturer($manufacturerId, $documentType);
            }

            if (!$template) {
                $availableTemplates = DocusealTemplate::where('manufacturer_id', $manufacturerId)
                    ->pluck('template_name', 'id')->toArray();
                $allTemplates = DocusealTemplate::with('manufacturer')
                    ->get()
                    ->mapWithKeys(function ($t) {
                        return [$t->id => ($t->manufacturer->name ?? 'Unknown') . ' - ' . $t->template_name];
                    })->toArray();

                throw new \Exception(
                    "No DocuSeal template found for manufacturer '{$manufacturer->name}' (ID: {$manufacturerId}). " .
                    "Available templates for this manufacturer: " . (empty($availableTemplates) ? 'None' : implode(', ', $availableTemplates)) . ". " .
                    "All available templates: " . implode(', ', $allTemplates)
                );
            }

            Log::info('✅ Template FOUND', [
                'template_id' => $template->id,
                'docuseal_template_id' => $template->docuseal_template_id,
                'template_name' => $template->template_name,
                'manufacturer_name' => $template->manufacturer->name ?? 'unknown',
                'field_mappings_count' => is_array($template->field_mappings) ? count($template->field_mappings) : 0
            ]);

            // Step 5: SIMPLIFIED Field Mapping - Using UnifiedFieldMappingService exclusively
            $docuSealFields = [];
            
            if (!empty($data['prefill_data'])) {
                Log::info('🗂️ Starting unified field mapping', [
                    'input_fields_count' => count($data['prefill_data']),
                    'manufacturer' => $manufacturer->name,
                    'document_type' => $documentType
                ]);

                try {
                    // Get manufacturer configuration based on document type
                    $manufacturerConfig = $this->fieldMappingService->getManufacturerConfig($manufacturer->name, $documentType);
                    
                    if (!$manufacturerConfig) {
                        throw new \Exception("No field mapping configuration found for manufacturer: {$manufacturer->name} and document type: {$documentType}");
                    }
                    
                    Log::info('📋 Manufacturer config loaded', [
                        'manufacturer' => $manufacturer->name,
                        'has_docuseal_field_names' => isset($manufacturerConfig['docuseal_field_names']),
                        'field_count' => count($manufacturerConfig['fields'] ?? [])
                    ]);
                    
                    // Map the data using the unified service
                    $mappingResult = $this->fieldMappingService->mapEpisodeToTemplate(
                        $episode ? $episode->id : null,
                        $manufacturer->name,
                        $data['prefill_data'] ?? [],
                        $documentType
                    );
                    
                    Log::info('🔄 Field mapping completed', [
                        'canonical_fields_mapped' => count($mappingResult['data'] ?? []),
                        'validation_valid' => $mappingResult['validation']['valid'] ?? false,
                        'completeness_percentage' => $mappingResult['completeness']['percentage'] ?? 0
                    ]);
                    
                    // Convert to DocuSeal format
                    $docuSealFields = $this->fieldMappingService->convertToDocuSealFields(
                        $mappingResult['data'], 
                        $manufacturerConfig,
                        $documentType
                    );
                    
                    Log::info('✅ DocuSeal field conversion completed', [
                        'docuseal_fields_count' => count($docuSealFields),
                        'sample_fields' => array_slice($docuSealFields, 0, 5),
                        'all_field_names' => array_map(function($f) { 
                            return $f['name'] ?? 'unnamed'; 
                        }, $docuSealFields)
                    ]);
                    
                } catch (\Exception $e) {
                    Log::error('❌ Unified field mapping FAILED', [
                        'error' => $e->getMessage(),
                        'manufacturer' => $manufacturer->name
                    ]);
                    // Continue with empty fields rather than failing completely
                    $docuSealFields = [];
                }
            }

            // Step 6: Role Resolution with Better Error Handling
            Log::info('🎭 Resolving template role', [
                'template_id' => $template->docuseal_template_id
            ]);

            try {
                $templateRole = $this->getTemplateRole($template->docuseal_template_id, $apiKey);
                Log::info('✅ Template role resolved', [
                    'role' => $templateRole,
                    'template_id' => $template->docuseal_template_id
                ]);
            } catch (\Exception $e) {
                Log::warning('⚠️ Template role resolution failed, using fallback', [
                    'template_id' => $template->docuseal_template_id,
                    'error' => $e->getMessage()
                ]);
                $templateRole = 'First Party'; // Safe fallback
            }

            // Step 7: Create Submission Data with Validation
            $submissionData = [
                'template_id' => (int) $template->docuseal_template_id,
                'send_email' => false,
                'submitters' => [
                    [
                        'email' => $data['integration_email'] ?? $data['user_email'],
                        'role' => $templateRole,
                        'fields' => $docuSealFields
                    ]
                ]
            ];

            // Validate submission data
            if (empty($submissionData['submitters'][0]['email'])) {
                throw new \Exception('Submitter email is required but was empty');
            }

            if (!filter_var($submissionData['submitters'][0]['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \Exception('Submitter email is invalid: ' . $submissionData['submitters'][0]['email']);
            }

            // Log the submission data being sent
            Log::info('📤 Sending DocuSeal submission request', [
                'template_id' => $submissionData['template_id'],
                'submitter_email' => $submissionData['submitters'][0]['email'],
                'submitter_role' => $submissionData['submitters'][0]['role'],
                'field_count' => count($docuSealFields),
                'field_names' => array_map(function($f) { 
                    return $f['name'] ?? 'unnamed'; 
                }, $docuSealFields),
                'api_url' => $apiUrl . '/submissions'
            ]);

            // Step 8: Make API Call with Enhanced Error Handling
            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey,
                'Content-Type' => 'application/json',
                'User-Agent' => 'MSC-WoundCare/1.0'
            ])
            ->timeout(config('docuseal.timeout', 30))
            ->retry(2, 1000) // Retry twice with 1 second delay
            ->post("{$apiUrl}/submissions", $submissionData);

            // Enhanced error handling for API response
            if (!$response->successful()) {
                $statusCode = $response->status();
                $responseBody = $response->body();
                $responseData = $response->json() ?? [];

                Log::error('❌ DocuSeal API error', [
                    'status' => $statusCode,
                    'body' => $responseBody,
                    'response_data' => $responseData,
                    'submission_data' => $submissionData,
                    'api_url' => $apiUrl
                ]);

                // Provide specific error messages based on status code
                $errorMessage = match($statusCode) {
                    401 => 'Authentication failed - check API key and permissions. API Key: ' . substr($apiKey, 0, 8) . '...',
                    404 => "Template not found - verify template ID {$template->docuseal_template_id} exists in DocuSeal",
                    422 => 'Validation failed - ' . ($responseData['error'] ?? 'check role name and field mappings'),
                    429 => 'Rate limit exceeded - please try again in a moment',
                    500 => 'DocuSeal server error - please try again later',
                    default => $responseData['error'] ?? $responseBody ?: 'Unknown error'
                };

                throw new \Exception("DocuSeal API error ({$statusCode}): {$errorMessage}");
            }

            $submissionResponse = $response->json();

            Log::info('🔍 DocuSeal API Response Analysis', [
                'response_structure' => array_keys($submissionResponse),
                'is_array' => is_array($submissionResponse),
                'response_type' => gettype($submissionResponse),
                'has_submitters' => isset($submissionResponse['submitters']),
                'is_direct_array' => is_array($submissionResponse) && isset($submissionResponse[0])
            ]);

            // Step 9: Parse Response with Multiple Format Support
            $submitters = [];
            $slug = null;
            $submissionId = null;

            // Format 1: Response contains 'submitters' array
            if (isset($submissionResponse['submitters']) && is_array($submissionResponse['submitters'])) {
                $submitters = $submissionResponse['submitters'];
                $slug = $submitters[0]['slug'] ?? null;
                $submissionId = $submissionResponse['id'] ?? $submitters[0]['submission_id'] ?? null;
                Log::info('✅ Using submitters array format');
            }
            // Format 2: Response IS an array of submitters (direct format)
            elseif (is_array($submissionResponse) && isset($submissionResponse[0]['slug'])) {
                $submitters = $submissionResponse;
                $slug = $submitters[0]['slug'] ?? null;
                $submissionId = $submitters[0]['submission_id'] ?? $submitters[0]['id'] ?? null;
                Log::info('✅ Using direct submitters array format');
            }
            // Format 3: Single submission object
            elseif (isset($submissionResponse['slug'])) {
                $slug = $submissionResponse['slug'];
                $submissionId = $submissionResponse['id'] ?? $submissionResponse['submission_id'] ?? null;
                $submitters = [$submissionResponse];
                Log::info('✅ Using single submission format');
            }

            // Step 10: Final Validation
            if (empty($slug)) {
                Log::error('❌ No slug found in response', [
                    'full_response' => $submissionResponse,
                    'response_keys' => array_keys($submissionResponse)
                ]);
                throw new \Exception('No slug returned from DocuSeal API. Response format: ' . json_encode(array_keys($submissionResponse)));
            }

            if (empty($submitters)) {
                Log::error('❌ No submitters found in response', [
                    'full_response' => $submissionResponse
                ]);
                throw new \Exception('No submitters returned from DocuSeal API.');
            }

            // Step 11: Success Response
            Log::info('🎉 DocuSeal submission created successfully', [
                'template_id' => $template->docuseal_template_id,
                'submission_id' => $submissionId,
                'slug' => $slug,
                'submitter_email' => $submitters[0]['email'] ?? null,
                'fields_mapped' => count($docuSealFields),
                'manufacturer' => $manufacturer->name,
                'document_type' => $documentType,
                'integration_type' => 'standard'
            ]);

            return response()->json([
                'success' => true,
                'slug' => $slug,
                'submission_id' => $submissionId,
                'template_id' => $template->docuseal_template_id,
                'template_name' => $template->template_name,
                'manufacturer' => $manufacturer->name,
                'fields_mapped' => count($docuSealFields),
                'embed_url' => "https://docuseal.com/s/{$slug}",
                'integration_type' => 'standard',
                'mapping_method' => 'unified_field_mapping_service'
            ]);

        } catch (ValidationException $e) {
            Log::error('❌ Validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'validation_errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('❌ DocuSeal submission generation FAILED', [
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'error_line' => $e->getLine(),
                'error_file' => basename($e->getFile()),
                'request_data' => $request->all(),
                'user_id' => Auth::id(),
                'timestamp' => now()->toISOString()
            ]);

            // Don't expose sensitive information in production
            $errorMessage = app()->environment('production')
                ? 'Failed to generate submission slug. Please check the logs for details.'
                : 'Failed to generate submission slug: ' . $e->getMessage();

            return response()->json([
                'success' => false,
                'error' => $errorMessage,
                'debug_info' => app()->environment('production') ? null : [
                    'error_type' => get_class($e),
                    'error_line' => $e->getLine(),
                    'error_file' => basename($e->getFile())
                ]
            ], 500);
        }
    }

    /**
     * Debug endpoint for DocuSeal integration issues - Enhanced diagnostics
     */
    public function debugDocuSealIntegration(Request $request): JsonResponse
    {
        try {
            $manufacturerId = $request->get('manufacturerId', 32);
            $productCode = $request->get('productCode', 'Q4250');

            $debugInfo = $this->buildBaseDebugInfo();
            $debugInfo['manufacturer_info'] = $this->getManufacturerDebugInfo($manufacturerId);
            $debugInfo['template_info'] = $this->getTemplateDebugInfo($manufacturerId);
            $debugInfo['api_connectivity'] = $this->testApiConnectivity();

            if ($template = $this->getTemplateForManufacturer($manufacturerId)) {
                $debugInfo['role_detection_test'] = $this->testRoleDetection($template);
                $debugInfo['field_mapping_test'] = $this->testFieldMapping($template);
            }

            return response()->json([
                'success' => true,
                'debug_info' => $debugInfo,
                'recommendations' => $this->generateDebugRecommendations($debugInfo),
                'next_steps' => $this->getDebugNextSteps()
            ]);

        } catch (\Exception $e) {
            Log::error('Debug endpoint failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Debug endpoint failed: ' . $e->getMessage(),
                'basic_checks' => [
                    'api_key_configured' => !empty(config('docuseal.api_key')),
                    'manufacturer_exists' => \App\Models\Order\Manufacturer::find($request->get('manufacturerId', 32)) !== null,
                    'templates_exist' => DocusealTemplate::count() > 0
                ]
            ], 500);
        }
    }

    private function buildBaseDebugInfo(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'system_info' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'user_id' => Auth::id(),
                'environment' => app()->environment()
            ],
            'configuration' => [
                'api_key_configured' => !empty(config('docuseal.api_key')),
                'api_key_length' => strlen(config('docuseal.api_key') ?? ''),
                'api_key_prefix' => substr(config('docuseal.api_key') ?? '', 0, 8) . '...',
                'api_url' => config('docuseal.api_url', 'https://api.docuseal.com'),
                'timeout' => config('docuseal.timeout', 30)
            ]
        ];
    }

    private function getManufacturerDebugInfo(int $manufacturerId): array
    {
        $manufacturer = \App\Models\Order\Manufacturer::find($manufacturerId);

        if ($manufacturer) {
            return [
                'found' => true,
                'id' => $manufacturer->id,
                'name' => $manufacturer->name,
                'templates_count' => $manufacturer->docusealTemplates()->count(),
                'templates' => $manufacturer->docusealTemplates()->pluck('template_name', 'id')->toArray()
            ];
        }

        return [
            'found' => false,
            'searched_id' => $manufacturerId,
            'available_manufacturers' => \App\Models\Order\Manufacturer::pluck('name', 'id')->toArray()
        ];
    }

    private function getTemplateDebugInfo(int $manufacturerId): array
    {
        $template = $this->getTemplateForManufacturer($manufacturerId);

        if ($template) {
            return [
                'found' => true,
                'id' => $template->id,
                'docuseal_template_id' => $template->docuseal_template_id,
                'template_name' => $template->template_name,
                'manufacturer_name' => $template->manufacturer->name ?? 'unknown',
                'field_mappings_count' => is_array($template->field_mappings) ? count($template->field_mappings) : 0,
                'has_field_mappings' => !empty($template->field_mappings)
            ];
        }

        return [
            'found' => false,
            'searched_manufacturer_id' => $manufacturerId,
            'all_templates' => DocusealTemplate::with('manufacturer')->get()->map(function ($t) {
                return [
                    'id' => $t->id,
                    'docuseal_template_id' => $t->docuseal_template_id,
                    'name' => $t->template_name,
                    'manufacturer' => $t->manufacturer->name ?? 'Unknown',
                    'manufacturer_id' => $t->manufacturer_id
                ];
            })->toArray()
        ];
    }

    private function testApiConnectivity(): array
    {
        $apiKey = config('docuseal.api_key');
        $apiUrl = config('docuseal.api_url', 'https://api.docuseal.com');

        if (!$apiKey) {
            return [
                'status' => 'no_api_key',
                'message' => 'DocuSeal API key not configured'
            ];
        }

        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey,
                'User-Agent' => 'MSC-WoundCare/1.0'
            ])->timeout(15)->get("{$apiUrl}/templates", ['page' => 1, 'per_page' => 10]);

            if (!$response->successful()) {
                return [
                    'status' => 'failed',
                    'error' => $response->body(),
                    'status_code' => $response->status()
                ];
            }

            $data = $response->json();
            $templates = $data['data'] ?? $data;

            return [
                'status' => 'success',
                'total_templates_found' => count($templates),
                'sample_templates' => array_slice($templates, 0, 3)
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'exception',
                'error' => $e->getMessage(),
                'error_type' => get_class($e)
            ];
        }
    }

    private function testRoleDetection(DocusealTemplate $template): array
    {
        $apiKey = config('docuseal.api_key');

        try {
            $role = $this->getTemplateRole($template->docuseal_template_id, $apiKey);
            return [
                'status' => 'success',
                'detected_role' => $role,
                'template_id' => $template->docuseal_template_id
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'template_id' => $template->docuseal_template_id,
                'fallback_role' => 'First Party'
            ];
        }
    }

    private function testFieldMapping(DocusealTemplate $template): array
    {
        $sampleData = [
            'patient_first_name' => 'John',
            'patient_last_name' => 'Doe',
            'patient_dob' => '1990-01-01',
            'provider_name' => 'Dr. Smith',
            'provider_npi' => '1234567890'
        ];

        try {
            $mappedFields = $this->docuSealService->mapFieldsWithAI($sampleData, $template);
            return [
                'status' => 'success',
                'input_fields' => count($sampleData),
                'mapped_fields' => count($mappedFields),
                'mapping_success_rate' => count($sampleData) > 0 ?
                    round((count($mappedFields) / count($sampleData)) * 100, 2) . '%' : '0%'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'input_fields' => count($sampleData)
            ];
        }
    }

    private function getDebugNextSteps(): array
    {
        return [
            'If API connectivity failed: Check your DocuSeal API key and network connectivity',
            'If template not found: Verify manufacturer has associated DocuSeal templates',
            'If role detection failed: Check template configuration in DocuSeal',
            'If field mapping failed: Review template field mappings in database',
            'Test with: POST /quick-requests/docuseal/generate-submission-slug'
        ];
    }

    private function generateDebugRecommendations(array $debugInfo): array
    {
        $recommendations = [];

        if (!$debugInfo['configuration']['api_key_configured']) {
            $recommendations[] = '❌ Configure DocuSeal API key in DOCUSEAL_API_KEY environment variable';
        }

        if (!$debugInfo['manufacturer_info']['found']) {
            $recommendations[] = '❌ Manufacturer not found - check manufacturerId parameter';
        }

        if (!$debugInfo['template_info']['found']) {
            $recommendations[] = '❌ No template found for manufacturer - create DocuSeal template association';
        }

        if (isset($debugInfo['api_connectivity']['status']) && $debugInfo['api_connectivity']['status'] !== 'success') {
            $recommendations[] = '❌ DocuSeal API connectivity failed - check API key and network';
        }

        if (empty($recommendations)) {
            $recommendations[] = '✅ All checks passed - DocuSeal integration should work properly';
        }

        return $recommendations;
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
        $userRole = $user->getPrimaryRole()?->slug ?? $user->roles->first()?->slug;
        
        // Office managers should only see their assigned facility
        if ($userRole === 'office-manager') {
            $userFacilities = $user->facilities->map(function($facility) {
                return [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'address' => $facility->full_address,
                    'source' => 'user_relationship'
                ];
            });
            
            // Office managers should have exactly one facility
            return $userFacilities;
        }
        
        // Providers can select from multiple facilities
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
    private function getActiveProducts(?int $providerId = null): \Illuminate\Support\Collection
    {
        $query = Product::where('is_active', true);
        
        // If a provider ID is specified, filter to only products they're onboarded with
        if ($providerId) {
            $query->whereHas('activeProviders', function($q) use ($providerId) {
                $q->where('users.id', $providerId);
            });
        }
        
        return $query->get()
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
     * Get role-based restrictions for pricing and data visibility
     */
    private function getRoleRestrictions(string $role): array
    {
        switch ($role) {
            case 'office-manager':
                return [
                    'can_view_financials' => true,
                    'can_see_discounts' => false,
                    'can_see_msc_pricing' => false,
                    'can_see_order_totals' => false,
                    'pricing_access_level' => 'national_asp_only',
                    'commission_access_level' => 'none'
                ];
            case 'provider':
                return [
                    'can_view_financials' => true,
                    'can_see_discounts' => false,
                    'can_see_msc_pricing' => false,
                    'can_see_order_totals' => false,
                    'pricing_access_level' => 'national_asp_only',
                    'commission_access_level' => 'none'
                ];
            case 'msc-subrep':
                return [
                    'can_view_financials' => true,
                    'can_see_discounts' => true,
                    'can_see_msc_pricing' => true,
                    'can_see_order_totals' => true,
                    'pricing_access_level' => 'full',
                    'commission_access_level' => 'limited'
                ];
            case 'msc-rep':
            case 'msc-admin':
            case 'super-admin':
                return [
                    'can_view_financials' => true,
                    'can_see_discounts' => true,
                    'can_see_msc_pricing' => true,
                    'can_see_order_totals' => true,
                    'pricing_access_level' => 'full',
                    'commission_access_level' => 'full'
                ];
            default:
                return [
                    'can_view_financials' => false,
                    'can_see_discounts' => false,
                    'can_see_msc_pricing' => false,
                    'can_see_order_totals' => false,
                    'pricing_access_level' => 'none',
                    'commission_access_level' => 'none'
                ];
        }
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
                if ($product && $product->manufacturer) {
                    // Look up manufacturer by name (case-insensitive) since Product stores manufacturer as a string
                    $manufacturer = \App\Models\Order\Manufacturer::whereRaw('LOWER(name) = ?', [strtolower($product->manufacturer)])->first();
                    return $manufacturer?->id;
                }
            }
        }

        return null;
    }

    /**
     * Get template for manufacturer
     */
    private function getTemplateForManufacturer(?int $manufacturerId, string $documentType = 'IVR'): ?DocusealTemplate
    {
        if (!$manufacturerId) {
            return null;
        }

        return DocusealTemplate::getDefaultTemplateForManufacturer($manufacturerId, $documentType);
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
    private function handleFileUploads(Request $request, ProductRequest $productRequest, PatientManufacturerIVREpisode $episode): void
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
     * Show order summary page
     */
    public function showOrderSummary(Request $request, string $orderId): Response
    {
        try {
            $productRequest = ProductRequest::findOrFail($orderId);
            
            // Load relationships
            $productRequest->load(['provider', 'facility', 'products']);
            
            // Get episode if available
            $episode = null;
            if ($productRequest->patient_fhir_id) {
                $episode = Episode::where('patient_fhir_id', $productRequest->patient_fhir_id)->first();
            }
            
            // Build comprehensive order data
            $orderData = [
                'order' => $productRequest,
                'episode' => $episode,
                'patient' => [
                    'name' => $productRequest->getValue('patient_name'),
                    'fhir_id' => $productRequest->patient_fhir_id,
                    'display_id' => $productRequest->getValue('patient_display_id'),
                ],
                'provider' => [
                    'name' => $productRequest->getValue('provider_name'),
                    'npi' => $productRequest->getValue('provider_npi'),
                ],
                'facility' => [
                    'name' => $productRequest->getValue('facility_name'),
                ],
                'product' => [
                    'name' => $productRequest->product_name,
                    'code' => $productRequest->product_code,
                    'manufacturer' => $productRequest->manufacturer,
                    'size' => $productRequest->size,
                    'quantity' => $productRequest->quantity,
                ],
                'submission' => [
                    'docuseal_submission_id' => $productRequest->docuseal_submission_id,
                    'completed_at' => $productRequest->ivr_completed_at,
                    'pdf_url' => $productRequest->docuseal_submission_id 
                        ? config('services.docuseal.api_url', 'https://api.docuseal.com') . "/submissions/{$productRequest->docuseal_submission_id}/download"
                        : null,
                ],
                'status' => [
                    'current' => $productRequest->order_status,
                    'display' => ucwords(str_replace('_', ' ', $productRequest->order_status)),
                    'ivr_completed' => !empty($productRequest->ivr_completed_at),
                ],
            ];
            
            return Inertia::render('QuickRequest/OrderSummary', [
                'orderData' => $orderData,
                'submissionId' => $request->get('submission_id'),
                'episodeId' => $request->get('episode_id'),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to load order summary', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            
            return redirect()->route('quick-requests.create')
                ->with('error', 'Order not found or unable to load order summary.');
        }
    }
    
    /**
     * Get order status via API
     */
    public function getOrderStatus(string $orderId): JsonResponse
    {
        try {
            $productRequest = ProductRequest::findOrFail($orderId);
            
            return response()->json([
                'success' => true,
                'order_id' => $orderId,
                'status' => $productRequest->order_status,
                'status_display' => ucwords(str_replace('_', ' ', $productRequest->order_status)),
                'ivr_completed' => !empty($productRequest->ivr_completed_at),
                'ivr_completed_at' => $productRequest->ivr_completed_at?->format('Y-m-d H:i:s'),
                'docuseal_submission_id' => $productRequest->docuseal_submission_id,
                'pdf_url' => $productRequest->docuseal_submission_id 
                    ? config('services.docuseal.api_url', 'https://api.docuseal.com') . "/submissions/{$productRequest->docuseal_submission_id}/download"
                    : null,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get order status', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Order not found'
            ], 404);
        }
    }

    /**
     * Get the correct role name from DocuSeal template with enhanced error handling
     */
    private function getTemplateRole(string $templateId, string $apiKey): string
    {
        try {
            Log::info('🔍 Fetching template role from DocuSeal API', [
                'template_id' => $templateId,
                'api_key_length' => strlen($apiKey)
            ]);

            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey,
                'User-Agent' => 'MSC-WoundCare/1.0'
            ])
            ->timeout(15) // Shorter timeout for template fetching
            ->retry(2, 500) // Retry twice with 500ms delay
            ->get(config('services.docuseal.api_url', 'https://api.docuseal.com') . "/templates/{$templateId}");

            if ($response->successful()) {
                $templateData = $response->json();

                Log::info('✅ Template data received', [
                    'template_id' => $templateId,
                    'data_keys' => array_keys($templateData),
                    'has_submitter_roles' => isset($templateData['submitter_roles']),
                    'has_roles' => isset($templateData['roles']),
                    'has_schema' => isset($templateData['schema']),
                    'has_fields' => isset($templateData['fields'])
                ]);

                $role = $this->extractRoleFromTemplate($templateData);

                if ($role) {
                    Log::info('✅ Template role successfully extracted', [
                        'template_id' => $templateId,
                        'role' => $role
                    ]);
                    return $role;
                }

                Log::warning('⚠️ No role found in template data, checking for common patterns', [
                    'template_id' => $templateId,
                    'template_name' => $templateData['name'] ?? 'unknown'
                ]);

            } else {
                $statusCode = $response->status();
                $responseBody = $response->body();

                Log::warning('⚠️ DocuSeal template API error', [
                    'template_id' => $templateId,
                    'status' => $statusCode,
                    'response' => $responseBody
                ]);

                // If it's a 404, the template might not exist
                if ($statusCode === 404) {
                    throw new \Exception("Template {$templateId} not found in DocuSeal");
                }

                // If it's 401, there might be an API key issue
                if ($statusCode === 401) {
                    throw new \Exception("Authentication failed when fetching template {$templateId}");
                }
            }

        } catch (\Exception $e) {
            Log::warning('⚠️ Exception while fetching template role', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
                'error_type' => get_class($e)
            ]);

            // If it's a critical authentication error, re-throw it
            if (str_contains($e->getMessage(), 'Authentication failed') ||
                str_contains($e->getMessage(), 'not found')) {
                throw $e;
            }
        }

        // Enhanced fallback logic based on common DocuSeal role patterns
        $commonRoles = [
            'First Party',      // Most common in healthcare forms
            'Signer',          // Generic signer role
            'Patient',         // Healthcare specific
            'Provider',        // Healthcare specific
            'Submitter',       // Generic submitter
            'User',            // Simple user role
            'Client'           // Business role
        ];

        Log::info('🔄 Using intelligent role fallback', [
            'template_id' => $templateId,
            'trying_roles' => $commonRoles
        ]);

        // Return the most likely role for healthcare forms
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

    /**
     * Quick template count test
     */
    public function testTemplateCount(Request $request): JsonResponse
    {
        try {
            $apiKey = config('docuseal.api_key');
            $apiUrl = config('docuseal.api_url', 'https://api.docuseal.com');

            if (!$apiKey) {
                return response()->json([
                    'success' => false,
                    'error' => 'DocuSeal API key not configured'
                ], 500);
            }

            // Test just the first page to see what we're dealing with
            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey,
            ])->timeout(15)->get("{$apiUrl}/templates", [
                'page' => 1,
                'per_page' => 20
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => 'API request failed: ' . $response->body(),
                    'status_code' => $response->status()
                ], 500);
            }

            $responseData = $response->json();
            $templates = $responseData['data'] ?? $responseData;

            // Check if response has pagination info
            $pagination = $responseData['pagination'] ?? null;

            $result = [
                'success' => true,
                'first_page_count' => count($templates),
                'pagination_info' => $pagination,
                'sample_templates' => array_slice($templates, 0, 5),
                'response_structure' => [
                    'has_data_wrapper' => isset($responseData['data']),
                    'has_pagination' => isset($responseData['pagination']),
                    'response_keys' => array_keys($responseData)
                ]
            ];

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Load authenticated user's profile data for DocuSeal forms
     * @param int|null $facilityId Optional facility ID to use instead of primary facility
     */
    private function loadUserProfileDataForDocuSeal(?int $facilityId = null): array
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return [];
            }

            // Load user with relationships
            $user->load([
                'providerProfile',
                'providerCredentials',
                'facilities',
                'organizations',
                'currentOrganization'
            ]);

            $profileData = [];

            // Provider Information
            $profileData['provider_name'] = $user->first_name . ' ' . $user->last_name;
            $profileData['provider_first_name'] = $user->first_name;
            $profileData['provider_last_name'] = $user->last_name;
            $profileData['provider_email'] = $user->email;
            $profileData['provider_phone'] = $user->phone ?? '';

            // Provider NPI and credentials
            if ($user->npi_number) {
                $profileData['provider_npi'] = $user->npi_number;
            } elseif ($npiCredential = $user->providerCredentials->where('credential_type', 'npi_number')->first()) {
                $profileData['provider_npi'] = $npiCredential->credential_number;
            }

            // Provider profile details
            if ($providerProfile = $user->providerProfile) {
                $profileData['provider_specialty'] = $providerProfile->primary_specialty ?? '';
                $profileData['provider_license'] = $providerProfile->state_license_number ?? '';
                $profileData['provider_dea'] = $providerProfile->dea_number ?? '';
                $profileData['provider_practice_name'] = $providerProfile->practice_name ?? '';
                $profileData['provider_tax_id'] = $providerProfile->tax_id ?? '';
                
                // Additional provider credentials
                $profileData['practice_name'] = $providerProfile->practice_name ?? '';
                $profileData['practice_npi'] = $providerProfile->practice_npi ?? '';
                $profileData['practice_ptan'] = $providerProfile->ptan ?? '';
                $profileData['ptanNumber'] = $providerProfile->ptan ?? '';
                $profileData['medicaidNumber'] = $providerProfile->medicaid_number ?? '';

                if ($providerProfile->credentials) {
                    $profileData['provider_credentials'] = $providerProfile->credentials;
                }
                
                // Provider contact information
                $profileData['physician_name'] = $user->first_name . ' ' . $user->last_name;
                $profileData['physician_npi'] = $profileData['provider_npi'] ?? '';
                $profileData['physician_email'] = $user->email;
            }

            // Facility Information - Use specified facility or fallback to primary
            $facility = null;
            
            if ($facilityId) {
                // Use the facility specified in the request
                $facility = $user->facilities()->where('facilities.id', $facilityId)->first();
                if (!$facility) {
                    // Try to find the facility in the database (in case user has access through organization)
                    $facility = \App\Models\Fhir\Facility::find($facilityId);
                }
            }
            
            // Fallback to primary facility if no specific facility was requested or found
            if (!$facility) {
                $facility = $user->facilities()->wherePivot('is_primary', true)->first();
                if (!$facility && $user->facilities->count() > 0) {
                    $facility = $user->facilities->first();
                }
            }

            if ($facility) {
                $profileData['facility_name'] = $facility->name;
                $profileData['facility_npi'] = $facility->npi ?? '';
                $profileData['facility_phone'] = $facility->phone ?? '';
                $profileData['facility_fax'] = $facility->fax ?? '';
                $profileData['facility_address'] = $facility->address ?? '';
                $profileData['facility_address_line1'] = $facility->address ?? '';
                $profileData['facility_address_line2'] = $facility->address_line2 ?? '';
                $profileData['facility_city'] = $facility->city ?? '';
                $profileData['facility_state'] = $facility->state ?? '';
                $profileData['facility_zip'] = $facility->zip_code ?? $facility->zip ?? '';
                
                // Additional facility fields
                $profileData['facility_group_npi'] = $facility->group_npi ?? '';
                $profileData['facility_ptan'] = $facility->ptan ?? '';
                $profileData['facility_tax_id'] = $facility->tax_id ?? '';
                $profileData['facility_type'] = $facility->facility_type ?? '';
                $profileData['place_of_service'] = $facility->default_place_of_service ?? '';
                
                // Contact information
                $profileData['facility_contact_name'] = $facility->contact_name ?? '';
                $profileData['facility_contact_phone'] = $facility->contact_phone ?? '';
                $profileData['facility_contact_email'] = $facility->contact_email ?? '';
                $profileData['facility_contact_fax'] = $facility->contact_fax ?? '';
                
                // Common contact mappings
                $profileData['contact_name'] = $facility->contact_name ?? $user->first_name . ' ' . $user->last_name;
                $profileData['contact_email'] = $facility->contact_email ?? $user->email;
                $profileData['contact_phone'] = $facility->contact_phone ?? $user->phone ?? '';
                $profileData['office_contact_name'] = $profileData['contact_name'];
                $profileData['office_contact_email'] = $profileData['contact_email'];

                // Full facility address
                if ($facility->full_address) {
                    $profileData['facility_full_address'] = $facility->full_address;
                }
            }

            // Organization Information
            $currentOrg = $user->currentOrganization ?? $user->primaryOrganization();
            if (!$currentOrg && $user->organizations->count() > 0) {
                $currentOrg = $user->organizations->first();
            }

            if ($currentOrg) {
                $profileData['organization_name'] = $currentOrg->name;
                $profileData['organization_phone'] = $currentOrg->phone ?? '';
                $profileData['organization_fax'] = $currentOrg->fax ?? '';
                $profileData['organization_address'] = $currentOrg->billing_address ?? '';
                $profileData['organization_city'] = $currentOrg->billing_city ?? '';
                $profileData['organization_state'] = $currentOrg->billing_state ?? '';
                $profileData['organization_zip'] = $currentOrg->billing_zip ?? '';
                $profileData['organization_tax_id'] = $currentOrg->tax_id ?? '';

                // Sales rep information
                if ($currentOrg->salesRep) {
                    $profileData['sales_rep_name'] = $currentOrg->salesRep->first_name . ' ' . $currentOrg->salesRep->last_name;
                    $profileData['sales_rep_email'] = $currentOrg->salesRep->email;
                    $profileData['sales_rep_phone'] = $currentOrg->salesRep->phone ?? '';
                }
            }

            // Add current date/time for form
            $profileData['request_date'] = date('m/d/Y');
            $profileData['request_time'] = date('h:i A');

            // Remove any null values
            $profileData = array_filter($profileData, function($value) {
                return $value !== null && $value !== '';
            });

            Log::info('User profile data loaded for DocuSeal', [
                'user_id' => $user->id,
                'fields_loaded' => count($profileData),
                'has_provider_profile' => isset($providerProfile),
                'has_facility' => isset($facility),
                'facility_id' => $facility->id ?? null,
                'facility_name' => $facility->name ?? null,
                'requested_facility_id' => $facilityId,
                'has_organization' => isset($currentOrg)
            ]);

            return $profileData;

        } catch (\Exception $e) {
            Log::error('Failed to load user profile data for DocuSeal', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return [];
        }
    }

    /**
     * Display the provider's orders page
     *
     * @param Request $request
     * @return Response
     */
    public function myOrders(Request $request): Response
    {
        $user = Auth::user();
        
        // Build the query for orders based on user role
        $query = PatientManufacturerIVREpisode::query()
            ->with(['patient', 'manufacturer', 'product', 'facility', 'provider']);

        // Apply role-based filtering
        if ($user->role === 'Admin') {
            // Admins see all orders
        } elseif ($user->role === 'Provider') {
            // Providers see their own orders
            $query->where('provider_id', $user->id);
        } elseif ($user->role === 'OM') {
            // Office Managers see orders from their organization
            $organizationIds = $user->organizations()->pluck('organizations.id');
            $query->whereHas('provider', function($q) use ($organizationIds) {
                $q->whereHas('organizations', function($q2) use ($organizationIds) {
                    $q2->whereIn('organizations.id', $organizationIds);
                });
            });
        } else {
            // Other roles see only their created orders
            $query->where('created_by', $user->id);
        }

        // Apply filters if provided
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('episode_id', 'like', "%{$search}%")
                    ->orWhereHas('patient', function($q2) use ($search) {
                        $q2->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('product', function($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%")
                            ->orWhere('product_code', 'like', "%{$search}%");
                    });
            });
        }

        // Get orders with pagination
        $orders = $query->orderBy('created_at', 'desc')
            ->paginate(25)
            ->through(function ($episode) use ($user) {
                // Map episode status to order status
                $orderStatus = match($episode->status) {
                    'draft' => 'draft',
                    'submitted' => 'submitted',
                    'ivr_pending' => 'ivr_pending',
                    'ivr_completed' => 'ivr_approved',
                    'order_form_pending' => 'order_form_pending',
                    'order_form_completed' => 'order_form_signed',
                    'approved' => 'approved',
                    'shipped' => 'shipped',
                    'delivered' => 'delivered',
                    'cancelled' => 'cancelled',
                    default => 'submitted'
                };

                $orderData = [
                    'id' => $episode->id,
                    'order_number' => $episode->episode_id,
                    'patient_name' => $episode->patient ? 
                        "{$episode->patient->first_name} {$episode->patient->last_name}" : 
                        'Unknown Patient',
                    'product_name' => $episode->product->name ?? 'Unknown Product',
                    'product_code' => $episode->product->product_code ?? 'N/A',
                    'status' => $orderStatus,
                    'created_at' => $episode->created_at->toIso8601String(),
                    'updated_at' => $episode->updated_at->toIso8601String(),
                    'facility_name' => $episode->facility->name ?? null,
                    'tracking_number' => $episode->tracking_number ?? null,
                ];

                // Add pricing info only if user has permission
                if ($user->role !== 'OM') {
                    $orderData['asp_price'] = $episode->product->price ?? 0;
                }

                return $orderData;
            });

        return Inertia::render('QuickRequest/MyOrders', [
            'auth' => [
                'user' => [
                    'id' => $user->id,
                    'role' => $user->role,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ],
            'orders' => $orders->items(),
            'filter' => $request->status,
            'search' => $request->search,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        ]);
    }
}

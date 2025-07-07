<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\QuickRequestData;
use App\Events\QuickRequestSubmitted;
use App\Http\Requests\QuickRequest\StoreRequest;
use App\Http\Requests\QuickRequest\SubmitOrderRequest;
use App\Models\Order\ProductRequest;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\User;
use App\Models\Users\Organization\Organization;
use App\Services\CurrentOrganization;
use App\Services\QuickRequest\QuickRequestCalculationService;
use App\Services\QuickRequest\QuickRequestFileService;
use App\Services\QuickRequest\QuickRequestOrchestrator;
use App\Services\QuickRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Refactored QuickRequestController - Focused and Clean
 *
 * This replaces the previous 3,198-line monolithic controller.
 * Responsibilities are now properly separated into services and DTOs.
 */
class QuickRequestController extends Controller
{
    public function __construct(
        protected QuickRequestService $quickRequestService,
        protected QuickRequestOrchestrator $orchestrator,
        protected QuickRequestCalculationService $calculationService,
        protected QuickRequestFileService $fileService,
        protected CurrentOrganization $currentOrganization,
        // protected DocusealService $docusealService, // Removed - replaced with new PDF/manufacturer system
    ) {}

    /**
     * Display the quick request creation form
     */
    public function create(): Response
    {
        $user = Auth::user();
        $formData = $this->quickRequestService->getFormData($user);

        return Inertia::render('QuickRequest/CreateNew', $formData);
    }

    /**
     * Display the order review page
     */
    public function reviewOrder(Request $request): Response|RedirectResponse
    {
        $formData = $request->session()->get('quick_request_form_data', []);
        $episodeData = $request->session()->get('validated_episode_data', []);

        if (empty($formData)) {
            Log::warning('ReviewOrder - No form data found in session', [
                'user_id' => Auth::id(),
            ]);

            return redirect()->route('quick-requests.create-new')
                ->with('error', 'No form data found. Please complete the form first.');
        }

        // Convert to DTO and calculate totals
        $quickRequestData = QuickRequestData::fromFormData($formData);
        $calculation = $this->calculationService->calculateOrderTotal($quickRequestData->productSelection);

        return Inertia::render('QuickRequest/Orders/Index', [
            'formData' => $formData,
            'validatedEpisodeData' => $episodeData,
            'calculation' => $calculation,
            'orderSummary' => $this->buildOrderSummary($formData, $calculation),
        ]);
    }

    /**
     * Submit the order after review - FIXED to calculate total_order_value
     */
    public function submitOrder(SubmitOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            // Convert to structured DTO
            $quickRequestData = QuickRequestData::fromFormData($validated['formData']);
            $quickRequestData = new QuickRequestData(
                patient: $quickRequestData->patient,
                provider: $quickRequestData->provider,
                facility: $quickRequestData->facility,
                clinical: $quickRequestData->clinical,
                insurance: $quickRequestData->insurance,
                productSelection: $quickRequestData->productSelection,
                orderPreferences: $quickRequestData->orderPreferences,
                manufacturerFields: $quickRequestData->manufacturerFields,
                pdfDocumentId: $quickRequestData->pdfDocumentId,
                attestations: $quickRequestData->attestations,
                adminNote: $validated['adminNote'] ?? null,
            );

            // Calculate order totals - THIS FIXES THE $0 ISSUE
            $calculation = $this->calculationService->calculateOrderTotal($quickRequestData->productSelection);

            // Check if we have an existing draft episode to finalize
            $episode = null;
            if (isset($validated['formData']['episode_id'])) {
                $draftEpisode = PatientManufacturerIVREpisode::find($validated['formData']['episode_id']);
                if ($draftEpisode && $draftEpisode->status === PatientManufacturerIVREpisode::STATUS_DRAFT && $draftEpisode->created_by === Auth::id()) {
                    // Finalize the existing draft episode
                    $finalData = [
                        'patient' => $this->extractPatientData($validated['formData']),
                        'provider' => $this->extractProviderData($validated['formData']),
                        'facility' => $this->extractFacilityData($validated['formData']),
                        'organization' => $this->extractOrganizationData(),
                        'clinical' => $this->extractClinicalData($validated['formData']),
                        'insurance' => $this->extractInsuranceData($validated['formData']),
                        'order_details' => $this->extractOrderData($validated['formData']),
                    ];
                    $episode = $this->orchestrator->finalizeDraftEpisode($draftEpisode, $finalData);
                }
            }

            // If no draft episode was finalized, create a new episode
            if (!$episode) {
                $episode = $this->orchestrator->startEpisode([
                    'patient' => $this->extractPatientData($validated['formData']),
                    'provider' => $this->extractProviderData($validated['formData']),
                    'facility' => $this->extractFacilityData($validated['formData']),
                    'organization' => $this->extractOrganizationData(),
                    'clinical' => $this->extractClinicalData($validated['formData']),
                    'insurance' => $this->extractInsuranceData($validated['formData']),
                    'order_details' => $this->extractOrderData($validated['formData']),
                    'manufacturer_id' => $this->getManufacturerIdFromProducts($validated['formData']['selected_products']),
                ]);
            }

            // Create product request with CALCULATED TOTAL
            $productRequest = $this->createProductRequest($quickRequestData, $episode, $calculation);

            // Handle file uploads
            $documentMetadata = $this->fileService->handleFileUploads($request, $productRequest, $episode);
            $this->fileService->updateProductRequestWithDocuments($productRequest, $documentMetadata);

            // Clear session data
            $request->session()->forget(['quick_request_form_data', 'validated_episode_data']);

            DB::commit();

            // Dispatch event for background processing
            event(new QuickRequestSubmitted($episode, $productRequest, $quickRequestData, $calculation));

            Log::info('Quick request order submitted successfully', [
                'episode_id' => $episode->id,
                'product_request_id' => $productRequest->id,
                'total_amount' => $calculation['total'], // Now properly calculated
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order submitted successfully! Your order is now being processed.',
                'episode_id' => $episode->id,
                'order_id' => $productRequest->id,
                'reference_number' => $productRequest->request_number,
                'total_amount' => $calculation['total'], // Return the calculated total
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to submit quick request order', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Legacy store method - redirect to new flow
     */
    public function store(StoreRequest $request): RedirectResponse
    {
        return redirect()->route('quick-requests.create-new')
            ->with('info', 'Please use the new Quick Request form.');
    }

    /**
     * Create ProductRequest with proper total calculation
     */
    private function createProductRequest(
        QuickRequestData $data,
        PatientManufacturerIVREpisode $episode,
        array $calculation
    ): ProductRequest {
        // Ensure expected_service_date has a valid value - default to tomorrow if empty
        $expectedServiceDate = $data->orderPreferences->expectedServiceDate;
        if (empty($expectedServiceDate)) {
            $expectedServiceDate = date('Y-m-d', strtotime('+1 day')); // Default to tomorrow
        }

        // Handle empty place_of_service - convert empty string to null
        $placeOfService = $data->orderPreferences->placeOfService;
        if (empty($placeOfService)) {
            $placeOfService = null;
        }

        $productRequest = ProductRequest::create([
            'request_number' => $this->generateRequestNumber(),
            'provider_id' => $data->provider->id,
            'facility_id' => $data->facility->id,
            'patient_fhir_id' => $episode->patient_fhir_id,
            'patient_display_id' => $episode->patient_display_id,
            'payer_name_submitted' => $data->insurance->primaryName,
            'payer_id' => $data->insurance->primaryMemberId,
            'expected_service_date' => $expectedServiceDate,
            'wound_type' => $data->clinical->woundType,
            'place_of_service' => $placeOfService,
            'order_status' => ProductRequest::ORDER_STATUS_PENDING,
            'submitted_at' => now(),
            'total_order_value' => $calculation['total'], // FIX: Set the calculated total
            'pdf_document_id' => $data->pdfDocumentId, // Add PDF document ID
            'clinical_summary' => array_merge($data->toArray(), [
                'admin_note' => $data->adminNote,
                'admin_note_added_at' => $data->adminNote ? now()->toIso8601String() : null,
            ]),
        ]);

        // Note: Previously saved DocuSeal template ID - removed as part of new manufacturer system
        // $manufacturerId = $data->manufacturer->id ?? $this->getManufacturerIdFromProducts($data->orderPreferences->products ?? []);
        // Template association now handled by the new manufacturer response system

        // Create product relationships with proper pricing
        $this->createProductRelationships($productRequest, $calculation['item_breakdown']);

        return $productRequest;
    }

    /**
     * Create product relationships with calculated pricing
     */
    private function createProductRelationships(ProductRequest $productRequest, array $itemBreakdown): void
    {
        foreach ($itemBreakdown as $item) {
            DB::table('product_request_products')->insert([
                'product_request_id' => $productRequest->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'size' => $item['size'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['total_price'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Log::info('Product relationships created with proper pricing', [
            'product_request_id' => $productRequest->id,
            'items_count' => count($itemBreakdown),
            'total_amount' => array_sum(array_column($itemBreakdown, 'total_price')),
        ]);
    }

    /**
     * Build order summary for display
     */
    private function buildOrderSummary(array $formData, array $calculation): array
    {
        return [
            'patient_name' => ($formData['patient_first_name'] ?? '') . ' ' . ($formData['patient_last_name'] ?? ''),
            'total_amount' => $calculation['total'],
            'product_count' => count($formData['selected_products'] ?? []),
            'estimated_delivery' => now()->addBusinessDays(3)->format('M j, Y'),
        ];
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

    // Legacy extraction methods - will be moved to services eventually
    private function extractPatientData(array $formData): array
    {
        $displayId = $formData['patient_display_id'] ?? $this->generateRandomPatientDisplayId($formData);

        return [
            'id' => $formData['patient_id'] ?? uniqid('patient-'),
            'first_name' => $formData['patient_first_name'] ?? '',
            'last_name' => $formData['patient_last_name'] ?? '',
            'dob' => $formData['patient_dob'] ?? '',
            'gender' => $formData['patient_gender'] ?? 'unknown',
            'display_id' => $displayId,
            'phone' => $formData['patient_phone'] ?? '',
            'email' => $formData['patient_email'] ?? null,
        ];
    }

    private function extractProviderData(array $formData): array
    {
        $providerId = $formData['provider_id'];

        // Load provider with full profile information
        $provider = User::with(['providerProfile', 'providerCredentials'])->find($providerId);

        if (!$provider) {
            throw new \Exception("Provider not found with ID: {$providerId}");
        }

        $providerData = [
            'id' => $provider->id,
            'name' => $provider->first_name . ' ' . $provider->last_name,
            'first_name' => $provider->first_name,
            'last_name' => $provider->last_name,
            'email' => $provider->email,
            'phone' => $provider->phone ?? '',
            'npi' => $provider->npi_number ?? '',
        ];

        // Add provider profile data if available
        if ($provider->providerProfile) {
            $profile = $provider->providerProfile;
            $providerData = array_merge($providerData, [
                'specialty' => $profile->primary_specialty ?? '',
                'credentials' => $profile->credentials ?? '',
                'license_number' => $profile->state_license_number ?? '',
                'license_state' => $profile->license_state ?? '',
                'dea_number' => $profile->dea_number ?? '',
                'ptan' => $profile->ptan ?? '',
                'tax_id' => $profile->tax_id ?? '',
                'practice_name' => $profile->practice_name ?? '',
            ]);
        }

        // Add credential data if available
        if ($provider->providerCredentials) {
            foreach ($provider->providerCredentials as $credential) {
                if ($credential->credential_type === 'npi_number' && empty($providerData['npi'])) {
                    $providerData['npi'] = $credential->credential_number;
                }
            }
        }

        return $providerData;
    }

    private function extractFacilityData(array $formData): array
    {
        $facilityId = $formData['facility_id'] ?? null;

        // If facility_id is provided, try to load from database
        if ($facilityId) {
            $facility = \App\Models\Fhir\Facility::find($facilityId);

            if ($facility) {
                return [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'address' => $facility->address ?? '',
                    'address_line1' => $facility->address ?? '',
                    'address_line2' => $facility->address_line2 ?? '',
                    'city' => $facility->city ?? '',
                    'state' => $facility->state ?? '',
                    'zip' => $facility->zip ?? '',
                    'phone' => $facility->phone ?? '',
                    'npi' => $facility->npi ?? '',
                ];
            }
        }

        // Fallback: use form data or defaults
        return [
            'id' => $facilityId ?? 'default',
            'name' => $formData['facility_name'] ?? 'Default Facility',
            'address' => $formData['facility_address'] ?? '',
            'address_line1' => $formData['facility_address'] ?? '',
            'address_line2' => $formData['facility_address_line2'] ?? '',
            'city' => $formData['facility_city'] ?? '',
            'state' => $formData['facility_state'] ?? '',
            'zip' => $formData['facility_zip'] ?? '',
            'phone' => $formData['facility_phone'] ?? '',
            'npi' => $formData['facility_npi'] ?? '',
        ];
    }

    private function extractClinicalData(array $formData): array
    {
        return [
            'wound_type' => $formData['wound_type'] ?? '',
            'wound_location' => $formData['wound_location'] ?? '',
            'wound_length' => $formData['wound_size_length'] ?? 0,
            'wound_width' => $formData['wound_size_width'] ?? 0,
            'wound_depth' => $formData['wound_size_depth'] ?? null,
            'wound_size_length' => $formData['wound_size_length'] ?? 0,
            'wound_size_width' => $formData['wound_size_width'] ?? 0,
            'wound_size_depth' => $formData['wound_size_depth'] ?? null,

            // Add diagnosis codes
            'primary_diagnosis_code' => $formData['primary_diagnosis_code'] ?? '',
            'secondary_diagnosis_code' => $formData['secondary_diagnosis_code'] ?? '',

            // Add CPT codes
            'application_cpt_codes' => $formData['application_cpt_codes'] ?? [],

            // Add post-op status fields
            'global_period_status' => $formData['global_period_status'] ?? false,
            'global_period_cpt' => $formData['global_period_cpt'] ?? '',
            'global_period_surgery_date' => $formData['global_period_surgery_date'] ?? '',

            // Add other clinical fields
            'wound_duration_days' => $formData['wound_duration_days'] ?? '',
            'wound_duration_weeks' => $formData['wound_duration_weeks'] ?? '',
            'wound_duration_months' => $formData['wound_duration_months'] ?? '',
            'wound_duration_years' => $formData['wound_duration_years'] ?? '',
            'previous_treatments' => $formData['previous_treatments'] ?? '',
            'failed_conservative_treatment' => $formData['failed_conservative_treatment'] ?? false,
            'information_accurate' => $formData['information_accurate'] ?? false,
            'medical_necessity_established' => $formData['medical_necessity_established'] ?? false,
            'maintain_documentation' => $formData['maintain_documentation'] ?? false,
        ];
    }

    private function extractInsuranceData(array $formData): array
    {
        return [
            'primary_name' => $formData['primary_insurance_name'] ?? '',
            'primary_member_id' => $formData['primary_member_id'] ?? '',
            'primary_payer_phone' => $formData['primary_payer_phone'] ?? '',
            'primary_plan_type' => $formData['primary_plan_type'] ?? '',
            'has_secondary_insurance' => $formData['has_secondary_insurance'] ?? false,
            'secondary_insurance_name' => $formData['secondary_insurance_name'] ?? '',
            'secondary_member_id' => $formData['secondary_member_id'] ?? '',
            'secondary_payer_phone' => $formData['secondary_payer_phone'] ?? '',
            'secondary_plan_type' => $formData['secondary_plan_type'] ?? '',
        ];
    }

    private function extractOrderData(array $formData): array
    {
        $expectedServiceDate = $formData['expected_service_date'] ?? '';
        if (empty($expectedServiceDate)) {
            $expectedServiceDate = date('Y-m-d', strtotime('+1 day')); // Default to tomorrow
        }

        // Enhance products with code information
        $products = $formData['selected_products'] ?? [];
        foreach ($products as &$productData) {
            if (isset($productData['product_id']) && !isset($productData['product']['code'])) {
                $product = \App\Models\Order\Product::find($productData['product_id']);
                if ($product) {
                    $productData['product'] = [
                        'id' => $product->id,
                        'code' => $product->code,
                        'name' => $product->name,
                        'manufacturer' => $product->manufacturer,
                        'manufacturer_id' => $product->manufacturer_id,
                    ];
                }
            }
        }

        return [
            'products' => $products,
            'expected_service_date' => $expectedServiceDate,
            'shipping_speed' => $formData['shipping_speed'] ?? 'standard',
            'place_of_service' => $formData['place_of_service'] ?? '',
        ];
    }

    private function getManufacturerIdFromProducts(array $selectedProducts): ?int
    {
        if (empty($selectedProducts)) {
            \Log::warning('getManufacturerIdFromProducts: No selected products provided');
            return null;
        }

        $firstProduct = $selectedProducts[0];
        $productId = $firstProduct['product_id'] ?? null;

        if (!$productId) {
            \Log::warning('getManufacturerIdFromProducts: No product_id in first selected product', [
                'selected_products' => $selectedProducts
            ]);
            return null;
        }

        // Try to get manufacturer_id from the product record in database
        $product = \App\Models\Order\Product::with('manufacturer')->find($productId);

        if (!$product) {
            \Log::warning('getManufacturerIdFromProducts: Product not found in database', [
                'product_id' => $productId
            ]);
            return null;
        }

        if ($product->manufacturer_id) {
            \Log::info('getManufacturerIdFromProducts: Found manufacturer_id from database', [
                'product_id' => $productId,
                'manufacturer_id' => $product->manufacturer_id,
                'manufacturer_name' => $product->manufacturer?->name ?? 'unknown'
            ]);
            return $product->manufacturer_id;
        }

        // Fallback: try to get manufacturer_id from the product data in the request
        if (isset($firstProduct['product']['manufacturer_id'])) {
            \Log::info('getManufacturerIdFromProducts: Found manufacturer_id from request data', [
                'product_id' => $productId,
                'manufacturer_id' => $firstProduct['product']['manufacturer_id']
            ]);
            return $firstProduct['product']['manufacturer_id'];
        }

        // Fallback: look up manufacturer by name
        if (isset($firstProduct['product']['manufacturer'])) {
            $manufacturerName = $firstProduct['product']['manufacturer'];
            $manufacturer = \App\Models\Order\Manufacturer::where('name', $manufacturerName)->first();

            if ($manufacturer) {
                \Log::info('getManufacturerIdFromProducts: Found manufacturer by name', [
                    'product_id' => $productId,
                    'manufacturer_name' => $manufacturerName,
                    'manufacturer_id' => $manufacturer->id
                ]);
                return $manufacturer->id;
            }
        }

        \Log::error('getManufacturerIdFromProducts: Unable to determine manufacturer', [
            'product_id' => $productId,
            'product_manufacturer_id' => $product->manufacturer_id,
            'product_manufacturer_name' => $product->manufacturer?->name ?? 'null',
            'request_manufacturer_id' => $firstProduct['product']['manufacturer_id'] ?? 'not_set',
            'request_manufacturer_name' => $firstProduct['product']['manufacturer'] ?? 'not_set'
        ]);

        return null;
    }

    private function generateRandomPatientDisplayId(array $formData): string
    {
        if (!empty($formData['patient_first_name']) && !empty($formData['patient_last_name'])) {
            $first = substr(strtoupper($formData['patient_first_name']), 0, 2);
            $last = substr(strtoupper($formData['patient_last_name']), 0, 2);
            $random = str_pad((string)rand(0, 999), 3, '0', STR_PAD_LEFT);
            return $first . $last . $random;
        }

        return 'PAT' . str_pad((string)rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function extractOrganizationData(array $formData = []): array
    {
        $organization = null;

        // Priority 1: Get organization from formData if available
        if (!empty($formData['organization_id'])) {
            $organization = \App\Models\Users\Organization\Organization::find($formData['organization_id']);
        }

        // Priority 2: Try to get organization from authenticated user
        if (!$organization && Auth::check()) {
            $user = Auth::user();

            // First try current_organization_id
            if ($user->current_organization_id) {
                $organization = \App\Models\Users\Organization\Organization::find($user->current_organization_id);
                if ($organization) {
                    Log::info('Found organization from current_organization_id', [
                        'user_id' => $user->id,
                        'current_organization_id' => $user->current_organization_id,
                        'organization_name' => $organization->name
                    ]);
                }
            }

            // If still no organization, try the relationships
            if (!$organization) {
                // Try currentOrganization relationship
                if (!$user->relationLoaded('currentOrganization')) {
                    $user->load('currentOrganization');
                }
                $organization = $user->currentOrganization;

                // Try primaryOrganization
                if (!$organization && method_exists($user, 'primaryOrganization')) {
                    $organization = $user->primaryOrganization();
                }

                // Try first active organization
                if (!$organization && method_exists($user, 'activeOrganizations')) {
                    $organization = $user->activeOrganizations()->first();
                }
            }
        }

        // Priority 3: Fallback to CurrentOrganization service
        if (!$organization) {
            $organization = $this->currentOrganization->getOrganization();
        }

        // Priority 4: If still no organization, try to find the first active organization (for providers with single org)
        if (!$organization && Auth::check()) {
            $user = Auth::user();
            // For providers, they might have access to facilities which belong to organizations
            if ($user->hasRole('provider') && $user->facilities()->exists()) {
                $facility = $user->facilities()->with('organization')->first();
                if ($facility && $facility->organization) {
                    $organization = $facility->organization;
                }
            }
        }

        if (!$organization) {
            Log::error('No organization found for draft episode creation', [
                'user_id' => Auth::id(),
                'form_data_has_org_id' => !empty($formData['organization_id']),
                'form_data_org_id' => $formData['organization_id'] ?? null,
                'current_org_service_has_org' => $this->currentOrganization->hasOrganization(),
                'current_org_service_id' => $this->currentOrganization->getId(),
            ]);

            throw new \Exception("No current organization found. Please ensure you are associated with an organization to create requests.");
        }

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'tax_id' => $organization->tax_id ?? '',
            'type' => $organization->type ?? '',
            'address' => $organization->address ?? '',
            'city' => $organization->city ?? '',
            'state' => $organization->region ?? '',
            'zip_code' => $organization->postal_code ?? '',
            'phone' => $organization->phone ?? '',
            'email' => $organization->email ?? '',
            'status' => $organization->status ?? '',
        ];
    }

    /**
     * Create IVR submission - Now using new manufacturer response system
     */
    public function createIvrSubmission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'episode_id' => 'required|string|exists:patient_manufacturer_ivr_episodes,id',
            'manufacturer_name' => 'required|string',
        ]);

        try {
            // Load the episode
            $episode = PatientManufacturerIVREpisode::findOrFail($validated['episode_id']);

            // Check if user has permission to access this episode
            if ((int)$episode->created_by !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to episode'
                ], 403);
            }

            // Note: This method previously used DocuSeal but has been updated
            // to work with the new manufacturer response system.
            // For now, we'll return a success response indicating the episode is ready
            // for the new IVR/manufacturer workflow

            Log::info('IVR submission request received - using new manufacturer system', [
                'episode_id' => $episode->id,
                'user_id' => Auth::id(),
                'manufacturer' => $validated['manufacturer_name']
            ]);

            return response()->json([
                'success' => true,
                'episode_id' => $episode->id,
                'status' => 'ready_for_manufacturer_workflow',
                'manufacturer' => $validated['manufacturer_name'],
                'message' => 'Episode ready for new manufacturer response workflow'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to prepare IVR for manufacturer workflow', [
                'episode_id' => $validated['episode_id'],
                'manufacturer' => $validated['manufacturer_name'],
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to prepare IVR: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a draft episode for IVR generation before final submission
     */
    public function createDraftEpisode(Request $request): JsonResponse
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'User authentication required to create draft episode',
                'requires_auth' => true
            ], 401);
        }

        $validated = $request->validate([
            'form_data' => 'required|array',
            'manufacturer_name' => 'required|string'
        ]);

        try {
            // Extract data from form
            $formData = $validated['form_data'];

            // Get manufacturer ID
            $manufacturerId = $this->getManufacturerIdFromProducts($formData['selected_products'] ?? []);
            if (!$manufacturerId) {
                throw new \Exception('Unable to determine manufacturer from selected products');
            }

            // Prepare data for draft episode creation
            $episodeData = [
                'patient' => $this->extractPatientData($formData),
                'provider' => $this->extractProviderData($formData),
                'facility' => $this->extractFacilityData($formData),
                'organization' => $this->extractOrganizationData($formData),
                'clinical' => $this->extractClinicalData($formData),
                'insurance' => $this->extractInsuranceData($formData),
                'order_details' => $this->extractOrderData($formData),
                'manufacturer_id' => $manufacturerId,
                'request_type' => $formData['request_type'] ?? 'new_request', // Add request_type
            ];

            // Create draft episode (no FHIR resources created yet)
            $episode = $this->orchestrator->createDraftEpisode($episodeData);

            Log::info('Draft episode created for IVR generation', [
                'episode_id' => $episode->id,
                'user_id' => Auth::id(),
                'manufacturer' => $validated['manufacturer_name']
            ]);

            return response()->json([
                'success' => true,
                'episode_id' => $episode->id,
                'status' => $episode->status,
                'message' => 'Draft episode created successfully for IVR generation'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create draft episode for IVR', [
                'user_id' => Auth::id(),
                'manufacturer' => $validated['manufacturer_name'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create draft episode: ' . $e->getMessage()
            ], 500);
        }
    }
}

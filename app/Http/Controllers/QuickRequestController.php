<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\QuickRequestData;
use App\Events\QuickRequestSubmitted;
use App\Http\Requests\QuickRequest\StoreRequest;
use App\Http\Requests\QuickRequest\SubmitOrderRequest;
use App\Models\Order\ProductRequest;
use App\Models\PatientManufacturerIVREpisode;
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
                docusealSubmissionId: $quickRequestData->docusealSubmissionId,
                attestations: $quickRequestData->attestations,
                adminNote: $validated['adminNote'] ?? null,
            );

            // Calculate order totals - THIS FIXES THE $0 ISSUE
            $calculation = $this->calculationService->calculateOrderTotal($quickRequestData->productSelection);

            // Create episode
            $episode = $this->orchestrator->startEpisode([
                'patient' => $this->extractPatientData($validated['formData']),
                'provider' => $this->extractProviderData($validated['formData']),
                'facility' => $this->extractFacilityData($validated['formData']),
                'clinical' => $this->extractClinicalData($validated['formData']),
                'insurance' => $this->extractInsuranceData($validated['formData']),
                'order_details' => $this->extractOrderData($validated['formData']),
                'manufacturer_id' => $this->getManufacturerIdFromProducts($validated['formData']['selected_products']),
            ]);

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
        $productRequest = ProductRequest::create([
            'request_number' => $this->generateRequestNumber(),
            'provider_id' => $data->provider->id,
            'facility_id' => $data->facility->id,
            'patient_fhir_id' => $episode->patient_fhir_id,
            'patient_display_id' => $episode->patient_display_id,
            'payer_name_submitted' => $data->insurance->primaryName,
            'payer_id' => $data->insurance->primaryMemberId,
            'expected_service_date' => $data->orderPreferences->expectedServiceDate,
            'wound_type' => $data->clinical->woundType,
            'place_of_service' => $data->orderPreferences->placeOfService,
            'order_status' => ProductRequest::ORDER_STATUS_PENDING,
            'submitted_at' => now(),
            'total_order_value' => $calculation['total'], // FIX: Set the calculated total
            'clinical_summary' => array_merge($data->toArray(), [
                'admin_note' => $data->adminNote,
                'admin_note_added_at' => $data->adminNote ? now()->toIso8601String() : null,
            ]),
        ]);

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
        return [
            'id' => $formData['provider_id'],
            'name' => $formData['provider_name'] ?? '',
            'npi' => $formData['provider_npi'] ?? null,
        ];
    }

    private function extractFacilityData(array $formData): array
    {
        return [
            'id' => $formData['facility_id'],
            'name' => $formData['facility_name'] ?? '',
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
        ];
    }

    private function extractInsuranceData(array $formData): array
    {
        return [
            'primary_name' => $formData['primary_insurance_name'] ?? '',
            'primary_member_id' => $formData['primary_member_id'] ?? '',
        ];
    }

    private function extractOrderData(array $formData): array
    {
        return [
            'products' => $formData['selected_products'] ?? [],
            'expected_service_date' => $formData['expected_service_date'] ?? '',
            'shipping_speed' => $formData['shipping_speed'] ?? 'standard',
        ];
    }

    private function getManufacturerIdFromProducts(array $selectedProducts): ?int
    {
        if (empty($selectedProducts)) {
            return null;
        }

        $product = \App\Models\Order\Product::find($selectedProducts[0]['product_id']);
        return $product?->manufacturer_id;
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
} 
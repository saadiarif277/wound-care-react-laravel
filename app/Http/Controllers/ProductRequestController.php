<?php

namespace App\Http\Controllers;

use App\Models\ProductRequest;
use App\Models\Product;
use App\Models\User;
use App\Models\Facility;
use App\Services\PatientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use App\Services\ProductRecommendationEngine\MSCProductRecommendationService;

class ProductRequestController extends Controller
{
    protected PatientService $patientService;

    public function __construct(PatientService $patientService)
    {
        $this->patientService = $patientService;
    }

    public function index()
    {
        $user = Auth::user();

        // Get status counts for the current user
        $statusCounts = ProductRequest::where('provider_id', $user->id)
            ->selectRaw('order_status, count(*) as count')
            ->groupBy('order_status')
            ->pluck('count', 'order_status')
            ->toArray();

        // Define all possible statuses with labels
        $allStatuses = [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'processing' => 'Processing',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];

        // Build status options with counts
        $statusOptions = [];
        foreach ($allStatuses as $value => $label) {
            $statusOptions[] = [
                'value' => $value,
                'label' => $label,
                'count' => $statusCounts[$value] ?? 0
            ];
        }

        // Get facilities for filter dropdown
        $facilities = Facility::where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('ProductRequest/Index', [
            'filters' => request()->all(['search', 'status', 'facility', 'date_from', 'date_to']),
            'statusOptions' => $statusOptions,
            'facilities' => $facilities,
            'requests' => ProductRequest::query()
                ->where('provider_id', Auth::id())
                ->when(request('search'), function ($query, $search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('request_number', 'like', "%{$search}%")
                            ->orWhere('patient_fhir_id', 'like', "%{$search}%")
                            ->orWhere('patient_display_id', 'like', "%{$search}%");
                    });
                })
                ->when(request('status'), function ($query, $status) {
                    $query->where('order_status', $status);
                })
                ->when(request('facility'), function ($query, $facility) {
                    $query->where('facility_id', $facility);
                })
                ->when(request('date_from'), function ($query, $date) {
                    $query->whereDate('created_at', '>=', $date);
                })
                ->when(request('date_to'), function ($query, $date) {
                    $query->whereDate('created_at', '<=', $date);
                })
                ->with(['products', 'facility'])
                ->orderBy('created_at', 'desc')
                ->paginate(10)
                ->withQueryString()
                ->through(fn ($request) => [
                    'id' => $request->id,
                    'request_number' => $request->request_number,
                    'patient_display' => $request->formatPatientDisplay(),
                    'patient_fhir_id' => $request->patient_fhir_id,
                    'order_status' => $request->order_status,
                    'step' => $request->step,
                    'step_description' => $request->step_description,
                    'facility_name' => $request->facility->name ?? 'No facility',
                    'created_at' => $request->created_at->format('M j, Y'),
                    'total_products' => $request->products->count(),
                    'total_amount' => $request->total_order_value,
                    'mac_validation_status' => $request->mac_validation_status,
                    'eligibility_status' => $request->eligibility_status,
                    'pre_auth_required' => $request->isPriorAuthRequired(),
                    'submitted_at' => $request->submitted_at?->format('M j, Y'),
                    'approved_at' => $request->approved_at?->format('M j, Y'),
                    'wound_type' => $request->wound_type,
                    'expected_service_date' => $request->expected_service_date?->format('M j, Y'),
                ])
        ]);
    }

    public function create()
    {
        $user = Auth::user();

        return Inertia::render('ProductRequest/Create', [
            'woundTypes' => ProductRequest::getWoundTypeDescriptions(),
            'facilities' => Facility::where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'address'])
                ->toArray(),
            'userFacilityId' => $user->facility_id ?? null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // Step 1: Patient Information
            'patient_api_input' => 'required|array',
            'patient_api_input.first_name' => 'required|string|max:255',
            'patient_api_input.last_name' => 'required|string|max:255',
            'patient_api_input.dob' => 'required|date',
            'patient_api_input.gender' => 'required|string|in:male,female,other',
            'patient_api_input.member_id' => 'required|string|max:255',

            'facility_id' => 'required|exists:facilities,id',
            'expected_service_date' => 'required|date|after:today',
            'payer_name' => 'required|string|max:255',
            'payer_id' => 'nullable|string|max:255',
            'wound_type' => 'required|string|in:DFU,VLU,PU,TW,AU,OTHER',

            // Step 2: Clinical Assessment (will be processed separately)
            'clinical_data' => 'nullable|array',

            // Step 3: Product Selection
            'selected_products' => 'nullable|array',
            'selected_products.*.product_id' => 'required_with:selected_products|exists:msc_products,id',
            'selected_products.*.quantity' => 'required_with:selected_products|integer|min:1',
            'selected_products.*.size' => 'nullable|string',
        ]);

        // Step 1: Process PHI and create patient record using PatientService
        $patientIdentifiers = $this->patientService->createPatientRecord(
            $validated['patient_api_input'],
            $validated['facility_id']
        );

        // Create product request with non-PHI data only
        $productRequest = ProductRequest::create([
            'request_number' => 'PR-' . strtoupper(uniqid()),
            'provider_id' => Auth::id(),
            'patient_fhir_id' => $patientIdentifiers['patient_fhir_id'],
            'patient_display_id' => $patientIdentifiers['patient_display_id'], // "JoSm001" format
            'facility_id' => $validated['facility_id'],
            'payer_name_submitted' => $validated['payer_name'],
            'payer_id' => $validated['payer_id'],
            'expected_service_date' => $validated['expected_service_date'],
            'wound_type' => $validated['wound_type'],
            'order_status' => 'draft',
            'step' => 1,
        ]);

        // Attach products if provided
        if (!empty($validated['selected_products'])) {
            foreach ($validated['selected_products'] as $productData) {
                $product = Product::find($productData['product_id']);
                $productRequest->products()->attach($productData['product_id'], [
                    'quantity' => $productData['quantity'],
                    'size' => $productData['size'] ?? null,
                    'unit_price' => $product->price ?? 0,
                    'total_price' => ($product->price ?? 0) * $productData['quantity'],
                ]);
            }
        }

        return Redirect::route('product-requests.show', $productRequest)
            ->with('success', 'Product request created successfully.');
    }

    public function show(ProductRequest $productRequest)
    {
        // Ensure the provider can only view their own requests
        if ($productRequest->provider_id !== Auth::id()) {
            abort(403);
        }

        return Inertia::render('ProductRequest/Show', [
            'request' => [
                'id' => $productRequest->id,
                'request_number' => $productRequest->request_number,
                'order_status' => $productRequest->order_status,
                'step' => $productRequest->step,
                'step_description' => $productRequest->step_description,
                'wound_type' => $productRequest->wound_type,
                'expected_service_date' => $productRequest->expected_service_date,
                'patient_display' => $productRequest->formatPatientDisplay(),
                'patient_fhir_id' => $productRequest->patient_fhir_id,
                'facility' => $productRequest->facility ? [
                    'id' => $productRequest->facility->id,
                    'name' => $productRequest->facility->name,
                ] : null,
                'payer_name' => $productRequest->payer_name_submitted,
                'clinical_summary' => $productRequest->clinical_summary,
                'mac_validation_results' => $productRequest->mac_validation_results,
                'mac_validation_status' => $productRequest->mac_validation_status,
                'eligibility_results' => $productRequest->eligibility_results,
                'eligibility_status' => $productRequest->eligibility_status,
                'pre_auth_required' => $productRequest->isPriorAuthRequired(),
                'clinical_opportunities' => $productRequest->clinical_opportunities,
                'total_amount' => $productRequest->total_order_value,
                'created_at' => $productRequest->created_at->format('M j, Y'),
                'products' => $productRequest->products->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'q_code' => $product->q_code,
                    'image_url' => $product->image_url,
                    'quantity' => $product->pivot->quantity,
                    'size' => $product->pivot->size,
                    'unit_price' => $product->pivot->unit_price,
                    'total_price' => $product->pivot->total_price,
                ]),
                'provider' => [
                    'name' => $productRequest->provider->first_name . ' ' . $productRequest->provider->last_name,
                    'facility' => $productRequest->facility->name ?? null,
                ]
            ]
        ]);
    }

    /**
     * API endpoint for searching patients by display ID.
     */
    public function searchPatients(Request $request)
    {
        $validated = $request->validate([
            'search' => 'required|string|min:1|max:10',
            'facility_id' => 'required|exists:facilities,id',
        ]);

        $patients = $this->patientService->searchPatientsByDisplayId(
            $validated['search'],
            $validated['facility_id']
        );

        return response()->json([
            'patients' => $patients
        ]);
    }

    public function updateStep(Request $request, ProductRequest $productRequest)
    {
        // Ensure the provider can only update their own requests
        if ($productRequest->provider_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'step' => 'required|integer|min:1|max:6',
            'step_data' => 'required|array',
        ]);

        // Process step-specific data
        $updateData = ['step' => $validated['step']];

        switch ($validated['step']) {
            case 2: // Clinical Assessment
                $azureFhirId = $this->storeClinicalDataInAzure($validated['step_data'], $productRequest);
                $updateData['azure_order_checklist_fhir_id'] = $azureFhirId;
                $updateData['clinical_summary'] = $this->generateClinicalSummary($validated['step_data']);
                break;

            case 3: // Product Selection
                $this->updateProductSelection($productRequest, $validated['step_data']);
                break;

            case 4: // Validation & Eligibility (Automated)
                $this->runAutomatedValidation($productRequest);
                break;

            case 5: // Clinical Opportunities
                $this->scanClinicalOpportunities($productRequest);
                break;
        }

        $productRequest->update($updateData);

        return response()->json([
            'success' => true,
            'step' => $productRequest->step,
            'step_description' => $productRequest->step_description,
        ]);
    }

    public function runMacValidation(ProductRequest $productRequest)
    {
        // Ensure the provider can only validate their own requests
        if ($productRequest->provider_id !== Auth::id()) {
            abort(403);
        }

        // MAC Validation Engine - implement actual validation logic
        $macResults = $this->performMacValidation($productRequest);

        $productRequest->update([
            'mac_validation_results' => $macResults,
            'mac_validation_status' => $macResults['overall_status'],
        ]);

        return response()->json([
            'success' => true,
            'results' => $macResults,
        ]);
    }

    public function runEligibilityCheck(ProductRequest $productRequest)
    {
        // Ensure the provider can only check eligibility for their own requests
        if ($productRequest->provider_id !== Auth::id()) {
            abort(403);
        }

        // Eligibility Engine - implement actual eligibility checking
        $eligibilityResults = $this->performEligibilityCheck($productRequest);

        $productRequest->update([
            'eligibility_results' => $eligibilityResults,
            'eligibility_status' => $eligibilityResults['status'],
            'pre_auth_required_determination' => $eligibilityResults['prior_auth_required'] ? 'required' : 'not_required',
        ]);

        return response()->json([
            'success' => true,
            'results' => $eligibilityResults,
        ]);
    }

    public function submit(ProductRequest $productRequest)
    {
        // Ensure the provider can only submit their own requests
        if ($productRequest->provider_id !== Auth::id()) {
            abort(403);
        }

        if (!$productRequest->canBeSubmitted()) {
            return response()->json([
                'success' => false,
                'message' => 'Request cannot be submitted at this time.',
            ], 400);
        }

        $productRequest->update([
            'order_status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product request submitted successfully.',
        ]);
    }

    /**
     * Get AI-powered product recommendations for a product request
     */
    public function getRecommendations(ProductRequest $productRequest)
    {
        // Ensure the provider can only get recommendations for their own requests
        if ($productRequest->provider_id !== Auth::id()) {
            abort(403);
        }

        try {
            $user = Auth::user();

            $recommendationService = app(MSCProductRecommendationService::class);
            $recommendations = $recommendationService->getRecommendations($productRequest, [
                'use_ai' => true,
                'max_recommendations' => 6,
                'user_role' => $user->userRole?->name ?? 'provider',
                'show_msc_pricing' => $user->canSeeDiscounts() // Only show MSC pricing if user can see discounts
            ]);

            return response()->json($recommendations);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate recommendations',
                'message' => 'Our recommendation engine is temporarily unavailable. Please try again later.'
            ], 500);
        }
    }

    // Private helper methods for MSC-MVP implementation

    private function storeClinicalDataInAzure(array $clinicalData, ProductRequest $productRequest): string
    {
        // TODO: Implement actual clinical data storage in Azure HDS as FHIR resources
        // This should create Condition, Observation, and DocumentReference resources
        return 'DocumentReference/' . uniqid();
    }

    private function generateClinicalSummary(array $clinicalData): array
    {
        // Generate non-PHI summary for UI display
        return [
            'wound_characteristics' => $clinicalData['wound_details'] ?? [],
            'conservative_care_provided' => $clinicalData['conservative_care'] ?? [],
            'assessment_complete' => true,
        ];
    }

    private function updateProductSelection(ProductRequest $productRequest, array $productData): void
    {
        // Update product selection and pricing
        $productRequest->products()->detach();

        if (!empty($productData['selected_products'])) {
            foreach ($productData['selected_products'] as $item) {
                $product = Product::find($item['product_id']);
                $productRequest->products()->attach($item['product_id'], [
                    'quantity' => $item['quantity'],
                    'size' => $item['size'] ?? null,
                    'unit_price' => $product->price ?? 0,
                    'total_price' => ($product->price ?? 0) * $item['quantity'],
                ]);
            }
        }
    }

    private function runAutomatedValidation(ProductRequest $productRequest): void
    {
        // Run both MAC validation and eligibility check automatically
        $macResults = $this->performMacValidation($productRequest);
        $eligibilityResults = $this->performEligibilityCheck($productRequest);

        $productRequest->update([
            'mac_validation_results' => $macResults,
            'mac_validation_status' => $macResults['overall_status'],
            'eligibility_results' => $eligibilityResults,
            'eligibility_status' => $eligibilityResults['status'],
            'pre_auth_required_determination' => $eligibilityResults['prior_auth_required'] ? 'required' : 'not_required',
        ]);
    }

    private function scanClinicalOpportunities(ProductRequest $productRequest): void
    {
        // Clinical Opportunity Engine - scan for additional billable services
        $opportunities = $this->performClinicalOpportunityScanning($productRequest);

        $productRequest->update([
            'clinical_opportunities' => $opportunities,
        ]);
    }

    private function performMacValidation(ProductRequest $productRequest): array
    {
        // TODO: Implement actual MAC validation engine
        return [
            'overall_status' => 'passed',
            'validations' => [
                [
                    'rule' => 'Wound documentation',
                    'status' => 'passed',
                    'message' => 'Wound measurements and assessment documented'
                ],
                [
                    'rule' => 'Conservative care',
                    'status' => 'passed',
                    'message' => 'Conservative care adequately documented'
                ],
                [
                    'rule' => 'Medical necessity',
                    'status' => 'passed',
                    'message' => 'Medical necessity clearly documented'
                ]
            ]
        ];
    }

    private function performEligibilityCheck(ProductRequest $productRequest): array
    {
        // TODO: Implement actual eligibility checking with payer APIs
        return [
            'status' => 'eligible',
            'coverage_details' => [
                'plan_type' => 'Medicare Part B',
                'active' => true,
                'effective_date' => '2024-01-01'
            ],
            'prior_auth_required' => false,
            'copay_amount' => 20.00,
        ];
    }

    private function performClinicalOpportunityScanning(ProductRequest $productRequest): array
    {
        // TODO: Implement Clinical Opportunity Engine (COE)
        return [
            'opportunities' => [
                [
                    'service_type' => 'Debridement',
                    'cpt_code' => '11042',
                    'clinical_rationale' => 'Wound shows signs of necrotic tissue',
                    'estimated_revenue' => 125.00,
                    'recommended' => true,
                ]
            ],
            'total_potential_revenue' => 125.00,
        ];
    }
}

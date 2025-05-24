<?php

namespace App\Http\Controllers;

use App\Models\ProductRequest;
use App\Models\Patient;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class ProductRequestController extends Controller
{
    public function index()
    {
        return Inertia::render('ProductRequest/Index', [
            'filters' => request()->all(['search', 'status']),
            'requests' => ProductRequest::query()
                ->where('provider_id', Auth::id())
                ->when(request('search'), function ($query, $search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('request_number', 'like', "%{$search}%")
                            ->orWhereHas('patient', function ($query) use ($search) {
                                $query->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('member_id', 'like', "%{$search}%");
                            });
                    });
                })
                ->when(request('status'), function ($query, $status) {
                    $query->where('status', $status);
                })
                ->with(['patient', 'products'])
                ->orderBy('created_at', 'desc')
                ->paginate(10)
                ->withQueryString()
                ->through(fn ($request) => [
                    'id' => $request->id,
                    'request_number' => $request->request_number,
                    'patient' => [
                        'name' => $request->patient->first_name . ' ' . $request->patient->last_name,
                        'member_id' => $request->patient->member_id,
                        'dob' => $request->patient->date_of_birth,
                    ],
                    'status' => $request->status,
                    'created_at' => $request->created_at->format('M j, Y'),
                    'total_products' => $request->products->count(),
                    'total_amount' => $request->total_amount,
                ])
        ]);
    }

    public function create()
    {
        return Inertia::render('ProductRequest/Create', [
            'patients' => Patient::query()
                ->where('organization_id', Auth::user()->organization_id)
                ->select('id', 'first_name', 'last_name', 'member_id', 'date_of_birth', 'insurance_plan')
                ->get(),
            'woundTypes' => [
                'DFU' => 'Diabetic Foot Ulcer',
                'VLU' => 'Venous Leg Ulcer',
                'ALU' => 'Arterial Leg Ulcer',
                'PI' => 'Pressure Injury',
                'SWI' => 'Surgical Wound Infection',
                'OTHER' => 'Other'
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'expected_service_date' => 'required|date',
            'wound_type' => 'required|string',
            'clinical_data' => 'required|array',
            'selected_products' => 'required|array',
            'selected_products.*.product_id' => 'required|exists:products,id',
            'selected_products.*.quantity' => 'required|integer|min:1',
            'selected_products.*.size' => 'nullable|string',
        ]);

        $productRequest = ProductRequest::create([
            'request_number' => 'PR-' . strtoupper(uniqid()),
            'provider_id' => Auth::id(),
            'patient_id' => $validated['patient_id'],
            'expected_service_date' => $validated['expected_service_date'],
            'wound_type' => $validated['wound_type'],
            'clinical_data' => $validated['clinical_data'],
            'status' => 'draft',
            'step' => 1,
        ]);

        // Attach products
        foreach ($validated['selected_products'] as $productData) {
            $product = Product::find($productData['product_id']);
            $productRequest->products()->attach($productData['product_id'], [
                'quantity' => $productData['quantity'],
                'size' => $productData['size'] ?? null,
                'unit_price' => $product->price ?? 0,
                'total_price' => ($product->price ?? 0) * $productData['quantity'],
            ]);
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
                'status' => $productRequest->status,
                'step' => $productRequest->step,
                'wound_type' => $productRequest->wound_type,
                'expected_service_date' => $productRequest->expected_service_date,
                'clinical_data' => $productRequest->clinical_data,
                'mac_validation_results' => $productRequest->mac_validation_results,
                'eligibility_results' => $productRequest->eligibility_results,
                'clinical_opportunities' => $productRequest->clinical_opportunities,
                'total_amount' => $productRequest->total_amount,
                'created_at' => $productRequest->created_at->format('M j, Y'),
                'patient' => [
                    'id' => $productRequest->patient->id,
                    'name' => $productRequest->patient->first_name . ' ' . $productRequest->patient->last_name,
                    'member_id' => $productRequest->patient->member_id,
                    'dob' => $productRequest->patient->date_of_birth,
                    'insurance_plan' => $productRequest->patient->insurance_plan,
                ],
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
                    'organization' => $productRequest->provider->organization->name ?? null,
                ]
            ]
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

        $productRequest->update([
            'step' => $validated['step'],
            'clinical_data' => array_merge($productRequest->clinical_data ?? [], $validated['step_data']),
        ]);

        return response()->json([
            'success' => true,
            'step' => $productRequest->step,
        ]);
    }

    public function runMacValidation(ProductRequest $productRequest)
    {
        // Ensure the provider can only validate their own requests
        if ($productRequest->provider_id !== Auth::id()) {
            abort(403);
        }

        // Mock MAC validation logic - replace with actual implementation
        $macResults = [
            'overall_status' => 'warning',
            'validations' => [
                [
                    'rule' => 'Wound documentation',
                    'status' => 'passed',
                    'message' => 'Wound measurements and assessment documented'
                ],
                [
                    'rule' => 'Conservative care',
                    'status' => 'warning',
                    'message' => 'Consider adding more conservative care documentation'
                ],
                [
                    'rule' => 'Medical necessity',
                    'status' => 'passed',
                    'message' => 'Medical necessity clearly documented'
                ]
            ]
        ];

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

        // Mock eligibility check - replace with actual implementation
        $eligibilityResults = [
            'status' => 'eligible',
            'coverage_percentage' => 80,
            'copay_amount' => 25.00,
            'deductible_remaining' => 150.00,
            'prior_auth_required' => false,
            'effective_date' => now()->toDateString(),
            'expiration_date' => now()->addYear()->toDateString(),
        ];

        $productRequest->update([
            'eligibility_results' => $eligibilityResults,
            'eligibility_status' => $eligibilityResults['status'],
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

        $productRequest->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return Redirect::route('product-requests.show', $productRequest)
            ->with('success', 'Product request submitted successfully.');
    }
}

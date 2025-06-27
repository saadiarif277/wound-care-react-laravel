<?php

namespace App\Http\Controllers;

use App\Models\Order\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-requests')->only(['index', 'show']);
    }

    /**
     * Display a listing of requests
     */
    public function index(Request $request): Response
    {
        $query = ProductRequest::query()
            ->with(['facility', 'provider', 'acquiringRep']);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhere('patient_display_id', 'like', "%{$search}%")
                  ->orWhereHas('facility', function ($facilityQuery) use ($search) {
                      $facilityQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('order_status', $request->get('status'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->get('date_to'));
        }

        // Paginate results
        $requests = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(function ($request) {
                return [
                    'id' => $request->id,
                    'request_number' => $request->request_number,
                    'patient_display' => $request->formatPatientDisplay(),
                    'wound_type' => $request->wound_type,
                    'status' => $request->order_status,
                    'status_color' => $request->status_color,
                    'created_at' => $request->created_at->toISOString(),
                    'expected_service_date' => $request->expected_service_date ? $request->expected_service_date->toDateString() : null,
                    'facility_name' => $request->facility->name ?? 'Unknown Facility',
                    'provider_name' => $request->provider ? $request->provider->first_name . ' ' . $request->provider->last_name : 'Unknown Provider',
                    'total_amount' => $request->total_order_value,
                ];
            });

        return Inertia::render('Requests/Index', [
            'requests' => $requests,
            'filters' => $request->only(['search', 'status', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Display the specified request
     */
    public function show(int $id): Response
    {
        $request = ProductRequest::with(['facility', 'provider', 'acquiringRep', 'products'])
            ->findOrFail($id);

        return Inertia::render('Requests/Show', [
            'request' => [
                'id' => $request->id,
                'request_number' => $request->request_number,
                'patient_display' => $request->formatPatientDisplay(),
                'wound_type' => $request->wound_type,
                'status' => $request->order_status,
                'status_color' => $request->status_color,
                'step' => $request->step,
                'step_description' => $request->step_description,
                'created_at' => $request->created_at->toISOString(),
                'expected_service_date' => $request->expected_service_date ? $request->expected_service_date->toDateString() : null,
                'facility_name' => $request->facility->name ?? 'Unknown Facility',
                'provider_name' => $request->provider ? $request->provider->first_name . ' ' . $request->provider->last_name : 'Unknown Provider',
                'acquiring_rep_name' => $request->acquiringRep ? $request->acquiringRep->name : null,
                'total_amount' => $request->total_order_value,
                'clinical_summary' => $request->clinical_summary,
                'mac_validation_results' => $request->mac_validation_results,
                'mac_validation_status' => $request->mac_validation_status,
                'eligibility_results' => $request->eligibility_results,
                'eligibility_status' => $request->eligibility_status,
                'clinical_opportunities' => $request->clinical_opportunities,
                'products' => $request->products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name ?? 'Unknown Product',
                        'quantity' => $product->pivot->quantity,
                        'size' => $product->pivot->size,
                        'unit_price' => $product->pivot->unit_price,
                        'total_price' => $product->pivot->total_price,
                    ];
                }),
            ],
        ]);
    }
}

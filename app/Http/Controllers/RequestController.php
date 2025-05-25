<?php

namespace App\Http\Controllers;

use App\Models\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RequestController extends Controller
{
    /**
     * Display a listing of requests
     */
    public function index(Request $request): Response
    {
        $query = ProductRequest::query()
            ->with(['facility', 'user'])
            ->where('user_id', Auth::id());

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhereHas('patient', function ($patientQuery) use ($search) {
                      $patientQuery->where('first_name', 'like', "%{$search}%")
                                   ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
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
                    'patient_name' => $request->patient_first_name . ' ' . $request->patient_last_name,
                    'wound_type' => $request->wound_type,
                    'status' => $request->status,
                    'created_at' => $request->created_at->toISOString(),
                    'expected_service_date' => $request->expected_service_date,
                    'facility_name' => $request->facility->name ?? 'Unknown Facility',
                    'total_amount' => $request->total_amount,
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
        $request = ProductRequest::with(['facility', 'user', 'orderItems.product'])
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return Inertia::render('Requests/Show', [
            'request' => [
                'id' => $request->id,
                'request_number' => $request->request_number,
                'patient_name' => $request->patient_first_name . ' ' . $request->patient_last_name,
                'wound_type' => $request->wound_type,
                'status' => $request->status,
                'created_at' => $request->created_at->toISOString(),
                'expected_service_date' => $request->expected_service_date,
                'facility_name' => $request->facility->name ?? 'Unknown Facility',
                'total_amount' => $request->total_amount,
                'clinical_data' => $request->clinical_data,
                'mac_validation_results' => $request->mac_validation_results,
                'eligibility_results' => $request->eligibility_results,
                'clinical_opportunities' => $request->clinical_opportunities,
                'order_items' => $request->orderItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_name' => $item->product->name ?? 'Unknown Product',
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->total_price,
                    ];
                }),
            ],
        ]);
    }
}

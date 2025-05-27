<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-orders')->only(['index', 'show']);
        $this->middleware('permission:create-orders')->only(['create', 'store']);
        $this->middleware('permission:edit-orders')->only(['edit', 'update']);
        $this->middleware('permission:delete-orders')->only('destroy');
        $this->middleware('permission:approve-orders')->only('approval');
        $this->middleware('permission:manage-orders')->only('manage');
    }

    public function index(): Response
    {

        return Inertia::render('Order/Index');
    }
     public function create(): Response
    {

        return Inertia::render('Order/CreateOrder');
    }

    public function approval(): Response

    {

        return Inertia::render('Order/OrderApproval');
    }

    /**
     * Display the order management interface for administrators
     */
    public function manage(Request $request): Response
    {
        $query = Order::query()
            ->with(['organization', 'facility', 'salesRep', 'items']);

        // Apply filters based on request parameters
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('patient_fhir_id', 'like', "%{$search}%")
                  ->orWhereHas('facility', function ($facilityQuery) use ($search) {
                      $facilityQuery->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('organization', function ($orgQuery) use ($search) {
                      $orgQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('date_from')) {
            $query->where('order_date', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('order_date', '<=', $request->get('date_to'));
        }

        if ($request->filled('sales_rep_id')) {
            $query->where('sales_rep_id', $request->get('sales_rep_id'));
        }

        // Default to showing orders pending admin approval
        if (!$request->filled('status')) {
            $query->where('status', 'pending_admin_approval');
        }

        // Paginate results
        $orders = $query->orderBy('order_date', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'patient_fhir_id' => $order->patient_fhir_id,
                    'status' => $order->status,
                    'order_date' => $order->order_date->toISOString(),
                    'total_amount' => $order->total_amount,
                    'organization_name' => $order->organization->name ?? 'Unknown Organization',
                    'facility_name' => $order->facility->name ?? 'Unknown Facility',
                    'sales_rep_name' => $order->salesRep->name ?? 'Unknown Sales Rep',
                    'items_count' => $order->items->count(),
                ];
            });

        // Get available statuses for filter dropdown
        $statuses = [
            'pending_admin_approval' => 'Pending Admin Approval',
            'pending_documents' => 'Pending Documents',
            'approved' => 'Approved',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
        ];

        return Inertia::render('Order/Manage', [
            'orders' => $orders,
            'filters' => $request->only(['search', 'status', 'date_from', 'date_to', 'sales_rep_id']),
            'statuses' => $statuses,
        ]);
    }
}

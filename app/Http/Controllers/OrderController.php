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
        $this->middleware('permission:view-analytics')->only('analytics');
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

    /**
     * Display order analytics dashboard for administrators
     */
    public function analytics(Request $request): Response
    {
        $user = $request->user();

        // Get date range (default to last 30 days)
        $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());

        // Base query for orders in date range
        $baseQuery = Order::whereBetween('order_date', [$dateFrom, $dateTo]);

        // Order statistics
        $totalOrders = (clone $baseQuery)->count();
        $approvedOrders = (clone $baseQuery)->where('status', 'approved')->count();
        $pendingOrders = (clone $baseQuery)->where('status', 'pending_admin_approval')->count();
        $rejectedOrders = (clone $baseQuery)->where('status', 'rejected')->count();

        // Financial data (only if user has permission)
        $financialData = null;
        if ($user->hasPermission('view-financials')) {
            $totalRevenue = (clone $baseQuery)->where('status', 'approved')->sum('total_amount');
            $averageOrderValue = $totalOrders > 0 ? ($totalRevenue / max($totalOrders, 1)) : 0;

            $financialData = [
                'total_revenue' => $totalRevenue,
                'average_order_value' => $averageOrderValue,
            ];
        }

        // Status breakdown
        $statusBreakdown = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // Daily order trends (last 7 days)
        $dailyTrends = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $count = Order::whereDate('order_date', $date)->count();
            $dailyTrends[] = [
                'date' => $date,
                'orders' => $count,
            ];
        }

        // Top facilities by order count
        $topFacilities = (clone $baseQuery)
            ->with('facility:id,name')
            ->selectRaw('facility_id, COUNT(*) as order_count')
            ->groupBy('facility_id')
            ->orderByDesc('order_count')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'facility_name' => $item->facility->name ?? 'Unknown',
                    'order_count' => $item->order_count,
                ];
            });

        $analyticsData = [
            'summary' => [
                'total_orders' => $totalOrders,
                'approved_orders' => $approvedOrders,
                'pending_orders' => $pendingOrders,
                'rejected_orders' => $rejectedOrders,
                'approval_rate' => $totalOrders > 0 ? round(($approvedOrders / $totalOrders) * 100, 1) : 0,
            ],
            'financial' => $financialData,
            'status_breakdown' => $statusBreakdown,
            'daily_trends' => $dailyTrends,
            'top_facilities' => $topFacilities,
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
        ];

        return Inertia::render('Order/Analytics', [
            'analyticsData' => $analyticsData,
            'roleRestrictions' => [
                'can_view_financials' => $user->hasPermission('view-financials'),
                'can_see_discounts' => $user->hasPermission('view-discounts'),
                'can_see_msc_pricing' => $user->hasPermission('view-msc-pricing'),
                'can_see_order_totals' => $user->hasPermission('view-order-totals'),
            ],
        ]);
    }
}

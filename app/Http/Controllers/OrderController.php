<?php

namespace App\Http\Controllers;

use App\Models\Order\Order;
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
        $this->middleware('permission:manage-orders')->only(['center', 'manage']);
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

    /**
     * Display the centralized order center for administrators - combining request reviews and order management
     */
    public function center(Request $request): Response
    {
        $user = $request->user();

        // ===== REQUEST REVIEWS TAB DATA =====
        // Build base query for product requests (similar to ProductRequestReviewController)
        $query = \App\Models\Order\ProductRequest::with([
            'provider:id,first_name,last_name,email,npi_number',
            'facility:id,name,city,state',
            'products'
        ])
        ->whereIn('order_status', ['submitted', 'processing', 'pending_approval', 'approved', 'rejected'])
        ->orderBy('submitted_at', 'desc');

        // Apply filters for request reviews
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('request_number', 'like', "%{$search}%")
                  ->orWhere('patient_display_id', 'like', "%{$search}%")
                  ->orWhere('patient_fhir_id', 'like', "%{$search}%")
                  ->orWhereHas('provider', function ($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('order_status', $request->input('status'));
        }

        if ($request->filled('facility')) {
            $query->where('facility_id', $request->input('facility'));
        }

        if ($request->filled('days_pending')) {
            $days = (int) $request->input('days_pending');
            $query->whereDate('submitted_at', '<=', now()->subDays($days));
        }

        if ($request->filled('priority')) {
            $priority = $request->input('priority');
            if ($priority === 'high') {
                $query->where(function ($q) {
                    $q->where('pre_auth_required_determination', 'required')
                      ->orWhere('mac_validation_status', 'failed')
                      ->orWhereRaw('DATEDIFF(NOW(), submitted_at) > 3');
                });
            } elseif ($priority === 'urgent') {
                $query->whereRaw('DATEDIFF(NOW(), submitted_at) > 7');
            }
        }

        // Get paginated results
        $requests = $query->paginate(20)->withQueryString();

        // Transform for frontend (matching ProductRequestReviewController logic)
        $requests->getCollection()->transform(function ($request) {
            return [
                'id' => $request->id,
                'request_number' => $request->request_number,
                'patient_display' => $request->formatPatientDisplay(),
                'patient_fhir_id' => $request->patient_fhir_id,
                'order_status' => $request->order_status,
                'wound_type' => $request->wound_type,
                'expected_service_date' => $request->expected_service_date?->format('Y-m-d'),
                'submitted_at' => $request->submitted_at?->format('Y-m-d H:i:s'),
                'total_order_value' => $request->total_order_value,
                'facility' => [
                    'id' => $request->facility->id,
                    'name' => $request->facility->name,
                    'city' => $request->facility->city ?? '',
                    'state' => $request->facility->state ?? '',
                ],
                'provider' => [
                    'id' => $request->provider->id,
                    'name' => $request->provider->first_name . ' ' . $request->provider->last_name,
                    'email' => $request->provider->email,
                    'npi_number' => $request->provider->npi_number,
                ],
                'payer_name' => $request->payer_name_submitted,
                'mac_validation_status' => $request->mac_validation_status,
                'eligibility_status' => $request->eligibility_status,
                'pre_auth_required' => $request->isPriorAuthRequired(),
                'clinical_summary' => $request->clinical_summary,
                'products_count' => $request->products->count(),
                'days_since_submission' => $request->submitted_at ? $request->submitted_at->diffInDays(now()) : 0,
                'priority_score' => $this->calculatePriorityScore($request),
            ];
        });

        // Get status counts for request reviews
        $statusCounts = \App\Models\Order\ProductRequest::whereIn('order_status', ['submitted', 'processing', 'pending_approval', 'approved', 'rejected'])
            ->selectRaw('order_status, count(*) as count')
            ->groupBy('order_status')
            ->pluck('count', 'order_status')
            ->toArray();

        // Map status names for consistency
        $mappedStatusCounts = [
            'submitted' => $statusCounts['submitted'] ?? 0,
            'processing' => ($statusCounts['processing'] ?? 0) + ($statusCounts['pending_approval'] ?? 0),
            'approved' => $statusCounts['approved'] ?? 0,
            'rejected' => $statusCounts['rejected'] ?? 0,
        ];

        // Get facilities for filter dropdown
        $facilities = \App\Models\Fhir\Facility::where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        
        return Inertia::render('Order/OrderCenter', [
            'requests' => $requests,
            'filters' => $request->only(['search', 'status', 'priority', 'facility', 'days_pending']),
            'statusCounts' => $mappedStatusCounts,
            'facilities' => $facilities,
            'roleRestrictions' => [
                'can_approve_requests' => $user->hasAnyPermission(['approve-product-requests', 'manage-product-requests']),
                'can_reject_requests' => $user->hasAnyPermission(['reject-product-requests', 'manage-product-requests']),
                'can_view_clinical_data' => $user->hasPermission('view-clinical-data'),
                'can_view_financials' => $user->hasAnyPermission(['view-financials', 'manage-financials']),
                'can_see_discounts' => $user->hasPermission('view-discounts'),
                'can_see_msc_pricing' => $user->hasPermission('view-msc-pricing'),
                'can_see_order_totals' => $user->hasPermission('view-order-totals'),
                'access_level' => $this->getUserAccessLevel($user),
            ]
        ]);
    }

    /**
     * Calculate priority score for requests (from ProductRequestReviewController)
     */
    private function calculatePriorityScore(\App\Models\Order\ProductRequest $request): int
    {
        $score = 0;

        // Days since submission
        $daysSince = $request->submitted_at ? $request->submitted_at->diffInDays(now()) : 0;
        if ($daysSince > 7) $score += 40;
        elseif ($daysSince > 3) $score += 20;
        elseif ($daysSince > 1) $score += 10;

        // Prior auth required
        if ($request->isPriorAuthRequired()) $score += 30;

        // MAC validation status
        if ($request->mac_validation_status === 'failed') $score += 25;

        // High value orders
        if ($request->total_order_value > 1000) $score += 15;

        return min($score, 100);
    }

    /**
     * Get user access level (from ProductRequestReviewController)
     */
    private function getUserAccessLevel(\App\Models\User $user): string
    {
        if ($user->hasRole('msc-admin')) return 'full';
        if ($user->hasRole('clinical-reviewer')) return 'clinical';
        if ($user->hasRole('admin')) return 'admin';
        return 'limited';
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

        return Inertia::render('Order/OrderCenter', [
            'requests' => [
                'data' => [],
                'links' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 15,
                'total' => 0
            ],
            'filters' => $request->only(['search', 'status', 'date_from', 'date_to', 'sales_rep_id']),
            'statusCounts' => [
                'submitted' => 0,
                'processing' => 0,
                'approved' => 0,
                'rejected' => 0
            ],
            'facilities' => [],
            'roleRestrictions' => [
                'can_view_financials' => $request->user()->hasPermission('view-financials'),
                'can_see_discounts' => $request->user()->hasPermission('view-discounts'),
                'can_see_msc_pricing' => $request->user()->hasPermission('view-msc-pricing'),
                'can_see_order_totals' => $request->user()->hasPermission('view-order-totals'),
            ]
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

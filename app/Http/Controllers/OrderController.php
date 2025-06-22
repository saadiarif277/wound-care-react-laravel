<?php

namespace App\Http\Controllers;

use App\Models\Order\Order;
use App\Models\Order\ProductRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\DocuSealService;

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

        // Build base query for product requests using DB
        $query = DB::table('product_requests')
            ->select([
                'product_requests.*',
                'facilities.name as facility_name',
                'facilities.city as facility_city',
                'facilities.state as facility_state',
                'users.first_name as provider_first_name',
                'users.last_name as provider_last_name',
                'users.email as provider_email',
                'users.npi_number as provider_npi_number',
                DB::raw('(SELECT COUNT(*) FROM product_request_products WHERE product_request_products.product_request_id = product_requests.id) as products_count')
            ])
            ->leftJoin('facilities', 'product_requests.facility_id', '=', 'facilities.id')
            ->leftJoin('users', 'product_requests.provider_id', '=', 'users.id')
            ->whereIn('product_requests.order_status', ['submitted', 'processing', 'pending_approval', 'approved', 'rejected'])
            ->orderBy('product_requests.submitted_at', 'desc');

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('product_requests.request_number', 'like', "%{$search}%")
                    ->orWhere('product_requests.patient_display_id', 'like', "%{$search}%")
                    ->orWhere('product_requests.patient_fhir_id', 'like', "%{$search}%")
                    ->orWhere('users.first_name', 'like', "%{$search}%")
                    ->orWhere('users.last_name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('product_requests.order_status', $request->input('status'));
        }

        if ($request->filled('facility')) {
            $query->where('product_requests.facility_id', $request->input('facility'));
        }

        if ($request->filled('days_pending')) {
            $days = (int) $request->input('days_pending');
            $query->whereDate('product_requests.submitted_at', '<=', now()->subDays($days));
        }

        if ($request->filled('priority')) {
            $priority = $request->input('priority');
            if ($priority === 'high') {
                $query->where(function ($q) {
                    $q->where('product_requests.pre_auth_required_determination', 'required')
                      ->orWhere('product_requests.mac_validation_status', 'failed')
                      ->orWhereRaw('DATEDIFF(NOW(), product_requests.submitted_at) > 3');
                });
            } elseif ($priority === 'urgent') {
                $query->whereRaw('DATEDIFF(NOW(), product_requests.submitted_at) > 7');
            }
        }

        // Get paginated results
        $requests = $query->paginate(20)
            ->withQueryString()
            ->through(function ($request) {
                // Calculate priority score
                $score = 0;
                $daysSince = $request->submitted_at ? Carbon::parse($request->submitted_at)->diffInDays(now()) : 0;
                if ($daysSince > 7) $score += 40;
                elseif ($daysSince > 3) $score += 20;
                elseif ($daysSince > 1) $score += 10;

                if ($request->pre_auth_required_determination === 'required') $score += 30;
                if ($request->mac_validation_status === 'failed') $score += 25;
                if ($request->total_order_value > 1000) $score += 15;

                return [
                    'id' => $request->id,
                    'request_number' => $request->request_number,
                    'patient_display' => $this->formatPatientDisplay($request->patient_display_id, $request->patient_fhir_id),
                    'patient_fhir_id' => $request->patient_fhir_id,
                    'order_status' => $request->order_status,
                    'wound_type' => $request->wound_type,
                    'expected_service_date' => $request->expected_service_date,
                    'submitted_at' => $request->submitted_at,
                    'total_order_value' => $request->total_order_value,
                    'facility' => [
                        'id' => $request->facility_id,
                        'name' => $request->facility_name,
                        'city' => $request->facility_city,
                        'state' => $request->facility_state,
                    ],
                    'provider' => [
                        'id' => $request->provider_id,
                        'name' => $request->provider_first_name . ' ' . $request->provider_last_name,
                        'email' => $request->provider_email,
                        'npi_number' => $request->provider_npi_number,
                    ],
                    'payer_name' => $request->payer_name_submitted,
                    'mac_validation_status' => $request->mac_validation_status,
                    'eligibility_status' => $request->eligibility_status,
                    'pre_auth_required' => $request->pre_auth_required_determination === 'required',
                    'clinical_summary' => json_decode($request->clinical_summary, true),
                    'products_count' => $request->products_count,
                    'days_since_submission' => $daysSince,
                    'priority_score' => min($score, 100),
                ];
            });

        // Get status counts using DB
        $statusCounts = DB::table('product_requests')
            ->whereIn('order_status', ['submitted', 'processing', 'pending_approval', 'approved', 'rejected'])
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

        // Get facilities for filter dropdown using DB
        $facilities = DB::table('facilities')
            ->where('active', true)
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
     * Format patient display for UI using sequential display ID.
     */
    private function formatPatientDisplay(?string $displayId, string $fhirId): string
    {
        if (!$displayId) {
            return 'Patient ' . substr($fhirId, -4);
        }
        return $displayId; // "JoSm001" format - no age for better privacy
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
        if ($user->hasPermission('manage-orders')) return 'full';
        if ($user->hasPermission('review-orders')) return 'clinical';
        if ($user->hasPermission('admin-orders')) return 'admin';
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

    /**
     * Show order tracking information for providers
     */
    public function tracking($id)
    {
        $user = Auth::user();

        // Find the order and ensure the provider has access
        $order = ProductRequest::where('id', $id)
            ->where('provider_id', $user->id)
            ->whereIn('order_status', ['submitted_to_manufacturer', 'shipped', 'delivered'])
            ->with(['provider', 'facility', 'products'])
            ->firstOrFail();

        // Transform order for display
        $orderData = [
            'id' => $order->id,
            'order_number' => $order->request_number,
            'patient_display_id' => $order->patient_display_id,
            'order_status' => $order->order_status,
            'expected_service_date' => $order->expected_service_date?->format('Y-m-d') ?? $order->date_of_service,
            'submitted_at' => $order->created_at->format('Y-m-d H:i:s'),
            'tracking_number' => $order->tracking_number,
            'tracking_carrier' => $order->tracking_carrier,
            'shipped_at' => $order->shipped_at?->format('Y-m-d H:i:s'),
            'delivered_at' => $order->delivered_at?->format('Y-m-d H:i:s'),
            'provider' => [
                'name' => $order->provider->full_name,
                'email' => $order->provider->email,
            ],
            'facility' => [
                'name' => $order->facility->name,
                'city' => $order->facility->city,
                'state' => $order->facility->state,
            ],
            'products' => $order->products->map(function($product) {
                return [
                    'name' => $product->name,
                    'quantity' => $product->pivot->quantity ?? 1,
                    'size' => $product->pivot->size ?? null,
                ];
            }),
        ];

        return Inertia::render('Order/Tracking', [
            'order' => $orderData,
        ]);
    }

    public function show($id, DocuSealService $docuSealService)
    {
        $order = Order::with([
            'provider',
            'facility',
            'manufacturer',
            'items.product',
            'ivrEpisode.orders',
        ])->findOrFail($id);

        $this->authorize('view', $order);

        $patientName = $order->patient_fhir_id;

        // Fetch DocuSeal status for the order
        $orderDocuseal = [
            'status' => $order->docuseal_status,
            'signed_documents' => [],
            'audit_log_url' => $order->docuseal_audit_log_url,
            'last_synced_at' => $order->docuseal_last_synced_at,
        ];

        // Check if the DocuSealService has the method before calling it
        if ($order->docuseal_submission_id && method_exists($docuSealService, 'getSubmissionStatus')) {
            $live = $docuSealService->{'getSubmissionStatus'}($order->docuseal_submission_id);
            if (is_array($live)) {
                $orderDocuseal['status'] = $live['status'] ?? $orderDocuseal['status'];
                $orderDocuseal['signed_documents'] = $live['documents'] ?? [];
                $orderDocuseal['audit_log_url'] = $live['audit_log_url'] ?? $orderDocuseal['audit_log_url'];
                $orderDocuseal['last_synced_at'] = now();
            }
        }

        // Fetch DocuSeal status for the IVR episode
        $ivrDocuseal = null;
        if ($order->ivrEpisode) {
            $ivrDocuseal = [
                'status' => $order->ivrEpisode->docuseal_status,
                'signed_documents' => [],
                'audit_log_url' => $order->ivrEpisode->docuseal_audit_log_url,
                'last_synced_at' => $order->ivrEpisode->docuseal_last_synced_at,
            ];
            if (
                $order->ivrEpisode->docuseal_submission_id &&
                method_exists($docuSealService, 'getSubmissionStatus')
            ) {
                $live = $docuSealService->{'getSubmissionStatus'}($order->ivrEpisode->docuseal_submission_id);
                if (is_array($live)) {
                    $ivrDocuseal['status'] = $live['status'] ?? $ivrDocuseal['status'];
                    $ivrDocuseal['signed_documents'] = $live['documents'] ?? [];
                    $ivrDocuseal['audit_log_url'] = $live['audit_log_url'] ?? $ivrDocuseal['audit_log_url'];
                    $ivrDocuseal['last_synced_at'] = now();
                }
            }
        }

        $orderDetail = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'patient_display_id' => $order->patient_display_id,
            'patient_fhir_id' => $order->patient_fhir_id,
            'patient_name' => $patientName,
            'order_status' => $order->order_status,
            'provider' => [
                'id' => $order->provider->id,
                'name' => $order->provider->first_name . ' ' . $order->provider->last_name,
                'email' => $order->provider->email,
                'npi_number' => $order->provider->npi_number ?? null,
            ],
            'facility' => [
                'id' => $order->facility->id,
                'name' => $order->facility->name,
            ],
            'manufacturer' => $order->manufacturer ? [
                'id' => $order->manufacturer->id,
                'name' => $order->manufacturer->name,
            ] : null,
            'order_details' => [
                'products' => $order->items->map(function($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->product->name,
                        'sku' => $item->product->sku,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->price,
                        'total_price' => $item->total_amount,
                    ];
                }),
            ],
            'ivr_episode' => $order->ivrEpisode ? [
                'id' => $order->ivrEpisode->id,
                'verification_status' => $order->ivrEpisode->verification_status,
                'verified_date' => $order->ivrEpisode->verified_date,
                'expiration_date' => $order->ivrEpisode->expiration_date,
                'docuseal' => $ivrDocuseal,
                'orders' => $order->ivrEpisode->orders->map(function($o) {
                    return [
                        'id' => $o->id,
                        'order_number' => $o->order_number,
                        'status' => $o->order_status,
                    ];
                }),
            ] : null,
            'docuseal' => $orderDocuseal,
            'confirmation_documents' => [], // TODO: Populate when Document model is available
            'audit_log' => [], // TODO: Implement audit log for provider
        ];

        return Inertia::render('Provider/Orders/Show', [
            'order' => $orderDetail,
        ]);
    }
}

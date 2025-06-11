<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order\ProductRequest;
use App\Models\Fhir\Facility;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class ProductRequestReviewController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:admin|msc-admin|clinical-reviewer']);
    }

    public function index(Request $request)
    {
        $user = Auth::user();

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
        $requests = $query->paginate(15)
            ->withQueryString()
            ->through(function ($request) {
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
                    'days_since_submission' => $request->submitted_at ? Carbon::parse($request->submitted_at)->diffInDays(now()) : 0,
                    'priority_score' => $this->calculatePriorityScore($request),
                ];
            });

        // Get status counts using DB
        $statusCounts = DB::table('product_requests')
            ->whereIn('order_status', ['submitted', 'processing', 'pending_approval', 'approved', 'rejected'])
            ->selectRaw('order_status, count(*) as count')
            ->groupBy('order_status')
            ->pluck('count', 'order_status')
            ->toArray();

        // Get facilities for filter dropdown using DB
        $facilities = DB::table('facilities')
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/ProductRequests/Review', [
            'requests' => $requests,
            'filters' => $request->only(['search', 'status', 'priority', 'facility', 'days_pending']),
            'statusCounts' => $statusCounts,
            'facilities' => $facilities,
            'roleRestrictions' => [
                'can_approve_requests' => $user->hasAnyPermission(['approve-product-requests', 'manage-product-requests']),
                'can_reject_requests' => $user->hasAnyPermission(['reject-product-requests', 'manage-product-requests']),
                'can_view_clinical_data' => $user->hasPermission('view-clinical-data'),
                'can_view_financials' => $user->hasAnyPermission(['view-financials', 'manage-financials']),
                'access_level' => $this->getUserAccessLevel($user),
            ]
        ]);
    }

    public function show(ProductRequest $productRequest)
    {
        $user = Auth::user();

        // Load necessary relationships
        $productRequest->load([
            'provider:id,first_name,last_name,email,npi_number',
            'facility:id,name,address,city,state,zip',
            'products' => function ($query) {
                $query->withPivot(['quantity', 'size', 'unit_price', 'total_price']);
            }
        ]);

        return Inertia::render('Admin/OrderCenter/Show', [
            'request' => [
                'id' => $productRequest->id,
                'request_number' => $productRequest->request_number,
                'order_status' => $productRequest->order_status,
                'step' => $productRequest->step,
                'wound_type' => $productRequest->wound_type,
                'expected_service_date' => $productRequest->expected_service_date,
                'patient_display' => $productRequest->formatPatientDisplay(),
                'patient_fhir_id' => $productRequest->patient_fhir_id,
                'facility' => $productRequest->facility,
                'provider' => [
                    'id' => $productRequest->provider->id,
                    'name' => $productRequest->provider->first_name . ' ' . $productRequest->provider->last_name,
                    'email' => $productRequest->provider->email,
                    'npi_number' => $productRequest->provider->npi_number,
                ],
                'payer_name' => $productRequest->payer_name_submitted,
                'clinical_summary' => $productRequest->clinical_summary,
                'mac_validation_results' => $productRequest->mac_validation_results,
                'mac_validation_status' => $productRequest->mac_validation_status,
                'eligibility_results' => $productRequest->eligibility_results,
                'eligibility_status' => $productRequest->eligibility_status,
                'pre_auth_required' => $productRequest->isPriorAuthRequired(),
                'clinical_opportunities' => $productRequest->clinical_opportunities,
                'total_amount' => $productRequest->total_order_value,
                'submitted_at' => $productRequest->submitted_at?->format('M j, Y H:i'),
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
                'review_history' => $this->getReviewHistory($productRequest),
            ],
            'roleRestrictions' => [
                'can_approve_requests' => $user->hasAnyPermission(['approve-product-requests', 'manage-product-requests']),
                'can_reject_requests' => $user->hasAnyPermission(['reject-product-requests', 'manage-product-requests']),
                'can_view_clinical_data' => $user->hasPermission('view-clinical-data'),
                'can_view_financials' => $user->hasAnyPermission(['view-financials', 'manage-financials']),
            ]
        ]);
    }

    public function approve(Request $request, ProductRequest $productRequest)
    {
        $validated = $request->validate([
            'comments' => 'nullable|string|max:1000',
            'conditions' => 'nullable|array',
            'notify_provider' => 'boolean',
        ]);

        DB::transaction(function () use ($productRequest, $validated) {
            $productRequest->update([
                'order_status' => 'pending_ivr', // Move to IVR generation stage
                'approved_at' => now(),
                'approved_by' => Auth::id(),
                'approval_comments' => $validated['comments'] ?? null,
                'approval_conditions' => $validated['conditions'] ?? null,
            ]);

            // Log the approval action
            $this->logReviewAction($productRequest, 'approved', $validated['comments'] ?? null);

            // Send notification to provider if requested
            if ($validated['notify_provider'] ?? false) {
                $this->notifyProvider($productRequest, 'approved');
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Product request approved successfully.',
        ]);
    }

    public function reject(Request $request, ProductRequest $productRequest)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
            'category' => 'required|string|in:clinical,documentation,eligibility,other',
            'notify_provider' => 'boolean',
        ]);

        DB::transaction(function () use ($productRequest, $validated) {
            $productRequest->update([
                'order_status' => 'rejected',
                'rejected_at' => now(),
                'rejected_by' => Auth::id(),
                'rejection_reason' => $validated['reason'],
                'rejection_category' => $validated['category'],
            ]);

            // Log the rejection action
            $this->logReviewAction($productRequest, 'rejected', $validated['reason']);

            // Send notification to provider if requested
            if ($validated['notify_provider'] ?? false) {
                $this->notifyProvider($productRequest, 'rejected');
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Product request rejected.',
        ]);
    }

    public function requestInformation(Request $request, ProductRequest $productRequest)
    {
        $validated = $request->validate([
            'information_needed' => 'required|string|max:1000',
            'specific_items' => 'nullable|array',
            'due_date' => 'nullable|date|after:today',
            'notify_provider' => 'boolean',
        ]);

        DB::transaction(function () use ($productRequest, $validated) {
            $productRequest->update([
                'order_status' => 'additional_info_required',
                'info_requested_at' => now(),
                'info_requested_by' => Auth::id(),
                'info_request_details' => [
                    'information_needed' => $validated['information_needed'],
                    'specific_items' => $validated['specific_items'] ?? [],
                    'due_date' => $validated['due_date'] ?? null,
                ],
            ]);

            // Log the information request
            $this->logReviewAction($productRequest, 'info_requested', $validated['information_needed']);

            // Send notification to provider if requested
            if ($validated['notify_provider'] ?? false) {
                $this->notifyProvider($productRequest, 'info_requested');
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Information request sent to provider.',
        ]);
    }

    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|string|in:approve,reject,request_info,assign_reviewer',
            'request_ids' => 'required|array|min:1',
            'request_ids.*' => 'exists:product_requests,id',
            'data' => 'nullable|array', // Additional data based on action
        ]);

        $requestIds = $validated['request_ids'];
        $action = $validated['action'];
        $data = $validated['data'] ?? [];

        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($requestIds as $requestId) {
            try {
                $productRequest = ProductRequest::findOrFail($requestId);

                // Ensure we have a single model instance, not a collection
                if ($productRequest instanceof \Illuminate\Database\Eloquent\Collection) {
                    $productRequest = $productRequest->first();
                }

                switch ($action) {
                    case 'approve':
                        $this->performBulkApproval($productRequest, $data);
                        break;
                    case 'reject':
                        $this->performBulkRejection($productRequest, $data);
                        break;
                    case 'request_info':
                        $this->performBulkInfoRequest($productRequest, $data);
                        break;
                    case 'assign_reviewer':
                        $this->performBulkAssignment($productRequest, $data);
                        break;
                }

                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Request {$requestId}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Bulk action completed. {$results['success']} successful, {$results['failed']} failed.",
            'results' => $results,
        ]);
    }

    // Private helper methods

    private function calculatePriorityScore(ProductRequest $request): int
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

    private function getUserAccessLevel(User $user): string
    {
        if ($user->hasRole('msc-admin')) return 'full';
        if ($user->hasRole('clinical-reviewer')) return 'clinical';
        if ($user->hasRole('admin')) return 'admin';
        return 'limited';
    }

    private function getReviewHistory(ProductRequest $productRequest): array
    {
        // This would typically fetch from a review_history table
        // For now, return basic status changes
        return [
            [
                'action' => 'submitted',
                'timestamp' => $productRequest->submitted_at?->format('M j, Y H:i'),
                'user' => $productRequest->provider->first_name . ' ' . $productRequest->provider->last_name,
                'comments' => 'Product request submitted for review',
            ]
        ];
    }

    private function logReviewAction(ProductRequest $productRequest, string $action, ?string $comments): void
    {
        // Implementation would log to review_history table
        // For MVP, we'll add to product request notes/audit
    }

    private function notifyProvider(ProductRequest $productRequest, string $action): void
    {
        // Implementation would send email/notification to provider
        // For MVP, we'll log the notification
    }

    private function performBulkApproval(ProductRequest $request, array $data): void
    {
        $request->update([
            'order_status' => 'pending_ivr', // Move to IVR generation stage
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'approval_comments' => $data['comments'] ?? 'Bulk approval',
        ]);
    }

    private function performBulkRejection(ProductRequest $request, array $data): void
    {
        $request->update([
            'order_status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => Auth::id(),
            'rejection_reason' => $data['reason'] ?? 'Bulk rejection',
            'rejection_category' => $data['category'] ?? 'other',
        ]);
    }

    private function performBulkInfoRequest(ProductRequest $request, array $data): void
    {
        $request->update([
            'order_status' => 'additional_info_required',
            'info_requested_at' => now(),
            'info_requested_by' => Auth::id(),
            'info_request_details' => $data,
        ]);
    }

    private function performBulkAssignment(ProductRequest $request, array $data): void
    {
        $request->update([
            'assigned_reviewer_id' => $data['reviewer_id'] ?? null,
            'assigned_at' => now(),
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
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\MedicareMacValidation;
use App\Services\MedicareMacValidationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class MedicareMacValidationController extends Controller
{
    protected MedicareMacValidationService $validationService;

    public function __construct(MedicareMacValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    /**
     * Validate Medicare compliance for an order
     *
     * POST /api/v1/orders/{order_id}/medicare-validation
     */
    public function validateOrder(Request $request, int $orderId): JsonResponse
    {
        try {
            $request->validate([
                'validation_type' => ['required', Rule::in(['vascular_wound_care', 'wound_care_only', 'vascular_only'])],
                'enable_daily_monitoring' => 'sometimes|boolean',
            ]);

            $order = Order::with(['orderItems.product', 'facility', 'patient'])->findOrFail($orderId);

            $validation = $this->validationService->validateOrder(
                $order,
                $request->input('validation_type', 'wound_care_only')
            );

            // Enable daily monitoring if requested
            if ($request->has('enable_daily_monitoring')) {
                $validation->update([
                    'daily_monitoring_enabled' => $request->boolean('enable_daily_monitoring')
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Medicare MAC validation completed',
                'data' => [
                    'validation_id' => $validation->validation_id,
                    'order_id' => $orderId,
                    'validation_type' => $validation->validation_type,
                    'status' => $validation->validation_status,
                    'compliance_score' => $validation->getComplianceScore(),
                    'is_compliant' => $validation->isCompliant(),
                    'missing_items' => $validation->getMissingComplianceItems(),
                    'mac_contractor' => $validation->mac_contractor,
                    'mac_jurisdiction' => $validation->mac_jurisdiction,
                    'estimated_reimbursement' => $validation->estimated_reimbursement,
                    'reimbursement_risk' => $validation->reimbursement_risk,
                    'validated_at' => $validation->validated_at,
                    'next_validation_due' => $validation->next_validation_due,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Medicare MAC validation failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Medicare MAC validation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Medicare validation status for an order
     *
     * GET /api/v1/orders/{order_id}/medicare-validation
     */
    public function getValidation(int $orderId): JsonResponse
    {
        try {
            $validation = MedicareMacValidation::where('order_id', $orderId)
                ->with(['order', 'facility', 'patient'])
                ->first();

            if (!$validation) {
                return response()->json([
                    'success' => false,
                    'message' => 'No Medicare validation found for this order'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'validation_id' => $validation->validation_id,
                    'order_id' => $orderId,
                    'validation_type' => $validation->validation_type,
                    'status' => $validation->validation_status,
                    'compliance_score' => $validation->getComplianceScore(),
                    'is_compliant' => $validation->isCompliant(),
                    'missing_items' => $validation->getMissingComplianceItems(),

                    // MAC Information
                    'mac_contractor' => $validation->mac_contractor,
                    'mac_jurisdiction' => $validation->mac_jurisdiction,
                    'mac_region' => $validation->mac_region,

                    // Validation Results
                    'coverage_met' => $validation->coverage_met,
                    'coverage_notes' => $validation->coverage_notes,
                    'documentation_complete' => $validation->documentation_complete,
                    'missing_documentation' => $validation->missing_documentation,
                    'frequency_compliant' => $validation->frequency_compliant,
                    'medical_necessity_met' => $validation->medical_necessity_met,
                    'prior_auth_required' => $validation->prior_auth_required,
                    'prior_auth_obtained' => $validation->prior_auth_obtained,
                    'billing_compliant' => $validation->billing_compliant,
                    'billing_issues' => $validation->billing_issues,

                    // Financial Information
                    'estimated_reimbursement' => $validation->estimated_reimbursement,
                    'reimbursement_risk' => $validation->reimbursement_risk,

                    // Timing
                    'validated_at' => $validation->validated_at,
                    'last_revalidated_at' => $validation->last_revalidated_at,
                    'next_validation_due' => $validation->next_validation_due,
                    'daily_monitoring_enabled' => $validation->daily_monitoring_enabled,
                    'last_monitored_at' => $validation->last_monitored_at,

                    // Metadata
                    'validation_count' => $validation->validation_count,
                    'validation_errors' => $validation->validation_errors,
                    'validation_warnings' => $validation->validation_warnings,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve Medicare validation', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Medicare validation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Medicare validations for vascular group (daily monitoring)
     *
     * GET /api/v1/medicare-validation/vascular-group
     */
    public function getVascularGroupValidations(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'facility_id' => 'sometimes|integer|exists:facilities,id',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date',
                'status' => 'sometimes|array',
                'status.*' => Rule::in(['pending', 'validated', 'failed', 'requires_review']),
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $query = MedicareMacValidation::query()
                ->whereIn('validation_type', ['vascular_wound_care', 'vascular_only'])
                ->where('daily_monitoring_enabled', true)
                ->with(['order.orderItems.product', 'order.facility', 'order.patient']);

            // Apply filters
            if ($request->has('facility_id')) {
                $query->where('facility_id', $request->input('facility_id'));
            }

            if ($request->has('date_from')) {
                $query->whereDate('validated_at', '>=', $request->input('date_from'));
            }

            if ($request->has('date_to')) {
                $query->whereDate('validated_at', '<=', $request->input('date_to'));
            }

            if ($request->has('status')) {
                $query->whereIn('validation_status', $request->input('status'));
            }

            // Order by most recent
            $query->orderBy('validated_at', 'desc');

            $perPage = $request->input('per_page', 15);
            $validations = $query->paginate($perPage);

            $summary = [
                'total_validations' => $validations->total(),
                'validated' => MedicareMacValidation::vascularWoundCare()->validated()->count(),
                'failed' => MedicareMacValidation::vascularWoundCare()->failed()->count(),
                'requires_review' => MedicareMacValidation::vascularWoundCare()->requiresReview()->count(),
                'high_risk' => MedicareMacValidation::vascularWoundCare()->highRisk()->count(),
                'due_for_revalidation' => MedicareMacValidation::vascularWoundCare()->dueForRevalidation()->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Vascular group validations retrieved',
                'data' => $validations->items(),
                'pagination' => [
                    'current_page' => $validations->currentPage(),
                    'last_page' => $validations->lastPage(),
                    'per_page' => $validations->perPage(),
                    'total' => $validations->total(),
                ],
                'summary' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve vascular group validations', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve vascular group validations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get wound care only Medicare validations
     *
     * GET /api/v1/medicare-validation/wound-care-only
     */
    public function getWoundCareValidations(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'facility_id' => 'sometimes|integer|exists:facilities,id',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date',
                'status' => 'sometimes|array',
                'status.*' => Rule::in(['pending', 'validated', 'failed', 'requires_review']),
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $query = MedicareMacValidation::woundCareOnly()
                ->with(['order.orderItems.product', 'order.facility', 'order.patient']);

            // Apply filters
            if ($request->has('facility_id')) {
                $query->where('facility_id', $request->input('facility_id'));
            }

            if ($request->has('date_from')) {
                $query->whereDate('validated_at', '>=', $request->input('date_from'));
            }

            if ($request->has('date_to')) {
                $query->whereDate('validated_at', '<=', $request->input('date_to'));
            }

            if ($request->has('status')) {
                $query->whereIn('validation_status', $request->input('status'));
            }

            $query->orderBy('validated_at', 'desc');

            $perPage = $request->input('per_page', 15);
            $validations = $query->paginate($perPage);

            $summary = [
                'total_validations' => $validations->total(),
                'validated' => MedicareMacValidation::woundCareOnly()->validated()->count(),
                'failed' => MedicareMacValidation::woundCareOnly()->failed()->count(),
                'requires_review' => MedicareMacValidation::woundCareOnly()->requiresReview()->count(),
                'high_risk' => MedicareMacValidation::woundCareOnly()->highRisk()->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Wound care validations retrieved',
                'data' => $validations->items(),
                'pagination' => [
                    'current_page' => $validations->currentPage(),
                    'last_page' => $validations->lastPage(),
                    'per_page' => $validations->perPage(),
                    'total' => $validations->total(),
                ],
                'summary' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve wound care validations', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve wound care validations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Run daily monitoring for Medicare validations
     *
     * POST /api/v1/medicare-validation/daily-monitoring
     */
    public function runDailyMonitoring(): JsonResponse
    {
        try {
            $results = $this->validationService->runDailyMonitoring();

            return response()->json([
                'success' => true,
                'message' => 'Daily monitoring completed',
                'data' => [
                    'processed' => $results['processed'],
                    'revalidated' => $results['revalidated'],
                    'new_issues' => $results['new_issues'],
                    'resolved_issues' => $results['resolved_issues'],
                    'executed_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Daily monitoring failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Daily monitoring failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Medicare validation dashboard summary
     *
     * GET /api/v1/medicare-validation/dashboard
     */
    public function getDashboard(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'facility_id' => 'sometimes|integer|exists:facilities,id',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date',
            ]);

            $query = MedicareMacValidation::query();

            if ($request->has('facility_id')) {
                $query->where('facility_id', $request->input('facility_id'));
            }

            if ($request->has('date_from')) {
                $query->whereDate('validated_at', '>=', $request->input('date_from'));
            }

            if ($request->has('date_to')) {
                $query->whereDate('validated_at', '<=', $request->input('date_to'));
            }

            // Overall statistics
            $stats = [
                'total_validations' => $query->count(),
                'validated' => (clone $query)->validated()->count(),
                'failed' => (clone $query)->failed()->count(),
                'requires_review' => (clone $query)->requiresReview()->count(),
                'pending' => (clone $query)->pending()->count(),
                'high_risk' => (clone $query)->highRisk()->count(),
                'due_for_revalidation' => (clone $query)->dueForRevalidation()->count(),
            ];

            // Breakdown by validation type
            $byType = [
                'vascular_wound_care' => (clone $query)->byValidationType('vascular_wound_care')->count(),
                'wound_care_only' => (clone $query)->byValidationType('wound_care_only')->count(),
                'vascular_only' => (clone $query)->byValidationType('vascular_only')->count(),
            ];

            // MAC Contractor breakdown
            $byMac = MedicareMacValidation::selectRaw('mac_contractor, COUNT(*) as count')
                ->when($request->has('facility_id'), function ($q) use ($request) {
                    return $q->where('facility_id', $request->input('facility_id'));
                })
                ->groupBy('mac_contractor')
                ->pluck('count', 'mac_contractor')
                ->toArray();

            // Recent high-risk validations
            $highRiskValidations = MedicareMacValidation::highRisk()
                ->when($request->has('facility_id'), function ($q) use ($request) {
                    return $q->where('facility_id', $request->input('facility_id'));
                })
                ->with(['order.facility'])
                ->latest('validated_at')
                ->limit(10)
                ->get()
                ->map(function ($validation) {
                    return [
                        'validation_id' => $validation->validation_id,
                        'order_id' => $validation->order_id,
                        'facility_name' => $validation->order->facility->name ?? 'Unknown',
                        'validation_type' => $validation->validation_type,
                        'compliance_score' => $validation->getComplianceScore(),
                        'missing_items' => $validation->getMissingComplianceItems(),
                        'estimated_reimbursement' => $validation->estimated_reimbursement,
                        'validated_at' => $validation->validated_at,
                    ];
                });

            // Daily monitoring summary
            $dailyMonitoring = [
                'enabled_count' => MedicareMacValidation::dailyMonitoring()->count(),
                'monitored_today' => MedicareMacValidation::dailyMonitoring()
                    ->whereDate('last_monitored_at', now()->toDateString())
                    ->count(),
                'due_for_monitoring' => MedicareMacValidation::dailyMonitoring()
                    ->where(function ($q) {
                        $q->whereDate('last_monitored_at', '<', now()->toDateString())
                          ->orWhereNull('last_monitored_at');
                    })
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'by_validation_type' => $byType,
                    'by_mac_contractor' => $byMac,
                    'high_risk_validations' => $highRiskValidations,
                    'daily_monitoring' => $dailyMonitoring,
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate Medicare validation dashboard', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enable/disable daily monitoring for a validation
     *
     * PATCH /api/v1/medicare-validation/{validation_id}/monitoring
     */
    public function toggleMonitoring(Request $request, string $validationId): JsonResponse
    {
        try {
            $request->validate([
                'enabled' => 'required|boolean',
            ]);

            $validation = MedicareMacValidation::where('validation_id', $validationId)->firstOrFail();

            $validation->update([
                'daily_monitoring_enabled' => $request->boolean('enabled')
            ]);

            $validation->addAuditEntry('monitoring_toggled', [
                'enabled' => $request->boolean('enabled'),
                'user' => auth()->user()?->name ?? 'system'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Daily monitoring ' . ($request->boolean('enabled') ? 'enabled' : 'disabled'),
                'data' => [
                    'validation_id' => $validation->validation_id,
                    'daily_monitoring_enabled' => $validation->daily_monitoring_enabled
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to toggle monitoring', [
                'validation_id' => $validationId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle monitoring',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get validation audit trail
     *
     * GET /api/v1/medicare-validation/{validation_id}/audit
     */
    public function getAuditTrail(string $validationId): JsonResponse
    {
        try {
            $validation = MedicareMacValidation::where('validation_id', $validationId)->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'validation_id' => $validation->validation_id,
                    'audit_trail' => $validation->audit_trail ?? [],
                    'validation_count' => $validation->validation_count,
                    'created_at' => $validation->created_at,
                    'updated_at' => $validation->updated_at,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve audit trail', [
                'validation_id' => $validationId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve audit trail',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

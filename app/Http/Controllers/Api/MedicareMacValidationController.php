<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\MedicareMacValidation;
use App\Services\MedicareMacValidationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
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
                'provider_specialty' => 'sometimes|string|in:vascular_surgery,interventional_radiology,cardiology,wound_care_specialty,podiatry,plastic_surgery',
                'enable_daily_monitoring' => 'sometimes|boolean',
            ]);

            $order = Order::with(['orderItems.product', 'facility', 'patient'])->where('id', $orderId)->firstOrFail();

            $validation = $this->validationService->validateOrder(
                $order,
                $request->input('validation_type', 'wound_care_only'),
                $request->input('provider_specialty')
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
                'user' => Auth::check() ? Auth::user()->name : 'system'
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

    // ===== SPECIALTY-SPECIFIC METHODS =====

    /**
     * Get vascular surgery specialty validations
     * GET /api/v1/medicare-validation/specialty/vascular-surgery
     */
    public function getVascularSurgeryValidations(Request $request): JsonResponse
    {
        return $this->getSpecialtyValidations($request, 'vascular_surgery', 'Vascular Surgery');
    }

    /**
     * Get vascular surgery dashboard
     * GET /api/v1/medicare-validation/specialty/vascular-surgery/dashboard
     */
    public function getVascularSurgeryDashboard(Request $request): JsonResponse
    {
        return $this->getSpecialtyDashboard($request, 'vascular_surgery', 'Vascular Surgery');
    }

    /**
     * Get vascular surgery compliance report
     * GET /api/v1/medicare-validation/specialty/vascular-surgery/compliance-report
     */
    public function getVascularSurgeryCompliance(Request $request): JsonResponse
    {
        try {
            $validations = MedicareMacValidation::vascularSurgery()
                ->when($request->has('facility_id'), function ($q) use ($request) {
                    return $q->where('facility_id', $request->input('facility_id'));
                })
                ->with(['order.orderItems.product', 'order.facility'])
                ->get();

            $complianceMetrics = [
                'total_procedures' => $validations->count(),
                'compliant_procedures' => $validations->where('validation_status', 'validated')->count(),
                'high_risk_procedures' => $validations->where('reimbursement_risk', 'high')->count(),
                'prior_auth_compliance' => $validations->where('prior_auth_required', true)
                    ->where('prior_auth_obtained', true)->count(),
                'documentation_complete' => $validations->where('documentation_complete', true)->count(),
                'average_compliance_score' => round($validations->avg(function ($v) {
                    return $v->getComplianceScore();
                }), 2),
                'common_missing_items' => $this->getCommonMissingItems($validations),
                'specialty_requirements' => [
                    'abi_measurements_required' => $validations->count(),
                    'angiography_documented' => $validations->filter(function ($v) {
                        return !in_array('angiography', $v->missing_documentation ?? []);
                    })->count(),
                    'vascular_assessment_complete' => $validations->filter(function ($v) {
                        return !in_array('vascular_assessment', $v->missing_documentation ?? []);
                    })->count(),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $complianceMetrics
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate vascular surgery compliance report', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate compliance report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get interventional radiology validations
     * GET /api/v1/medicare-validation/specialty/interventional-radiology
     */
    public function getInterventionalRadiologyValidations(Request $request): JsonResponse
    {
        return $this->getSpecialtyValidations($request, 'interventional_radiology', 'Interventional Radiology');
    }

    /**
     * Get interventional radiology dashboard
     * GET /api/v1/medicare-validation/specialty/interventional-radiology/dashboard
     */
    public function getInterventionalRadiologyDashboard(Request $request): JsonResponse
    {
        return $this->getSpecialtyDashboard($request, 'interventional_radiology', 'Interventional Radiology');
    }

    /**
     * Get cardiology validations
     * GET /api/v1/medicare-validation/specialty/cardiology
     */
    public function getCardiologyValidations(Request $request): JsonResponse
    {
        return $this->getSpecialtyValidations($request, 'cardiology', 'Cardiology');
    }

    /**
     * Get cardiology dashboard
     * GET /api/v1/medicare-validation/specialty/cardiology/dashboard
     */
    public function getCardiologyDashboard(Request $request): JsonResponse
    {
        return $this->getSpecialtyDashboard($request, 'cardiology', 'Cardiology');
    }

    /**
     * Get wound care only dashboard
     * GET /api/v1/medicare-validation/specialty/wound-care/dashboard
     */
    public function getWoundCareOnlyDashboard(Request $request): JsonResponse
    {
        return $this->getSpecialtyDashboard($request, 'wound_care_specialty', 'Wound Care');
    }

    // ===== VALIDATION TYPE METHODS (Legacy Support) =====

    /**
     * Get vascular-only validations
     * GET /api/v1/medicare-validation/type/vascular-only
     */
    public function getVascularOnlyValidations(Request $request): JsonResponse
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

            $query = MedicareMacValidation::byValidationType('vascular_only')
                ->with(['order.orderItems.product', 'order.facility', 'order.patient']);

            $this->applyCommonFilters($query, $request);

            $perPage = $request->input('per_page', 15);
            $validations = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Vascular-only validations retrieved',
                'data' => $validations->items(),
                'pagination' => [
                    'current_page' => $validations->currentPage(),
                    'last_page' => $validations->lastPage(),
                    'per_page' => $validations->perPage(),
                    'total' => $validations->total(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve vascular-only validations', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve vascular-only validations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== MAC CONTRACTOR METHODS =====

    /**
     * Get Novitas validations
     * GET /api/v1/medicare-validation/mac-contractor/novitas
     */
    public function getNovitasValidations(Request $request): JsonResponse
    {
        return $this->getMacContractorValidations($request, 'Novitas Solutions');
    }

    /**
     * Get CGS validations
     * GET /api/v1/medicare-validation/mac-contractor/cgs
     */
    public function getCgsValidations(Request $request): JsonResponse
    {
        return $this->getMacContractorValidations($request, 'CGS Administrators');
    }

    /**
     * Get Palmetto validations
     * GET /api/v1/medicare-validation/mac-contractor/palmetto
     */
    public function getPalmettoValidations(Request $request): JsonResponse
    {
        return $this->getMacContractorValidations($request, 'Palmetto GBA');
    }

    /**
     * Get Wisconsin Physicians Service validations
     * GET /api/v1/medicare-validation/mac-contractor/wisconsin-physicians
     */
    public function getWisconsinPhysiciansValidations(Request $request): JsonResponse
    {
        return $this->getMacContractorValidations($request, 'Wisconsin Physicians Service');
    }

    /**
     * Get Noridian validations
     * GET /api/v1/medicare-validation/mac-contractor/noridian
     */
    public function getNoridianValidations(Request $request): JsonResponse
    {
        return $this->getMacContractorValidations($request, 'Noridian Healthcare Solutions');
    }

    // ===== INDIVIDUAL VALIDATION METHODS =====

    /**
     * Revalidate a specific validation
     * POST /api/v1/medicare-validation/{validation_id}/revalidate
     */
    public function revalidate(string $validationId): JsonResponse
    {
        try {
            $validation = MedicareMacValidation::where('validation_id', $validationId)
                ->with(['order.orderItems.product', 'order.facility'])
                ->firstOrFail();

            $previousStatus = $validation->validation_status;

            // Re-run validation
            $updatedValidation = $this->validationService->validateOrder(
                $validation->order,
                $validation->validation_type,
                $validation->provider_specialty
            );

                         $updatedValidation->addAuditEntry('manual_revalidation', [
                 'previous_status' => $previousStatus,
                 'new_status' => $updatedValidation->validation_status,
                 'user' => Auth::check() ? Auth::user()->name : 'system'
             ]);

            return response()->json([
                'success' => true,
                'message' => 'Validation revalidated successfully',
                'data' => [
                    'validation_id' => $updatedValidation->validation_id,
                    'previous_status' => $previousStatus,
                    'current_status' => $updatedValidation->validation_status,
                    'compliance_score' => $updatedValidation->getComplianceScore(),
                    'is_compliant' => $updatedValidation->isCompliant(),
                    'revalidated_at' => $updatedValidation->last_revalidated_at,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to revalidate', [
                'validation_id' => $validationId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to revalidate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed compliance information
     * GET /api/v1/medicare-validation/{validation_id}/compliance-details
     */
    public function getComplianceDetails(string $validationId): JsonResponse
    {
        try {
            $validation = MedicareMacValidation::where('validation_id', $validationId)
                ->with(['order.orderItems.product', 'order.facility'])
                ->firstOrFail();

            $complianceDetails = [
                'validation_id' => $validation->validation_id,
                'overall_compliance' => [
                    'is_compliant' => $validation->isCompliant(),
                    'compliance_score' => $validation->getComplianceScore(),
                    'missing_items' => $validation->getMissingComplianceItems(),
                ],
                'coverage_compliance' => [
                    'coverage_met' => $validation->coverage_met,
                    'coverage_notes' => $validation->coverage_notes,
                    'coverage_policies' => $validation->coverage_policies,
                    'coverage_requirements' => $validation->coverage_requirements,
                ],
                'documentation_compliance' => [
                    'documentation_complete' => $validation->documentation_complete,
                    'required_documentation' => $validation->required_documentation,
                    'missing_documentation' => $validation->missing_documentation,
                    'documentation_status' => $validation->documentation_status,
                ],
                'clinical_compliance' => [
                    'frequency_compliant' => $validation->frequency_compliant,
                    'frequency_notes' => $validation->frequency_notes,
                    'medical_necessity_met' => $validation->medical_necessity_met,
                    'medical_necessity_notes' => $validation->medical_necessity_notes,
                ],
                'authorization_compliance' => [
                    'prior_auth_required' => $validation->prior_auth_required,
                    'prior_auth_obtained' => $validation->prior_auth_obtained,
                    'prior_auth_number' => $validation->prior_auth_number,
                    'prior_auth_expiry' => $validation->prior_auth_expiry,
                    'is_prior_auth_expired' => $validation->isPriorAuthExpired(),
                ],
                'billing_compliance' => [
                    'billing_compliant' => $validation->billing_compliant,
                    'billing_issues' => $validation->billing_issues,
                    'estimated_reimbursement' => $validation->estimated_reimbursement,
                    'reimbursement_risk' => $validation->reimbursement_risk,
                ],
                'specialty_requirements' => $validation->getSpecialtyRequirements(),
            ];

            return response()->json([
                'success' => true,
                'data' => $complianceDetails
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get compliance details', [
                'validation_id' => $validationId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get compliance details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== BULK OPERATIONS =====

    /**
     * Bulk validate multiple orders
     * POST /api/v1/medicare-validation/bulk/validate
     */
    public function bulkValidate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'order_ids' => 'required|array|min:1|max:50',
                'order_ids.*' => 'integer|exists:orders,id',
                'validation_type' => ['required', Rule::in(['vascular_wound_care', 'wound_care_only', 'vascular_only'])],
                'provider_specialty' => 'sometimes|string',
            ]);

            $results = [
                'processed' => 0,
                'successful' => 0,
                'failed' => 0,
                'validations' => []
            ];

            foreach ($request->input('order_ids') as $orderId) {
                try {
                    $order = Order::with(['orderItems.product', 'facility', 'patient'])->where('id', $orderId)->firstOrFail();

                    $validation = $this->validationService->validateOrder(
                        $order,
                        $request->input('validation_type'),
                        $request->input('provider_specialty')
                    );

                    $results['validations'][] = [
                        'order_id' => $orderId,
                        'validation_id' => $validation->validation_id,
                        'status' => 'success',
                        'compliance_score' => $validation->getComplianceScore(),
                    ];

                    $results['successful']++;

                } catch (\Exception $e) {
                    $results['validations'][] = [
                        'order_id' => $orderId,
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];

                    $results['failed']++;
                }

                $results['processed']++;
            }

            return response()->json([
                'success' => true,
                'message' => "Bulk validation completed: {$results['successful']} successful, {$results['failed']} failed",
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk validation failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk validation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk enable monitoring
     * POST /api/v1/medicare-validation/bulk/enable-monitoring
     */
    public function bulkEnableMonitoring(Request $request): JsonResponse
    {
        return $this->bulkUpdateMonitoring($request, true);
    }

    /**
     * Bulk disable monitoring
     * POST /api/v1/medicare-validation/bulk/disable-monitoring
     */
    public function bulkDisableMonitoring(Request $request): JsonResponse
    {
        return $this->bulkUpdateMonitoring($request, false);
    }

    // ===== REPORTING METHODS =====

    /**
     * Get compliance summary report
     * GET /api/v1/medicare-validation/reports/compliance-summary
     */
    public function getComplianceSummaryReport(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'facility_id' => 'sometimes|integer|exists:facilities,id',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date',
                'specialty' => 'sometimes|string',
            ]);

            $query = MedicareMacValidation::query();
            $this->applyCommonFilters($query, $request);

            if ($request->has('specialty')) {
                $query->bySpecialty($request->input('specialty'));
            }

            $validations = $query->get();

            $report = [
                'summary' => [
                    'total_validations' => $validations->count(),
                    'compliant_validations' => $validations->filter->isCompliant()->count(),
                    'average_compliance_score' => round($validations->avg(function ($v) {
                        return $v->getComplianceScore();
                    }), 2),
                    'high_risk_count' => $validations->where('reimbursement_risk', 'high')->count(),
                ],
                'by_status' => $validations->groupBy('validation_status')->map->count(),
                'by_specialty' => $validations->groupBy('provider_specialty')->map->count(),
                'by_validation_type' => $validations->groupBy('validation_type')->map->count(),
                'compliance_breakdown' => [
                    'coverage_met' => $validations->where('coverage_met', true)->count(),
                    'documentation_complete' => $validations->where('documentation_complete', true)->count(),
                    'frequency_compliant' => $validations->where('frequency_compliant', true)->count(),
                    'medical_necessity_met' => $validations->where('medical_necessity_met', true)->count(),
                    'billing_compliant' => $validations->where('billing_compliant', true)->count(),
                    'prior_auth_compliant' => $validations->filter(function ($v) {
                        return !$v->prior_auth_required || $v->prior_auth_obtained;
                    })->count(),
                ],
                'common_issues' => $this->getCommonMissingItems($validations),
                'generated_at' => now()->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'data' => $report
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate compliance summary report', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate compliance summary report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reimbursement risk report
     * GET /api/v1/medicare-validation/reports/reimbursement-risk
     */
    public function getReimbursementRiskReport(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'facility_id' => 'sometimes|integer|exists:facilities,id',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date',
            ]);

            $query = MedicareMacValidation::query();
            $this->applyCommonFilters($query, $request);

            $validations = $query->with(['order.facility'])->get();

            $report = [
                'risk_distribution' => $validations->groupBy('reimbursement_risk')->map->count(),
                'total_estimated_reimbursement' => $validations->sum('estimated_reimbursement'),
                'high_risk_details' => $validations->where('reimbursement_risk', 'high')->map(function ($v) {
                    return [
                        'validation_id' => $v->validation_id,
                        'order_id' => $v->order_id,
                        'facility_name' => $v->order->facility->name ?? 'Unknown',
                        'estimated_reimbursement' => $v->estimated_reimbursement,
                        'missing_items' => $v->getMissingComplianceItems(),
                        'validation_type' => $v->validation_type,
                    ];
                })->values(),
                'reimbursement_by_specialty' => $validations->groupBy('provider_specialty')->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'total_reimbursement' => $group->sum('estimated_reimbursement'),
                        'average_reimbursement' => round($group->avg('estimated_reimbursement'), 2),
                        'high_risk_count' => $group->where('reimbursement_risk', 'high')->count(),
                    ];
                }),
                'generated_at' => now()->toISOString(),
            ];

            return response()->json([
                'success' => true,
                'data' => $report
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate reimbursement risk report', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate reimbursement risk report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specialty performance report
     * GET /api/v1/medicare-validation/reports/specialty-performance
     */
    public function getSpecialtyPerformanceReport(Request $request): JsonResponse
    {
        try {
            $validations = MedicareMacValidation::with(['order.facility'])->get();

            $report = $validations->groupBy('provider_specialty')->map(function ($group, $specialty) {
                return [
                    'specialty' => $specialty ?? 'Unknown',
                    'total_validations' => $group->count(),
                    'compliance_rate' => round(($group->filter->isCompliant()->count() / $group->count()) * 100, 2),
                    'average_compliance_score' => round($group->avg(function ($v) {
                        return $v->getComplianceScore();
                    }), 2),
                    'validation_type_breakdown' => $group->groupBy('validation_type')->map->count(),
                    'common_issues' => $this->getCommonMissingItems($group),
                    'total_estimated_reimbursement' => $group->sum('estimated_reimbursement'),
                    'high_risk_count' => $group->where('reimbursement_risk', 'high')->count(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'specialty_performance' => $report,
                    'generated_at' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate specialty performance report', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate specialty performance report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get MAC contractor analysis
     * GET /api/v1/medicare-validation/reports/mac-contractor-analysis
     */
    public function getMacContractorAnalysis(Request $request): JsonResponse
    {
        try {
            $validations = MedicareMacValidation::with(['order.facility'])->get();

            $report = $validations->groupBy('mac_contractor')->map(function ($group, $contractor) {
                return [
                    'contractor' => $contractor ?? 'Unknown',
                    'total_validations' => $group->count(),
                    'compliance_rate' => round(($group->filter->isCompliant()->count() / $group->count()) * 100, 2),
                    'jurisdictions' => $group->groupBy('mac_jurisdiction')->map->count(),
                    'validation_types' => $group->groupBy('validation_type')->map->count(),
                    'average_compliance_score' => round($group->avg(function ($v) {
                        return $v->getComplianceScore();
                    }), 2),
                    'total_estimated_reimbursement' => $group->sum('estimated_reimbursement'),
                    'common_issues' => $this->getCommonMissingItems($group),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'mac_contractor_analysis' => $report,
                    'generated_at' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate MAC contractor analysis', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate MAC contractor analysis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get validation trends
     * GET /api/v1/medicare-validation/reports/validation-trends
     */
    public function getValidationTrends(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'period' => 'sometimes|string|in:daily,weekly,monthly',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date',
            ]);

            $period = $request->input('period', 'weekly');
            $dateFrom = $request->input('date_from', now()->subMonth()->toDateString());
            $dateTo = $request->input('date_to', now()->toDateString());

            $validations = MedicareMacValidation::whereBetween('validated_at', [$dateFrom, $dateTo])
                ->with(['order.facility'])
                ->get();

            $groupByFormat = match($period) {
                'daily' => 'Y-m-d',
                'weekly' => 'Y-W',
                'monthly' => 'Y-m',
                default => 'Y-W'
            };

            $trends = $validations->groupBy(function ($validation) use ($groupByFormat) {
                return $validation->validated_at?->format($groupByFormat) ?? 'unknown';
            })->map(function ($group, $period) {
                return [
                    'period' => $period,
                    'total_validations' => $group->count(),
                    'compliant_count' => $group->filter->isCompliant()->count(),
                    'compliance_rate' => round(($group->filter->isCompliant()->count() / $group->count()) * 100, 2),
                    'by_status' => $group->groupBy('validation_status')->map->count(),
                    'by_specialty' => $group->groupBy('provider_specialty')->map->count(),
                    'average_compliance_score' => round($group->avg(function ($v) {
                        return $v->getComplianceScore();
                    }), 2),
                ];
            })->sortKeys();

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => $period,
                    'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
                    'trends' => $trends,
                    'generated_at' => now()->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate validation trends', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate validation trends',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ===== HELPER METHODS =====

    /**
     * Get specialty validations (helper method)
     */
    private function getSpecialtyValidations(Request $request, string $specialty, string $specialtyName): JsonResponse
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

            $query = MedicareMacValidation::bySpecialty($specialty)
                ->with(['order.orderItems.product', 'order.facility', 'order.patient']);

            $this->applyCommonFilters($query, $request);

            $perPage = $request->input('per_page', 15);
            $validations = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => "{$specialtyName} validations retrieved",
                'data' => $validations->items(),
                'pagination' => [
                    'current_page' => $validations->currentPage(),
                    'last_page' => $validations->lastPage(),
                    'per_page' => $validations->perPage(),
                    'total' => $validations->total(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to retrieve {$specialtyName} validations", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => "Failed to retrieve {$specialtyName} validations",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specialty dashboard (helper method)
     */
    private function getSpecialtyDashboard(Request $request, string $specialty, string $specialtyName): JsonResponse
    {
        try {
            $query = MedicareMacValidation::bySpecialty($specialty);

            if ($request->has('facility_id')) {
                $query->where('facility_id', $request->input('facility_id'));
            }

            $summary = [
                'specialty' => $specialtyName,
                'total_validations' => $query->count(),
                'validated' => (clone $query)->validated()->count(),
                'failed' => (clone $query)->failed()->count(),
                'requires_review' => (clone $query)->requiresReview()->count(),
                'pending' => (clone $query)->pending()->count(),
                'high_risk' => (clone $query)->highRisk()->count(),
                'due_for_revalidation' => (clone $query)->dueForRevalidation()->count(),
                'daily_monitoring_enabled' => (clone $query)->dailyMonitoring()->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to generate {$specialtyName} dashboard", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => "Failed to generate {$specialtyName} dashboard",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get MAC contractor validations (helper method)
     */
    private function getMacContractorValidations(Request $request, string $contractor): JsonResponse
    {
        try {
            $request->validate([
                'facility_id' => 'sometimes|integer|exists:facilities,id',
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $query = MedicareMacValidation::byMacContractor($contractor)
                ->with(['order.orderItems.product', 'order.facility', 'order.patient']);

            $this->applyCommonFilters($query, $request);

            $perPage = $request->input('per_page', 15);
            $validations = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => "{$contractor} validations retrieved",
                'data' => $validations->items(),
                'pagination' => [
                    'current_page' => $validations->currentPage(),
                    'last_page' => $validations->lastPage(),
                    'per_page' => $validations->perPage(),
                    'total' => $validations->total(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to retrieve {$contractor} validations", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => "Failed to retrieve {$contractor} validations",
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply common filters to query
     */
    private function applyCommonFilters($query, Request $request): void
    {
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
    }

    /**
     * Bulk update monitoring (helper method)
     */
    private function bulkUpdateMonitoring(Request $request, bool $enabled): JsonResponse
    {
        try {
            $request->validate([
                'validation_ids' => 'required|array|min:1|max:50',
                'validation_ids.*' => 'string',
            ]);

            $updated = 0;
            $errors = [];

            foreach ($request->input('validation_ids') as $validationId) {
                try {
                    $validation = MedicareMacValidation::where('validation_id', $validationId)->firstOrFail();

                    $validation->update([
                        'daily_monitoring_enabled' => $enabled
                    ]);

                                         $validation->addAuditEntry('bulk_monitoring_' . ($enabled ? 'enabled' : 'disabled'), [
                         'user' => Auth::check() ? Auth::user()->name : 'system'
                     ]);

                    $updated++;

                } catch (\Exception $e) {
                    $errors[] = [
                        'validation_id' => $validationId,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Bulk monitoring " . ($enabled ? 'enabled' : 'disabled') . ": {$updated} successful, " . count($errors) . " failed",
                'data' => [
                    'updated' => $updated,
                    'errors' => $errors
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Bulk monitoring update failed', [
                'enabled' => $enabled,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Bulk monitoring update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get common missing compliance items
     */
    private function getCommonMissingItems($validations): array
    {
        $allMissingItems = $validations->flatMap(function ($validation) {
            return $validation->getMissingComplianceItems();
        });

        return $allMissingItems->countBy()->sortDesc()->take(10)->toArray();
    }
}

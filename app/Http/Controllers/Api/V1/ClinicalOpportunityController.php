<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ClinicalOpportunityEngine\ClinicalOpportunityService;
use App\Models\ClinicalOpportunity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ClinicalOpportunityController extends Controller
{
    protected $clinicalOpportunityService;

    public function __construct(ClinicalOpportunityService $clinicalOpportunityService)
    {
        $this->clinicalOpportunityService = $clinicalOpportunityService;
    }

    /**
     * Get clinical opportunities for a patient
     */
    public function getOpportunities(Request $request, string $patientId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'categories' => 'array',
                'categories.*' => 'string|in:wound_care,diabetes_management,quality_improvement,preventive_care',
                'min_confidence' => 'numeric|min:0|max:1',
                'limit' => 'integer|min:1|max:50',
                'use_ai' => 'boolean',
                'force_refresh' => 'boolean'
            ]);

            $options = array_merge([
                'use_ai' => true,
                'force_refresh' => false,
                'limit' => 20
            ], $validated);

            $result = $this->clinicalOpportunityService->identifyOpportunities($patientId, $options);

            if ($result['success']) {
                return response()->json($result);
            }

            return response()->json($result, 500);

        } catch (\Exception $e) {
            Log::error('Failed to get clinical opportunities', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve clinical opportunities',
                'message' => 'An error occurred while processing your request.'
            ], 500);
        }
    }

    /**
     * Get details for a specific opportunity
     */
    public function getOpportunityDetails(string $opportunityId): JsonResponse
    {
        try {
            $result = $this->clinicalOpportunityService->getOpportunityDetails($opportunityId);

            if ($result['success']) {
                return response()->json($result);
            }

            return response()->json($result, 404);

        } catch (\Exception $e) {
            Log::error('Failed to get opportunity details', [
                'opportunity_id' => $opportunityId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve opportunity details'
            ], 500);
        }
    }

    /**
     * Take action on an opportunity
     */
    public function takeAction(Request $request, string $opportunityId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => 'required|string|in:order_product,schedule_assessment,refer_specialist,update_care_plan,dismiss',
                'data' => 'array',
                'notes' => 'string|nullable'
            ]);

            $actionData = array_merge($validated, [
                'user_id' => Auth::id()
            ]);

            $result = $this->clinicalOpportunityService->takeAction($opportunityId, $actionData);

            if ($result['success']) {
                return response()->json($result);
            }

            return response()->json($result, 400);

        } catch (\Exception $e) {
            Log::error('Failed to take action on opportunity', [
                'opportunity_id' => $opportunityId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to complete action'
            ], 500);
        }
    }

    /**
     * Get opportunities dashboard data
     */
    public function getDashboard(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'date_from' => 'date',
                'date_to' => 'date',
                'provider_id' => 'integer|exists:users,id',
                'category' => 'string'
            ]);

            $query = ClinicalOpportunity::query()
                ->with(['patient', 'provider'])
                ->active();

            // Apply filters
            if (isset($validated['date_from'])) {
                $query->where('identified_at', '>=', $validated['date_from']);
            }

            if (isset($validated['date_to'])) {
                $query->where('identified_at', '<=', $validated['date_to']);
            }

            if (isset($validated['provider_id'])) {
                $query->where('provider_id', $validated['provider_id']);
            }

            if (isset($validated['category'])) {
                $query->where('category', $validated['category']);
            }

            // Get statistics
            $stats = [
                'total_opportunities' => $query->count(),
                'requiring_action' => $query->clone()->requireingAction()->count(),
                'high_priority' => $query->clone()->highPriority()->count(),
                'by_category' => $query->clone()
                    ->selectRaw('category, COUNT(*) as count')
                    ->groupBy('category')
                    ->pluck('count', 'category'),
                'by_status' => $query->clone()
                    ->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status'),
                'recent_opportunities' => $query->clone()
                    ->orderBy('identified_at', 'desc')
                    ->limit(10)
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get dashboard data', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve dashboard data'
            ], 500);
        }
    }

    /**
     * Get opportunity trends
     */
    public function getTrends(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'period' => 'string|in:day,week,month',
                'category' => 'string',
                'limit' => 'integer|min:1|max:365'
            ]);

            $period = $validated['period'] ?? 'week';
            $limit = $validated['limit'] ?? 30;

            $query = ClinicalOpportunity::query()
                ->selectRaw("DATE_TRUNC(?, identified_at) as period, COUNT(*) as count, AVG(confidence_score) as avg_confidence", [$period])
                ->groupBy('period')
                ->orderBy('period', 'desc')
                ->limit($limit);

            if (isset($validated['category'])) {
                $query->where('category', $validated['category']);
            }

            $trends = $query->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'trends' => $trends,
                    'period' => $period
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get trends', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve trends'
            ], 500);
        }
    }

    /**
     * Dismiss an opportunity
     */
    public function dismiss(Request $request, string $opportunityId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'required|string|max:500'
            ]);

            $opportunity = ClinicalOpportunity::findOrFail($opportunityId);
            $opportunity->dismiss($validated['reason']);

            // Log the dismissal as an action
            $this->clinicalOpportunityService->takeAction($opportunityId, [
                'type' => 'dismiss',
                'user_id' => Auth::id(),
                'data' => ['reason' => $validated['reason']]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Opportunity dismissed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to dismiss opportunity', [
                'opportunity_id' => $opportunityId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to dismiss opportunity'
            ], 500);
        }
    }

    /**
     * Get opportunity history for a patient
     */
    public function getPatientHistory(string $patientId): JsonResponse
    {
        try {
            $opportunities = ClinicalOpportunity::where('patient_id', $patientId)
                ->with(['actions', 'provider'])
                ->orderBy('identified_at', 'desc')
                ->get();

            $stats = [
                'total' => $opportunities->count(),
                'active' => $opportunities->where('status', 'identified')->count(),
                'resolved' => $opportunities->where('status', 'resolved')->count(),
                'dismissed' => $opportunities->where('status', 'dismissed')->count()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'opportunities' => $opportunities,
                    'statistics' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get patient history', [
                'patient_id' => $patientId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve patient history'
            ], 500);
        }
    }
}
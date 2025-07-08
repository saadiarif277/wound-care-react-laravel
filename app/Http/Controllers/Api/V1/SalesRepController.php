<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SalesRep;
use App\Models\NewUser;
use App\Services\SalesAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesRepController extends Controller
{
    protected SalesAssignmentService $assignmentService;

    public function __construct(SalesAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    /**
     * Display a listing of sales reps.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SalesRep::with(['user', 'parentRep.user']);

        // Apply filters
        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->filled('territory')) {
            $query->inTerritory($request->territory);
        }

        if ($request->filled('region')) {
            $query->inRegion($request->region);
        }

        if ($request->filled('rep_type')) {
            $query->where('rep_type', $request->rep_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = min($request->input('per_page', 20), 100);
        $salesReps = $query->paginate($perPage);

        return response()->json([
            'data' => $salesReps->items(),
            'meta' => [
                'current_page' => $salesReps->currentPage(),
                'last_page' => $salesReps->lastPage(),
                'per_page' => $salesReps->perPage(),
                'total' => $salesReps->total(),
            ],
        ]);
    }

    /**
     * Store a newly created sales rep.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id|unique:sales_reps,user_id',
            'parent_rep_id' => 'nullable|exists:sales_reps,id',
            'territory' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:100',
            'commission_rate_direct' => 'required|numeric|min:0|max:100',
            'sub_rep_parent_share_percentage' => 'required|numeric|min:0|max:100',
            'rep_type' => 'required|in:direct,independent,distributor',
            'commission_tier' => 'required|in:bronze,silver,gold,platinum',
            'can_have_sub_reps' => 'boolean',
            'hired_date' => 'nullable|date',
        ]);

        try {
            DB::beginTransaction();

            $salesRep = SalesRep::create($validated);

            // Update user role if needed
            $user = NewUser::find($validated['user_id']);
            // Add sales rep role assignment logic here

            DB::commit();

            return response()->json([
                'message' => 'Sales rep created successfully',
                'data' => $salesRep->load(['user', 'parentRep']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create sales rep',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified sales rep.
     */
    public function show(string $id): JsonResponse
    {
        $salesRep = SalesRep::with([
            'user',
            'parentRep.user',
            'subReps.user',
            'providerAssignments',
            'facilityAssignments',
            'targets' => function ($q) {
                $q->where('target_year', date('Y'));
            },
        ])->findOrFail($id);

        // Calculate current performance
        $performance = $salesRep->calculatePerformanceMetrics();

        return response()->json([
            'data' => array_merge($salesRep->toArray(), [
                'current_performance' => $performance,
            ]),
        ]);
    }

    /**
     * Update the specified sales rep.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $salesRep = SalesRep::findOrFail($id);

        $validated = $request->validate([
            'parent_rep_id' => 'nullable|exists:sales_reps,id',
            'territory' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:100',
            'commission_rate_direct' => 'nullable|numeric|min:0|max:100',
            'sub_rep_parent_share_percentage' => 'nullable|numeric|min:0|max:100',
            'rep_type' => 'nullable|in:direct,independent,distributor',
            'commission_tier' => 'nullable|in:bronze,silver,gold,platinum',
            'can_have_sub_reps' => 'boolean',
            'is_active' => 'boolean',
            'terminated_date' => 'nullable|date',
        ]);

        try {
            DB::beginTransaction();

            // Prevent circular parent relationships
            if (isset($validated['parent_rep_id'])) {
                if ($this->wouldCreateCircularRelationship($id, $validated['parent_rep_id'])) {
                    return response()->json([
                        'message' => 'Cannot create circular parent relationship',
                    ], 422);
                }
            }

            $salesRep->update($validated);

            DB::commit();

            return response()->json([
                'message' => 'Sales rep updated successfully',
                'data' => $salesRep->fresh(['user', 'parentRep']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update sales rep',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified sales rep.
     */
    public function destroy(string $id): JsonResponse
    {
        $salesRep = SalesRep::findOrFail($id);

        // Check for dependencies
        if ($salesRep->hasSubReps()) {
            return response()->json([
                'message' => 'Cannot delete sales rep with sub-reps',
            ], 422);
        }

        if ($salesRep->providerAssignments()->active()->exists()) {
            return response()->json([
                'message' => 'Cannot delete sales rep with active assignments',
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Soft delete by deactivating
            $salesRep->update([
                'is_active' => false,
                'terminated_date' => now(),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Sales rep deactivated successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to deactivate sales rep',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get provider assignments for a sales rep.
     */
    public function providerAssignments(string $id): JsonResponse
    {
        $salesRep = SalesRep::findOrFail($id);
        
        $assignments = $this->assignmentService->getProvidersForRep($id);

        return response()->json([
            'data' => $assignments,
        ]);
    }

    /**
     * Get facility assignments for a sales rep.
     */
    public function facilityAssignments(string $id): JsonResponse
    {
        $salesRep = SalesRep::findOrFail($id);
        
        $assignments = $this->assignmentService->getFacilitiesForRep($id);

        return response()->json([
            'data' => $assignments,
        ]);
    }

    /**
     * Get performance metrics for a sales rep.
     */
    public function performance(Request $request, string $id): JsonResponse
    {
        $salesRep = SalesRep::findOrFail($id);

        $period = $request->input('period', 'monthly');
        $startDate = $request->input('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->endOfMonth()->format('Y-m-d'));

        // This would calculate detailed performance metrics
        $performance = [
            'revenue' => 0, // Calculate from orders
            'commission_earned' => 0, // Calculate from commission records
            'orders_count' => 0, // Count orders
            'new_providers' => 0, // Count new provider assignments
            'target_achievement' => 0, // Compare to targets
        ];

        return response()->json([
            'data' => $performance,
            'period' => [
                'type' => $period,
                'start' => $startDate,
                'end' => $endDate,
            ],
        ]);
    }

    /**
     * Check if assignment would create circular relationship.
     */
    protected function wouldCreateCircularRelationship(string $repId, string $parentId): bool
    {
        if ($repId === $parentId) {
            return true;
        }

        $current = SalesRep::find($parentId);
        while ($current && $current->parent_rep_id) {
            if ($current->parent_rep_id === $repId) {
                return true;
            }
            $current = $current->parentRep;
        }

        return false;
    }
}
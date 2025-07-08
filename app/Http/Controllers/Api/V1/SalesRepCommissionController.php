<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SalesRep;
use App\Models\NewCommissionRecord;
use App\Models\CommissionPayout;
use App\Services\CommissionPayoutService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SalesRepCommissionController extends Controller
{
    protected CommissionPayoutService $payoutService;

    public function __construct(CommissionPayoutService $payoutService)
    {
        $this->payoutService = $payoutService;
    }

    /**
     * Get commission summary for current user or specified rep.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = Auth::user();
        $salesRep = $this->getSalesRepForUser($user, $request->input('rep_id'));

        if (!$salesRep) {
            return response()->json(['message' => 'Sales rep not found'], 404);
        }

        $startDate = Carbon::parse($request->input('start_date', now()->startOfMonth()));
        $endDate = Carbon::parse($request->input('end_date', now()->endOfMonth()));

        $query = NewCommissionRecord::where('sales_rep_id', $salesRep->id)
            ->whereBetween('created_at', [$startDate, $endDate]);

        // Include sub-rep commissions if viewing as parent rep
        if ($salesRep->can_have_sub_reps && $request->boolean('include_team')) {
            $subRepIds = $salesRep->subReps()->pluck('id');
            $query->orWhereIn('sales_rep_id', $subRepIds);
        }

        $summary = [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'totals' => [
                'pending' => (clone $query)->pending()->sum('commission_amount'),
                'approved' => (clone $query)->approved()->sum('commission_amount'),
                'paid' => (clone $query)->paid()->sum('commission_amount'),
                'total' => (clone $query)->sum('commission_amount'),
            ],
            'counts' => [
                'pending' => (clone $query)->pending()->count(),
                'approved' => (clone $query)->approved()->count(),
                'paid' => (clone $query)->paid()->count(),
                'total' => (clone $query)->count(),
            ],
            'by_split_type' => (clone $query)->select('split_type')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('SUM(commission_amount) as total')
                ->groupBy('split_type')
                ->get(),
            'average_rate' => (clone $query)->where('base_amount', '>', 0)
                ->selectRaw('AVG(commission_amount / base_amount * 100) as rate')
                ->value('rate') ?? 0,
        ];

        // Get next payout date
        $nextPayout = CommissionPayout::where('sales_rep_id', $salesRep->id)
            ->where('status', 'calculated')
            ->orderBy('period_end')
            ->first();

        if ($nextPayout) {
            $summary['next_payout'] = [
                'date' => $nextPayout->period_end->addDays(15)->format('Y-m-d'),
                'amount' => $nextPayout->net_amount,
            ];
        }

        return response()->json(['data' => $summary]);
    }

    /**
     * Get detailed commission records.
     */
    public function details(Request $request): JsonResponse
    {
        $user = Auth::user();
        $salesRep = $this->getSalesRepForUser($user, $request->input('rep_id'));

        if (!$salesRep) {
            return response()->json(['message' => 'Sales rep not found'], 404);
        }

        $query = NewCommissionRecord::with(['order', 'rule', 'payout'])
            ->where('sales_rep_id', $salesRep->id);

        // Apply filters
        if ($request->filled('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('created_at', '<=', $request->end_date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('split_type')) {
            $query->where('split_type', $request->split_type);
        }

        if ($request->filled('order_number')) {
            $query->whereHas('order', function ($q) use ($request) {
                $q->where('order_number', 'like', "%{$request->order_number}%");
            });
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = min($request->input('per_page', 20), 100);
        $records = $query->paginate($perPage);

        return response()->json([
            'data' => $records->items(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    /**
     * Get commission analytics.
     */
    public function analytics(Request $request): JsonResponse
    {
        $user = Auth::user();
        $salesRep = $this->getSalesRepForUser($user, $request->input('rep_id'));

        if (!$salesRep) {
            return response()->json(['message' => 'Sales rep not found'], 404);
        }

        $period = $request->input('period', 'monthly');
        $startDate = Carbon::parse($request->input('start_date', now()->subMonths(6)));
        $endDate = Carbon::parse($request->input('end_date', now()));

        $analytics = [
            'commission_trend' => $this->getCommissionTrend($salesRep->id, $period, $startDate, $endDate),
            'performance_metrics' => $this->getPerformanceMetrics($salesRep->id, $startDate, $endDate),
            'top_products' => $this->getTopProducts($salesRep->id, $startDate, $endDate),
            'commission_by_category' => $this->getCommissionByCategory($salesRep->id, $startDate, $endDate),
        ];

        return response()->json(['data' => $analytics]);
    }

    /**
     * Get payout history.
     */
    public function payouts(Request $request): JsonResponse
    {
        $user = Auth::user();
        $salesRep = $this->getSalesRepForUser($user, $request->input('rep_id'));

        if (!$salesRep) {
            return response()->json(['message' => 'Sales rep not found'], 404);
        }

        $query = CommissionPayout::where('sales_rep_id', $salesRep->id)
            ->with(['approver']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('year')) {
            $query->whereYear('period_start', $request->year);
        }

        $perPage = min($request->input('per_page', 12), 50);
        $payouts = $query->orderBy('period_end', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $payouts->items(),
            'meta' => [
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
            ],
            'summary' => [
                'total_paid' => $query->paid()->sum('net_amount'),
                'total_pending' => $query->whereIn('status', ['calculated', 'approved'])->sum('net_amount'),
            ],
        ]);
    }

    /**
     * Download commission statement.
     */
    public function downloadStatement(Request $request, string $payoutId): JsonResponse
    {
        $user = Auth::user();
        $payout = CommissionPayout::findOrFail($payoutId);

        // Verify access
        $salesRep = $this->getSalesRepForUser($user);
        if (!$salesRep || $payout->sales_rep_id !== $salesRep->id) {
            // Check if user has admin access
            if (!$user->hasPermission('commissions.view_all')) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        // Generate PDF statement
        // This would use a PDF generation service
        // For now, return the data that would be in the statement
        $statementData = [
            'payout' => $payout,
            'sales_rep' => $payout->salesRep,
            'commission_records' => $payout->commissionRecords()->with('order')->get(),
            'summary' => [
                'gross_amount' => $payout->gross_amount,
                'deductions' => $payout->deductions,
                'net_amount' => $payout->net_amount,
            ],
        ];

        return response()->json([
            'message' => 'Statement data retrieved',
            'data' => $statementData,
        ]);
    }

    /**
     * Get sales rep for user.
     */
    protected function getSalesRepForUser($user, ?string $repId = null): ?SalesRep
    {
        if ($repId && $user->hasPermission('commissions.view_all')) {
            return SalesRep::find($repId);
        }

        return SalesRep::where('user_id', $user->id)->first();
    }

    /**
     * Get commission trend data.
     */
    protected function getCommissionTrend(string $repId, string $period, Carbon $startDate, Carbon $endDate): array
    {
        $groupBy = match($period) {
            'daily' => 'DATE(created_at)',
            'weekly' => 'YEARWEEK(created_at)',
            'monthly' => 'DATE_FORMAT(created_at, "%Y-%m")',
            'quarterly' => 'CONCAT(YEAR(created_at), "-Q", QUARTER(created_at))',
            'yearly' => 'YEAR(created_at)',
            default => 'DATE_FORMAT(created_at, "%Y-%m")',
        };

        return NewCommissionRecord::where('sales_rep_id', $repId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("{$groupBy} as period")
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(commission_amount) as total')
            ->selectRaw('AVG(commission_amount) as average')
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }

    /**
     * Get performance metrics.
     */
    protected function getPerformanceMetrics(string $repId, Carbon $startDate, Carbon $endDate): array
    {
        $commissions = NewCommissionRecord::where('sales_rep_id', $repId)
            ->whereBetween('created_at', [$startDate, $endDate]);

        return [
            'total_earned' => $commissions->sum('commission_amount'),
            'average_per_order' => $commissions->avg('commission_amount') ?? 0,
            'total_orders' => $commissions->distinct('order_id')->count('order_id'),
            'conversion_rate' => 0, // Would calculate based on leads/orders
            'average_order_value' => $commissions->avg('base_amount') ?? 0,
        ];
    }

    /**
     * Get top products by commission.
     */
    protected function getTopProducts(string $repId, Carbon $startDate, Carbon $endDate): array
    {
        return NewCommissionRecord::where('sales_rep_id', $repId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->join('order_items', 'commission_records.order_id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select('products.name', 'products.sku')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(commission_records.commission_amount) as total')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get commission by category.
     */
    protected function getCommissionByCategory(string $repId, Carbon $startDate, Carbon $endDate): array
    {
        return NewCommissionRecord::where('sales_rep_id', $repId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->join('order_items', 'commission_records.order_id', '=', 'order_items.order_id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select('products.category')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(commission_records.commission_amount) as total')
            ->groupBy('products.category')
            ->orderBy('total', 'desc')
            ->get()
            ->toArray();
    }
}
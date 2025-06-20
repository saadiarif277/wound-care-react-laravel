<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commissions\CommissionRecord;
use App\Models\MscSalesRep;
use App\Models\User;
use App\Models\Order\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesRepCommissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:msc-rep,msc-subrep,msc-admin');
    }

    /**
     * Get commission summary for sales rep dashboard
     */
    public function getSummary(Request $request)
    {
        $user = Auth::user();
        $repId = $this->getRepId($user);

        $dateFrom = $request->input('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->endOfMonth()->format('Y-m-d'));

        $query = CommissionRecord::where('rep_id', $repId)
                    ->whereBetween('calculation_date', [$dateFrom, $dateTo]);

        // Include sub-rep commissions if user is a parent rep
        if ($user->hasRole('msc-rep')) {
            $query->orWhere('parent_rep_id', $repId);
        }

        $totals = [
            'paid' => (clone $query)->where('status', 'paid')->sum('amount'),
            'pending' => (clone $query)->where('status', 'pending')->sum('amount'),
            'processing' => (clone $query)->where('status', 'approved')->sum('amount'),
        ];

        $byStatus = [
            'paid' => [
                'count' => (clone $query)->where('status', 'paid')->count(),
                'amount' => $totals['paid']
            ],
            'pending' => [
                'count' => (clone $query)->where('status', 'pending')->count(),
                'amount' => $totals['pending']
            ],
            'processing' => [
                'count' => (clone $query)->where('status', 'approved')->count(),
                'amount' => $totals['processing']
            ]
        ];

        // Calculate average payout days
        $avgPayoutDays = CommissionRecord::where('rep_id', $repId)
            ->where('status', 'paid')
            ->whereNotNull('payment_date')
            ->selectRaw('AVG(DATEDIFF(payment_date, calculation_date)) as avg_days')
            ->value('avg_days') ?? 0;

        // Next payout date (typically 15th of next month)
        $nextPayoutDate = now()->addMonth()->startOfMonth()->addDays(14)->format('Y-m-d');

        return response()->json([
            'dateRange' => [
                'start' => $dateFrom,
                'end' => $dateTo
            ],
            'totals' => $totals,
            'byStatus' => $byStatus,
            'averagePayoutDays' => round($avgPayoutDays, 1),
            'nextPayoutDate' => $nextPayoutDate
        ]);
    }

    /**
     * Get detailed commission records
     */
    public function getDetails(Request $request)
    {
        $user = Auth::user();
        $repId = $this->getRepId($user);

        $query = CommissionRecord::with([
            'order.provider',
            'order.facility',
            'orderItem.product.manufacturer',
            'rep',
            'parentRep'
        ])->where('rep_id', $repId);

        // Include sub-rep commissions if user is a parent rep
        if ($user->hasRole('msc-rep')) {
            $query->orWhere('parent_rep_id', $repId);
        }

        // Apply filters
        if ($request->filled('date_from')) {
            $query->where('calculation_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('calculation_date', '<=', $request->date_to);
        }

        if ($request->filled('status')) {
            $statuses = is_array($request->status) ? $request->status : [$request->status];
            $query->whereIn('status', $statuses);
        }

        if ($request->filled('provider')) {
            $query->whereHas('order.provider', function($q) use ($request) {
                $q->where('id', $request->provider);
            });
        }

        if ($request->filled('manufacturer')) {
            $query->whereHas('orderItem.product.manufacturer', function($q) use ($request) {
                $q->where('id', $request->manufacturer);
            });
        }

        $perPage = min($request->input('per_page', 50), 100);
        $records = $query->orderBy('calculation_date', 'desc')->paginate($perPage);

        $data = $records->getCollection()->map(function ($record) {
            return $this->formatCommissionDetail($record);
        });

        return response()->json([
            'data' => $data,
            'pagination' => [
                'page' => $records->currentPage(),
                'perPage' => $records->perPage(),
                'total' => $records->total(),
                'lastPage' => $records->lastPage()
            ]
        ]);
    }

    /**
     * Get delayed payments report
     */
    public function getDelayedPayments(Request $request)
    {
        $user = Auth::user();
        $repId = $this->getRepId($user);
        $thresholdDays = $request->input('threshold_days', 60);

        $cutoffDate = now()->subDays($thresholdDays);

        $query = CommissionRecord::with([
            'order.provider',
            'order.facility'
        ])->where('rep_id', $repId)
          ->where('status', 'pending')
          ->where('calculation_date', '<=', $cutoffDate);

        // Include sub-rep delayed payments if user is a parent rep
        if ($user->hasRole('msc-rep')) {
            $query->orWhere(function($q) use ($repId, $cutoffDate) {
                $q->where('parent_rep_id', $repId)
                  ->where('status', 'pending')
                  ->where('calculation_date', '<=', $cutoffDate);
            });
        }

        $delayedRecords = $query->get();

        $data = $delayedRecords->map(function ($record) use ($thresholdDays) {
            $daysDelayed = now()->diffInDays($record->calculation_date) - $thresholdDays;

            return [
                'orderId' => $record->order->request_number ?? $record->order_id,
                'invoiceNumber' => $record->invoice_number,
                'daysDelayed' => $daysDelayed,
                'originalDueDate' => $record->calculation_date->addDays($thresholdDays)->format('Y-m-d'),
                'amount' => $record->amount,
                'reason' => $this->getDelayReason($record),
                'provider' => $record->order->provider->full_name ?? 'Unknown Provider',
                'facility' => $record->order->facility->name ?? 'Unknown Facility'
            ];
        });

        $summary = [
            'totalDelayed' => $delayedRecords->count(),
            'totalAmount' => $delayedRecords->sum('amount'),
            'averageDelay' => $delayedRecords->avg(function($record) use ($thresholdDays) {
                return now()->diffInDays($record->calculation_date) - $thresholdDays;
            })
        ];

        return response()->json([
            'thresholdDays' => $thresholdDays,
            'data' => $data,
            'summary' => $summary
        ]);
    }

    /**
     * Get commission analytics for charts and metrics
     */
    public function getAnalytics(Request $request)
    {
        $user = Auth::user();
        $repId = $this->getRepId($user);

        $period = $request->input('period', 'monthly'); // monthly, quarterly, yearly
        $dateFrom = $request->input('date_from', now()->subMonths(6)->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));

        $analytics = [
            'commissionTrend' => $this->getCommissionTrend($repId, $period, $dateFrom, $dateTo),
            'topProviders' => $this->getTopProviders($repId, $dateFrom, $dateTo),
            'productPerformance' => $this->getProductPerformance($repId, $dateFrom, $dateTo),
            'monthlyTargets' => $this->getMonthlyTargets($repId),
            'paymentTimeline' => $this->getPaymentTimeline($repId, $dateFrom, $dateTo)
        ];

        return response()->json(['data' => $analytics]);
    }

    /**
     * Private helper methods
     */
    private function getRepId($user)
    {
        // If user is already a sales rep, return their ID
        if ($user->hasRole(['msc-rep', 'msc-subrep'])) {
            $rep = MscSalesRep::where('email', $user->email)->first();
            return $rep ? $rep->id : $user->id;
        }

        return $user->id;
    }

    private function formatCommissionDetail($record)
    {
        $order = $record->order;
        $orderItem = $record->orderItem;
        $product = $orderItem ? $orderItem->product : null;

        // Determine split information
        $split = null;
        if ($record->parent_rep_id) {
            $totalCommission = CommissionRecord::where('order_id', $record->order_id)
                ->where('order_item_id', $record->order_item_id)
                ->sum('amount');

            $split = [
                'type' => 'sub-rep',
                'repAmount' => $totalCommission - $record->amount,
                'subRepAmount' => $record->amount,
                'repPercentage' => round((($totalCommission - $record->amount) / $totalCommission) * 100, 1),
                'subRepPercentage' => round(($record->amount / $totalCommission) * 100, 1)
            ];
        }

        return [
            'id' => $record->id,
            'orderId' => $order->request_number ?? $record->order_id,
            'invoiceNumber' => $record->invoice_number,
            'providerName' => $order->provider->full_name ?? 'Unknown Provider',
            'facilityName' => $order->facility->name ?? 'Unknown Facility',
            'friendlyPatientId' => $record->friendly_patient_id,
            'dateOfService' => $order->created_at->format('Y-m-d'),
            'firstApplicationDate' => $record->first_application_date,
            'product' => [
                'name' => $product->name ?? 'Unknown Product',
                'manufacturer' => $product->manufacturer->name ?? 'Unknown Manufacturer',
                'sizes' => $this->extractSizes($orderItem),
                'qCode' => $product->q_code ?? null
            ],
            'orderValue' => $order->total_amount ?? 0,
            'commissionAmount' => $record->amount,
            'split' => $split,
            'status' => $record->status,
            'paymentDate' => $record->payment_date,
            'payoutBatch' => $record->payout_id ? "PAYOUT-{$record->payout_id}" : null,
            'tissueIds' => $record->tissue_ids ?? []
        ];
    }

    private function getDelayReason($record)
    {
        // Logic to determine delay reason based on order status, payment status, etc.
        $order = $record->order;

        if ($order->status === 'pending') {
            return 'Order pending fulfillment';
        } elseif ($order->payment_status === 'pending') {
            return 'Payment not received';
        } elseif (!$record->first_application_date) {
            return 'First application date not recorded';
        } else {
            return 'Processing delay';
        }
    }

    private function extractSizes($orderItem)
    {
        if (!$orderItem) return [];

        // Extract sizes from order item description or product attributes
        // This would need to be implemented based on your data structure
        return ['4x4cm']; // Placeholder
    }

    private function getCommissionTrend($repId, $period, $dateFrom, $dateTo)
    {
        $query = CommissionRecord::where('rep_id', $repId)
            ->whereBetween('calculation_date', [$dateFrom, $dateTo]);

        $groupBy = match($period) {
            'monthly' => 'YEAR(calculation_date), MONTH(calculation_date)',
            'quarterly' => 'YEAR(calculation_date), QUARTER(calculation_date)',
            'yearly' => 'YEAR(calculation_date)',
            default => 'YEAR(calculation_date), MONTH(calculation_date)'
        };

        return $query->selectRaw("
            {$groupBy},
            SUM(amount) as total_commission,
            COUNT(*) as commission_count,
            AVG(amount) as avg_commission
        ")
        ->groupByRaw($groupBy)
        ->orderBy('calculation_date')
        ->get();
    }

    private function getTopProviders($repId, $dateFrom, $dateTo)
    {
        return CommissionRecord::with(['order.provider'])
            ->where('rep_id', $repId)
            ->whereBetween('calculation_date', [$dateFrom, $dateTo])
            ->where('status', 'paid')
            ->selectRaw('
                COUNT(*) as order_count,
                SUM(amount) as total_commission,
                AVG(amount) as avg_commission
            ')
            ->groupBy('order.provider_id')
            ->orderBy('total_commission', 'desc')
            ->limit(10)
            ->get();
    }

    private function getProductPerformance($repId, $dateFrom, $dateTo)
    {
        return CommissionRecord::with(['orderItem.product'])
            ->where('rep_id', $repId)
            ->whereBetween('calculation_date', [$dateFrom, $dateTo])
            ->where('status', 'paid')
            ->selectRaw('
                COUNT(*) as units_sold,
                SUM(amount) as total_commission
            ')
            ->groupBy('orderItem.product_id')
            ->orderBy('total_commission', 'desc')
            ->limit(10)
            ->get();
    }

    private function getMonthlyTargets($repId)
    {
        // This would come from a targets table - placeholder for now
        return [
            'current_month_target' => 25000,
            'current_month_actual' => 18450,
            'achievement_percentage' => 73.8
        ];
    }

    private function getPaymentTimeline($repId, $dateFrom, $dateTo)
    {
        return CommissionRecord::where('rep_id', $repId)
            ->whereBetween('calculation_date', [$dateFrom, $dateTo])
            ->selectRaw('
                status,
                COUNT(*) as count,
                SUM(amount) as amount,
                AVG(DATEDIFF(COALESCE(payment_date, NOW()), calculation_date)) as avg_days_to_payment
            ')
            ->groupBy('status')
            ->get();
    }
}

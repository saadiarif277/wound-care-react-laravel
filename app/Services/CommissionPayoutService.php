<?php

namespace App\Services;

use App\Models\SalesRep;
use App\Models\CommissionPayout;
use App\Models\NewCommissionRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionPayoutService
{
    /**
     * Generate payouts for a period.
     */
    public function generatePayouts(Carbon $periodStart, Carbon $periodEnd, ?string $tenantId = null): Collection
    {
        try {
            DB::beginTransaction();

            $payouts = collect();

            // Get all sales reps with approved commissions
            $salesReps = $this->getSalesRepsWithCommissions($periodStart, $periodEnd, $tenantId);

            foreach ($salesReps as $salesRep) {
                $payout = $this->createPayoutForRep($salesRep, $periodStart, $periodEnd);
                if ($payout) {
                    $payouts->push($payout);
                }
            }

            DB::commit();
            return $payouts;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to generate payouts: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get sales reps with approved commissions.
     */
    protected function getSalesRepsWithCommissions(Carbon $periodStart, Carbon $periodEnd, ?string $tenantId): Collection
    {
        $query = NewCommissionRecord::approved()
            ->withoutPayout()
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->select('sales_rep_id')
            ->distinct();

        if ($tenantId) {
            $query->whereHas('order', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            });
        }

        $repIds = $query->pluck('sales_rep_id');

        return SalesRep::whereIn('id', $repIds)->get();
    }

    /**
     * Create payout for a sales rep.
     */
    protected function createPayoutForRep(SalesRep $salesRep, Carbon $periodStart, Carbon $periodEnd): ?CommissionPayout
    {
        // Get approved commissions for this rep
        $commissions = NewCommissionRecord::approved()
            ->withoutPayout()
            ->where('sales_rep_id', $salesRep->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->get();

        if ($commissions->isEmpty()) {
            return null;
        }

        // Calculate totals
        $grossAmount = $commissions->sum('commission_amount');
        $deductions = $this->calculateDeductions($salesRep, $grossAmount);
        $netAmount = $grossAmount - $deductions;

        // Create payout record
        $payout = CommissionPayout::create([
            'sales_rep_id' => $salesRep->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'gross_amount' => $grossAmount,
            'deductions' => $deductions,
            'net_amount' => $netAmount,
            'commission_count' => $commissions->count(),
            'status' => 'calculated',
            'summary_data' => $this->generateSummaryData($commissions),
        ]);

        // Link commissions to payout
        $commissions->each(function ($commission) use ($payout) {
            $commission->update([
                'payout_id' => $payout->id,
                'status' => 'included_in_payout',
            ]);
        });

        return $payout;
    }

    /**
     * Calculate deductions.
     */
    protected function calculateDeductions(SalesRep $salesRep, float $grossAmount): float
    {
        // This is a placeholder - implement actual deduction logic
        // Could include things like:
        // - Tax withholding
        // - Benefit deductions
        // - Advance repayments
        // - Chargebacks
        return 0;
    }

    /**
     * Generate summary data for payout.
     */
    protected function generateSummaryData(Collection $commissions): array
    {
        return [
            'by_split_type' => $commissions->groupBy('split_type')->map->count(),
            'by_status' => $commissions->groupBy('status')->map->count(),
            'total_base_amount' => $commissions->sum('base_amount'),
            'average_commission_rate' => $commissions->avg(function ($c) {
                return $c->base_amount > 0 ? ($c->commission_amount / $c->base_amount) * 100 : 0;
            }),
            'order_ids' => $commissions->pluck('order_id')->unique()->values(),
        ];
    }

    /**
     * Approve a payout.
     */
    public function approvePayout(CommissionPayout $payout, string $approvedBy): void
    {
        if (!$payout->isCalculated()) {
            throw new \Exception("Payout must be in calculated status to approve");
        }

        $payout->approve($approvedBy);
    }

    /**
     * Process payment for approved payouts.
     */
    public function processPayments(Collection $payoutIds, string $paymentMethod = 'bank_transfer'): array
    {
        $results = [];

        foreach ($payoutIds as $payoutId) {
            $payout = CommissionPayout::find($payoutId);
            
            if (!$payout || !$payout->isApproved()) {
                $results[] = [
                    'payout_id' => $payoutId,
                    'success' => false,
                    'error' => 'Payout not found or not approved',
                ];
                continue;
            }

            try {
                // Here you would integrate with payment processor
                // For now, we'll simulate with a reference number
                $paymentReference = $this->processPayment($payout, $paymentMethod);
                
                $payout->markAsPaid($paymentReference, $paymentMethod);
                
                $results[] = [
                    'payout_id' => $payoutId,
                    'success' => true,
                    'payment_reference' => $paymentReference,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'payout_id' => $payoutId,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Process payment (placeholder for actual payment integration).
     */
    protected function processPayment(CommissionPayout $payout, string $paymentMethod): string
    {
        // This would integrate with actual payment processor
        // For now, return a mock reference
        return 'PAY-' . strtoupper(uniqid());
    }

    /**
     * Cancel a payout.
     */
    public function cancelPayout(CommissionPayout $payout, string $reason): void
    {
        if ($payout->isPaid()) {
            throw new \Exception("Cannot cancel a paid payout");
        }

        $payout->cancel($reason);
    }

    /**
     * Get payout summary for a period.
     */
    public function getPayoutSummary(Carbon $periodStart, Carbon $periodEnd, ?string $tenantId = null): array
    {
        $query = CommissionPayout::forPeriod($periodStart, $periodEnd);

        if ($tenantId) {
            $query->whereHas('salesRep.orders', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            });
        }

        $payouts = $query->get();

        return [
            'period' => [
                'start' => $periodStart->format('Y-m-d'),
                'end' => $periodEnd->format('Y-m-d'),
            ],
            'summary' => [
                'total_payouts' => $payouts->count(),
                'total_gross' => $payouts->sum('gross_amount'),
                'total_deductions' => $payouts->sum('deductions'),
                'total_net' => $payouts->sum('net_amount'),
                'total_commissions' => $payouts->sum('commission_count'),
            ],
            'by_status' => $payouts->groupBy('status')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total' => $group->sum('net_amount'),
                ];
            }),
            'by_rep' => $payouts->groupBy('sales_rep_id')->map(function ($group) {
                $rep = $group->first()->salesRep;
                return [
                    'rep_name' => $rep->full_name,
                    'count' => $group->count(),
                    'total' => $group->sum('net_amount'),
                ];
            }),
        ];
    }
}
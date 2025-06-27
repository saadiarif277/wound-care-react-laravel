<?php

namespace App\Services;

use App\Models\Commissions\CommissionRecord;
use App\Models\Commissions\CommissionPayout;
use App\Models\MscSalesRep;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutCalculatorService
{
    public function generatePayouts(Carbon $startDate, Carbon $endDate)
    {
        try {
            DB::beginTransaction();

            // Get all approved commission records for the period
            $records = CommissionRecord::approved()
                ->whereBetween('calculation_date', [$startDate, $endDate])
                ->whereNull('payout_id')
                ->get();

            // Group records by rep
            $recordsByRep = $records->groupBy('rep_id');

            foreach ($recordsByRep as $repId => $repRecords) {
                $rep = MscSalesRep::find($repId);
                if (!$rep) {
                    Log::warning("Sales rep #{$repId} not found while generating payouts");
                    continue;
                }

                $totalAmount = $repRecords->sum('amount');

                // Create payout record
                $payout = CommissionPayout::create([
                    'rep_id' => $repId,
                    'period_start' => $startDate,
                    'period_end' => $endDate,
                    'total_amount' => $totalAmount,
                    'status' => 'calculated',
                ]);

                // Update commission records with payout ID
                CommissionRecord::whereIn('id', $repRecords->pluck('id'))
                    ->update([
                        'payout_id' => $payout->id,
                        'status' => 'included_in_payout'
                    ]);
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to generate payouts: " . $e->getMessage());
            throw $e;
        }
    }

    public function approvePayout(CommissionPayout $payout, int $approvedBy)
    {
        try {
            DB::beginTransaction();

            $payout->update([
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => Carbon::now(),
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to approve payout #{$payout->id}: " . $e->getMessage());
            throw $e;
        }
    }

    public function markPayoutAsProcessed(CommissionPayout $payout, string $paymentReference)
    {
        try {
            DB::beginTransaction();

            $payout->update([
                'status' => 'processed',
                'processed_at' => Carbon::now(),
                'payment_reference' => $paymentReference,
            ]);

            // Update all associated commission records to paid status
            CommissionRecord::where('payout_id', $payout->id)
                ->update(['status' => 'paid']);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to mark payout #{$payout->id} as processed: " . $e->getMessage());
            throw $e;
        }
    }
}

<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\MscSalesRep;
use App\Models\CommissionRecord;
use Carbon\Carbon;

class OrderItemCommissionCalculatorService
{
    protected $ruleFinder;

    public function __construct(CommissionRuleFinderService $ruleFinder)
    {
        $this->ruleFinder = $ruleFinder;
    }

    public function calculateCommission(OrderItem $orderItem, MscSalesRep $rep)
    {
        $product = $orderItem->product;
        $commissionRate = $this->ruleFinder->getCommissionRate($product, $rep);

        // Calculate base commission amount
        $baseAmount = $orderItem->price * $orderItem->quantity * ($commissionRate / 100);

        // Create commission records
        if ($rep->parent_rep_id) {
            // This is a sub-rep, split commission with parent
            $parentRep = MscSalesRep::find($rep->parent_rep_id);
            $parentShare = $baseAmount * ($rep->sub_rep_parent_share_percentage / 100);
            $subRepShare = $baseAmount - $parentShare;

            // Create record for sub-rep's share
            CommissionRecord::create([
                'order_id' => $orderItem->order_id,
                'order_item_id' => $orderItem->id,
                'rep_id' => $rep->id,
                'parent_rep_id' => $parentRep->id,
                'amount' => $subRepShare,
                'percentage_rate' => $commissionRate,
                'type' => 'sub-rep-share',
                'status' => 'pending',
                'calculation_date' => Carbon::now(),
            ]);

            // Create record for parent rep's share
            CommissionRecord::create([
                'order_id' => $orderItem->order_id,
                'order_item_id' => $orderItem->id,
                'rep_id' => $parentRep->id,
                'parent_rep_id' => null,
                'amount' => $parentShare,
                'percentage_rate' => $rep->sub_rep_parent_share_percentage,
                'type' => 'parent-rep-share',
                'status' => 'pending',
                'calculation_date' => Carbon::now(),
            ]);
        } else {
            // This is a direct sale
            CommissionRecord::create([
                'order_id' => $orderItem->order_id,
                'order_item_id' => $orderItem->id,
                'rep_id' => $rep->id,
                'parent_rep_id' => null,
                'amount' => $baseAmount,
                'percentage_rate' => $commissionRate,
                'type' => 'direct-rep',
                'status' => 'pending',
                'calculation_date' => Carbon::now(),
            ]);
        }
    }
}

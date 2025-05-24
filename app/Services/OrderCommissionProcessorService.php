<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MscSalesRep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderCommissionProcessorService
{
    protected $calculator;

    public function __construct(OrderItemCommissionCalculatorService $calculator)
    {
        $this->calculator = $calculator;
    }

    public function processOrder(Order $order)
    {
        try {
            DB::beginTransaction();

            // Get the sales rep for this order
            $rep = MscSalesRep::where('id', $order->sales_rep_id)->first();
            if (!$rep) {
                throw new \Exception("No sales rep found for order #{$order->id}");
            }

            // Process each order item
            foreach ($order->items as $item) {
                $this->calculator->calculateCommission($item, $rep);
            }

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to process commissions for order #{$order->id}: " . $e->getMessage());
            throw $e;
        }
    }

    public function processOrderStatusChange(Order $order, string $newStatus)
    {
        // Only process commissions for certain statuses
        $commissionableStatuses = ['fulfilled', 'shipped', 'paid'];

        if (in_array($newStatus, $commissionableStatuses)) {
            return $this->processOrder($order);
        }

        return false;
    }
}

<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\SalesRep;
use App\Models\NewCommissionRule;
use App\Models\NewCommissionRecord;
use App\Models\ProviderSalesAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionCalculatorService
{
    /**
     * Process commission for an order.
     */
    public function processOrderCommission(Order $order): void
    {
        try {
            DB::beginTransaction();

            // Get the sales rep for this order
            $salesRep = $this->getSalesRepForOrder($order);
            if (!$salesRep) {
                Log::warning("No sales rep found for order {$order->order_number}");
                return;
            }

            // Calculate commission for each order item
            foreach ($order->orderItems as $item) {
                $this->calculateItemCommission($order, $item, $salesRep);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to process commission for order {$order->order_number}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate commission for a single order item.
     */
    protected function calculateItemCommission(Order $order, $orderItem, SalesRep $salesRep): void
    {
        $product = $orderItem->product;
        $lineTotal = $orderItem->line_total ?? ($orderItem->quantity * $orderItem->unit_price);

        // Find applicable commission rule
        $rule = $this->findApplicableRule($product, $order);
        if (!$rule) {
            Log::warning("No commission rule found for product {$product->id}");
            return;
        }

        // Calculate base commission amount
        $commissionAmount = $rule->calculateCommission($lineTotal);

        // Check for provider-specific assignment and splits
        $assignment = $this->getProviderAssignment($order, $salesRep);
        if ($assignment) {
            $commissionAmount = $assignment->calculateCommission($lineTotal);
        }

        // Create commission record for the sales rep
        $this->createCommissionRecord([
            'order_id' => $order->id,
            'user_id' => $salesRep->user_id,
            'rule_id' => $rule->id,
            'sales_rep_id' => $salesRep->id,
            'base_amount' => $lineTotal,
            'commission_amount' => $commissionAmount,
            'split_type' => 'direct',
            'status' => 'pending',
        ]);

        // Handle parent/sub-rep splits if applicable
        if ($salesRep->isSubRep() && $salesRep->parentRep) {
            $this->handleParentRepSplit($order, $salesRep, $rule, $lineTotal, $commissionAmount);
        }
    }

    /**
     * Handle commission split for parent rep.
     */
    protected function handleParentRepSplit(Order $order, SalesRep $subRep, NewCommissionRule $rule, float $baseAmount, float $totalCommission): void
    {
        $splitRules = $rule->getSplitRules();
        $splitAmounts = $rule->calculateSplitAmounts($totalCommission);

        // Update sub-rep commission to their portion
        NewCommissionRecord::where('order_id', $order->id)
            ->where('sales_rep_id', $subRep->id)
            ->where('split_type', 'direct')
            ->update(['commission_amount' => $splitAmounts['rep_amount']]);

        // Create parent rep commission record
        $this->createCommissionRecord([
            'order_id' => $order->id,
            'user_id' => $subRep->parentRep->user_id,
            'rule_id' => $rule->id,
            'sales_rep_id' => $subRep->parentRep->id,
            'parent_rep_id' => $subRep->id, // Reference to the sub-rep who generated this
            'base_amount' => $baseAmount,
            'commission_amount' => $splitAmounts['parent_amount'],
            'split_type' => 'parent_share',
            'status' => 'pending',
        ]);
    }

    /**
     * Find applicable commission rule.
     */
    protected function findApplicableRule(Product $product, Order $order): ?NewCommissionRule
    {
        // Try product-specific rules first
        $rule = NewCommissionRule::active()
            ->where('tenant_id', $order->tenant_id ?? null)
            ->whereJsonContains('applies_to_products', $product->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($rule) {
            return $rule;
        }

        // Try category-specific rules
        $rule = NewCommissionRule::active()
            ->where('tenant_id', $order->tenant_id ?? null)
            ->whereJsonContains('applies_to_categories', $product->category)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($rule) {
            return $rule;
        }

        // Try facility-specific rules
        if ($order->facility_id) {
            $rule = NewCommissionRule::active()
                ->where('tenant_id', $order->tenant_id ?? null)
                ->whereJsonContains('applies_to_facilities', $order->facility_id)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($rule) {
                return $rule;
            }
        }

        // Return default rule if exists
        return NewCommissionRule::active()
            ->where('tenant_id', $order->tenant_id ?? null)
            ->whereNull('applies_to_products')
            ->whereNull('applies_to_categories')
            ->whereNull('applies_to_facilities')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get sales rep for order.
     */
    protected function getSalesRepForOrder(Order $order): ?SalesRep
    {
        // First check if order has direct sales rep assignment
        if ($order->sales_rep_id) {
            return SalesRep::find($order->sales_rep_id);
        }

        // Check provider assignment
        if ($order->ordering_provider_fhir_id) {
            $assignment = ProviderSalesAssignment::active()
                ->forProvider($order->ordering_provider_fhir_id)
                ->primary()
                ->first();

            if ($assignment) {
                return $assignment->salesRep;
            }
        }

        // Check facility assignment as fallback
        if ($order->facility_id) {
            $facilityAssignment = \App\Models\FacilitySalesAssignment::active()
                ->forFacility($order->facility_id)
                ->coordinators()
                ->first();

            if ($facilityAssignment && $facilityAssignment->hasCommissionEligibility()) {
                return $facilityAssignment->salesRep;
            }
        }

        return null;
    }

    /**
     * Get provider assignment.
     */
    protected function getProviderAssignment(Order $order, SalesRep $salesRep): ?ProviderSalesAssignment
    {
        if (!$order->ordering_provider_fhir_id) {
            return null;
        }

        return ProviderSalesAssignment::active()
            ->forProvider($order->ordering_provider_fhir_id)
            ->where('sales_rep_id', $salesRep->id)
            ->first();
    }

    /**
     * Create commission record.
     */
    protected function createCommissionRecord(array $data): NewCommissionRecord
    {
        return NewCommissionRecord::create($data);
    }

    /**
     * Recalculate commissions for an order.
     */
    public function recalculateOrderCommission(Order $order): void
    {
        // Delete existing commission records for this order
        NewCommissionRecord::where('order_id', $order->id)
            ->where('status', 'pending')
            ->delete();

        // Recalculate
        $this->processOrderCommission($order);
    }

    /**
     * Approve commissions for an order.
     */
    public function approveOrderCommissions(Order $order, string $approvedBy): void
    {
        NewCommissionRecord::where('order_id', $order->id)
            ->pending()
            ->each(function ($record) use ($approvedBy) {
                $record->approve($approvedBy);
            });
    }
}
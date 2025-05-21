<?php

namespace App\Services;

use App\Models\CommissionRule;
use App\Models\Product;
use App\Models\MscSalesRep;

class CommissionRuleFinderService
{
    public function findApplicableRule(Product $product, MscSalesRep $rep)
    {
        // First try to find a product-specific rule
        $rule = CommissionRule::active()
            ->where('target_type', 'product')
            ->where('target_id', $product->id)
            ->first();

        if ($rule) {
            return $rule;
        }

        // Then try manufacturer-specific rule
        $rule = CommissionRule::active()
            ->where('target_type', 'manufacturer')
            ->where('target_id', $product->manufacturer_id)
            ->first();

        if ($rule) {
            return $rule;
        }

        // Finally try category-specific rule
        $rule = CommissionRule::active()
            ->where('target_type', 'category')
            ->where('target_id', $product->category_id)
            ->first();

        if ($rule) {
            return $rule;
        }

        // If no specific rule is found, return null to use the rep's base rate
        return null;
    }

    public function getCommissionRate(Product $product, MscSalesRep $rep)
    {
        $rule = $this->findApplicableRule($product, $rep);

        if ($rule) {
            return $rule->percentage_rate;
        }

        // Fall back to rep's base rate
        return $rep->commission_rate_direct;
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FilterFinancialData
{
    /**
     * Fields to filter out for users without financial permissions
     */
    protected array $financialFields = [
        'price',
        'msc_price',
        'price_per_sq_cm',
        'national_asp',
        'commission',
        'commission_rate',
        'amount_to_be_billed',
        'asp',
        'total_price',
        'unit_price',
        'discount',
        'discount_amount',
        'billing_amount',
        'reimbursement_amount',
        'payout_amount',
        'financial_data',
        'pricing',
        'cost',
        'revenue',
        'margin',
        'profitability'
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only filter JSON responses
        if ($response->headers->get('Content-Type') !== 'application/json') {
            return $response;
        }

        $user = Auth::user();
        
        // If no user or user has financial permissions, don't filter
        if (!$user || $this->userCanViewFinancials($user)) {
            return $response;
        }

        // Get the original data
        $data = $response->getData(true);
        
        // Filter the data
        $filteredData = $this->filterData($data);
        
        // Log filtering action
        Log::info('Financial data filtered from API response', [
            'user_id' => $user->id,
            'user_role' => $user->roles->first()->slug ?? 'unknown',
            'endpoint' => $request->path(),
            'method' => $request->method()
        ]);
        
        // Set the filtered data back
        $response->setData($filteredData);
        
        return $response;
    }

    /**
     * Check if user can view financial data
     */
    protected function userCanViewFinancials($user): bool
    {
        // Check if user has any financial permissions
        return $user->hasAnyPermission([
            'view-financials',
            'manage-financials',
            'view-msc-pricing',
            'view-order-totals',
            'view-discounts',
            'view-commission'
        ]);
    }

    /**
     * Recursively filter financial data from response
     */
    protected function filterData($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                // Check if this key should be filtered
                if ($this->shouldFilterKey($key)) {
                    unset($data[$key]);
                } else {
                    // Recursively filter nested data
                    $data[$key] = $this->filterData($value);
                }
            }
        } elseif (is_object($data)) {
            foreach ($data as $key => $value) {
                if ($this->shouldFilterKey($key)) {
                    unset($data->$key);
                } else {
                    $data->$key = $this->filterData($value);
                }
            }
        }
        
        return $data;
    }

    /**
     * Check if a key should be filtered
     */
    protected function shouldFilterKey(string $key): bool
    {
        // Convert to lowercase for comparison
        $lowerKey = strtolower($key);
        
        // Check exact matches
        if (in_array($lowerKey, $this->financialFields)) {
            return true;
        }
        
        // Check partial matches for common patterns
        $patterns = [
            'price',
            'cost',
            'commission',
            'financial',
            'billing',
            'payment',
            'revenue',
            'discount',
            'asp',
            'reimbursement'
        ];
        
        foreach ($patterns as $pattern) {
            if (str_contains($lowerKey, $pattern)) {
                return true;
            }
        }
        
        return false;
    }
}
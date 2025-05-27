<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\UserRole;

class FinancialAccessControl
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->userRole) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Load the user role relationship if not already loaded
        if (!$user->relationLoaded('userRole')) {
            $user->load('userRole');
        }

        $userRole = $user->userRole;

        // Check if the route requires financial access
        $routeName = $request->route()->getName();
        $restrictedRoutes = [
            'orders.financial',
            'products.pricing.full',
            'commission.*',
            'reports.financial',
            'analytics.revenue'
        ];

        $requiresFinancialAccess = collect($restrictedRoutes)->some(function ($pattern) use ($routeName) {
            return fnmatch($pattern, $routeName);
        });

        if ($requiresFinancialAccess && !$userRole->canAccessFinancials()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Access denied. Financial information not available for your role.',
                    'message' => 'Office Managers cannot access financial data.'
                ], 403);
            }

            abort(403, 'Access denied. Financial information not available for your role.');
        }

        // Add role restrictions to request for use in controllers
        $request->merge([
            'role_restrictions' => [
                'can_view_financials' => $userRole->canAccessFinancials(),
                'can_see_discounts' => $userRole->canSeeDiscounts(),
                'can_see_msc_pricing' => $userRole->canSeeMscPricing(),
                'can_see_order_totals' => $userRole->canSeeOrderTotals(),
                'pricing_access_level' => $userRole->getPricingAccessLevel(),
                'customer_data_restrictions' => $userRole->hasCustomerDataRestrictions(),
                'can_view_phi' => $userRole->canViewPhi(),
                'commission_access_level' => $userRole->getCommissionAccessLevel()
            ]
        ]);

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FinancialAccessControl
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Load the user roles if not already loaded
        if (!$user->relationLoaded('roles')) {
            $user->load('roles');
        }

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

        if ($requiresFinancialAccess && !$user->hasAnyPermission(['view-financials', 'manage-financials'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Access denied. Financial information not available for your role.',
                    'message' => 'Your role does not have permission to access financial data.'
                ], 403);
            }

            abort(403, 'Access denied. Financial information not available for your role.');
        }

        // Add role restrictions to request for use in controllers
        $request->merge([
            'role_restrictions' => [
                'can_view_financials' => $user->hasAnyPermission(['view-financials', 'manage-financials']),
                'can_see_discounts' => $user->hasPermission('view-discounts'),
                'can_see_msc_pricing' => $user->hasPermission('view-msc-pricing'),
                'can_see_order_totals' => $user->hasPermission('view-order-totals'),
                'can_view_phi' => $user->hasPermission('view-phi'),
                'can_view_commission' => $user->hasPermission('view-commission'),
                'can_manage_commission' => $user->hasPermission('manage-commission'),
                'can_manage_orders' => $user->hasPermission('manage-orders'),
                'can_manage_products' => $user->hasPermission('manage-products'),
            ]
        ]);

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Get the effective user role from authenticated user
     */
    private function getEffectiveUserRole(Request $request): ?string
    {
        if (!Auth::check()) {
            return null;
        }

        $user = Auth::user()->load('userRole');
        return $user->userRole->name ?? 'provider';
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     */
    public function share(Request $request): array
    {
        $effectiveUserRole = $this->getEffectiveUserRole($request);

        return array_merge(parent::share($request), [
            'auth' => function () {
                $user = Auth::check() ? Auth::user()->load(['account', 'userRole']) : null;
                return [
                    'user' => $user ? new UserResource($user) : null,
                ];
            },
            'userRole' => function () use ($effectiveUserRole) {
                return $effectiveUserRole;
            },
            'roleRestrictions' => function () use ($request) {
                if (!Auth::check()) {
                    return null;
                }

                $user = Auth::user()->load('userRole');
                if (!$user->userRole) {
                    return null;
                }

                $role = $user->userRole;
                return [
                    'can_view_financials' => $role->canAccessFinancials(),
                    'can_see_discounts' => $role->canSeeDiscounts(),
                    'can_see_msc_pricing' => $role->canSeeMscPricing(),
                    'can_see_order_totals' => $role->canSeeOrderTotals(),
                    'pricing_access_level' => $role->getPricingAccessLevel(),
                    'customer_data_restrictions' => $role->hasCustomerDataRestrictions(),
                    'can_view_phi' => $role->canViewPhi(),
                    'commission_access_level' => $role->getCommissionAccessLevel(),
                ];
            },
            'flash' => function () use ($request) {
                return [
                    'success' => $request->session()->get('success'),
                    'error' => $request->session()->get('error'),
                ];
            },
        ]);
    }
}

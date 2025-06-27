<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectBasedOnRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Get the user's primary role
        $role = $user->getPrimaryRole();

        if (!$role) {
            return $next($request);
        }

        // If accessing the base dashboard route, redirect based on role
        if ($request->routeIs('dashboard') || $request->routeIs('dashboard.alias')) {
            switch ($role->slug) {
                case 'msc-admin':
                    // All admins go to the enhanced episode-based order center (consolidated dashboard)
                    return redirect()->route('admin.orders.index');

                case 'provider':
                    // Providers go to their custom dashboard
                    return redirect()->route('provider.dashboard');

                case 'office-manager':
                    // Office managers can view orders but not financial data
                    if ($user->hasPermission('view-orders')) {
                        return redirect()->route('orders.center');
                    }
                    break;

                case 'msc-rep':
                case 'msc-subrep':
                    // Sales reps go to commission dashboard
                    if ($user->hasPermission('view-commission')) {
                        return redirect()->route('commission.management');
                    }
                    break;
            }
        }

        return $next($request);
    }
}

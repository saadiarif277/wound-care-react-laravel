<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $role
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $role)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Normalize role name for comparison
        $normalizedRole = $role === 'superadmin' ? 'super-admin' : $role;

        if (!$user->hasRole($normalizedRole)) {
            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            // Check if user is authenticated and session is valid
            if (Auth::guard($guard)->check() && Auth::guard($guard)->user()) {
                // Additional check to ensure the user session is still valid
                try {
                    $user = Auth::guard($guard)->user();
                    if ($user && $user->exists) {
                        return redirect('/dashboard');
                    }
                } catch (\Exception $e) {
                    // If there's any error with the user, clear the session
                    Auth::guard($guard)->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
            }
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RefreshCsrfToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ensure session is started
        if (!Session::isStarted()) {
            Session::start();
        }

        // Always ensure we have a valid CSRF token
        if (!Session::has('_token')) {
            Session::regenerateToken();
            Log::info('CSRF token created as it was missing', [
                'session_id' => Session::getId(),
                'request_url' => $request->url(),
            ]);
        }

        // Regenerate token if it's older than 15 minutes (more frequent for reliability)
        $tokenAge = time() - Session::get('_token_created_at', 0);
        if ($tokenAge > 900) { // 15 minutes
            Session::regenerateToken();
            Session::put('_token_created_at', time());
            Log::info('CSRF token regenerated due to age', [
                'previous_age' => $tokenAge,
                'session_id' => Session::getId(),
            ]);
        }

        // Store token creation time if not exists
        if (!Session::has('_token_created_at')) {
            Session::put('_token_created_at', time());
        }

        // Log token status for debugging (only in development)
        if (app()->environment('local', 'development')) {
            Log::debug('CSRF Token Status', [
                'has_token' => Session::has('_token'),
                'token_age' => $tokenAge,
                'session_id' => Session::getId(),
                'request_method' => $request->method(),
                'request_url' => $request->url(),
                'has_csrf_header' => $request->hasHeader('X-CSRF-TOKEN'),
                'token_matches' => $request->hasHeader('X-CSRF-TOKEN') &&
                    $request->header('X-CSRF-TOKEN') === Session::token(),
            ]);
        }

        return $next($request);
    }
}

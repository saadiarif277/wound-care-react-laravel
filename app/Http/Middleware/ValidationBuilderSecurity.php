<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ValidationBuilderSecurity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Rate limiting per user
        $key = 'validation-api:' . $request->user()?->id;
        if (RateLimiter::tooManyAttempts($key, 100)) {
            return response()->json([
                'success' => false,
                'error' => 'Too many validation requests. Please try again later.',
                'retry_after' => RateLimiter::availableIn($key)
            ], 429);
        }

        RateLimiter::hit($key, 60); // 100 requests per minute

        // 2. Input sanitization
        $this->sanitizeInputs($request);

        // 3. Validate content type for POST requests
        if ($request->isMethod('POST') && !$request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => 'Content-Type must be application/json'
            ], 400);
        }

        // 4. Add security headers to response
        $response = $next($request);

        return $this->addSecurityHeaders($response);
    }

    private function sanitizeInputs(Request $request): void
    {
        // Sanitize query parameters
        $query = $request->query();
        foreach ($query as $key => $value) {
            if (is_string($value)) {
                $request->query->set($key, $this->sanitizeString($value));
            }
        }

        // Sanitize request body for JSON requests
        if ($request->isJson()) {
            $input = $request->json()->all();
            $sanitized = $this->sanitizeArray($input);
            $request->json()->replace($sanitized);
        }
    }

    private function sanitizeString(string $value): string
    {
        // Remove potential XSS vectors
        $value = strip_tags($value);

        // Remove potential SQL injection vectors
        $value = str_replace(['--', ';', '/*', '*/', 'xp_', 'sp_'], '', $value);

        // Limit length to prevent DoS
        return substr($value, 0, 1000);
    }

    private function sanitizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = $this->sanitizeString($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeArray($value);
            }
        }

        return $data;
    }

    private function addSecurityHeaders(Response $response): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}

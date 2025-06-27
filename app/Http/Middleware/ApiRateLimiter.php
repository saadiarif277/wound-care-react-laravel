<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimiter
{
    public function __construct(
        protected RateLimiter $limiter
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $limiterName = 'api'): Response
    {
        $key = $this->resolveRequestSignature($request, $limiterName);
        
        if ($this->limiter->tooManyAttempts($key, $this->getMaxAttempts($limiterName))) {
            return $this->buildResponse($request, $key, $this->getMaxAttempts($limiterName));
        }

        $this->limiter->hit($key, $this->getDecayMinutes($limiterName) * 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $this->getMaxAttempts($limiterName),
            $this->calculateRemainingAttempts($key, $this->getMaxAttempts($limiterName))
        );
    }

    /**
     * Resolve request signature for rate limiting
     */
    protected function resolveRequestSignature(Request $request, string $limiterName): string
    {
        $user = $request->user();
        
        return match($limiterName) {
            'api' => $user ? "api|user:{$user->id}" : "api|ip:{$request->ip()}",
            'public' => "public|ip:{$request->ip()}|route:{$request->route()->getName()}",
            'medicare' => "medicare|ip:{$request->ip()}|npi:{$request->input('provider_npi', 'unknown')}",
            'fhir' => $user ? "fhir|user:{$user->id}|org:{$user->organization_id}" : "fhir|ip:{$request->ip()}",
            default => "default|ip:{$request->ip()}"
        };
    }

    /**
     * Get max attempts for specific limiter
     */
    protected function getMaxAttempts(string $limiterName): int
    {
        return match($limiterName) {
            'api' => 60,        // 60 requests per minute for authenticated users
            'public' => 10,     // 10 requests per minute for public endpoints
            'medicare' => 30,   // 30 requests per minute for Medicare validation
            'fhir' => 100,      // 100 requests per minute for FHIR operations
            default => 60
        };
    }

    /**
     * Get decay minutes for specific limiter
     */
    protected function getDecayMinutes(string $limiterName): int
    {
        return match($limiterName) {
            'api' => 1,         // 1 minute decay
            'public' => 5,      // 5 minute decay for public endpoints
            'medicare' => 1,    // 1 minute decay
            'fhir' => 1,        // 1 minute decay
            default => 1
        };
    }

    /**
     * Create a 'too many attempts' response
     */
    protected function buildResponse(Request $request, string $key, int $maxAttempts): Response
    {
        $retryAfter = $this->limiter->availableIn($key);
        
        $message = [
            'message' => 'Too many attempts. Please slow down.',
            'retry_after' => $retryAfter,
            'retry_after_human' => $this->secondsToHuman($retryAfter)
        ];

        // Log potential abuse
        if ($this->limiter->attempts($key) > $maxAttempts * 2) {
            app(\App\Logging\PhiSafeLogger::class)->warning('Potential API abuse detected', [
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
                'endpoint' => $request->path(),
                'attempts' => $this->limiter->attempts($key)
            ]);
        }

        return response()->json($message, 429)
            ->header('Retry-After', $retryAfter)
            ->header('X-RateLimit-Limit', $maxAttempts)
            ->header('X-RateLimit-Remaining', 0);
    }

    /**
     * Add rate limit headers to response
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        return $response
            ->header('X-RateLimit-Limit', $maxAttempts)
            ->header('X-RateLimit-Remaining', $remainingAttempts);
    }

    /**
     * Calculate remaining attempts
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->limiter->attempts($key));
    }

    /**
     * Convert seconds to human readable format
     */
    protected function secondsToHuman(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds} seconds";
        }
        
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        
        if ($remainingSeconds > 0) {
            return "{$minutes} minutes and {$remainingSeconds} seconds";
        }
        
        return "{$minutes} minutes";
    }
}
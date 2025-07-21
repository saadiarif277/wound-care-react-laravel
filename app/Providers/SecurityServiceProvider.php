<?php

namespace App\Providers;

use App\Http\Middleware\ApiRateLimiter;
use App\Http\Middleware\SecurityHeaders;
use App\Logging\PhiSafeLogger;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register PHI-safe logger as singleton
        $this->app->singleton(PhiSafeLogger::class, function ($app) {
            return new PhiSafeLogger();
        });
        
        // Register middleware aliases
        $this->app['router']->aliasMiddleware('security.headers', SecurityHeaders::class);
        $this->app['router']->aliasMiddleware('api.rate.limit', ApiRateLimiter::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureSecurityPolicies();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Standard API rate limit
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            )->response(function (Request $request, array $headers) {
                return response()->json([
                    'message' => 'Too many requests. Please try again later.',
                    'retry_after' => $headers['Retry-After'] ?? 60
                ], 429, $headers);
            });
        });

        // Strict rate limit for public endpoints
        RateLimiter::for('public', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Rate limit exceeded for public endpoint.',
                        'retry_after' => $headers['Retry-After'] ?? 300
                    ], 429, $headers);
                });
        });

        // Medicare validation rate limit
        RateLimiter::for('medicare', function (Request $request) {
            return Limit::perMinute(30)->by(
                $request->input('provider_npi', $request->ip())
            );
        });

        // FHIR operations rate limit
        RateLimiter::for('fhir', function (Request $request) {
            return Limit::perMinute(100)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Login rate limit (stricter)
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(3)->by(
                $request->input('email') . '|' . $request->ip()
            )->response(function (Request $request, array $headers) {
                return response()->json([
                    'message' => 'Too many login attempts. Please try again later.',
                    'retry_after' => $headers['Retry-After'] ?? 900
                ], 429, $headers);
            });
        });

        // Webhook rate limit
        RateLimiter::for('webhook', function (Request $request) {
            return Limit::perMinute(100)->by(
                $request->header('X-Webhook-Source', $request->ip())
            );
        });
    }

    /**
     * Configure security policies
     */
    protected function configureSecurityPolicies(): void
    {
        // Apply security headers to all web routes
        $this->app['router']->pushMiddlewareToGroup('web', SecurityHeaders::class);
        
        // Configure CORS for API routes
        config(['cors.paths' => ['api/*', 'fhir/*']]);
        config(['cors.allowed_origins' => [env('APP_URL'), 'https://app.mscwoundcare.com']]);
        config(['cors.allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'X-CSRF-TOKEN']]);
        config(['cors.exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining']]);
        config(['cors.max_age' => 86400]); // 24 hours
        
        // Configure session security
        if ($this->app->environment('production')) {
            config(['session.same_site' => 'strict']);
            config(['session.http_only' => true]);
        }
    }
}
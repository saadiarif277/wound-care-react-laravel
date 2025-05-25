<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class ValidationEngineMonitoring
{
    private array $metrics = [];
    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    /**
     * Track validation engine performance
     */
    public function trackValidation(string $engine, string $specialty, float $duration, bool $success): void
    {
        $this->metrics[] = [
            'engine' => $engine,
            'specialty' => $specialty,
            'duration' => $duration,
            'success' => $success,
            'timestamp' => now()->toISOString(),
            'memory_usage' => memory_get_usage(true),
        ];

        // Log performance metrics
        Log::info('Validation Engine Performance', [
            'engine' => $engine,
            'specialty' => $specialty,
            'duration_ms' => round($duration * 1000, 2),
            'success' => $success,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        // Update performance cache
        $this->updatePerformanceCache($engine, $specialty, $duration, $success);
    }

    /**
     * Track CMS API calls
     */
    public function trackCmsApiCall(string $endpoint, int $statusCode, float $duration, ?string $error = null): void
    {
        Log::info('CMS API Call', [
            'endpoint' => $this->sanitizeEndpoint($endpoint),
            'status_code' => $statusCode,
            'duration_ms' => round($duration * 1000, 2),
            'success' => $statusCode >= 200 && $statusCode < 300,
            'error' => $error ? substr($error, 0, 200) : null,
        ]);

        // Track API health
        $this->updateApiHealthCache($statusCode, $duration);
    }

    /**
     * Log validation errors without exposing sensitive data
     */
    public function logValidationError(Exception $exception, array $context = []): void
    {
        // Sanitize context to remove sensitive data
        $sanitizedContext = $this->sanitizeContext($context);

        Log::error('Validation Engine Error', [
            'error_type' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine(),
            'context' => $sanitizedContext,
            'memory_usage' => memory_get_usage(true),
            'execution_time' => microtime(true) - $this->startTime,
        ]);
    }

    /**
     * Get system health metrics
     */
    public function getHealthMetrics(): array
    {
        return [
            'validation_engine_health' => $this->getValidationEngineHealth(),
            'cms_api_health' => $this->getCmsApiHealth(),
            'system_performance' => $this->getSystemPerformance(),
            'cache_status' => $this->getCacheStatus(),
        ];
    }

    /**
     * Check if validation engines are healthy
     */
    public function areEnginesHealthy(): bool
    {
        $health = $this->getValidationEngineHealth();
        return $health['status'] === 'healthy' && $health['error_rate'] < 0.05;
    }

    private function updatePerformanceCache(string $engine, string $specialty, float $duration, bool $success): void
    {
        $key = "validation_performance_{$engine}_{$specialty}";
        $current = Cache::get($key, ['total_calls' => 0, 'successful_calls' => 0, 'total_duration' => 0]);

        $current['total_calls']++;
        if ($success) {
            $current['successful_calls']++;
        }
        $current['total_duration'] += $duration;
        $current['last_updated'] = now()->toISOString();

        Cache::put($key, $current, 3600); // Store for 1 hour
    }

    private function updateApiHealthCache(int $statusCode, float $duration): void
    {
        $key = 'cms_api_health';
        $current = Cache::get($key, ['total_calls' => 0, 'successful_calls' => 0, 'total_duration' => 0]);

        $current['total_calls']++;
        if ($statusCode >= 200 && $statusCode < 300) {
            $current['successful_calls']++;
        }
        $current['total_duration'] += $duration;
        $current['last_updated'] = now()->toISOString();

        Cache::put($key, $current, 300); // Store for 5 minutes
    }

    private function getValidationEngineHealth(): array
    {
        $totalCalls = 0;
        $successfulCalls = 0;
        $avgDuration = 0;

        try {
            // Check if we're using Redis cache driver
            $isRedis = false;
            try {
                // Try to detect Redis by attempting to get the Redis connection
                $isRedis = Cache::getStore() instanceof \Illuminate\Cache\RedisStore;
            } catch (Exception $e) {
                // Not Redis or cache not available, use fallback
                $isRedis = false;
            }

            if ($isRedis) {
                // For Redis cache stores, try to get keys with pattern
                $redis = Cache::connection()->getRedis();
                $keys = $redis->keys('*validation_performance_*');

                foreach ($keys as $key) {
                    // Remove cache prefix for Laravel cache get
                    $cleanKey = str_replace(config('cache.prefix') . ':', '', $key);
                    $data = Cache::get($cleanKey, []);

                    $totalCalls += $data['total_calls'] ?? 0;
                    $successfulCalls += $data['successful_calls'] ?? 0;
                    $avgDuration += $data['total_duration'] ?? 0;
                }
            } else {
                // Fallback for non-Redis cache stores - check specific known keys
                $knownEngines = ['WoundCareValidationEngine', 'PulmonologyWoundCareValidationEngine'];
                $knownSpecialties = ['wound_care_specialty', 'pulmonology_wound_care'];

                foreach ($knownEngines as $engine) {
                    foreach ($knownSpecialties as $specialty) {
                        $key = "validation_performance_{$engine}_{$specialty}";
                        $data = Cache::get($key, []);

                        $totalCalls += $data['total_calls'] ?? 0;
                        $successfulCalls += $data['successful_calls'] ?? 0;
                        $avgDuration += $data['total_duration'] ?? 0;
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to retrieve validation performance metrics', [
                'error' => $e->getMessage()
            ]);

            // Use fallback method if Redis fails
            $knownEngines = ['WoundCareValidationEngine', 'PulmonologyWoundCareValidationEngine'];
            $knownSpecialties = ['wound_care_specialty', 'pulmonology_wound_care'];

            foreach ($knownEngines as $engine) {
                foreach ($knownSpecialties as $specialty) {
                    $key = "validation_performance_{$engine}_{$specialty}";
                    try {
                        $data = Cache::get($key, []);
                        $totalCalls += $data['total_calls'] ?? 0;
                        $successfulCalls += $data['successful_calls'] ?? 0;
                        $avgDuration += $data['total_duration'] ?? 0;
                    } catch (Exception $fallbackError) {
                        // Silent fallback - metrics will show zero if cache is completely unavailable
                    }
                }
            }
        }

        $errorRate = $totalCalls > 0 ? 1 - ($successfulCalls / $totalCalls) : 0;
        $avgDuration = $totalCalls > 0 ? $avgDuration / $totalCalls : 0;

        return [
            'status' => $errorRate < 0.05 ? 'healthy' : 'degraded',
            'total_calls' => $totalCalls,
            'error_rate' => round($errorRate, 4),
            'avg_duration_ms' => round($avgDuration * 1000, 2),
        ];
    }

    private function getCmsApiHealth(): array
    {
        $data = Cache::get('cms_api_health', ['total_calls' => 0, 'successful_calls' => 0, 'total_duration' => 0]);

        $errorRate = $data['total_calls'] > 0 ? 1 - ($data['successful_calls'] / $data['total_calls']) : 0;
        $avgDuration = $data['total_calls'] > 0 ? $data['total_duration'] / $data['total_calls'] : 0;

        return [
            'status' => $errorRate < 0.1 ? 'healthy' : 'degraded',
            'total_calls' => $data['total_calls'],
            'error_rate' => round($errorRate, 4),
            'avg_duration_ms' => round($avgDuration * 1000, 2),
            'last_updated' => $data['last_updated'] ?? null,
        ];
    }

    private function getSystemPerformance(): array
    {
        return [
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'execution_time_ms' => round((microtime(true) - $this->startTime) * 1000, 2),
        ];
    }

    private function getCacheStatus(): array
    {
        try {
            // Test cache connectivity
            $testKey = 'health_check_' . time();
            Cache::put($testKey, 'test', 1);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            return [
                'status' => $retrieved === 'test' ? 'healthy' : 'degraded',
                'driver' => config('cache.default'),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'driver' => config('cache.default'),
                'error' => 'Cache connectivity failed',
            ];
        }
    }

    private function sanitizeContext(array $context): array
    {
        $sensitiveKeys = [
            'patient_first_name', 'patient_last_name', 'patient_dob',
            'patient_member_id', 'ssn', 'email', 'phone', 'address',
            'password', 'token', 'secret', 'key'
        ];

        return $this->recursiveSanitize($context, $sensitiveKeys);
    }

    private function recursiveSanitize(array $data, array $sensitiveKeys): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), array_map('strtolower', $sensitiveKeys))) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->recursiveSanitize($value, $sensitiveKeys);
            } elseif (is_string($value) && strlen($value) > 100) {
                $data[$key] = substr($value, 0, 100) . '[TRUNCATED]';
            }
        }

        return $data;
    }

    private function sanitizeEndpoint(string $endpoint): string
    {
        // Remove query parameters that might contain sensitive data
        return parse_url($endpoint, PHP_URL_PATH) ?: $endpoint;
    }
}

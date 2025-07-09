<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FieldMappingMetricsService
{
    private const CACHE_PREFIX = 'ai_field_mapping_metrics:';
    private const METRICS_TTL = 86400; // 24 hours

    /**
     * Record a successful AI mapping
     */
    public function recordSuccess(array $context): void
    {
        $this->incrementCounter('success_count');
        $this->recordResponseTime($context['response_time'] ?? 0);
        $this->recordConfidence($context['confidence'] ?? 0);
        $this->recordFieldCompleteness($context['fields_mapped'] ?? 0, $context['total_fields'] ?? 0);
        
        // Log detailed metrics
        Log::channel('ai_metrics')->info('AI mapping successful', [
            'episode_id' => $context['episode_id'] ?? null,
            'manufacturer' => $context['manufacturer'] ?? null,
            'template_id' => $context['template_id'] ?? null,
            'confidence' => $context['confidence'] ?? 0,
            'response_time_ms' => $context['response_time'] ?? 0,
            'fields_mapped' => $context['fields_mapped'] ?? 0,
            'method' => $context['method'] ?? 'ai'
        ]);
    }

    /**
     * Record a failed AI mapping attempt
     */
    public function recordFailure(array $context): void
    {
        $this->incrementCounter('failure_count');
        $this->incrementCounter('fallback_count');
        
        // Log failure details
        Log::channel('ai_metrics')->warning('AI mapping failed', [
            'episode_id' => $context['episode_id'] ?? null,
            'manufacturer' => $context['manufacturer'] ?? null,
            'error' => $context['error'] ?? 'Unknown error',
            'fallback_used' => true
        ]);
    }

    /**
     * Get current metrics summary
     */
    public function getMetricsSummary(): array
    {
        $successCount = $this->getCounter('success_count');
        $failureCount = $this->getCounter('failure_count');
        $totalCount = $successCount + $failureCount;
        
        return [
            'total_requests' => $totalCount,
            'successful_mappings' => $successCount,
            'failed_mappings' => $failureCount,
            'fallback_used' => $this->getCounter('fallback_count'),
            'success_rate' => $totalCount > 0 ? round(($successCount / $totalCount) * 100, 2) : 0,
            'average_response_time' => $this->getAverageResponseTime(),
            'average_confidence' => $this->getAverageConfidence(),
            'average_field_completeness' => $this->getAverageFieldCompleteness(),
            'last_24h_stats' => $this->getLast24HourStats(),
            'service_health' => $this->calculateServiceHealth()
        ];
    }

    /**
     * Calculate service health score
     */
    private function calculateServiceHealth(): array
    {
        $successRate = $this->getSuccessRate();
        $avgResponseTime = $this->getAverageResponseTime();
        
        $status = 'healthy';
        if ($successRate < 50 || $avgResponseTime > 5000) {
            $status = 'degraded';
        } elseif ($successRate < 80 || $avgResponseTime > 3000) {
            $status = 'warning';
        }
        
        return [
            'status' => $status,
            'success_rate' => $successRate,
            'avg_response_time_ms' => $avgResponseTime,
            'last_check' => Carbon::now()->toIso8601String()
        ];
    }

    /**
     * Get metrics for the last 24 hours
     */
    private function getLast24HourStats(): array
    {
        // This would be more sophisticated in production with time-series data
        return [
            'requests_per_hour' => $this->getHourlyRequests(),
            'peak_hour' => $this->getPeakHour(),
            'error_spike_detected' => $this->detectErrorSpike()
        ];
    }

    /**
     * Helper methods for metric calculations
     */
    private function incrementCounter(string $key): void
    {
        $fullKey = self::CACHE_PREFIX . $key;
        Cache::increment($fullKey);
    }

    private function getCounter(string $key): int
    {
        $fullKey = self::CACHE_PREFIX . $key;
        return (int) Cache::get($fullKey, 0);
    }

    private function recordResponseTime(float $responseTime): void
    {
        $times = Cache::get(self::CACHE_PREFIX . 'response_times', []);
        $times[] = $responseTime;
        
        // Keep only last 1000 entries
        if (count($times) > 1000) {
            $times = array_slice($times, -1000);
        }
        
        Cache::put(self::CACHE_PREFIX . 'response_times', $times, self::METRICS_TTL);
    }

    private function getAverageResponseTime(): float
    {
        $times = Cache::get(self::CACHE_PREFIX . 'response_times', []);
        return count($times) > 0 ? round(array_sum($times) / count($times), 2) : 0;
    }

    private function recordConfidence(float $confidence): void
    {
        $scores = Cache::get(self::CACHE_PREFIX . 'confidence_scores', []);
        $scores[] = $confidence;
        
        // Keep only last 1000 entries
        if (count($scores) > 1000) {
            $scores = array_slice($scores, -1000);
        }
        
        Cache::put(self::CACHE_PREFIX . 'confidence_scores', $scores, self::METRICS_TTL);
    }

    private function getAverageConfidence(): float
    {
        $scores = Cache::get(self::CACHE_PREFIX . 'confidence_scores', []);
        return count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : 0;
    }

    private function recordFieldCompleteness(int $fieldsMapped, int $totalFields): void
    {
        if ($totalFields > 0) {
            $completeness = ($fieldsMapped / $totalFields) * 100;
            $scores = Cache::get(self::CACHE_PREFIX . 'completeness_scores', []);
            $scores[] = $completeness;
            
            // Keep only last 1000 entries
            if (count($scores) > 1000) {
                $scores = array_slice($scores, -1000);
            }
            
            Cache::put(self::CACHE_PREFIX . 'completeness_scores', $scores, self::METRICS_TTL);
        }
    }

    private function getAverageFieldCompleteness(): float
    {
        $scores = Cache::get(self::CACHE_PREFIX . 'completeness_scores', []);
        return count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : 0;
    }

    private function getSuccessRate(): float
    {
        $success = $this->getCounter('success_count');
        $failure = $this->getCounter('failure_count');
        $total = $success + $failure;
        
        return $total > 0 ? round(($success / $total) * 100, 2) : 100;
    }

    private function getHourlyRequests(): array
    {
        // Simplified implementation - in production would use time-series data
        $current = $this->getCounter('success_count') + $this->getCounter('failure_count');
        return [
            'current_hour' => intval($current / 24), // Simple average
            'trend' => 'stable'
        ];
    }

    private function getPeakHour(): string
    {
        // Simplified - would track actual hourly data in production
        return '14:00-15:00';
    }

    private function detectErrorSpike(): bool
    {
        $failureRate = 100 - $this->getSuccessRate();
        return $failureRate > 20; // Alert if failure rate exceeds 20%
    }

    /**
     * Reset all metrics (for testing purposes)
     */
    public function resetMetrics(): void
    {
        $keys = [
            'success_count',
            'failure_count',
            'fallback_count',
            'response_times',
            'confidence_scores',
            'completeness_scores'
        ];
        
        foreach ($keys as $key) {
            Cache::forget(self::CACHE_PREFIX . $key);
        }
        
        Log::info('AI field mapping metrics reset');
    }
}
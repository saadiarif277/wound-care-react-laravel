<?php

declare(strict_types=1);

namespace App\Services\Fhir;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FhirCircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    private string $serviceName;
    private int $failureThreshold;
    private int $recoveryTimeout;
    private int $successThreshold;

    public function __construct(
        string $serviceName = 'fhir',
        int $failureThreshold = 5,
        int $recoveryTimeout = 60,
        int $successThreshold = 2
    ) {
        $this->serviceName = $serviceName;
        $this->failureThreshold = $failureThreshold;
        $this->recoveryTimeout = $recoveryTimeout;
        $this->successThreshold = $successThreshold;
    }

    /**
     * Execute a callable with circuit breaker protection
     *
     * @throws \Exception
     */
    public function call(callable $callback, ?callable $fallback = null): mixed
    {
        $state = $this->getState();

        if ($state === self::STATE_OPEN) {
            if ($this->shouldAttemptReset()) {
                $this->setState(self::STATE_HALF_OPEN);
                $state = self::STATE_HALF_OPEN;
            } else {
                Log::warning('Circuit breaker is OPEN', [
                    'service' => $this->serviceName,
                    'failures' => $this->getFailureCount(),
                ]);

                if ($fallback) {
                    return $fallback();
                }

                throw new \Exception('Service unavailable - circuit breaker is OPEN');
            }
        }

        try {
            $result = $callback();
            $this->recordSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->recordFailure();

            if ($state === self::STATE_HALF_OPEN) {
                $this->setState(self::STATE_OPEN);
            }

            throw $e;
        }
    }

    /**
     * Record a successful call
     */
    public function recordSuccess(): void
    {
        $state = $this->getState();

        if ($state === self::STATE_HALF_OPEN) {
            $successCount = $this->incrementSuccessCount();

            if ($successCount >= $this->successThreshold) {
                $this->reset();
                Log::info('Circuit breaker CLOSED after recovery', [
                    'service' => $this->serviceName,
                ]);
            }
        } elseif ($state === self::STATE_CLOSED) {
            // Reset failure count on success in closed state
            $this->setFailureCount(0);
        }
    }

    /**
     * Record a failed call
     */
    public function recordFailure(): void
    {
        $failureCount = $this->incrementFailureCount();

        if ($failureCount >= $this->failureThreshold && $this->getState() === self::STATE_CLOSED) {
            $this->setState(self::STATE_OPEN);
            $this->setLastFailureTime(time());

            Log::error('Circuit breaker OPENED due to failures', [
                'service' => $this->serviceName,
                'failures' => $failureCount,
                'threshold' => $this->failureThreshold,
            ]);
        }
    }

    /**
     * Get current circuit breaker state
     */
    public function getState(): string
    {
        return Cache::get($this->getStateKey(), self::STATE_CLOSED);
    }

    /**
     * Set circuit breaker state
     */
    private function setState(string $state): void
    {
        Cache::put($this->getStateKey(), $state, now()->addHours(24));
    }

    /**
     * Check if we should attempt to reset from OPEN state
     */
    private function shouldAttemptReset(): bool
    {
        $lastFailureTime = $this->getLastFailureTime();
        return $lastFailureTime && (time() - $lastFailureTime) >= $this->recoveryTimeout;
    }

    /**
     * Reset circuit breaker to CLOSED state
     */
    public function reset(): void
    {
        $this->setState(self::STATE_CLOSED);
        $this->setFailureCount(0);
        $this->setSuccessCount(0);
        Cache::forget($this->getLastFailureTimeKey());
    }

    /**
     * Get failure count
     */
    private function getFailureCount(): int
    {
        return (int) Cache::get($this->getFailureCountKey(), 0);
    }

    /**
     * Set failure count
     */
    private function setFailureCount(int $count): void
    {
        Cache::put($this->getFailureCountKey(), $count, now()->addHours(1));
    }

    /**
     * Increment failure count
     */
    private function incrementFailureCount(): int
    {
        return (int) Cache::increment($this->getFailureCountKey());
    }

    /**
     * Get success count (for HALF_OPEN state)
     */
    private function getSuccessCount(): int
    {
        return (int) Cache::get($this->getSuccessCountKey(), 0);
    }

    /**
     * Set success count
     */
    private function setSuccessCount(int $count): void
    {
        Cache::put($this->getSuccessCountKey(), $count, now()->addMinutes(10));
    }

    /**
     * Increment success count
     */
    private function incrementSuccessCount(): int
    {
        return (int) Cache::increment($this->getSuccessCountKey());
    }

    /**
     * Get last failure time
     */
    private function getLastFailureTime(): ?int
    {
        return Cache::get($this->getLastFailureTimeKey());
    }

    /**
     * Set last failure time
     */
    private function setLastFailureTime(int $time): void
    {
        Cache::put($this->getLastFailureTimeKey(), $time, now()->addHours(1));
    }

    /**
     * Get cache key for state
     */
    private function getStateKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:state";
    }

    /**
     * Get cache key for failure count
     */
    private function getFailureCountKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:failures";
    }

    /**
     * Get cache key for success count
     */
    private function getSuccessCountKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:successes";
    }

    /**
     * Get cache key for last failure time
     */
    private function getLastFailureTimeKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:last_failure";
    }

    /**
     * Get circuit breaker status
     */
    public function getStatus(): array
    {
        return [
            'state' => $this->getState(),
            'failures' => $this->getFailureCount(),
            'successes' => $this->getSuccessCount(),
            'last_failure_time' => $this->getLastFailureTime(),
            'thresholds' => [
                'failure' => $this->failureThreshold,
                'success' => $this->successThreshold,
                'recovery_timeout' => $this->recoveryTimeout,
            ],
        ];
    }
}
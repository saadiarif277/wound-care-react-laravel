<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Fhir;

use App\Services\Fhir\FhirCircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FhirCircuitBreakerTest extends TestCase
{
    private FhirCircuitBreaker $circuitBreaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->circuitBreaker = new FhirCircuitBreaker('test-service', 3, 10, 2);
        Cache::flush();
    }

    public function test_circuit_breaker_starts_in_closed_state(): void
    {
        $this->assertEquals('closed', $this->circuitBreaker->getState());
    }

    public function test_successful_calls_in_closed_state(): void
    {
        $result = $this->circuitBreaker->call(function () {
            return 'success';
        });

        $this->assertEquals('success', $result);
        $this->assertEquals('closed', $this->circuitBreaker->getState());
    }

    public function test_circuit_opens_after_failure_threshold(): void
    {
        // Simulate failures up to threshold
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->circuitBreaker->call(function () {
                    throw new \Exception('Service failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }

        $this->assertEquals('open', $this->circuitBreaker->getState());
    }

    public function test_circuit_breaker_blocks_calls_when_open(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->circuitBreaker->call(function () {
                    throw new \Exception('Service failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }

        // Try to make another call
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Service unavailable - circuit breaker is OPEN');

        $this->circuitBreaker->call(function () {
            return 'should not execute';
        });
    }

    public function test_fallback_is_called_when_circuit_is_open(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->circuitBreaker->call(function () {
                    throw new \Exception('Service failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }

        // Call with fallback
        $result = $this->circuitBreaker->call(
            function () {
                return 'should not execute';
            },
            function () {
                return 'fallback result';
            }
        );

        $this->assertEquals('fallback result', $result);
    }

    public function test_circuit_transitions_to_half_open_after_timeout(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->circuitBreaker->call(function () {
                    throw new \Exception('Service failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }

        // Simulate time passing
        Cache::put('circuit_breaker:test-service:last_failure', time() - 11, now()->addHour());

        // Next call should transition to half-open
        try {
            $this->circuitBreaker->call(function () {
                return 'success';
            });
        } catch (\Exception $e) {
            // Not expected in this test
        }

        // State should remain half-open until success threshold is met
        $status = $this->circuitBreaker->getStatus();
        $this->assertContains($status['state'], ['half_open', 'closed']);
    }

    public function test_circuit_closes_after_success_threshold_in_half_open(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->circuitBreaker->call(function () {
                    throw new \Exception('Service failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }

        // Force to half-open state
        Cache::put('circuit_breaker:test-service:state', 'half_open', now()->addHour());
        Cache::put('circuit_breaker:test-service:last_failure', time() - 11, now()->addHour());

        // Make successful calls to meet threshold
        for ($i = 0; $i < 2; $i++) {
            $this->circuitBreaker->call(function () {
                return 'success';
            });
        }

        $this->assertEquals('closed', $this->circuitBreaker->getState());
    }

    public function test_reset_clears_all_state(): void
    {
        // Create some state
        for ($i = 0; $i < 2; $i++) {
            try {
                $this->circuitBreaker->call(function () {
                    throw new \Exception('Service failure');
                });
            } catch (\Exception $e) {
                // Expected
            }
        }

        $this->circuitBreaker->reset();

        $status = $this->circuitBreaker->getStatus();
        $this->assertEquals('closed', $status['state']);
        $this->assertEquals(0, $status['failures']);
        $this->assertEquals(0, $status['successes']);
        $this->assertNull($status['last_failure_time']);
    }

    public function test_get_status_returns_complete_information(): void
    {
        $status = $this->circuitBreaker->getStatus();

        $this->assertArrayHasKey('state', $status);
        $this->assertArrayHasKey('failures', $status);
        $this->assertArrayHasKey('successes', $status);
        $this->assertArrayHasKey('last_failure_time', $status);
        $this->assertArrayHasKey('thresholds', $status);
        $this->assertEquals(3, $status['thresholds']['failure']);
        $this->assertEquals(2, $status['thresholds']['success']);
        $this->assertEquals(10, $status['thresholds']['recovery_timeout']);
    }
}
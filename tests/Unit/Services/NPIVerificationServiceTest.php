<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\NPIVerificationService;
use App\DTOs\NPIVerificationResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NPIVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NPIVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();

        // Create service with mock configuration
        $this->service = new NPIVerificationService(
            useMock: true,
            apiUrl: 'https://test-api.example.com',
            timeout: 10,
            cacheTtl: 3600,
            maxRetries: 2,
            retryDelay: 500
        );
    }

    /** @test */
    public function it_validates_npi_format()
    {
        // Valid NPI
        $result = $this->service->verifyNPI('1234567890');
        $this->assertInstanceOf(NPIVerificationResult::class, $result);

        // Invalid NPI - too short
        $result = $this->service->verifyNPI('123456789');
        $this->assertFalse($result->valid);
        $this->assertStringContainsString('Invalid NPI format', $result->error);

        // Invalid NPI - contains letters
        $result = $this->service->verifyNPI('123456789A');
        $this->assertFalse($result->valid);
        $this->assertStringContainsString('Invalid NPI format', $result->error);
    }

    /** @test */
    public function it_handles_mock_verification()
    {
        $result = $this->service->verifyNPI('1234567890');
        
        if ($result->valid) {
            $this->assertNotNull($result->npi);
            $this->assertEquals('1234567890', $result->npi);
        } else {
            $this->assertStringContainsString('mock response', $result->error);
        }
    }

    /** @test */
    public function it_caches_successful_results()
    {
        Cache::shouldReceive('get')
            ->once()
            ->with('npi_verification:1234567890')
            ->andReturn(null);

        Cache::shouldReceive('put')
            ->once()
            ->withArgs(function ($key, $data, $ttl) {
                return $key === 'npi_verification:1234567890' && is_array($data) && $ttl > 0;
            });

        $result = $this->service->verifyNPI('1234567890');
        
        // Should be a valid result that gets cached
        if ($result->valid) {
            $this->assertTrue(true); // Cache expectations will verify caching occurred
        }
    }

    /** @test */
    public function it_retrieves_from_cache()
    {
        $cachedData = [
            'valid' => true,
            'npi' => '1234567890',
            'provider_name' => 'Test Provider',
            'address' => '123 Test St',
            'city' => 'Test City',
            'state' => 'TS',
            'postal_code' => '12345',
            'primary_specialty' => 'Test Specialty',
            'last_verified' => '2024-01-01 00:00:00',
        ];

        Cache::partialMock()
            ->shouldReceive('get')
            ->once()
            ->with('npi_verification:1234567890')
            ->andReturnUsing(fn() => $cachedData);

        $result = $this->service->verifyNPI('1234567890');
        
        $this->assertTrue($result->valid);
        $this->assertEquals('1234567890', $result->npi);
        $this->assertEquals('Test Provider', $result->providerName);
        $this->assertTrue($result->fromCache);
    }

    /** @test */
    public function it_clears_individual_cache()
    {
        Cache::shouldReceive('forget')
            ->once()
            ->with('npi_verification:1234567890')
            ->andReturn(true);

        $result = $this->service->clearCache('1234567890');
        $this->assertTrue($result);
    }

    /** @test */
    public function it_batch_verifies_npis()
    {
        $npis = ['1234567890', '0987654321'];
        $results = $this->service->verifyNPIs($npis);

        $this->assertCount(2, $results);
        $this->assertArrayHasKey('1234567890', $results);
        $this->assertArrayHasKey('0987654321', $results);
        
        foreach ($results as $result) {
            $this->assertInstanceOf(NPIVerificationResult::class, $result);
        }
    }

    /** @test */
    public function it_handles_constructor_with_null_values()
    {
        // This should not throw a TypeError
        $service = new NPIVerificationService(
            useMock: null,
            apiUrl: null,
            timeout: null,
            cacheTtl: null,
            maxRetries: null,
            retryDelay: null
        );

        // Should use config defaults
        $result = $service->verifyNPI('1234567890');
        $this->assertInstanceOf(NPIVerificationResult::class, $result);
    }

    /** @test */
    public function it_handles_api_verification_with_retries()
    {
        $service = new NPIVerificationService(
            useMock: false,
            maxRetries: 2,
            retryDelay: 1
        );

        // Mock HTTP failures then success
        Http::fake([
            '*' => Http::sequence()
                ->push([], 500) // First attempt fails
                ->push([], 500) // Second attempt fails
                ->push(['results' => []], 200) // Third attempt succeeds but no results
        ]);

        $result = $service->verifyNPI('1234567890');
        
        // Should eventually succeed but find no results
        $this->assertFalse($result->valid);
        $this->assertStringContainsString('not found', $result->error);
    }

    /** @test */
    public function it_handles_cache_clearing_with_unsupported_store()
    {
        // Mock a cache store that doesn't support Redis or tags
        $mockStore = new class {
            // Empty mock store
        };

        Cache::shouldReceive('getStore')
            ->once()
            ->andReturn($mockStore);

        $result = $this->service->clearAllCache();
        $this->assertFalse($result);
    }

    /** @test */
    public function it_returns_mock_verification_result_for_valid_npi()
    {
        $result = $this->service->verifyNPI('1234567890');

        $this->assertInstanceOf(NPIVerificationResult::class, $result);
        $this->assertTrue($result->valid);
        $this->assertEquals('1234567890', $result->npi);
        $this->assertNotNull($result->lastVerified);
    }

    /** @test */
    public function it_returns_mock_failure_for_specific_npi_patterns()
    {
        // NPIs ending in 0, 3, 6, 9 should fail in mock (lastDigit % 3 === 0)
        $result = $this->service->verifyNPI('1234567890');

        $this->assertInstanceOf(NPIVerificationResult::class, $result);
        $this->assertFalse($result->valid);
        $this->assertEquals('NPI not found in registry (mock response)', $result->error);
    }

    /** @test */
    public function it_distinguishes_between_individual_and_organization_providers()
    {
        // Even last digit for individual providers
        $individualResult = $this->service->verifyNPI('1234567892');
        $this->assertNotNull($individualResult->providerName);
        $this->assertNull($individualResult->organizationName);

        // Odd last digit for organizations
        $orgResult = $this->service->verifyNPI('1234567891');
        $this->assertNull($orgResult->providerName);
        $this->assertNotNull($orgResult->organizationName);
    }

    /** @test */
    public function it_does_not_cache_failed_verification_results()
    {
        $npi = '1234567890'; // This should fail in mock

        // First call
        $result1 = $this->service->verifyNPI($npi);
        $this->assertFalse($result1->valid);
        $this->assertFalse($result1->fromCache);

        // Second call should not be from cache
        $result2 = $this->service->verifyNPI($npi);
        $this->assertFalse($result2->fromCache);
    }

    /** @test */
    public function it_can_clear_specific_npi_cache()
    {
        $npi = '1234567891';

        // Cache a result
        $this->service->verifyNPI($npi);

        // Verify it's cached
        $cachedResult = $this->service->verifyNPI($npi);
        $this->assertTrue($cachedResult->fromCache);

        // Clear cache
        $this->assertTrue($this->service->clearCache($npi));

        // Verify cache is cleared
        $newResult = $this->service->verifyNPI($npi);
        $this->assertFalse($newResult->fromCache);
    }

    /** @test */
    public function it_can_batch_verify_multiple_npis()
    {
        $npis = ['1234567891', '1234567892', '1234567893'];

        $results = $this->service->verifyNPIs($npis);

        $this->assertCount(3, $results);
        $this->assertArrayHasKey('1234567891', $results);
        $this->assertArrayHasKey('1234567892', $results);
        $this->assertArrayHasKey('1234567893', $results);

        foreach ($results as $npi => $result) {
            $this->assertInstanceOf(NPIVerificationResult::class, $result);
            $this->assertEquals($npi, $result->npi);
        }
    }

    /** @test */
    public function npi_verification_result_dto_has_correct_structure()
    {
        $result = NPIVerificationResult::success(
            npi: '1234567890',
            providerName: 'Dr. Test Provider',
            address: '123 Test Street',
            city: 'Test City',
            state: 'TC',
            postalCode: '12345',
            primarySpecialty: 'Internal Medicine',
            lastVerified: new \DateTime('2024-01-01')
        );

        $this->assertEquals('1234567890', $result->npi);
        $this->assertTrue($result->valid);
        $this->assertEquals('Dr. Test Provider', $result->providerName);
        $this->assertEquals('Dr. Test Provider', $result->getPrimaryName());
        $this->assertEquals('123 Test Street, Test City, TC, 12345', $result->getFormattedAddress());
        $this->assertTrue($result->isValidAndCurrent());
    }

    /** @test */
    public function npi_verification_result_failure_dto_has_correct_structure()
    {
        $result = NPIVerificationResult::failure(
            npi: '1234567890',
            error: 'Test error message'
        );

        $this->assertEquals('1234567890', $result->npi);
        $this->assertFalse($result->valid);
        $this->assertEquals('Test error message', $result->error);
        $this->assertNull($result->getPrimaryName());
        $this->assertNull($result->getFormattedAddress());
        $this->assertFalse($result->isValidAndCurrent());
    }

    /** @test */
    public function npi_verification_result_can_be_converted_to_array()
    {
        $result = NPIVerificationResult::success(
            npi: '1234567890',
            providerName: 'Dr. Test Provider',
            organizationName: 'Test Org',
            lastVerified: new \DateTime('2024-01-01 12:00:00')
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('1234567890', $array['npi']);
        $this->assertTrue($array['valid']);
        $this->assertEquals('Dr. Test Provider', $array['provider_name']);
        $this->assertEquals('Test Org', $array['organization_name']);
        $this->assertEquals('2024-01-01 12:00:00', $array['last_verified']);
        $this->assertEquals('Dr. Test Provider', $array['primary_name']);
    }

    /** @test */
    public function npi_verification_result_can_be_json_serialized()
    {
        $result = NPIVerificationResult::success(
            npi: '1234567890',
            providerName: 'Dr. Test Provider'
        );

        $json = json_encode($result);
        $decoded = json_decode($json, true);

        $this->assertEquals('1234567890', $decoded['npi']);
        $this->assertTrue($decoded['valid']);
        $this->assertEquals('Dr. Test Provider', $decoded['provider_name']);
    }

    /** @test */
    public function it_checks_result_validity_with_expiry()
    {
        // Current result should be valid
        $currentResult = NPIVerificationResult::success(
            npi: '1234567890',
            providerName: 'Dr. Test',
            lastVerified: new \DateTime()
        );
        $this->assertTrue($currentResult->isValidAndCurrent(30));

        // Old result should be invalid
        $oldResult = NPIVerificationResult::success(
            npi: '1234567890',
            providerName: 'Dr. Test',
            lastVerified: new \DateTime('-45 days')
        );
        $this->assertFalse($oldResult->isValidAndCurrent(30));

        // Result without verification date should be considered current
        $noDateResult = NPIVerificationResult::success(
            npi: '1234567890',
            providerName: 'Dr. Test'
        );
        $this->assertTrue($noDateResult->isValidAndCurrent(30));
    }

    /** @test */
    public function it_handles_service_exceptions_gracefully()
    {
        // Create service that will use real API (which should fail in test environment)
        $realApiService = new NPIVerificationService(
            useMock: false,
            apiUrl: 'https://invalid-url-that-should-fail.test',
            timeout: 1,
            maxRetries: 1
        );

        Log::shouldReceive('debug')->atLeast()->once();
        Log::shouldReceive('warning')->atLeast()->once();
        Log::shouldReceive('error')->once();

        $result = $realApiService->verifyNPI('1234567890');

        $this->assertInstanceOf(NPIVerificationResult::class, $result);
        $this->assertFalse($result->valid);
        $this->assertStringContainsString('Verification service unavailable', $result->error);
    }
}

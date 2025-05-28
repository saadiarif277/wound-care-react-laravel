<?php

namespace App\Services;

use App\DTOs\NPIVerificationResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class NPIVerificationService
{
    private const CACHE_PREFIX = 'npi_verification:';

    public function __construct(
        private bool $useMock = null,
        private string $apiUrl = null,
        private int $timeout = null,
        private int $cacheTtl = null,
        private int $maxRetries = null,
        private int $retryDelay = null
    ) {
        $this->useMock = $useMock ?? config('services.npi.use_mock', true);
        $this->apiUrl = $apiUrl ?? config('services.npi.api_url', 'https://npiregistry.cms.hhs.gov/api');
        $this->timeout = $timeout ?? config('services.npi.timeout', 30);
        $this->cacheTtl = $cacheTtl ?? config('services.npi.cache_ttl', 86400);
        $this->maxRetries = $maxRetries ?? config('services.npi.max_retries', 3);
        $this->retryDelay = $retryDelay ?? config('services.npi.retry_delay', 1000);
    }

    /**
     * Verify NPI number with caching and enhanced error handling
     */
    public function verifyNPI(string $npiNumber): NPIVerificationResult
    {
        try {
            // Validate NPI format first
            if (!$this->isValidNPIFormat($npiNumber)) {
                return NPIVerificationResult::failure(
                    $npiNumber,
                    'Invalid NPI format. NPI must be 10 digits.'
                );
            }

            // Check cache first
            $cacheKey = self::CACHE_PREFIX . $npiNumber;
            $cachedResult = Cache::get($cacheKey);

            if ($cachedResult) {
                Log::debug('NPI verification result retrieved from cache', ['npi' => $npiNumber]);

                // Reconstruct the DTO from cached data
                return $this->createResultFromCachedData($cachedResult, true);
            }

            // Perform verification (mock or real API)
            $result = $this->useMock
                ? $this->performMockVerification($npiNumber)
                : $this->performRealVerification($npiNumber);

            // Cache the successful result
            if ($result->valid) {
                Cache::put($cacheKey, $this->serializeResultForCache($result), $this->cacheTtl);
                Log::debug('NPI verification result cached', ['npi' => $npiNumber]);
            }

            return $result;

        } catch (Exception $e) {
            Log::error('NPI verification failed with exception', [
                'npi' => $npiNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return NPIVerificationResult::failure(
                $npiNumber,
                'Verification service unavailable: ' . $e->getMessage()
            );
        }
    }

    /**
     * Batch verify multiple NPIs efficiently
     */
    public function verifyNPIs(array $npiNumbers): array
    {
        $results = [];

        foreach ($npiNumbers as $npi) {
            $results[$npi] = $this->verifyNPI($npi);
        }

        return $results;
    }

    /**
     * Clear cached result for specific NPI
     */
    public function clearCache(string $npiNumber): bool
    {
        $cacheKey = self::CACHE_PREFIX . $npiNumber;
        return Cache::forget($cacheKey);
    }

    /**
     * Clear all NPI verification cache
     */
    public function clearAllCache(): bool
    {
        // This is a simple implementation - in production you might want to use cache tags
        $pattern = self::CACHE_PREFIX . '*';

        // For Redis cache store
        $store = Cache::getStore();
        if ($store instanceof \Illuminate\Cache\RedisStore) {
            /** @var \Illuminate\Cache\RedisStore $store */
            return $store->connection()->eval(
                "return redis.call('del', unpack(redis.call('keys', ARGV[1])))",
                0,
                $pattern
            ) > 0;
        }

        // For other cache stores, log a warning
        Log::warning('Cache clearing not implemented for current cache store');
        return false;
    }

    /**
     * Validate NPI format (10 digits)
     */
    private function isValidNPIFormat(string $npiNumber): bool
    {
        return strlen($npiNumber) === 10 && ctype_digit($npiNumber);
    }

    /**
     * Perform mock NPI verification for development/testing
     */
    private function performMockVerification(string $npiNumber): NPIVerificationResult
    {
        // Simulate some processing time
        usleep(100000); // 100ms

        // Mock different scenarios based on NPI patterns
        $lastDigit = (int) substr($npiNumber, -1);

        if ($lastDigit % 3 === 0) {
            // Mock a failure case
            return NPIVerificationResult::failure(
                $npiNumber,
                'NPI not found in registry (mock response)'
            );
        }

        // Mock successful verification
        $isProvider = $lastDigit % 2 === 0;

        if ($isProvider) {
            return NPIVerificationResult::success(
                npi: $npiNumber,
                providerName: 'Dr. Mock Provider ' . substr($npiNumber, -3),
                address: '123 Mock Medical Drive',
                city: 'Mockville',
                state: 'MC',
                postalCode: '12345',
                primarySpecialty: 'Internal Medicine',
                licenseNumber: 'LIC' . substr($npiNumber, -4),
                licenseState: 'MC',
                lastVerified: new \DateTime()
            );
        } else {
            return NPIVerificationResult::success(
                npi: $npiNumber,
                organizationName: 'Mock Medical Center ' . substr($npiNumber, -3),
                address: '456 Healthcare Boulevard',
                city: 'Mocktown',
                state: 'MC',
                postalCode: '12346',
                lastVerified: new \DateTime()
            );
        }
    }

    /**
     * Perform real NPI verification via CMS NPI Registry API
     */
    private function performRealVerification(string $npiNumber): NPIVerificationResult
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            try {
                Log::debug('Attempting NPI verification via API', [
                    'npi' => $npiNumber,
                    'attempt' => $attempt + 1,
                    'max_attempts' => $this->maxRetries
                ]);

                $response = Http::timeout($this->timeout)
                    ->get($this->apiUrl, [
                        'number' => $npiNumber,
                        'enumeration_type' => '',
                        'taxonomy_description' => '',
                        'first_name' => '',
                        'last_name' => '',
                        'organization_name' => '',
                        'address_purpose' => '',
                        'city' => '',
                        'state' => '',
                        'postal_code' => '',
                        'country_code' => '',
                        'limit' => 1,
                        'skip' => 0,
                        'pretty' => 'on',
                        'version' => '2.1'
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    return $this->parseApiResponse($npiNumber, $data);
                }

                throw new Exception("API request failed with status: " . $response->status());

            } catch (Exception $e) {
                $lastException = $e;
                $attempt++;

                Log::warning('NPI verification attempt failed', [
                    'npi' => $npiNumber,
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);

                if ($attempt < $this->maxRetries) {
                    usleep($this->retryDelay * 1000); // Convert to microseconds
                }
            }
        }

        // All attempts failed
        throw new Exception(
            "NPI verification failed after {$this->maxRetries} attempts. Last error: " .
            ($lastException ? $lastException->getMessage() : 'Unknown error')
        );
    }

    /**
     * Parse the CMS NPI Registry API response
     */
    private function parseApiResponse(string $npiNumber, array $data): NPIVerificationResult
    {
        if (empty($data['results']) || count($data['results']) === 0) {
            return NPIVerificationResult::failure(
                $npiNumber,
                'NPI not found in registry'
            );
        }

        $result = $data['results'][0];
        $basic = $result['basic'] ?? [];
        $addresses = $result['addresses'] ?? [];
        $taxonomies = $result['taxonomies'] ?? [];

        // Get primary practice address
        $practiceAddress = collect($addresses)->firstWhere('address_purpose', 'LOCATION')
                         ?? collect($addresses)->first();

        // Get primary taxonomy
        $primaryTaxonomy = collect($taxonomies)->firstWhere('primary', true)
                         ?? collect($taxonomies)->first();

        // Determine if it's an individual or organization
        $enumerationType = $basic['enumeration_type'] ?? '';
        $isIndividual = $enumerationType === 'NPI-1';

        return NPIVerificationResult::success(
            npi: $npiNumber,
            providerName: $isIndividual ? trim(($basic['first_name'] ?? '') . ' ' . ($basic['last_name'] ?? '')) : null,
            organizationName: !$isIndividual ? ($basic['organization_name'] ?? null) : null,
            address: $practiceAddress['address_1'] ?? null,
            city: $practiceAddress['city'] ?? null,
            state: $practiceAddress['state'] ?? null,
            postalCode: $practiceAddress['postal_code'] ?? null,
            primarySpecialty: $primaryTaxonomy['desc'] ?? null,
            lastVerified: new \DateTime()
        );
    }

    /**
     * Create result from cached data
     */
    private function createResultFromCachedData(array $cachedData, bool $fromCache = true): NPIVerificationResult
    {
        if (!$cachedData['valid']) {
            return NPIVerificationResult::failure(
                $cachedData['npi'],
                $cachedData['error'] ?? 'Unknown error',
                $fromCache
            );
        }

        return NPIVerificationResult::success(
            npi: $cachedData['npi'],
            providerName: $cachedData['provider_name'] ?? null,
            organizationName: $cachedData['organization_name'] ?? null,
            address: $cachedData['address'] ?? null,
            city: $cachedData['city'] ?? null,
            state: $cachedData['state'] ?? null,
            postalCode: $cachedData['postal_code'] ?? null,
            primarySpecialty: $cachedData['primary_specialty'] ?? null,
            licenseNumber: $cachedData['license_number'] ?? null,
            licenseState: $cachedData['license_state'] ?? null,
            fromCache: $fromCache,
            lastVerified: isset($cachedData['last_verified'])
                ? new \DateTime($cachedData['last_verified'])
                : null
        );
    }

    /**
     * Serialize result for caching
     */
    private function serializeResultForCache(NPIVerificationResult $result): array
    {
        return $result->toArray();
    }
}

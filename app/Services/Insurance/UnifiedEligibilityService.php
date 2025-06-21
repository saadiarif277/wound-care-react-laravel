<?php

namespace App\Services\Insurance;

use App\Models\Order\Order;
use App\Models\Order\ProductRequest;
use App\Models\Fhir\Coverage;
use App\Models\Insurance\EligibilityCheck;
use App\Services\Insurance\Providers\AvailityProvider;
use App\Services\Insurance\Providers\OptumProvider;
use App\Services\Insurance\Providers\DefaultProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UnifiedEligibilityService
{
    private array $providers = [];

    public function __construct()
    {
        // Register all available providers
        // TODO: Create provider classes that implement EligibilityProviderInterface
        // $this->registerProvider('availity', new AvailityProvider());
        // $this->registerProvider('optum', new OptumProvider());
        // $this->registerProvider('default', new DefaultProvider());
    }

    /**
     * Register an eligibility provider
     */
    public function registerProvider(string $name, EligibilityProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    /**
     * Check eligibility for a product request
     */
    public function checkEligibility(ProductRequest $productRequest): array
    {
        try {
            // Get or create coverage record
            $coverage = $this->getOrCreateCoverage($productRequest);

            // Determine which provider to use based on payer
            $provider = $this->selectProvider($coverage->payor_identifier);

            // Build unified request
            $request = $this->buildUnifiedRequest($productRequest, $coverage);
            // Check cache first
            $cacheKey = $this->generateCacheKey($request);
            $cachedResult = Cache::get($cacheKey);

            if ($cachedResult && !$this->isStale($cachedResult)) {
                Log::info('Using cached eligibility result', [
                    'cache_key' => $cacheKey,
                    'product_request_id' => $productRequest->id
                ]);
                return $cachedResult;
            }

            // Make the eligibility check
            $result = $provider->checkEligibility($request);
            // Save to database
            $eligibilityCheck = EligibilityCheck::create([
                'coverage_id' => $coverage->id,
                'product_request_id' => $productRequest->id,
                'provider' => $provider->getName(),
                'status' => $result['status'] ?? 'unknown',
                'response_data' => $result,
                'checked_at' => now(),
            ]);

            // Update product request with eligibility status
            $productRequest->update([
                'eligibility_status' => $result['status'] ?? 'unknown',
                'eligibility_checked_at' => now(),
                'eligibility_check_id' => $eligibilityCheck->id,
            ]);

            // Cache the result
            Cache::put($cacheKey, $result, now()->addHours(24));

            // Trigger events
            event(new \App\Events\EligibilityChecked($eligibilityCheck));

            return $result;

        } catch (\Exception $e) {
            Log::error('Eligibility check failed', [
                'product_request_id' => $productRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Check eligibility for an order
     */
    public function checkOrderEligibility(Order $order): array
    {
        // Convert order to product request format for unified handling
        $mockProductRequest = $this->convertOrderToProductRequest($order);
        return $this->checkEligibility($mockProductRequest);
    }

    /**
     * Get or create coverage record from product request
     */
    private function getOrCreateCoverage(ProductRequest $productRequest): Coverage
    {
        $patientData = $productRequest->patient_api_input;

        return Coverage::firstOrCreate([
            'patient_id' => $productRequest->patient->id ?? null,
            'subscriber_id' => $patientData['member_id'] ?? null,
            'payor_identifier' => $productRequest->payer_id_submitted ?? null,
        ], [
            'status' => 'active',
            'type' => 'medical',
            'beneficiary' => $productRequest->patient_fhir_id,
            'payor_name' => $productRequest->payer_name_submitted,
            'period_start' => now(),
            'period_end' => now()->addYear(),
            'verification_status' => 'pending',
        ]);
    }

    /**
     * Select the appropriate provider based on payer
     */
    private function selectProvider(string $payorIdentifier): EligibilityProviderInterface
    {
        // Load payer configuration
        $payerConfig = $this->loadPayerConfiguration($payorIdentifier);

        if ($payerConfig && isset($payerConfig['eligibility_provider'])) {
            $providerName = $payerConfig['eligibility_provider'];
            if (isset($this->providers[$providerName])) {
                return $this->providers[$providerName];
            }
        }

        // Check each provider if they support this payer
        foreach ($this->providers as $name => $provider) {
            if ($provider->supportsPayor($payorIdentifier)) {
                return $provider;
            }
        }

        // Default provider
        return $this->providers['default'];
    }

    /**
     * Build unified request from product request and coverage
     */
    private function buildUnifiedRequest(ProductRequest $productRequest, Coverage $coverage): array
    {
        return [
            'product_request_id' => $productRequest->id,
            'coverage_id' => $coverage->id,
            'patient_data' => $productRequest->patient_api_input,
            'payer_id' => $coverage->payor_identifier,
            'member_id' => $coverage->subscriber_id,
            'service_codes' => $productRequest->service_codes ?? [],
            'diagnosis_codes' => $productRequest->diagnosis_codes ?? [],
        ];
    }

    /**
     * Generate cache key for eligibility request
     */
    private function generateCacheKey(array $request): string
    {
        return 'eligibility_' . md5(json_encode($request));
    }

    /**
     * Check if cached result is stale
     */
    private function isStale(array $cachedResult): bool
    {
        $timestamp = $cachedResult['timestamp'] ?? null;
        return !$timestamp || now()->diffInHours($timestamp) > 24;
    }

    /**
     * Convert order to product request format
     */
    private function convertOrderToProductRequest(Order $order): ProductRequest
    {
        // Create a mock ProductRequest from Order data
        $productRequest = new ProductRequest();
        $productRequest->id = $order->id;
        $productRequest->patient = $order->patient;
        $productRequest->patient_fhir_id = $order->patient_fhir_id;
        $productRequest->patient_api_input = $order->patient_data ?? [];
        $productRequest->payer_id_submitted = $order->payer_id;
        $productRequest->payer_name_submitted = $order->payer_name;

        return $productRequest;
    }

    /**
     * Load payer configuration
     */
    private function loadPayerConfiguration(string $payorIdentifier): ?array
    {
        // Load from config or database
        $config = config('payers.' . $payorIdentifier);

        if (!$config) {
            // Try to load from database or external source
            Log::info('No configuration found for payer', ['payer_id' => $payorIdentifier]);
        }

        return $config;
    }
}
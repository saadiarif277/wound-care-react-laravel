<?php

namespace App\Services\Eligibility\Providers;

interface EligibilityProviderInterface
{
    /**
     * Get provider name
     */
    public function getName(): string;
    
    /**
     * Check if provider supports the given payer
     */
    public function supportsPayer(?string $payerId, ?string $payerName): bool;
    
    /**
     * Check eligibility for the given request
     */
    public function checkEligibility(array $request): array;
    
    /**
     * Get provider configuration
     */
    public function getConfig(): array;
    
    /**
     * Test provider connectivity
     */
    public function testConnection(): bool;
}
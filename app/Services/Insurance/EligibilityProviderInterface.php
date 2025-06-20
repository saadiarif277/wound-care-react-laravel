<?php

namespace App\Services\Insurance;

interface EligibilityProviderInterface
{
    /**
     * Check if this provider supports a specific payor
     */
    public function supportsPayor(string $payorIdentifier): bool;
    
    /**
     * Check eligibility for the given request
     */
    public function checkEligibility(array $request): array;
    
    /**
     * Get the provider name
     */
    public function getName(): string;
}

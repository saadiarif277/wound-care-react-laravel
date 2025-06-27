<?php

namespace App\Contracts\Eligibility;

interface EligibilityProviderInterface
{
    public function checkEligibility(array $request): array;
    public function supportsPayer(string $payerId, ?string $payerName = null): bool;
    public function getName(): string;
}

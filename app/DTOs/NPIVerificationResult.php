<?php

namespace App\DTOs;

use JsonSerializable;

class NPIVerificationResult implements JsonSerializable
{
    public function __construct(
        public readonly string $npi,
        public readonly bool $valid,
        public readonly ?string $providerName = null,
        public readonly ?string $organizationName = null,
        public readonly ?string $address = null,
        public readonly ?string $city = null,
        public readonly ?string $state = null,
        public readonly ?string $postalCode = null,
        public readonly ?string $primarySpecialty = null,
        public readonly ?string $licenseNumber = null,
        public readonly ?string $licenseState = null,
        public readonly ?string $error = null,
        public readonly bool $fromCache = false,
        public readonly ?\DateTime $lastVerified = null
    ) {}

    /**
     * Create a successful verification result
     */
    public static function success(
        string $npi,
        ?string $providerName = null,
        ?string $organizationName = null,
        ?string $address = null,
        ?string $city = null,
        ?string $state = null,
        ?string $postalCode = null,
        ?string $primarySpecialty = null,
        ?string $licenseNumber = null,
        ?string $licenseState = null,
        bool $fromCache = false,
        ?\DateTime $lastVerified = null
    ): self {
        return new self(
            npi: $npi,
            valid: true,
            providerName: $providerName,
            organizationName: $organizationName,
            address: $address,
            city: $city,
            state: $state,
            postalCode: $postalCode,
            primarySpecialty: $primarySpecialty,
            licenseNumber: $licenseNumber,
            licenseState: $licenseState,
            fromCache: $fromCache,
            lastVerified: $lastVerified
        );
    }

    /**
     * Create a failed verification result
     */
    public static function failure(
        string $npi,
        string $error,
        bool $fromCache = false
    ): self {
        return new self(
            npi: $npi,
            valid: false,
            error: $error,
            fromCache: $fromCache
        );
    }

    /**
     * Get the primary name (provider or organization)
     */
    public function getPrimaryName(): ?string
    {
        return $this->providerName ?? $this->organizationName;
    }

    /**
     * Get the full address as a formatted string
     */
    public function getFormattedAddress(): ?string
    {
        if (!$this->address) {
            return null;
        }

        $addressParts = [$this->address];

        if ($this->city) {
            $addressParts[] = $this->city;
        }

        if ($this->state) {
            $addressParts[] = $this->state;
        }

        if ($this->postalCode) {
            $addressParts[] = $this->postalCode;
        }

        return implode(', ', $addressParts);
    }

    /**
     * Check if the result is valid and not expired
     */
    public function isValidAndCurrent(int $maxAgeInDays = 30): bool
    {
        if (!$this->valid) {
            return false;
        }

        if (!$this->lastVerified) {
            return true; // If no verification date, consider it current
        }

        $expiryDate = (clone $this->lastVerified)->modify("+{$maxAgeInDays} days");
        return new \DateTime() <= $expiryDate;
    }

    /**
     * Convert to array for backward compatibility
     */
    public function toArray(): array
    {
        return [
            'npi' => $this->npi,
            'valid' => $this->valid,
            'provider_name' => $this->providerName,
            'organization_name' => $this->organizationName,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postalCode,
            'primary_specialty' => $this->primarySpecialty,
            'license_number' => $this->licenseNumber,
            'license_state' => $this->licenseState,
            'error' => $this->error,
            'from_cache' => $this->fromCache,
            'last_verified' => $this->lastVerified?->format('Y-m-d H:i:s'),
            'formatted_address' => $this->getFormattedAddress(),
            'primary_name' => $this->getPrimaryName()
        ];
    }

    /**
     * JSON serialization
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

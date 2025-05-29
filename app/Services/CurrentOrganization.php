<?php

namespace App\Services;

use App\Models\Users\Organization\Organization;

class CurrentOrganization
{
    private ?int $organizationId = null;
    private ?Organization $organization = null;

    /**
     * Set the current organization ID
     */
    public function setId(?int $organizationId): void
    {
        $this->organizationId = $organizationId;
        $this->organization = null; // Reset cached organization
    }

    /**
     * Get the current organization ID
     */
    public function getId(): ?int
    {
        return $this->organizationId;
    }

    /**
     * Get the current organization model
     */
    public function getOrganization(): ?Organization
    {
        if ($this->organization === null && $this->organizationId !== null) {
            $this->organization = Organization::find($this->organizationId);
        }

        return $this->organization;
    }

    /**
     * Set the current organization model directly
     */
    public function setOrganization(Organization $organization): void
    {
        $this->organization = $organization;
        $this->organizationId = $organization->id;
    }

    /**
     * Check if an organization is currently set
     */
    public function hasOrganization(): bool
    {
        return $this->organizationId !== null;
    }

    /**
     * Clear the current organization context
     */
    public function clear(): void
    {
        $this->organizationId = null;
        $this->organization = null;
    }

    /**
     * Legacy property access for backward compatibility
     */
    public function __get($property)
    {
        return match($property) {
            'id' => $this->getId(),
            'organization' => $this->getOrganization(),
            default => null,
        };
    }
}

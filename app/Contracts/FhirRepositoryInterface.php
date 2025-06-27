<?php

namespace App\Contracts;

interface FhirRepositoryInterface
{
    /**
     * Set the FHIR resource type for this repository
     */
    public function setResourceType(string $resourceType): self;

    /**
     * Create a new FHIR resource
     */
    public function create(array $data): array;

    /**
     * Find a FHIR resource by ID
     */
    public function find(string $id): ?array;

    /**
     * Update a FHIR resource
     */
    public function update(string $id, array $data): array;

    /**
     * Delete a FHIR resource
     */
    public function delete(string $id): bool;

    /**
     * Search for FHIR resources
     */
    public function search(array $parameters = []): array;

    /**
     * Get resource history
     */
    public function history(string $id): array;

    /**
     * Batch create resources
     */
    public function batchCreate(array $resources): array;

    /**
     * Check if resource exists
     */
    public function exists(string $id): bool;
}
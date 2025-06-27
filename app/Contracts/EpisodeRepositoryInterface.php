<?php

namespace App\Contracts;

use App\Models\Episode;
use Illuminate\Database\Eloquent\Collection;

interface EpisodeRepositoryInterface extends RepositoryInterface
{
    /**
     * Get episodes by patient and manufacturer
     */
    public function findByPatientAndManufacturer(string $patientFhirId, int $manufacturerId): ?Episode;

    /**
     * Get active episodes for a patient
     */
    public function getActiveEpisodesByPatient(string $patientFhirId): Collection;

    /**
     * Get episodes by provider
     */
    public function getEpisodesByProvider(string $providerFhirId, array $statuses = []): Collection;

    /**
     * Get episodes pending manufacturer review
     */
    public function getPendingManufacturerReview(int $manufacturerId): Collection;

    /**
     * Get episodes statistics for organization
     */
    public function getOrganizationStatistics(string $organizationFhirId): array;

    /**
     * Get episodes with expired documents
     */
    public function getEpisodesWithExpiredDocuments(): Collection;

    /**
     * Update episode status with validation
     */
    public function updateStatus(string $episodeId, string $newStatus, ?int $userId = null): bool;

    /**
     * Search episodes
     */
    public function searchEpisodes(array $criteria): Collection;
}
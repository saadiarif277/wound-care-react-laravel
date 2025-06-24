<?php

namespace App\Repositories;

use App\Models\Episode;
use App\Contracts\EpisodeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class EpisodeRepository extends BaseRepository implements EpisodeRepositoryInterface
{
    public function __construct(Episode $model)
    {
        parent::__construct($model);
    }

    /**
     * Get episodes by patient and manufacturer
     */
    public function findByPatientAndManufacturer(string $patientFhirId, int $manufacturerId): ?Episode
    {
        return $this->model
            ->where('patient_fhir_id', $patientFhirId)
            ->where('manufacturer_id', $manufacturerId)
            ->whereIn('status', ['draft', 'pending_review', 'active'])
            ->latest()
            ->first();
    }

    /**
     * Get active episodes for a patient
     */
    public function getActiveEpisodesByPatient(string $patientFhirId): Collection
    {
        return Cache::remember(
            "episodes.patient.{$patientFhirId}",
            300, // 5 minutes
            fn () => $this->model
                ->where('patient_fhir_id', $patientFhirId)
                ->whereIn('status', ['draft', 'pending_review', 'active', 'manufacturer_review'])
                ->with(['orders', 'manufacturer'])
                ->orderBy('created_at', 'desc')
                ->get()
        );
    }

    /**
     * Get episodes by provider
     */
    public function getEpisodesByProvider(string $providerFhirId, array $statuses = []): Collection
    {
        $query = $this->model
            ->where('practitioner_fhir_id', $providerFhirId)
            ->with(['orders', 'manufacturer']);

        if (!empty($statuses)) {
            $query->whereIn('status', $statuses);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get episodes pending manufacturer review
     */
    public function getPendingManufacturerReview(int $manufacturerId): Collection
    {
        return $this->model
            ->where('manufacturer_id', $manufacturerId)
            ->where('status', 'manufacturer_review')
            ->with(['orders' => function ($query) {
                $query->where('status', 'pending');
            }])
            ->orderBy('reviewed_at', 'asc')
            ->get();
    }

    /**
     * Get episodes statistics for organization
     */
    public function getOrganizationStatistics(string $organizationFhirId): array
    {
        return Cache::remember(
            "episodes.stats.org.{$organizationFhirId}",
            3600, // 1 hour
            function () use ($organizationFhirId) {
                $baseQuery = $this->model->where('organization_fhir_id', $organizationFhirId);

                return [
                    'total' => $baseQuery->count(),
                    'active' => $baseQuery->clone()->whereIn('status', ['active', 'manufacturer_review'])->count(),
                    'completed' => $baseQuery->clone()->where('status', 'completed')->count(),
                    'draft' => $baseQuery->clone()->where('status', 'draft')->count(),
                    'pending_review' => $baseQuery->clone()->where('status', 'pending_review')->count(),
                    'by_manufacturer' => $baseQuery->clone()
                        ->selectRaw('manufacturer_id, COUNT(*) as count')
                        ->groupBy('manufacturer_id')
                        ->pluck('count', 'manufacturer_id')
                        ->toArray(),
                ];
            }
        );
    }

    /**
     * Get episodes with expired documents
     */
    public function getEpisodesWithExpiredDocuments(): Collection
    {
        return $this->model
            ->whereHas('documents', function ($query) {
                $query->where('expires_at', '<', now())
                    ->where('type', 'insurance_verification');
            })
            ->where('status', '!=', 'completed')
            ->with(['documents', 'manufacturer'])
            ->get();
    }

    /**
     * Update episode status with validation
     */
    public function updateStatus(string $episodeId, string $newStatus, ?int $userId = null): bool
    {
        $episode = $this->find($episodeId);
        
        if (!$episode || !$this->canTransitionToStatus($episode->status, $newStatus)) {
            return false;
        }

        $updateData = ['status' => $newStatus];

        // Add metadata for specific status transitions
        switch ($newStatus) {
            case 'manufacturer_review':
                $updateData['reviewed_at'] = now();
                $updateData['reviewed_by'] = $userId;
                break;
            case 'completed':
                $updateData['completed_at'] = now();
                break;
        }

        // Clear cache
        Cache::forget("episodes.patient.{$episode->patient_fhir_id}");
        Cache::forget("episodes.stats.org.{$episode->organization_fhir_id}");

        return $episode->update($updateData);
    }

    /**
     * Check if status transition is valid
     */
    private function canTransitionToStatus(string $currentStatus, string $newStatus): bool
    {
        $validTransitions = [
            'draft' => ['pending_review', 'cancelled'],
            'pending_review' => ['manufacturer_review', 'draft', 'cancelled'],
            'manufacturer_review' => ['active', 'pending_review', 'cancelled'],
            'active' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => ['draft'],
        ];

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }

    /**
     * Search episodes
     */
    public function searchEpisodes(array $criteria): Collection
    {
        $query = $this->model->query();

        if (!empty($criteria['patient_name'])) {
            // This would need to join with FHIR data or maintain a denormalized field
            $query->where('patient_display_name', 'LIKE', "%{$criteria['patient_name']}%");
        }

        if (!empty($criteria['provider_id'])) {
            $query->where('practitioner_fhir_id', $criteria['provider_id']);
        }

        if (!empty($criteria['manufacturer_id'])) {
            $query->where('manufacturer_id', $criteria['manufacturer_id']);
        }

        if (!empty($criteria['status'])) {
            $query->where('status', $criteria['status']);
        }

        if (!empty($criteria['date_from'])) {
            $query->where('created_at', '>=', $criteria['date_from']);
        }

        if (!empty($criteria['date_to'])) {
            $query->where('created_at', '<=', $criteria['date_to']);
        }

        return $query->with(['orders', 'manufacturer'])
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
    }
}
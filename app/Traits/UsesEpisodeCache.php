<?php

namespace App\Traits;

use App\Services\EpisodeTemplateCacheService;
use App\Services\FhirService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

trait UsesEpisodeCache
{
    /**
     * Get the episode cache service instance
     */
    protected function episodeCache(): EpisodeTemplateCacheService
    {
        return App::make(EpisodeTemplateCacheService::class);
    }

    /**
     * Get cached episode data with automatic warming
     */
    public function getCachedEpisodeData(bool $forceRefresh = false): array
    {
        try {
            return $this->episodeCache()->getEpisodeData($this, $forceRefresh);
        } catch (\Exception $e) {
            Log::error('Failed to get cached episode data', [
                'episode_id' => $this->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get specific cached resource
     */
    public function getCachedResource(string $resourceType, string $resourceId): ?array
    {
        return $this->episodeCache()->getCachedResource($this->id, $resourceType, $resourceId);
    }

    /**
     * Warm cache for this episode
     */
    public function warmCache(): void
    {
        $this->episodeCache()->warmEpisodeCache($this);
    }

    /**
     * Invalidate cache for this episode
     */
    public function invalidateCache(string $reason = 'manual'): void
    {
        $this->episodeCache()->invalidateEpisodeCache($this, $reason);
    }

    /**
     * Get patient data from cache
     */
    public function getCachedPatientData(): ?array
    {
        if (!$this->patient_fhir_id) {
            return null;
        }
        
        return $this->getCachedResource('Patient', $this->patient_fhir_id);
    }

    /**
     * Get coverage data from cache
     */
    public function getCachedCoverageData(): array
    {
        $bundleData = $this->getCachedEpisodeData();
        $coverages = [];
        
        if (isset($bundleData['entry'])) {
            foreach ($bundleData['entry'] as $entry) {
                if (isset($entry['resource']) && $entry['resource']['resourceType'] === 'Coverage') {
                    $coverages[] = $entry['resource'];
                }
            }
        }
        
        return $coverages;
    }

    /**
     * Get provider data from cache
     */
    public function getCachedProviderData(): array
    {
        $bundleData = $this->getCachedEpisodeData();
        $providers = [];
        
        if (isset($bundleData['entry'])) {
            foreach ($bundleData['entry'] as $entry) {
                if (isset($entry['resource']) && $entry['resource']['resourceType'] === 'Practitioner') {
                    $providers[] = $entry['resource'];
                }
            }
        }
        
        return $providers;
    }

    /**
     * Get all clinical observations from cache
     */
    public function getCachedObservations(string $code = null): array
    {
        $bundleData = $this->getCachedEpisodeData();
        $observations = [];
        
        if (isset($bundleData['entry'])) {
            foreach ($bundleData['entry'] as $entry) {
                if (isset($entry['resource']) && $entry['resource']['resourceType'] === 'Observation') {
                    if ($code === null || $this->observationHasCode($entry['resource'], $code)) {
                        $observations[] = $entry['resource'];
                    }
                }
            }
        }
        
        return $observations;
    }

    /**
     * Check if observation has specific code
     */
    private function observationHasCode(array $observation, string $code): bool
    {
        if (!isset($observation['code']['coding'])) {
            return false;
        }
        
        foreach ($observation['code']['coding'] as $coding) {
            if (isset($coding['code']) && $coding['code'] === $code) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Boot the trait - add model events
     */
    public static function bootUsesEpisodeCache()
    {
        // Warm cache when episode is created
        static::created(function ($episode) {
            // Temporarily disabled to prevent order creation failures
            // TODO: Fix provider_fhir_id issue in EpisodeTemplateCacheService
            // $episode->warmCache();
        });
        
        // Invalidate cache on status changes
        static::updated(function ($episode) {
            if ($episode->isDirty('status') || $episode->isDirty('ivr_status')) {
                $episode->invalidateCache('status_change');
            }
        });
        
        // Clear cache when episode is deleted
        static::deleted(function ($episode) {
            $episode->invalidateCache('deleted');
        });
    }
}

<?php

namespace App\Services;

use App\Models\PatientManufacturerIVREpisode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
class EpisodeTemplateCacheService
{
    private FhirService $fhirService;
    private array $cacheConfig;

    // Episode template types based on wound care assessment patterns
    const TEMPLATE_STANDARD_WOUND = 'standard_wound_care';
    const TEMPLATE_DIABETIC_WOUND = 'diabetic_wound_care';
    const TEMPLATE_PRESSURE_ULCER = 'pressure_ulcer_care';
    const TEMPLATE_SURGICAL_WOUND = 'surgical_wound_care';
    const TEMPLATE_VASCULAR_WOUND = 'vascular_wound_care';

    public function __construct(FhirService $fhirService)
    {
        $this->fhirService = $fhirService;
        $this->cacheConfig = config('cache.episode_templates', [
            'ttl' => [
                'active_episode' => 86400,    // 24 hours for active episodes
                'pending_episode' => 3600,    // 1 hour for pending
                'completed_episode' => 300,   // 5 minutes for completed
                'reference_data' => 172800,   // 48 hours for reference data
            ],
            'prefetch' => [
                'enabled' => true,
                'advance_minutes' => 30,
            ]
        ]);
    }

    /**
     * Warm cache when episode is created or becomes active
     */
    public function warmEpisodeCache(PatientManufacturerIVREpisode $episode): void
    {
        try {
            $startTime = microtime(true);

            // Determine template type based on episode metadata
            $templateType = $this->determineTemplateType($episode);

            // Get all required FHIR resources for this template in one batch
            $fhirBundle = $this->fetchEpisodeTemplateBundle($episode, $templateType);

            // Cache the entire bundle
            $this->cacheEpisodeBundle($episode, $fhirBundle);

            // Cache individual resources for granular access
            $this->cacheIndividualResources($episode, $fhirBundle);

            // Pre-cache manufacturer-specific requirements
            $this->cacheManufacturerRequirements($episode);

            $duration = round(microtime(true) - $startTime, 2);
            Log::info('Episode cache warmed', [
                'episode_id' => $episode->id,
                'template_type' => $templateType,
                'duration_seconds' => $duration,
                'resources_cached' => count($fhirBundle['entry'] ?? [])
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to warm episode cache', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Determine template type based on episode metadata and orders
     */
    private function determineTemplateType(PatientManufacturerIVREpisode $episode): string
    {
        // Check episode metadata for wound type indicators
        $metadata = $episode->metadata ?? [];

        if (isset($metadata['wound_type'])) {
            switch (strtolower($metadata['wound_type'])) {
                case 'diabetic':
                case 'diabetic_foot':
                    return self::TEMPLATE_DIABETIC_WOUND;

                case 'pressure':
                case 'pressure_ulcer':
                    return self::TEMPLATE_PRESSURE_ULCER;

                case 'surgical':
                case 'post_surgical':
                    return self::TEMPLATE_SURGICAL_WOUND;

                case 'vascular':
                case 'venous':
                case 'arterial':
                    return self::TEMPLATE_VASCULAR_WOUND;
            }
        }

        // Check first order for wound type hints
        $firstOrder = $episode->orders()->first();
        if ($firstOrder && isset($firstOrder->metadata['assessment_type'])) {
            // Map assessment types to templates
            return $this->mapAssessmentToTemplate($firstOrder->metadata['assessment_type']);
        }

        return self::TEMPLATE_STANDARD_WOUND;
    }

    /**
     * Fetch all required FHIR resources for an episode template
     */
    private function fetchEpisodeTemplateBundle(PatientManufacturerIVREpisode $episode, string $templateType): array
    {
        // Build a FHIR Bundle transaction to get all resources in one call
        $bundleEntries = [];

        // Core patient data (always needed)
        $bundleEntries[] = [
            'request' => [
                'method' => 'GET',
                'url' => "Patient/{$episode->patient_fhir_id}?_elements=identifier,name,birthDate,gender,address,telecom"
            ]
        ];

        // Active coverage/insurance
        $bundleEntries[] = [
            'request' => [
                'method' => 'GET',
                'url' => "Coverage?patient={$episode->patient_fhir_id}&status=active&_include=Coverage:payor"
            ]
        ];

        // Provider information from orders
        // TODO: Fix this - provider_id is not a FHIR ID
        // Temporarily disabled to prevent cache warming failures
        /*
        $providerIds = $episode->orders()->pluck('provider_id')->unique()->filter();
        foreach ($providerIds as $providerId) {
            $bundleEntries[] = [
                'request' => [
                    'method' => 'GET',
                    'url' => "Practitioner/{$providerId}?_include=Practitioner:organization"
                ]
            ];
        }
        */

        // Template-specific resources
        $bundleEntries = array_merge($bundleEntries, $this->getTemplateSpecificRequests($episode, $templateType));

        // Execute the bundle transaction
        $bundle = [
            'resourceType' => 'Bundle',
            'type' => 'batch',
            'entry' => $bundleEntries
        ];

        // TODO: Implement bundle execution in FhirService
        return [
            'resourceType' => 'Bundle',
            'type' => 'batch-response',
            'entry' => []
        ];
    }

    /**
     * Get template-specific FHIR requests based on wound type
     */
    private function getTemplateSpecificRequests(PatientManufacturerIVREpisode $episode, string $templateType): array
    {
        $requests = [];
        $patientId = $episode->patient_fhir_id;

        switch ($templateType) {
            case self::TEMPLATE_DIABETIC_WOUND:
                // HbA1c observations
                $requests[] = [
                    'request' => [
                        'method' => 'GET',
                        'url' => "Observation?patient={$patientId}&code=4548-4&_sort=-date&_count=5"
                    ]
                ];
                // Glucose observations
                $requests[] = [
                    'request' => [
                        'method' => 'GET',
                        'url' => "Observation?patient={$patientId}&code=2339-0&_sort=-date&_count=10"
                    ]
                ];
                // Diabetic conditions
                $requests[] = [
                    'request' => [
                        'method' => 'GET',
                        'url' => "Condition?patient={$patientId}&code=44054006"
                    ]
                ];
                break;

            case self::TEMPLATE_PRESSURE_ULCER:
                // Braden scale assessments
                $requests[] = [
                    'request' => [
                        'method' => 'GET',
                        'url' => "Observation?patient={$patientId}&code=38227-7&_sort=-date&_count=5"
                    ]
                ];
                // Mobility assessments
                $requests[] = [
                    'request' => [
                        'method' => 'GET',
                        'url' => "Observation?patient={$patientId}&code=89414-4&_sort=-date&_count=3"
                    ]
                ];
                break;

            case self::TEMPLATE_VASCULAR_WOUND:
                // ABI (Ankle-Brachial Index)
                $requests[] = [
                    'request' => [
                        'method' => 'GET',
                        'url' => "Observation?patient={$patientId}&code=41979-6&_sort=-date&_count=3"
                    ]
                ];
                // Vascular conditions
                $requests[] = [
                    'request' => [
                        'method' => 'GET',
                        'url' => "Condition?patient={$patientId}&category=vascular"
                    ]
                ];
                break;
        }

        // Common wound assessments for all templates
        $requests[] = [
            'request' => [
                'method' => 'GET',
                'url' => "Observation?patient={$patientId}&code=89191-2&_sort=-date&_count=5"
            ]
        ];

        // Recent procedures
        $requests[] = [
            'request' => [
                'method' => 'GET',
                'url' => "Procedure?patient={$patientId}&_sort=-date&_count=10"
            ]
        ];

        // Active medications
        $requests[] = [
            'request' => [
                'method' => 'GET',
                'url' => "MedicationRequest?patient={$patientId}&status=active"
            ]
        ];

        return $requests;
    }

    /**
     * Cache the entire episode bundle
     */
    private function cacheEpisodeBundle(PatientManufacturerIVREpisode $episode, array $bundle): void
    {
        $cacheKey = $this->getEpisodeBundleCacheKey($episode);
        $ttl = $this->getEpisodeCacheTTL($episode);

        Cache::put($cacheKey, $bundle, $ttl);

        // Also cache a compressed version for long-term storage
        $compressedBundle = gzcompress(json_encode($bundle), 9);
        Cache::put("{$cacheKey}:compressed", $compressedBundle, $ttl * 4);
    }

    /**
     * Cache individual resources for granular access
     */
    private function cacheIndividualResources(PatientManufacturerIVREpisode $episode, array $bundle): void
    {
        if (!isset($bundle['entry'])) {
            return;
        }

        $ttl = $this->getEpisodeCacheTTL($episode);

        foreach ($bundle['entry'] as $entry) {
            if (isset($entry['resource'])) {
                $resource = $entry['resource'];
                $resourceType = $resource['resourceType'] ?? null;
                $resourceId = $resource['id'] ?? null;

                if ($resourceType && $resourceId) {
                    $cacheKey = $this->getResourceCacheKey($episode->id, $resourceType, $resourceId);

                    // Use different TTLs for different resource types
                    $resourceTTL = $this->getResourceTypeTTL($resourceType, $ttl);
                    Cache::put($cacheKey, $resource, $resourceTTL);
                }
            }
        }
    }

    /**
     * Cache manufacturer-specific requirements
     */
    private function cacheManufacturerRequirements(PatientManufacturerIVREpisode $episode): void
    {
        $manufacturer = $episode->manufacturer;
        if (!$manufacturer) {
            return;
        }

        // Cache manufacturer-specific document requirements
        $cacheKey = "episode:{$episode->id}:manufacturer:{$manufacturer->id}:requirements";

        $requirements = [
            'required_documents' => $manufacturer->required_documents ?? [],
            'additional_fields' => $manufacturer->additional_ivr_fields ?? [],
            'special_instructions' => $manufacturer->special_instructions ?? [],
            'submission_format' => $manufacturer->submission_format ?? 'standard',
        ];

        Cache::put($cacheKey, $requirements, $this->cacheConfig['ttl']['reference_data']);
    }

    /**
     * Get or fetch episode data with caching
     */
    public function getEpisodeData(PatientManufacturerIVREpisode $episode, bool $forceRefresh = false): array
    {
        $cacheKey = $this->getEpisodeBundleCacheKey($episode);

        // Check if we need to refresh
        if ($forceRefresh) {
            Cache::forget($cacheKey);
            Cache::forget("{$cacheKey}:compressed");
        }

        // Try to get from cache
        $cachedData = Cache::get($cacheKey);

        if (!$cachedData) {
            // Try compressed cache
            $compressedData = Cache::get("{$cacheKey}:compressed");
            if ($compressedData) {
                $cachedData = json_decode(gzuncompress($compressedData), true);
                // Re-cache uncompressed for faster access
                Cache::put($cacheKey, $cachedData, 300);
            }
        }

        if (!$cachedData) {
            // Fetch fresh data
            $this->warmEpisodeCache($episode);
            $cachedData = Cache::get($cacheKey);
        }

        return $cachedData ?? [];
    }

    /**
     * Get specific resource from cache
     */
    public function getCachedResource(string $episodeId, string $resourceType, string $resourceId): ?array
    {
        $cacheKey = $this->getResourceCacheKey($episodeId, $resourceType, $resourceId);
        return Cache::get($cacheKey);
    }

    /**
     * Invalidate episode cache based on status changes
     */
    public function invalidateEpisodeCache(PatientManufacturerIVREpisode $episode, string $reason = 'status_change'): void
    {
        Log::info('Invalidating episode cache', [
            'episode_id' => $episode->id,
            'reason' => $reason
        ]);

        // Remove main bundle cache
        $bundleKey = $this->getEpisodeBundleCacheKey($episode);
        Cache::forget($bundleKey);
        Cache::forget("{$bundleKey}:compressed");

        // Remove individual resource caches
        $pattern = "episode:{$episode->id}:resource:*";
        $this->forgetCachePattern($pattern);

        // Re-warm if episode is still active
        if (in_array($episode->status, [
            PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW,
            PatientManufacturerIVREpisode::STATUS_IVR_SENT,
            PatientManufacturerIVREpisode::STATUS_IVR_VERIFIED
        ])) {
            $this->warmEpisodeCache($episode);
        }
    }

    /**
     * Pre-cache upcoming appointments
     */
    public function preCacheUpcomingEpisodes(): void
    {
        if (!$this->cacheConfig['prefetch']['enabled']) {
            return;
        }

        $advanceMinutes = $this->cacheConfig['prefetch']['advance_minutes'];

        // Find episodes likely to be accessed soon
        $upcomingEpisodes = PatientManufacturerIVREpisode::where('status', PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW)
            ->whereHas('orders', function ($query) use ($advanceMinutes) {
                $query->where('appointment_date', '>=', now())
                    ->where('appointment_date', '<=', now()->addMinutes($advanceMinutes));
            })
            ->get();

        foreach ($upcomingEpisodes as $episode) {
            $this->warmEpisodeCache($episode);
        }
    }

    /**
     * Get cache statistics for monitoring
     */
    public function getCacheStats(): array
    {
        return [
            'active_episodes_cached' => $this->countCachedEpisodes('active'),
            'total_cached_resources' => $this->countCachedResources(),
            'cache_hit_rate' => $this->calculateHitRate(),
            'average_cache_size_kb' => $this->calculateAverageCacheSize(),
        ];
    }

    // Utility methods

    private function getEpisodeBundleCacheKey(PatientManufacturerIVREpisode $episode): string
    {
        return "episode:{$episode->id}:bundle:v1";
    }

    private function getResourceCacheKey(string $episodeId, string $resourceType, string $resourceId): string
    {
        return "episode:{$episodeId}:resource:{$resourceType}:{$resourceId}";
    }

    private function getEpisodeCacheTTL(PatientManufacturerIVREpisode $episode): int
    {
        switch ($episode->status) {
            case PatientManufacturerIVREpisode::STATUS_COMPLETED:
                return $this->cacheConfig['ttl']['completed_episode'];

            case PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW:
            case PatientManufacturerIVREpisode::STATUS_IVR_SENT:
            case PatientManufacturerIVREpisode::STATUS_IVR_VERIFIED:
                return $this->cacheConfig['ttl']['active_episode'];

            default:
                return $this->cacheConfig['ttl']['pending_episode'];
        }
    }

    private function getResourceTypeTTL(string $resourceType, int $defaultTTL): int
    {
        // Reference data gets longer TTL
        $referenceTypes = ['Practitioner', 'Organization', 'Location'];
        if (in_array($resourceType, $referenceTypes)) {
            return $this->cacheConfig['ttl']['reference_data'];
        }

        // Clinical data uses episode TTL
        return $defaultTTL;
    }

    private function mapAssessmentToTemplate(string $assessmentType): string
    {
        $mapping = [
            'diabetic_foot_assessment' => self::TEMPLATE_DIABETIC_WOUND,
            'pressure_ulcer_assessment' => self::TEMPLATE_PRESSURE_ULCER,
            'vascular_assessment' => self::TEMPLATE_VASCULAR_WOUND,
            'surgical_wound_assessment' => self::TEMPLATE_SURGICAL_WOUND,
        ];

        return $mapping[$assessmentType] ?? self::TEMPLATE_STANDARD_WOUND;
    }

    private function forgetCachePattern(string $pattern): void
    {
        // For database cache driver, we need to handle pattern deletion differently
        // This is a simplified version - in production you might want to use Redis
        $cacheDriver = config('cache.default');

        if ($cacheDriver === 'database') {
            // For database driver, we'd need to query the cache table
            // This is a limitation of database caching
            Log::warning('Pattern cache deletion not fully supported with database driver', ['pattern' => $pattern]);
        }
    }

    private function countCachedEpisodes(string $status = null): int
    {
        // Implementation would depend on cache driver
        return 0; // Placeholder
    }

    private function countCachedResources(): int
    {
        // Implementation would depend on cache driver
        return 0; // Placeholder
    }

    private function calculateHitRate(): float
    {
        // Would track hits/misses in production
        return 0.0; // Placeholder
    }

    private function calculateAverageCacheSize(): float
    {
        // Would calculate average size of cached items
        return 0.0; // Placeholder
    }
}

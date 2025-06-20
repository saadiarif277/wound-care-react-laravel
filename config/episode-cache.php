<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Episode Template Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the episode-based FHIR caching system that reduces
    | Azure Health Data Services costs by intelligently caching data at the
    | episode level rather than individual order level.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Cache TTL Settings
    |--------------------------------------------------------------------------
    |
    | Time-to-live settings for different episode states and resource types.
    | Values are in seconds.
    |
    */
    'ttl' => [
        // Active episodes (ready for review, IVR sent, verified)
        'active_episode' => env('EPISODE_CACHE_TTL_ACTIVE', 86400), // 24 hours
        
        // Pending episodes (not yet active)
        'pending_episode' => env('EPISODE_CACHE_TTL_PENDING', 3600), // 1 hour
        
        // Completed episodes
        'completed_episode' => env('EPISODE_CACHE_TTL_COMPLETED', 300), // 5 minutes
        
        // Reference data (providers, organizations, manufacturers)
        'reference_data' => env('EPISODE_CACHE_TTL_REFERENCE', 172800), // 48 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Prefetch Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for predictive cache warming based on appointments
    | and episode patterns.
    |
    */
    'prefetch' => [
        // Enable/disable predictive caching
        'enabled' => env('EPISODE_CACHE_PREFETCH_ENABLED', true),
        
        // How many minutes before appointment to warm cache
        'advance_minutes' => env('EPISODE_CACHE_PREFETCH_MINUTES', 30),
        
        // Maximum episodes to prefetch in one batch
        'max_batch_size' => env('EPISODE_CACHE_PREFETCH_BATCH', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Template Definitions
    |--------------------------------------------------------------------------
    |
    | Define which FHIR resources to cache for each wound care template type.
    | These templates optimize caching based on the type of wound being treated.
    |
    */
    'templates' => [
        'standard_wound_care' => [
            'name' => 'Standard Wound Care',
            'resources' => [
                'Patient', 'Coverage', 'Practitioner', 'Organization',
                'Observation', 'Procedure', 'MedicationRequest'
            ],
        ],
        
        'diabetic_wound_care' => [
            'name' => 'Diabetic Wound Care',
            'resources' => [
                'Patient', 'Coverage', 'Practitioner', 'Organization',
                'Observation', 'Procedure', 'MedicationRequest', 'Condition'
            ],
            'specific_codes' => [
                '4548-4', // HbA1c
                '2339-0', // Glucose
                '44054006', // Diabetes diagnosis
            ],
        ],
        
        'pressure_ulcer_care' => [
            'name' => 'Pressure Ulcer Care',
            'resources' => [
                'Patient', 'Coverage', 'Practitioner', 'Organization',
                'Observation', 'Procedure', 'MedicationRequest'
            ],
            'specific_codes' => [
                '38227-7', // Braden Scale
                '89414-4', // Mobility assessment
            ],
        ],
        
        'vascular_wound_care' => [
            'name' => 'Vascular Wound Care',
            'resources' => [
                'Patient', 'Coverage', 'Practitioner', 'Organization',
                'Observation', 'Procedure', 'MedicationRequest', 'Condition'
            ],
            'specific_codes' => [
                '41979-6', // Ankle-Brachial Index
            ],
        ],
        
        'surgical_wound_care' => [
            'name' => 'Surgical Wound Care',
            'resources' => [
                'Patient', 'Coverage', 'Practitioner', 'Organization',
                'Observation', 'Procedure', 'MedicationRequest'
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Settings to control cache performance and resource usage.
    |
    */
    'performance' => [
        // Enable compression for large cache entries
        'compression_enabled' => env('EPISODE_CACHE_COMPRESSION', true),
        
        // Compression level (1-9, where 9 is maximum compression)
        'compression_level' => env('EPISODE_CACHE_COMPRESSION_LEVEL', 6),
        
        // Maximum cache entry size in KB (larger entries won't be cached)
        'max_entry_size_kb' => env('EPISODE_CACHE_MAX_SIZE_KB', 5120), // 5MB
        
        // Enable cache statistics tracking
        'track_statistics' => env('EPISODE_CACHE_TRACK_STATS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Storage
    |--------------------------------------------------------------------------
    |
    | Which cache store to use for episode caching. Can be different from
    | the default Laravel cache store for better performance isolation.
    |
    */
    'store' => env('EPISODE_CACHE_STORE', config('cache.default')),
];

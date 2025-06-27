<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fuzzy Mapping Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the IVR fuzzy field mapping system
    |
    */

    'test_mode' => env('FUZZY_MAPPING_TEST_MODE', false),
    
    'cache_ttl' => env('FUZZY_MAPPING_CACHE_TTL', 3600), // 1 hour
    
    'confidence_thresholds' => [
        'high' => 0.8,
        'medium' => 0.6,
        'low' => 0.4,
    ],
    
    'validation' => [
        'strict_mode' => env('FUZZY_MAPPING_STRICT_VALIDATION', true),
        'allow_empty_required' => env('FUZZY_MAPPING_ALLOW_EMPTY_REQUIRED', false),
    ],
    
    'fuzzy_matching' => [
        'levenshtein_weight' => 0.4,
        'jaro_winkler_weight' => 0.4,
        'token_similarity_weight' => 0.2,
    ],
];
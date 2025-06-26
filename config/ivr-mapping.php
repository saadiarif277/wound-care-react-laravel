<?php

return [
    /*
    |--------------------------------------------------------------------------
    | IVR Fuzzy Mapping Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the IVR field mapping system that handles fuzzy matching
    | between FHIR data and manufacturer-specific IVR templates.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Matching Thresholds
    |--------------------------------------------------------------------------
    |
    | Configure the minimum confidence scores required for different matching strategies.
    |
    */
    'thresholds' => [
        'fuzzy' => 0.7,          // Minimum score for fuzzy string matching
        'semantic' => 0.8,       // Minimum score for semantic matching
        'pattern' => 0.75,       // Minimum score for pattern matching
        'auto_accept' => 0.95,   // Score above which mappings are auto-accepted
    ],

    /*
    |--------------------------------------------------------------------------
    | Matching Boosts
    |--------------------------------------------------------------------------
    |
    | Score multipliers for different matching strategies to prioritize certain types.
    |
    */
    'boosts' => [
        'exact' => 1.5,          // Boost for exact matches
        'semantic' => 1.2,       // Boost for semantic matches
        'pattern' => 1.1,        // Boost for pattern matches
        'fuzzy' => 1.0,          // Base score for fuzzy matches
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Configure caching behavior for field mappings and template data.
    |
    */
    'cache' => [
        'enabled' => true,
        'ttl' => [
            'field_mappings' => 3600,      // 1 hour
            'template_fields' => 86400,    // 24 hours
            'successful_mappings' => 1800, // 30 minutes
        ],
        'prefix' => 'ivr_mapping',
    ],

    /*
    |--------------------------------------------------------------------------
    | Learning System
    |--------------------------------------------------------------------------
    |
    | Configure the self-learning behavior of the mapping system.
    |
    */
    'learning' => [
        'enabled' => true,
        'min_usage_for_confidence_update' => 10,  // Minimum uses before updating confidence
        'confidence_decay_factor' => 0.95,        // Factor to reduce confidence on failures
        'confidence_boost_factor' => 1.02,        // Factor to increase confidence on success
        'max_confidence' => 0.99,                 // Maximum confidence score
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Global validation rules that apply across all manufacturers.
    |
    */
    'validation' => [
        'strict_mode' => false,  // If true, all validation errors prevent submission
        'warn_on_low_confidence' => true,
        'low_confidence_threshold' => 0.8,
        'require_manual_review' => [
            'fields' => ['ssn', 'medicare_number', 'insurance_id'],
            'confidence_threshold' => 0.95,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Manufacturer-Specific Settings
    |--------------------------------------------------------------------------
    |
    | Override default settings for specific manufacturers.
    |
    */
    'manufacturers' => [
        'Advanced Solution' => [
            'date_format' => 'MM/DD/YYYY',
            'strict_validation' => true,
        ],
        'Bio Excellence' => [
            'date_format' => 'MM/DD/YYYY',
            'phone_format' => '###-###-####',
        ],
        'Centurion Therapeutics' => [
            'date_format' => 'YYYY-MM-DD',
            'require_prior_auth' => true,
        ],
        'ACZ Distribution' => [
            'date_format' => 'MM/DD/YY',
            'allow_partial_mappings' => true,
        ],
        'Medlife Solutions' => [
            'date_format' => 'M/D/YYYY',
            'auto_fill_defaults' => true,
        ],
        'Biowound' => [
            'date_format' => 'MM-DD-YYYY',
            'validate_medicare' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit and Logging
    |--------------------------------------------------------------------------
    |
    | Configure audit trail and logging behavior.
    |
    */
    'audit' => [
        'enabled' => true,
        'log_successful_mappings' => true,
        'log_failed_mappings' => true,
        'log_fallback_usage' => true,
        'truncate_values' => true,  // Truncate PHI in logs
        'value_max_length' => 50,   // Max length for logged values
        'retention_days' => 90,     // Days to keep audit records
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Configure performance-related settings.
    |
    */
    'performance' => [
        'batch_size' => 100,              // Fields to process in batch
        'timeout' => 30,                  // Seconds before timeout
        'max_memory' => '256M',           // Maximum memory usage
        'parallel_processing' => false,   // Enable parallel processing
        'queue_large_mappings' => true,   // Queue mappings with > 50 fields
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Strategies
    |--------------------------------------------------------------------------
    |
    | Configure which fallback strategies are enabled.
    |
    */
    'fallbacks' => [
        'use_defaults' => true,
        'derive_values' => true,
        'conditional_defaults' => true,
        'manufacturer_specific' => true,
        'prompt_user_for_unmapped' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Type Detection
    |--------------------------------------------------------------------------
    |
    | Patterns for automatic field type detection.
    |
    */
    'field_patterns' => [
        'date' => ['/date/i', '/dob/i', '/_at$/i', '/birth/i'],
        'phone' => ['/phone/i', '/mobile/i', '/tel/i', '/fax/i'],
        'email' => ['/email/i', '/e_mail/i'],
        'ssn' => ['/ssn/i', '/social_security/i'],
        'npi' => ['/npi/i', '/provider.*identifier/i'],
        'zip' => ['/zip/i', '/postal/i'],
        'currency' => ['/price/i', '/cost/i', '/amount/i', '/fee/i'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | Enable detailed logging for debugging.
    |
    */
    'debug' => env('IVR_MAPPING_DEBUG', false),
];
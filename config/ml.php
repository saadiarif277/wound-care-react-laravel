<?php

return [
    
    /*
    |--------------------------------------------------------------------------
    | ML Field Mapping Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains settings for the ML field mapping
    | system integration with the Laravel application.
    |
    */

    'enabled' => env('ML_FIELD_MAPPING_ENABLED', false),

    'field_mapping_server_url' => env('ML_FIELD_MAPPING_SERVER_URL', 'http://localhost:8000'),

    'api_timeout' => env('ML_API_TIMEOUT', 30),

    'confidence_threshold' => env('ML_CONFIDENCE_THRESHOLD', 0.6),

    'batch_size' => env('ML_BATCH_SIZE', 100),

    'auto_training' => env('ML_AUTO_TRAINING', true),

    'training_schedule' => env('ML_TRAINING_SCHEDULE', 'daily'),

    'data_collection' => [
        'enabled' => env('ML_DATA_COLLECTION_ENABLED', true),
        'sources' => [
            'ivr_field_mappings' => true,
            'pdf_field_metadata' => true,
            'template_field_mappings' => true,
            'docuseal_mappings' => true,
        ],
        'collection_interval' => env('ML_DATA_COLLECTION_INTERVAL', 6), // hours
        'max_records_per_batch' => env('ML_MAX_RECORDS_PER_BATCH', 1000),
    ],

    'models' => [
        'field_similarity' => [
            'enabled' => env('ML_FIELD_SIMILARITY_ENABLED', true),
            'model_path' => env('ML_FIELD_SIMILARITY_MODEL_PATH', 'models/field_similarity_model.pkl'),
            'min_training_samples' => env('ML_FIELD_SIMILARITY_MIN_SAMPLES', 100),
        ],
        'manufacturer_classifier' => [
            'enabled' => env('ML_MANUFACTURER_CLASSIFIER_ENABLED', true),
            'model_path' => env('ML_MANUFACTURER_CLASSIFIER_MODEL_PATH', 'models/manufacturer_classifier.pkl'),
            'min_training_samples' => env('ML_MANUFACTURER_CLASSIFIER_MIN_SAMPLES', 50),
        ],
        'context_predictor' => [
            'enabled' => env('ML_CONTEXT_PREDICTOR_ENABLED', true),
            'model_path' => env('ML_CONTEXT_PREDICTOR_MODEL_PATH', 'models/context_predictor.pkl'),
            'min_training_samples' => env('ML_CONTEXT_PREDICTOR_MIN_SAMPLES', 200),
        ],
    ],

    'analytics' => [
        'enabled' => env('ML_ANALYTICS_ENABLED', true),
        'retention_days' => env('ML_ANALYTICS_RETENTION_DAYS', 90),
        'export_enabled' => env('ML_ANALYTICS_EXPORT_ENABLED', true),
        'real_time_updates' => env('ML_ANALYTICS_REAL_TIME_UPDATES', true),
    ],

    'cache' => [
        'enabled' => env('ML_CACHE_ENABLED', true),
        'driver' => env('ML_CACHE_DRIVER', 'redis'),
        'ttl' => env('ML_CACHE_TTL', 3600), // seconds
        'prefix' => env('ML_CACHE_PREFIX', 'ml_field_mapping'),
    ],

    'logging' => [
        'enabled' => env('ML_LOGGING_ENABLED', true),
        'level' => env('ML_LOG_LEVEL', 'info'),
        'channel' => env('ML_LOG_CHANNEL', 'daily'),
    ],

    'fallback' => [
        'enabled' => env('ML_FALLBACK_ENABLED', true),
        'methods' => [
            'heuristic_matching' => true,
            'exact_string_matching' => true,
            'fuzzy_matching' => true,
        ],
        'fallback_threshold' => env('ML_FALLBACK_THRESHOLD', 0.4),
    ],

    'performance' => [
        'max_concurrent_requests' => env('ML_MAX_CONCURRENT_REQUESTS', 10),
        'request_timeout' => env('ML_REQUEST_TIMEOUT', 30),
        'retry_attempts' => env('ML_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('ML_RETRY_DELAY', 1000), // milliseconds
    ],

    'security' => [
        'api_key' => env('ML_API_KEY'),
        'encrypt_data' => env('ML_ENCRYPT_DATA', true),
        'validate_requests' => env('ML_VALIDATE_REQUESTS', true),
    ],

    'development' => [
        'debug_mode' => env('ML_DEBUG_MODE', false),
        'mock_responses' => env('ML_MOCK_RESPONSES', false),
        'test_mode' => env('ML_TEST_MODE', false),
    ],

]; 
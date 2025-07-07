<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Service Configuration
    |--------------------------------------------------------------------------
    |
    | Master switch to enable/disable all AI features across the application.
    |
    */

    'enabled' => env('AI_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which AI provider to use for enhancements. Options:
    | - 'azure' : Azure OpenAI Service
    | - 'openai' : OpenAI API
    | - 'mock' : Mock responses for testing
    |
    */

    'provider' => env('AI_PROVIDER', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    |
    | Configuration for each supported AI provider.
    |
    */

    'providers' => [
        'azure' => [
            'endpoint' => env('AZURE_OPENAI_ENDPOINT','https://msc-ai-services.openai.azure.com/'),
            'api_key' => env('AZURE_OPENAI_API_KEY','CPBG2LnTpdGKMKrONcWPWkD97e5ceXskv2eH4a2gzfeh39t0lqPcJQQJ99BFACYeBjFXJ3w3AAAAACOGeD0P'),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2023-12-01-preview'),
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4'),
        ],

        'mock' => [
            // Mock provider doesn't need configuration
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable or disable AI features across the application.
    |
    */

    'features' => [
        'clinical_opportunities' => env('AI_CLINICAL_OPPORTUNITIES', true),
        'product_recommendations' => env('AI_PRODUCT_RECOMMENDATIONS', true),
        'pdf_analysis' => env('AI_FEATURE_PDF_ANALYSIS', true),
        'field_suggestions' => env('AI_FEATURE_FIELD_SUGGESTIONS', true),
        'auto_mapping' => env('AI_FEATURE_AUTO_MAPPING', false),
        'confidence_indicators' => env('AI_FEATURE_CONFIDENCE_INDICATORS', true),
        'historical_learning' => env('AI_FEATURE_HISTORICAL_LEARNING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Intelligence Configuration
    |--------------------------------------------------------------------------
    |
    | Azure Document Intelligence service for PDF analysis
    |
    */

    'document_intelligence' => [
        'enabled' => env('AZURE_DOCUMENT_INTELLIGENCE_ENABLED', true),
        'endpoint' => env('AZURE_DOCUMENT_INTELLIGENCE_ENDPOINT'),
        'key' => env('AZURE_DOCUMENT_INTELLIGENCE_KEY'),
        'api_version' => env('AZURE_DOCUMENT_INTELLIGENCE_API_VERSION', '2023-07-31'),
        'timeout' => env('AZURE_DOCUMENT_INTELLIGENCE_TIMEOUT', 60),
        'cache_duration' => env('AZURE_DOCUMENT_INTELLIGENCE_CACHE_DURATION', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Mapping AI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI-powered field mapping suggestions
    |
    */

    'field_mapping' => [
        'enabled' => env('AI_FIELD_MAPPING_ENABLED', true),
        'min_confidence' => env('AI_FIELD_MAPPING_MIN_CONFIDENCE', 0.5),
        'max_suggestions' => env('AI_FIELD_MAPPING_MAX_SUGGESTIONS', 5),
        'cache_duration' => env('AI_FIELD_MAPPING_CACHE_DURATION', 3600),
        'auto_accept_threshold' => env('AI_FIELD_MAPPING_AUTO_ACCEPT_THRESHOLD', 0.9),
        'learn_from_history' => env('AI_FIELD_MAPPING_LEARN_FROM_HISTORY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limits for AI API calls.
    |
    */

    'rate_limits' => [
        'requests_per_minute' => env('AI_RATE_LIMIT_PER_MINUTE', 60),
        'requests_per_hour' => env('AI_RATE_LIMIT_PER_HOUR', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF Analysis Limits
    |--------------------------------------------------------------------------
    |
    | Limits for PDF analysis features
    |
    */

    'pdf_limits' => [
        'max_pdf_size_mb' => env('AI_MAX_PDF_SIZE_MB', 10),
        'max_fields_per_template' => env('AI_MAX_FIELDS_PER_TEMPLATE', 500),
        'max_concurrent_requests' => env('AI_MAX_CONCURRENT_REQUESTS', 5),
    ],
];

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
];

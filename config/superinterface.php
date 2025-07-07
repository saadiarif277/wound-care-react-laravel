<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Superinterface Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Superinterface AI assistant integration
    |
    */

    'api_key' => env('SUPERINTERFACE_API_KEY'),

    // Azure OpenAI Configuration for Superinterface
    'azure_openai' => [
        'endpoint' => env('AZURE_OPENAI_ENDPOINT'),
        'api_key' => env('AZURE_OPENAI_API_KEY'),
        'deployment_name' => env('AZURE_OPENAI_DEPLOYMENT_NAME', 'gpt-4o'),
        'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-02-15-preview'),
    ],

    // Assistant Configuration
    'assistant' => [
        'name' => 'MSC Wound Care Assistant',
        'description' => 'AI assistant for wound care product requests and clinical documentation',
        'avatar_url' => '/images/msc-assistant-avatar.png',
    ],

    // Function Definitions for API Calls
    'functions' => [
        'create_product_request' => [
            'endpoint' => '/api/v1/quick-request/episodes',
            'method' => 'POST',
        ],
        'validate_insurance' => [
            'endpoint' => '/api/v1/eligibility/check',
            'method' => 'POST',
        ],
        'search_products' => [
            'endpoint' => '/api/v1/products/with-sizes',
            'method' => 'GET',
        ],
        'check_medicare_coverage' => [
            'endpoint' => '/api/v1/medicare-validation/quick-check',
            'method' => 'POST',
        ],
        'process_document' => [
            'endpoint' => '/api/v1/document/analyze',
            'method' => 'POST',
        ],
    ],

    // UI Configuration
    'ui' => [
        'theme' => 'light',
        'position' => 'bottom-right',
        'show_voice_chat' => true,
        'show_file_upload' => true,
    ],
]; 
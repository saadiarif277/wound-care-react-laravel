<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Azure Document Intelligence Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Azure Document Intelligence (formerly Form Recognizer)
    | service used for OCR-based field detection in PDF forms.
    |
    */

    'document_intelligence' => [
        'endpoint' => env('AZURE_DI_ENDPOINT'),
        'key' => env('AZURE_DI_KEY'),
        'api_version' => env('AZURE_DI_API_VERSION', '2023-07-31'),
        'timeout' => env('AZURE_DI_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Document Analysis Model
    |--------------------------------------------------------------------------
    |
    | The default model to use for document analysis. Options include:
    | - prebuilt-layout: Extract text, tables, structure
    | - prebuilt-document: Extract key-value pairs, tables, text
    | - prebuilt-read: Extract text only
    |
    */
    'default_model' => env('AZURE_DOCUMENT_INTELLIGENCE_MODEL', 'prebuilt-layout'),

    /*
    |--------------------------------------------------------------------------
    | Service Configuration
    |--------------------------------------------------------------------------
    */
    'service' => [
        'max_retries' => env('AZURE_DOCUMENT_INTELLIGENCE_MAX_RETRIES', 3),
        'retry_delay' => env('AZURE_DOCUMENT_INTELLIGENCE_RETRY_DELAY', 2), // seconds
        'max_file_size' => env('AZURE_DOCUMENT_INTELLIGENCE_MAX_FILE_SIZE', 50 * 1024 * 1024), // 50MB
    ],

    /*
    |--------------------------------------------------------------------------
    | Azure AI Foundry Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Azure AI Foundry integration including OpenAI,
    | cognitive services, and other AI capabilities for intelligent form
    | translation and field mapping.
    |
    */

    'ai_foundry' => [
        'enabled' => env('AZURE_AI_FOUNDRY_ENABLED', false),
        
        /*
        |--------------------------------------------------------------------------
        | Azure OpenAI Configuration
        |--------------------------------------------------------------------------
        */
        'endpoint' => env('AZURE_OPENAI_ENDPOINT'),
        'api_key' => env('AZURE_OPENAI_API_KEY'),
        'deployment_name' => env('AZURE_OPENAI_DEPLOYMENT_NAME', 'gpt-4'),
        'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-02-15-preview'),
        
        /*
        |--------------------------------------------------------------------------
        | AI Model Configuration
        |--------------------------------------------------------------------------
        */
        'models' => [
            'form_translation' => [
                'model' => env('AZURE_OPENAI_FORM_MODEL', 'gpt-4'),
                'temperature' => 0.1, // Low for consistent mapping
                'max_tokens' => 2000,
            ],
            'fhir_mapping' => [
                'model' => env('AZURE_OPENAI_FHIR_MODEL', 'gpt-4'),
                'temperature' => 0.1, // Low for accurate medical mapping
                'max_tokens' => 3000,
            ],
            'data_extraction' => [
                'model' => env('AZURE_OPENAI_EXTRACTION_MODEL', 'gpt-4'),
                'temperature' => 0.2, // Slightly higher for flexibility
                'max_tokens' => 2000,
            ],
            'validation' => [
                'model' => env('AZURE_OPENAI_VALIDATION_MODEL', 'gpt-3.5-turbo'),
                'temperature' => 0.3, // Higher for suggestions
                'max_tokens' => 1500,
            ]
        ],

        /*
        |--------------------------------------------------------------------------
        | Caching and Performance
        |--------------------------------------------------------------------------
        */
        'cache' => [
            'enabled' => env('AZURE_AI_CACHE_ENABLED', true),
            'ttl_hours' => env('AZURE_AI_CACHE_TTL_HOURS', 24),
            'prefix' => 'azure_ai_',
        ],

        'rate_limit' => [
            'requests_per_minute' => env('AZURE_AI_RATE_LIMIT', 60),
            'tokens_per_minute' => env('AZURE_AI_TOKEN_LIMIT', 150000),
        ],

        'retry' => [
            'max_attempts' => env('AZURE_AI_RETRY_ATTEMPTS', 3),
            'delay_ms' => env('AZURE_AI_RETRY_DELAY', 1000),
        ],

        'timeout' => [
            'connection_seconds' => env('AZURE_AI_CONNECTION_TIMEOUT', 30),
            'request_seconds' => env('AZURE_AI_REQUEST_TIMEOUT', 60),
        ],

        /*
        |--------------------------------------------------------------------------
        | Security and Compliance
        |--------------------------------------------------------------------------
        */
        'security' => [
            'phi_protection' => [
                'enabled' => env('AZURE_AI_PHI_PROTECTION', true),
                'mask_ssn' => true,
                'mask_dob_in_logs' => true,
                'mask_addresses_in_logs' => true,
            ],
            'data_residency' => [
                'region' => env('AZURE_AI_REGION', 'eastus'),
                'require_us_region' => env('AZURE_AI_REQUIRE_US', true),
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Feature Flags
        |--------------------------------------------------------------------------
        */
        'features' => [
            'form_translation' => env('AZURE_AI_FORM_TRANSLATION', true),
            'fhir_mapping' => env('AZURE_AI_FHIR_MAPPING', true),
            'data_extraction' => env('AZURE_AI_DATA_EXTRACTION', true),
            'validation' => env('AZURE_AI_VALIDATION', true),
            'field_suggestions' => env('AZURE_AI_FIELD_SUGGESTIONS', true),
        ],
    ],
];

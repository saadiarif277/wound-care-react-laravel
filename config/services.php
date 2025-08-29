<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
        'webhook_signing_secret' => env('MAILGUN_WEBHOOK_SIGNING_SECRET'),
        'tracking_domain' => env('MAILGUN_TRACKING_DOMAIN'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'azure' => [
        'tenant_id' => env('AZURE_FHIR_TENANT_ID', env('AZURE_TENANT_ID')),
        'client_id' => env('AZURE_FHIR_CLIENT_ID', env('AZURE_CLIENT_ID')),
        'client_secret' => env('AZURE_FHIR_CLIENT_SECRET', env('AZURE_CLIENT_SECRET')),
        'fhir_endpoint' => env('AZURE_FHIR_ENDPOINT', env('AZURE_FHIR_BASE_URL')),
        'fhir_scope' => env('AZURE_FHIR_SCOPE'),
        'fhir' => [
            'base_url' => env('AZURE_FHIR_ENDPOINT', env('AZURE_FHIR_BASE_URL')),
        ],
        // Azure Health Data Services (AHDS) Configuration
        'health_data_services' => [
            'workspace_url' => env('AZURE_HEALTH_DATA_SERVICES_WORKSPACE_URL'),
            'tenant_id' => env('AZURE_HEALTH_DATA_SERVICES_TENANT_ID', env('AZURE_TENANT_ID')),
            'client_id' => env('AZURE_HEALTH_DATA_SERVICES_CLIENT_ID', env('AZURE_CLIENT_ID')),
            'client_secret' => env('AZURE_HEALTH_DATA_SERVICES_CLIENT_SECRET', env('AZURE_CLIENT_SECRET')),
            'scope' => env('AZURE_HEALTH_DATA_SERVICES_SCOPE', 'https://azurehealthcareapis.com/.default'),
            'oauth_endpoint' => env('AZURE_HEALTH_DATA_SERVICES_OAUTH_ENDPOINT', 'https://login.microsoftonline.com'),
        ],
        'key_vault' => [
            'vault_url' => env('AZURE_KEY_VAULT_URL'),
            'use_managed_identity' => env('AZURE_USE_MANAGED_IDENTITY', false),
        ],
        'document_intelligence' => [
            'endpoint' => env('AZURE_DOCUMENT_INTELLIGENCE_ENDPOINT', env('AZURE_DI_ENDPOINT')),
            'key' => env('AZURE_DOCUMENT_INTELLIGENCE_KEY', env('AZURE_DI_KEY')),
            'resource_id' => env('AZURE_DOCUMENT_INTELLIGENCE_RESOURCE_ID'),
            'api_version' => env('AZURE_DOCUMENT_INTELLIGENCE_API_VERSION', '2023-07-31'),
        ],
    ],

    'cms' => [
        'base_url' => env('CMS_API_BASE_URL', 'https://api.coverage.cms.gov/v1'),
        'timeout' => env('CMS_API_TIMEOUT', 30),
        'max_retries' => env('CMS_API_MAX_RETRIES', 3),
        'retry_delay' => env('CMS_API_RETRY_DELAY', 1000),
        'throttle_limit' => env('CMS_API_THROTTLE_LIMIT', 9000),
        'cache_minutes' => env('CMS_API_CACHE_MINUTES', 60),
    ],


    // ECW configuration removed - deprecated integration

    'npi' => [
        'use_mock' => env('NPI_USE_MOCK', true),
        'api_url' => env('NPI_API_URL', 'https://npiregistry.cms.hhs.gov/api'),
        'timeout' => env('NPI_API_TIMEOUT', 30),
        'cache_ttl' => env('NPI_CACHE_TTL', 86400), // 24 hours in seconds
        'max_retries' => env('NPI_MAX_RETRIES', 3),
        'retry_delay' => env('NPI_RETRY_DELAY', 1000), // milliseconds
    ],

    'docuseal' => [
        'api_key' => env('DOCUSEAL_API_KEY'),
        'webhook_secret' => env('DOCUSEAL_WEBHOOK_SECRET'),
        'base_url' => env('DOCUSEAL_BASE_URL', 'https://api.docuseal.co'),
    ],


    'google' => [
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'umls' => [
        'api_key' => env('UMLS_API_KEY'),
        'cache_ttl' => env('UMLS_CACHE_TTL', 86400), // 24 hours
        'timeout' => env('UMLS_TIMEOUT', 30), // seconds
    ],

    'health_vocab' => [
        'api_url' => env('HEALTH_VOCAB_API_URL'),
    ],

    'ai_form_filler' => [
        'url' => env('AI_FORM_FILLER_URL', 'http://localhost:8081'),
        'timeout' => env('AI_FORM_FILLER_TIMEOUT', 30),
        'enable_cache' => env('AI_FORM_FILLER_CACHE', true),
        'enabled' => env('AI_FORM_FILLER_ENABLED', true),
    ],

    'medical_base_ai' => [
        'base_url' => env('MEDICAL_AI_BASE_URL', 'http://127.0.0.1:8081'),
        'timeout' => env('MEDICAL_AI_SERVICE_TIMEOUT', 30),
        'key' => env('MEDICAL_AI_SERVICE_API_KEY', ''),
        'enabled' => env('MEDICAL_AI_SERVICE_ENABLED', true),
        'cache_enabled' => env('MEDICAL_AI_CACHE_ENABLED', true),
        'cache_ttl' => env('MEDICAL_AI_CACHE_TTL', 300),
        'debug' => env('MEDICAL_AI_DEBUG', false),
        'fallback_enabled' => env('MEDICAL_AI_FALLBACK_ENABLED', true),
        'use_for_docuseal' => env('MEDICAL_AI_USE_FOR_DOCUSEAL', true),
    ],

];

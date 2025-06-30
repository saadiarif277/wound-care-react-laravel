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
        'fhir_endpoint' => env('AZURE_FHIR_BASE_URL', env('AZURE_FHIR_ENDPOINT')),
        'fhir' => [
            'base_url' => env('AZURE_FHIR_BASE_URL', env('AZURE_FHIR_ENDPOINT')),
        ],
        'key_vault' => [
            'vault_url' => env('AZURE_KEY_VAULT_URL'),
            'use_managed_identity' => env('AZURE_USE_MANAGED_IDENTITY', false),
        ],
<<<<<<< HEAD
=======
        'document_intelligence' => [
            'endpoint' => env('AZURE_DOCUMENT_INTELLIGENCE_ENDPOINT', env('AZURE_DI_ENDPOINT')),
            'key' => env('AZURE_DOCUMENT_INTELLIGENCE_KEY', env('AZURE_DI_KEY')),
            'resource_id' => env('AZURE_DOCUMENT_INTELLIGENCE_RESOURCE_ID'),
            'api_version' => env('AZURE_DOCUMENT_INTELLIGENCE_API_VERSION', '2023-07-31'),
        ],
>>>>>>> origin/provider-side
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
        'api_url' => env('DOCUSEAL_API_URL', 'https://api.docuseal.com'),
        'webhook_secret' => env('DOCUSEAL_WEBHOOK_SECRET'),
        'timeout' => env('DOCUSEAL_TIMEOUT', 30),
        'max_retries' => env('DOCUSEAL_MAX_RETRIES', 3),
        'retry_delay' => env('DOCUSEAL_RETRY_DELAY', 1000),
    ],

<<<<<<< HEAD
    'azure_di' => [
        'endpoint' => env('AZURE_DI_ENDPOINT'),
        'key' => env('AZURE_DI_KEY'),
        'api_version' => env('AZURE_DI_API_VERSION', '2023-07-31'),
    ],
=======
>>>>>>> origin/provider-side

    'google' => [
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

];

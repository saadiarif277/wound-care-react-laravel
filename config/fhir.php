<?php

return [

    /*
    |--------------------------------------------------------------------------
    | FHIR Server Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to Azure Health Data Services FHIR API
    |
    */

    'server' => [
        'url' => env('AZURE_FHIR_URL'),
        'version' => 'R4',
        'timeout' => 30,
        'retry_attempts' => 3,
        'retry_delay' => 1000, // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Azure Health Data Services Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Azure Health Data Services (AHDS) - the modern
    | replacement for Azure FHIR Server
    |
    */

    'ahds' => [
        'enabled' => env('AZURE_HEALTH_DATA_SERVICES_ENABLED', false),
        'workspace_url' => env('AZURE_HEALTH_DATA_SERVICES_WORKSPACE_URL'),
        'version' => 'R4',
        'timeout' => 30,
        'retry_attempts' => 3,
        'retry_delay' => 1000, // milliseconds
        'oauth' => [
            'tenant_id' => env('AZURE_HEALTH_DATA_SERVICES_TENANT_ID', env('AZURE_TENANT_ID')),
            'client_id' => env('AZURE_HEALTH_DATA_SERVICES_CLIENT_ID', env('AZURE_CLIENT_ID')),
            'client_secret' => env('AZURE_HEALTH_DATA_SERVICES_CLIENT_SECRET', env('AZURE_CLIENT_SECRET')),
            'scope' => env('AZURE_HEALTH_DATA_SERVICES_SCOPE', 'https://azurehealthcareapis.com/.default'),
            'endpoint' => env('AZURE_HEALTH_DATA_SERVICES_OAUTH_ENDPOINT', 'https://login.microsoftonline.com'),
            'token_cache_ttl' => 3300, // 55 minutes (tokens are valid for 60 minutes)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the circuit breaker pattern to prevent cascading failures
    |
    */

    'circuit_breaker' => [
        'failure_threshold' => 5,
        'recovery_timeout' => 60, // seconds
        'success_threshold' => 2,
        'monitoring_period' => 300, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Validation
    |--------------------------------------------------------------------------
    |
    | Settings for FHIR resource validation
    |
    */

    'validation' => [
        'strict_mode' => env('FHIR_STRICT_VALIDATION', false),
        'profiles' => [
            'Patient' => 'http://hl7.org/fhir/StructureDefinition/Patient',
            'Practitioner' => 'http://hl7.org/fhir/StructureDefinition/Practitioner',
            'Organization' => 'http://hl7.org/fhir/StructureDefinition/Organization',
            'EpisodeOfCare' => 'http://hl7.org/fhir/StructureDefinition/EpisodeOfCare',
            'Coverage' => 'http://hl7.org/fhir/StructureDefinition/Coverage',
        ],
        'required_fields' => [
            'Patient' => ['name', 'gender', 'birthDate'],
            'Practitioner' => ['name', 'identifier'],
            'Organization' => ['name', 'type'],
            'EpisodeOfCare' => ['status', 'patient', 'managingOrganization'],
            'Coverage' => ['status', 'beneficiary', 'payor'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Parameters
    |--------------------------------------------------------------------------
    |
    | Default search parameters and limits
    |
    */

    'search' => [
        'default_count' => 20,
        'max_count' => 100,
        'default_sort' => '-_lastUpdated',
        'include_total' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Cache settings for FHIR resources
    |
    */

    'cache' => [
        'enabled' => env('FHIR_CACHE_ENABLED', true),
        'ttl' => [
            'Patient' => 300, // 5 minutes
            'Practitioner' => 3600, // 1 hour
            'Organization' => 3600, // 1 hour
            'ValueSet' => 86400, // 24 hours
            'CodeSystem' => 86400, // 24 hours
        ],
        'prefix' => 'fhir:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for FHIR audit event generation
    |
    */

    'audit' => [
        'enabled' => env('FHIR_AUDIT_ENABLED', true),
        'log_reads' => true,
        'log_writes' => true,
        'log_deletes' => true,
        'log_searches' => false,
        'include_request_details' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | MSC Custom Extensions
    |--------------------------------------------------------------------------
    |
    | Custom FHIR extensions for wound care specific data
    |
    */

    'extensions' => [
        'base_url' => 'http://mscwoundcare.com/fhir/StructureDefinition/',
        'definitions' => [
            'wound-details' => [
                'url' => 'wound-details',
                'fields' => ['woundType', 'woundLocation', 'woundSize', 'woundStage'],
            ],
            'medicare-details' => [
                'url' => 'medicare-details',
                'fields' => ['medicareType', 'medicareNumber'],
            ],
            'platform-status' => [
                'url' => 'platform-status',
                'fields' => ['status', 'lastLogin', 'consentDate'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    |
    | Configuration for FHIR error responses
    |
    */

    'errors' => [
        'include_diagnostics' => env('APP_DEBUG', false),
        'log_errors' => true,
        'operation_outcome_on_error' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Terminology Service
    |--------------------------------------------------------------------------
    |
    | Settings for code system and value set validation
    |
    */

    'terminology' => [
        'validate_codes' => true,
        'code_systems' => [
            'icd10' => 'http://hl7.org/fhir/sid/icd-10-cm',
            'cpt' => 'http://www.ama-assn.org/go/cpt',
            'snomed' => 'http://snomed.info/sct',
            'loinc' => 'http://loinc.org',
        ],
        'value_sets' => [
            'wound_types' => 'http://mscwoundcare.com/fhir/ValueSet/wound-types',
            'wound_stages' => 'http://mscwoundcare.com/fhir/ValueSet/wound-stages',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bundle Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for FHIR Bundle operations
    |
    */

    'bundles' => [
        'max_entries' => 100,
        'transaction_timeout' => 60, // seconds
        'validate_references' => true,
        'rollback_on_error' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | FHIR-specific rate limiting configuration
    |
    */

    'rate_limits' => [
        'search' => 100, // per minute
        'read' => 200, // per minute
        'write' => 50, // per minute
        'transaction' => 10, // per minute
    ],

];
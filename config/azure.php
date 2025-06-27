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
];

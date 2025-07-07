<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PDF Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the PDF generation and signing system
    |
    */

    // Path to pdftk binary
    'pdftk_path' => env('PDFTK_PATH', '/usr/bin/pdftk'),

    // Azure Storage settings for PDFs
    'azure' => [
        'template_container' => env('AZURE_PDF_TEMPLATE_CONTAINER', 'pdf-templates'),
        'document_container' => env('AZURE_PDF_DOCUMENT_CONTAINER', 'order-pdfs'),
    ],

    // PDF generation settings
    'generation' => [
        'temp_path' => storage_path('app/temp/pdf'),
        'font_path' => resource_path('fonts'),
        'default_font' => 'helvetica',
        'default_font_size' => 10,
    ],

    // Signature settings
    'signatures' => [
        'canvas_width' => 500,
        'canvas_height' => 200,
        'line_width' => 2,
        'line_color' => '#000000',
        'background_color' => '#FFFFFF',
        'format' => 'png',
    ],

    // Security settings
    'security' => [
        'enable_encryption' => env('PDF_ENABLE_ENCRYPTION', true),
        'encryption_level' => 128, // 40 or 128 bit
        'owner_password' => env('PDF_OWNER_PASSWORD'),
        'permissions' => [
            'print' => true,
            'modify' => false,
            'copy' => true,
            'annotate' => true,
        ],
    ],

    // Expiration settings
    'expiration' => [
        'ivr_days' => env('PDF_IVR_EXPIRATION_DAYS', 30),
        'order_form_days' => env('PDF_ORDER_FORM_EXPIRATION_DAYS', 90),
        'signed_document_days' => env('PDF_SIGNED_EXPIRATION_DAYS', 365),
    ],

    // Field mapping defaults
    'field_mappings' => [
        'date_format' => 'm/d/Y',
        'phone_format' => '(XXX) XXX-XXXX',
        'ssn_format' => 'XXX-XX-XXXX',
        'currency_symbol' => '$',
        'checkbox_values' => [
            'checked' => 'Yes',
            'unchecked' => 'Off',
        ],
    ],

    // Manufacturer-specific settings
    'manufacturers' => [
        'default_template_version' => '1.0',
        'template_naming' => '{manufacturer_slug}_{document_type}_v{version}.pdf',
    ],

    // Access control
    'access' => [
        'log_all_access' => env('PDF_LOG_ALL_ACCESS', true),
        'require_authentication' => true,
        'secure_url_expiration_minutes' => 60,
    ],

    // Performance settings
    'performance' => [
        'enable_caching' => true,
        'cache_ttl' => 3600, // 1 hour
        'max_concurrent_generations' => 5,
        'generation_timeout' => 120, // seconds
    ],

    // Audit settings
    'audit' => [
        'enabled' => true,
        'log_channel' => env('PDF_AUDIT_LOG_CHANNEL', 'daily'),
        'retain_days' => 90,
        'log_signatures' => true,
        'log_downloads' => true,
        'log_views' => true,
    ],
];
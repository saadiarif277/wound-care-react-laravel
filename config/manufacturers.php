<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Manufacturer Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains manufacturer-specific configuration including
    | default email recipients for order notifications.
    |
    */

    'email_recipients' => [
        'ACZ' => [env('MANUFACTURER_ACZ_EMAIL', 'orders@acz.com')],
        'Integra' => [env('MANUFACTURER_INTEGRA_EMAIL', 'orders@integra.com')],
        'Kerecis' => [env('MANUFACTURER_KERECIS_EMAIL', 'orders@kerecis.com')],
        'MiMedx' => [env('MANUFACTURER_MIMEDX_EMAIL', 'orders@mimedx.com')],
        'Organogenesis' => [env('MANUFACTURER_ORGANOGENESIS_EMAIL', 'orders@organogenesis.com')],
        'Smith & Nephew' => [env('MANUFACTURER_SMITH_NEPHEW_EMAIL', 'orders@smith-nephew.com')],
        'StimLabs' => [env('MANUFACTURER_STIMLABS_EMAIL', 'orders@stimlabs.com')],
        'Tissue Tech' => [env('MANUFACTURER_TISSUE_TECH_EMAIL', 'orders@tissuetech.com')],
    ],

    /*
    |--------------------------------------------------------------------------
    | Manufacturer IVR Configuration
    |--------------------------------------------------------------------------
    |
    | DocuSeal template IDs for each manufacturer's IVR forms
    |
    */
    
    'ivr_templates' => [
        // Template IDs will be loaded from database
        // This is kept here for reference and fallback
    ],
];
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DocuSeal API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the DocuSeal e-signature integration
    |
    */

    'api_key' => env('DOCUSEAL_API_KEY'),
    'api_url' => env('DOCUSEAL_API_URL', 'https://api.docuseal.com'),

    /*
    |--------------------------------------------------------------------------
    | DocuSeal Template IDs
    |--------------------------------------------------------------------------
    |
    | Map of manufacturer names to their DocuSeal template IDs
    | Replace these with your actual template IDs from DocuSeal
    |
    */
    'templates' => [
        'ACZ' => [
            'default' => env('DOCUSEAL_TEMPLATE_ACZ', 1000001),
            'Membrane Wrap' => env('DOCUSEAL_TEMPLATE_ACZ_MEMBRANE', 1000002),
            'Revoshield' => env('DOCUSEAL_TEMPLATE_ACZ_REVOSHIELD', 1000003),
        ],
        'Advanced Health' => [
            'default' => env('DOCUSEAL_TEMPLATE_ADVANCED', 1000004),
            'CompleteAA' => env('DOCUSEAL_TEMPLATE_ADVANCED_AA', 1000005),
            'CompleteFT' => env('DOCUSEAL_TEMPLATE_ADVANCED_FT', 1000006),
            'WoundPlus' => env('DOCUSEAL_TEMPLATE_ADVANCED_WOUNDPLUS', 1000007),
        ],
        'MedLife' => [
            'default' => env('DOCUSEAL_TEMPLATE_MEDLIFE', 1000008),
            'Amnio AMP' => env('DOCUSEAL_TEMPLATE_MEDLIFE_AMNIO', 1000009),
        ],
        'BioWound' => [
            'default' => env('DOCUSEAL_TEMPLATE_BIOWOUND', 1000010),
            'Membrane Wrap' => env('DOCUSEAL_TEMPLATE_BIOWOUND_MEMBRANE', 1000011),
            'Derm-Maxx' => env('DOCUSEAL_TEMPLATE_BIOWOUND_DERMMAXX', 1000012),
            'Bio-Connekt' => env('DOCUSEAL_TEMPLATE_BIOWOUND_BIOCONNEKT', 1000013),
            'NeoStim' => env('DOCUSEAL_TEMPLATE_BIOWOUND_NEOSTIM', 1000014),
            'Amnio-Maxx' => env('DOCUSEAL_TEMPLATE_BIOWOUND_AMNIOMAXX', 1000015),
        ],
        'Centurion' => [
            'default' => env('DOCUSEAL_TEMPLATE_CENTURION', 1000016),
            'AmnioBand' => env('DOCUSEAL_TEMPLATE_CENTURION_AMNIOBAND', 1000017),
            'Allopatch' => env('DOCUSEAL_TEMPLATE_CENTURION_ALLOPATCH', 1000018),
        ],
        'BioWerX' => [
            'default' => env('DOCUSEAL_TEMPLATE_BIOWERX', 1000019),
        ],
        'Extremity Care' => [
            'default' => env('DOCUSEAL_TEMPLATE_EXTREMITY', 1000020),
            'Coll-e-Derm' => env('DOCUSEAL_TEMPLATE_EXTREMITY_COLLEDERM', 1000021),
            'CompleteFT' => env('DOCUSEAL_TEMPLATE_EXTREMITY_COMPLETEFT', 1000022),
            'Restorigin' => env('DOCUSEAL_TEMPLATE_EXTREMITY_RESTORIGIN', 1000023),
        ],
        'Skye Biologics' => [
            'default' => env('DOCUSEAL_TEMPLATE_SKYE', 1000024),
            'WoundPlus' => env('DOCUSEAL_TEMPLATE_SKYE_WOUNDPLUS', 1000025),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhook_secret' => env('DOCUSEAL_WEBHOOK_SECRET'),

    // Template ID for final order submissions to MSC
    'final_submission_template_id' => env('DOCUSEAL_FINAL_SUBMISSION_TEMPLATE_ID', 'template_final_submission'),

    // Default templates for common manufacturers
    'default_templates' => [
        'ACZ' => env('DOCUSEAL_ACZ_TEMPLATE_ID', ''),
        'MedLife' => env('DOCUSEAL_MEDLIFE_TEMPLATE_ID', ''),
        'BioWound' => env('DOCUSEAL_BIOWOUND_TEMPLATE_ID', ''),
        'Advanced Health' => env('DOCUSEAL_ADVANCED_HEALTH_TEMPLATE_ID', ''),
    ],
];

<?php

return [
    'id' => 2,
    'name' => 'Advanced Health',
    'signature_required' => true,
    'has_order_form' => true,
    'docuseal_template_id' => '', // TODO: Add the Docuseal template ID when available
    'order_form_template_id' => '', // TODO: Add the order form template ID when available
    'docuseal_field_names' => [
        // TODO: Add field mappings when template is available
    ],
    'fields' => [
        // Similar structure to ACZ with manufacturer-specific fields
        'patient_name' => [
            'source' => 'computed',
            'computation' => 'patient_first_name + patient_last_name',
            'required' => true,
            'type' => 'string'
        ],
        // TODO: Add additional fields specific to Advanced Health
    ]
];
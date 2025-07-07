<?php

return [
    'id' => 2,
    'name' => 'Advanced Health',
    'signature_required' => true,
    'has_order_form' => true,
    'docuseal_template_id' => '', // TODO: Add the Docuseal template ID when available
    'order_form_template_id' => '', // TODO: Add the order form template ID when available
    'docuseal_field_names' => [
        // Basic field mappings - to be updated when template is available
        'patient_name' => 'Patient Name',
        'patient_dob' => 'Patient DOB',
        'physician_name' => 'Physician Name',
        'physician_npi' => 'Physician NPI',
        'facility_name' => 'Facility Name',
        'facility_npi' => 'Facility NPI',
        // TODO: Add complete field mappings when template is available
    ],
    'fields' => [
        // Patient Information
        'patient_first_name' => [
            'source' => 'patient_first_name || patient.first_name || patient_firstName',
            'required' => false,
            'type' => 'string'
        ],
        'patient_last_name' => [
            'source' => 'patient_last_name || patient.last_name || patient_lastName',
            'required' => false,
            'type' => 'string'
        ],
        'patient_name' => [
            'source' => 'computed',
            'computation' => 'patient_first_name + " " + patient_last_name',
            'required' => true,
            'type' => 'string'
        ],
        'patient_dob' => [
            'source' => 'patient_dob || patient_date_of_birth',
            'transform' => 'date:m/d/Y',
            'required' => true,
            'type' => 'date'
        ],
        // Provider Information
        'physician_name' => [
            'source' => 'provider_name || physician_name',
            'required' => true,
            'type' => 'string'
        ],
        'physician_npi' => [
            'source' => 'provider_npi || physician_npi',
            'required' => true,
            'type' => 'string'
        ],
        // Facility Information
        'facility_name' => [
            'source' => 'facility_name',
            'required' => true,
            'type' => 'string'
        ],
        'facility_npi' => [
            'source' => 'facility_npi',
            'required' => true,
            'type' => 'string'
        ],
        // TODO: Add additional fields specific to Advanced Health
    ]
];
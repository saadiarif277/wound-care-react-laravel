<?php

return [
    'id' => 1,
    'name' => 'ACZ & Associates',
    'signature_required' => true,
    'has_order_form' => false,
    'duration_requirement' => 'greater_than_4_weeks',
    'docuseal_template_id' => '', // TODO: Add the Docuseal template ID when available
    'docuseal_field_names' => [
        // Map canonical names to ACZ's specific field names
        'patient_name' => 'PATIENT NAME',
        'patient_dob' => 'PATIENT DOB',
        'physician_name' => 'PHYSICIAN NAME',
        'physician_npi' => 'NPI',
        'facility_name' => 'FACILITY NAME',
        'facility_ptan' => 'PTAN',
        'insurance_name' => 'INSURANCE NAME',
        'policy_number' => 'POLICY NUMBER',
        'place_of_service' => 'PLACE OF SERVICE WHERE PATIENT IS BEING SEEN',
    ],
    'fields' => [
        // Patient Information
        'patient_name' => [
            'source' => 'computed',
            'computation' => 'patient_first_name + " " + patient_last_name',
            'required' => true,
            'type' => 'string'
        ],
        'patient_first_name' => [
            'source' => 'patient_first_name',
            'required' => true,
            'type' => 'string'
        ],
        'patient_last_name' => [
            'source' => 'patient_last_name',
            'required' => true,
            'type' => 'string'
        ],
        'patient_dob' => [
            'source' => 'patient_dob',
            'transform' => 'date:m/d/Y',
            'required' => true,
            'type' => 'date'
        ],
        'patient_gender' => [
            'source' => 'patient_gender',
            'required' => false,
            'type' => 'string'
        ],
        'patient_phone' => [
            'source' => 'patient_phone',
            'transform' => 'phone:US',
            'required' => true,
            'type' => 'phone'
        ],
        'patient_email' => [
            'source' => 'patient_email',
            'required' => false,
            'type' => 'email'
        ],
        'patient_address' => [
            'source' => 'computed',
            'computation' => 'patient_address_line1 + patient_address_line2',
            'required' => true,
            'type' => 'string'
        ],
        'patient_city' => [
            'source' => 'patient_city',
            'required' => true,
            'type' => 'string'
        ],
        'patient_state' => [
            'source' => 'patient_state',
            'required' => true,
            'type' => 'string'
        ],
        'patient_zip' => [
            'source' => 'patient_zip',
            'required' => true,
            'type' => 'zip'
        ],
        
        // Insurance Information
        'insurance_name' => [
            'source' => 'primary_insurance_name',
            'required' => true,
            'type' => 'string'
        ],
        'member_id' => [
            'source' => 'primary_member_id',
            'required' => true,
            'type' => 'string'
        ],
        'plan_type' => [
            'source' => 'primary_plan_type',
            'required' => false,
            'type' => 'string'
        ],
        
        // Clinical Information
        'diagnosis_code' => [
            'source' => 'primary_diagnosis_code || diagnosis_code',
            'required' => true,
            'type' => 'string'
        ],
        'wound_type' => [
            'source' => 'wound_type',
            'required' => true,
            'type' => 'string'
        ],
        'wound_location' => [
            'source' => 'wound_location',
            'required' => true,
            'type' => 'string'
        ],
        'wound_size' => [
            'source' => 'computed',
            'computation' => 'wound_size_length * wound_size_width',
            'transform' => 'number:2',
            'required' => true,
            'type' => 'number'
        ],
        'wound_duration' => [
            'source' => 'computed',
            'computation' => 'format_duration',
            'required' => true,
            'type' => 'string'
        ],
        
        // Provider Information
        'provider_name' => [
            'source' => 'provider_name',
            'required' => true,
            'type' => 'string'
        ],
        'provider_npi' => [
            'source' => 'provider_npi',
            'required' => true,
            'type' => 'npi'
        ],
        'provider_email' => [
            'source' => 'provider_email',
            'required' => false,
            'type' => 'email'
        ],
        'facility_name' => [
            'source' => 'facility_name',
            'required' => true,
            'type' => 'string'
        ],
        
        // Hospice Information
        'hospice_status' => [
            'source' => 'hospice_status',
            'transform' => 'boolean:yes_no',
            'required' => false,
            'type' => 'boolean'
        ],
        'hospice_family_consent' => [
            'source' => 'hospice_family_consent',
            'transform' => 'boolean:yes_no',
            'required' => false,
            'type' => 'boolean'
        ],
        'hospice_clinically_necessary' => [
            'source' => 'hospice_clinically_necessary',
            'transform' => 'boolean:yes_no',
            'required' => false,
            'type' => 'boolean'
        ],
    ]
];
<?php

return [
    'id' => 11,
    'name' => 'ADVANCED SOLUTION',
    'signature_required' => true,
    'has_order_form' => true,
    'duration_requirement' => 'greater_than_4_weeks',
    'docuseal_field_names' => [
        'patient_name' => 'Patient Full Name',
        'patient_dob' => 'Date of Birth',
        'patient_gender' => 'Gender',
        'patient_phone' => 'Patient Phone',
        'patient_address' => 'Patient Address',
        'patient_city' => 'City',
        'patient_state' => 'State',
        'patient_zip' => 'Zip Code',
        'insurance_name' => 'Primary Insurance',
        'insurance_member_id' => 'Member ID',
        'insurance_group_number' => 'Group Number',
        'insurance_policy_number' => 'Policy Number',
        'facility_name' => 'Facility Name',
        'facility_npi' => 'Facility NPI',
        'facility_address' => 'Facility Address',
        'facility_city' => 'Facility City',
        'facility_state' => 'Facility State',
        'facility_zip' => 'Facility Zip',
        'facility_phone' => 'Facility Phone',
        'facility_fax' => 'Facility Fax',
        'prescriber_name' => 'Prescribing Provider',
        'prescriber_npi' => 'Prescriber NPI',
        'prescriber_specialty' => 'Provider Specialty',
        'diagnosis_code' => 'Primary Diagnosis',
        'diagnosis_description' => 'Diagnosis Description',
        'wound_type' => 'Wound Type',
        'wound_location' => 'Wound Location',
        'wound_size_cm2' => 'Wound Size (cm²)',
        'wound_duration' => 'Duration of Wound',
        'product_name' => 'Product Requested',
        'product_hcpcs' => 'HCPCS Code',
        'product_size' => 'Size',
        'product_quantity' => 'Quantity',
        'date_of_service' => 'Expected Date of Service',
        'place_of_service' => 'Place of Service',
        'shipping_address' => 'Ship To Address',
        'shipping_attention' => 'Ship To Attention',
        'shipping_phone' => 'Ship To Phone',
        'special_instructions' => 'Special Instructions'
    ],
    'order_form_field_names' => [
        'patient_name' => 'Patient Full Name',
        'patient_dob' => 'Date of Birth',
        'patient_gender' => 'Gender',
        'patient_phone' => 'Patient Phone',
        'patient_address' => 'Patient Address',
        'patient_city' => 'City',
        'patient_state' => 'State',
        'patient_zip' => 'Zip Code',
        'insurance_name' => 'Primary Insurance',
        'insurance_member_id' => 'Member ID',
        'insurance_group_number' => 'Group Number',
        'insurance_policy_number' => 'Policy Number',
        'facility_name' => 'Facility Name',
        'facility_npi' => 'Facility NPI',
        'facility_address' => 'Facility Address',
        'facility_city' => 'Facility City',
        'facility_state' => 'Facility State',
        'facility_zip' => 'Facility Zip',
        'facility_phone' => 'Facility Phone',
        'facility_fax' => 'Facility Fax',
        'prescriber_name' => 'Prescribing Provider',
        'prescriber_npi' => 'Prescriber NPI',
        'prescriber_specialty' => 'Provider Specialty',
        'diagnosis_code' => 'Primary Diagnosis',
        'diagnosis_description' => 'Diagnosis Description',
        'wound_type' => 'Wound Type',
        'wound_location' => 'Wound Location',
        'wound_size_cm2' => 'Wound Size (cm²)',
        'wound_duration' => 'Duration of Wound',
        'product_name' => 'Product Requested',
        'product_hcpcs' => 'HCPCS Code',
        'product_size' => 'Size',
        'product_quantity' => 'Quantity',
        'date_of_service' => 'Expected Date of Service',
        'place_of_service' => 'Place of Service',
        'shipping_address' => 'Ship To Address',
        'shipping_attention' => 'Ship To Attention',
        'shipping_phone' => 'Ship To Phone',
        'special_instructions' => 'Special Instructions'
    ],
    'fields' => [
        'patient_name' => [
            'source' => 'computed',
            'computation' => 'patient_first_name + " " + patient_last_name',
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
        'patient_address' => [
            'source' => 'patient_address_line1',
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
        'insurance_name' => [
            'source' => 'primary_insurance_name',
            'required' => true,
            'type' => 'string'
        ],
        'insurance_member_id' => [
            'source' => 'primary_member_id',
            'required' => true,
            'type' => 'string'
        ],
        'insurance_group_number' => [
            'source' => 'primary_group_number',
            'required' => false,
            'type' => 'string'
        ],
        'insurance_policy_number' => [
            'source' => 'primary_policy_number',
            'required' => false,
            'type' => 'string'
        ],
        'facility_name' => [
            'source' => 'facility_name',
            'required' => true,
            'type' => 'string'
        ],
        'facility_npi' => [
            'source' => 'facility_npi',
            'required' => true,
            'type' => 'npi'
        ],
        'facility_address' => [
            'source' => 'facility_address_line1',
            'required' => true,
            'type' => 'string'
        ],
        'facility_city' => [
            'source' => 'facility_city',
            'required' => true,
            'type' => 'string'
        ],
        'facility_state' => [
            'source' => 'facility_state',
            'required' => true,
            'type' => 'string'
        ],
        'facility_zip' => [
            'source' => 'facility_zip',
            'required' => true,
            'type' => 'zip'
        ],
        'facility_phone' => [
            'source' => 'facility_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'facility_fax' => [
            'source' => 'facility_fax',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'prescriber_name' => [
            'source' => 'provider_name',
            'required' => true,
            'type' => 'string'
        ],
        'prescriber_npi' => [
            'source' => 'provider_npi',
            'required' => true,
            'type' => 'npi'
        ],
        'prescriber_specialty' => [
            'source' => 'provider_specialty',
            'required' => false,
            'type' => 'string'
        ],
        'diagnosis_code' => [
            'source' => 'primary_diagnosis_code',
            'required' => true,
            'type' => 'string'
        ],
        'diagnosis_description' => [
            'source' => 'primary_diagnosis_description',
            'required' => false,
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
        'wound_size_cm2' => [
            'source' => 'wound_size_cm2',
            'transform' => 'number:2',
            'required' => true,
            'type' => 'number'
        ],
        'wound_duration' => [
            'source' => 'wound_duration_formatted',
            'required' => true,
            'type' => 'string'
        ],
        'product_name' => [
            'source' => 'product_name',
            'required' => true,
            'type' => 'string'
        ],
        'product_hcpcs' => [
            'source' => 'product_hcpcs_code',
            'required' => true,
            'type' => 'string'
        ],
        'product_size' => [
            'source' => 'product_size',
            'required' => true,
            'type' => 'string'
        ],
        'product_quantity' => [
            'source' => 'product_quantity',
            'required' => true,
            'type' => 'number'
        ],
        'date_of_service' => [
            'source' => 'expected_service_date',
            'transform' => 'date:m/d/Y',
            'required' => true,
            'type' => 'date'
        ],
        'place_of_service' => [
            'source' => 'place_of_service_description',
            'required' => false,
            'type' => 'string'
        ],
        'shipping_address' => [
            'source' => 'shipping_address_line1',
            'required' => false,
            'type' => 'string'
        ],
        'shipping_attention' => [
            'source' => 'shipping_attention',
            'required' => false,
            'type' => 'string'
        ],
        'shipping_phone' => [
            'source' => 'shipping_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'special_instructions' => [
            'source' => 'special_instructions',
            'required' => false,
            'type' => 'text'
        ]
    ]
];
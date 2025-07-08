<?php

return [
    'id' => 1,
    'name' => 'ACZ & Associates',
    'signature_required' => true,
    'has_order_form' => true,
    'duration_requirement' => 'greater_than_4_weeks',
    'docuseal_field_names' => [
        'patient_name' => 'Patient Name',
        'patient_dob' => 'Date of Birth',
        'patient_phone' => 'Phone',
        'patient_address' => 'Address',
        'patient_city' => 'City',
        'patient_state' => 'State',
        'patient_zip' => 'Zip Code',
        'insurance_name' => 'Insurance Name',
        'insurance_member_id' => 'Member ID',
        'insurance_group_number' => 'Group Number',
        'insurance_policy_number' => 'Policy Number',
        'insurance_subscriber_name' => 'Subscriber Name',
        'insurance_subscriber_dob' => 'Subscriber DOB',
        'insurance_relationship' => 'Relationship',
        'facility_name' => 'Facility Name',
        'facility_npi' => 'NPI',
        'facility_contact_name' => 'Contact Name',
        'facility_phone' => 'Facility Phone',
        'facility_fax' => 'Facility Fax',
        'prescriber_name' => 'Prescriber Name',
        'prescriber_npi' => 'Prescriber NPI',
        'prescriber_phone' => 'Prescriber Phone',
        'prescriber_fax' => 'Prescriber Fax',
        'diagnosis_code' => 'Diagnosis Code',
        'diagnosis_description' => 'Diagnosis Description',
        'product_name' => 'Product Name',
        'product_hcpcs' => 'HCPCS Code',
        'product_size' => 'Size',
        'product_quantity' => 'Quantity',
        'wound_location' => 'Wound Location',
        'wound_type' => 'Wound Type',
        'wound_size' => 'Wound Size',
        'date_of_service' => 'Date of Service',
        'authorization_number' => 'Authorization Number',
        'urgency_level' => 'Urgency Level',
        'special_instructions' => 'Special Instructions',
        'shipping_address' => 'Shipping Address',
        'shipping_city' => 'Shipping City',
        'shipping_state' => 'Shipping State',
        'shipping_zip' => 'Shipping Zip'
    ],
    'order_form_field_names' => [
        'patient_name' => 'Patient Name',
        'patient_dob' => 'Date of Birth',
        'patient_phone' => 'Phone',
        'patient_address' => 'Address',
        'patient_city' => 'City',
        'patient_state' => 'State',
        'patient_zip' => 'Zip Code',
        'insurance_name' => 'Insurance Name',
        'insurance_member_id' => 'Member ID',
        'insurance_group_number' => 'Group Number',
        'insurance_policy_number' => 'Policy Number',
        'insurance_subscriber_name' => 'Subscriber Name',
        'insurance_subscriber_dob' => 'Subscriber DOB',
        'insurance_relationship' => 'Relationship',
        'facility_name' => 'Facility Name',
        'facility_npi' => 'NPI',
        'facility_contact_name' => 'Contact Name',
        'facility_phone' => 'Facility Phone',
        'facility_fax' => 'Facility Fax',
        'prescriber_name' => 'Prescriber Name',
        'prescriber_npi' => 'Prescriber NPI',
        'prescriber_phone' => 'Prescriber Phone',
        'prescriber_fax' => 'Prescriber Fax',
        'diagnosis_code' => 'Diagnosis Code',
        'diagnosis_description' => 'Diagnosis Description',
        'product_name' => 'Product Name',
        'product_hcpcs' => 'HCPCS Code',
        'product_size' => 'Size',
        'product_quantity' => 'Quantity',
        'wound_location' => 'Wound Location',
        'wound_type' => 'Wound Type',
        'wound_size' => 'Wound Size',
        'date_of_service' => 'Date of Service',
        'authorization_number' => 'Authorization Number',
        'urgency_level' => 'Urgency Level',
        'special_instructions' => 'Special Instructions',
        'shipping_address' => 'Shipping Address',
        'shipping_city' => 'Shipping City',
        'shipping_state' => 'Shipping State',
        'shipping_zip' => 'Shipping Zip'
    ],
    'fields' => [
        'patient_name' => [
            'label' => 'Patient Name',
            'type' => 'text',
            'required' => true,
            'source' => 'patient_data'
        ],
        'patient_dob' => [
            'label' => 'Date of Birth',
            'type' => 'date',
            'required' => true,
            'source' => 'patient_data'
        ],
        'patient_phone' => [
            'label' => 'Phone',
            'type' => 'tel',
            'required' => false,
            'source' => 'patient_data'
        ],
        'patient_address' => [
            'label' => 'Address',
            'type' => 'text',
            'required' => true,
            'source' => 'patient_data'
        ],
        'patient_city' => [
            'label' => 'City',
            'type' => 'text',
            'required' => true,
            'source' => 'patient_data'
        ],
        'patient_state' => [
            'label' => 'State',
            'type' => 'text',
            'required' => true,
            'source' => 'patient_data'
        ],
        'patient_zip' => [
            'label' => 'Zip Code',
            'type' => 'text',
            'required' => true,
            'source' => 'patient_data'
        ],
        'insurance_name' => [
            'label' => 'Insurance Name',
            'type' => 'text',
            'required' => true,
            'source' => 'insurance_data'
        ],
        'insurance_member_id' => [
            'label' => 'Member ID',
            'type' => 'text',
            'required' => true,
            'source' => 'insurance_data'
        ],
        'insurance_group_number' => [
            'label' => 'Group Number',
            'type' => 'text',
            'required' => false,
            'source' => 'insurance_data'
        ],
        'insurance_policy_number' => [
            'label' => 'Policy Number',
            'type' => 'text',
            'required' => false,
            'source' => 'insurance_data'
        ],
        'insurance_subscriber_name' => [
            'label' => 'Subscriber Name',
            'type' => 'text',
            'required' => false,
            'source' => 'insurance_data'
        ],
        'insurance_subscriber_dob' => [
            'label' => 'Subscriber DOB',
            'type' => 'date',
            'required' => false,
            'source' => 'insurance_data'
        ],
        'insurance_relationship' => [
            'label' => 'Relationship',
            'type' => 'text',
            'required' => false,
            'source' => 'insurance_data'
        ],
        'facility_name' => [
            'label' => 'Facility Name',
            'type' => 'text',
            'required' => true,
            'source' => 'facility_data'
        ],
        'facility_npi' => [
            'label' => 'NPI',
            'type' => 'text',
            'required' => true,
            'source' => 'facility_data'
        ],
        'facility_contact_name' => [
            'label' => 'Contact Name',
            'type' => 'text',
            'required' => false,
            'source' => 'facility_data'
        ],
        'facility_phone' => [
            'label' => 'Facility Phone',
            'type' => 'tel',
            'required' => false,
            'source' => 'facility_data'
        ],
        'facility_fax' => [
            'label' => 'Facility Fax',
            'type' => 'tel',
            'required' => false,
            'source' => 'facility_data'
        ],
        'prescriber_name' => [
            'label' => 'Prescriber Name',
            'type' => 'text',
            'required' => true,
            'source' => 'prescriber_data'
        ],
        'prescriber_npi' => [
            'label' => 'Prescriber NPI',
            'type' => 'text',
            'required' => true,
            'source' => 'prescriber_data'
        ],
        'prescriber_phone' => [
            'label' => 'Prescriber Phone',
            'type' => 'tel',
            'required' => false,
            'source' => 'prescriber_data'
        ],
        'prescriber_fax' => [
            'label' => 'Prescriber Fax',
            'type' => 'tel',
            'required' => false,
            'source' => 'prescriber_data'
        ],
        'diagnosis_code' => [
            'label' => 'Diagnosis Code',
            'type' => 'text',
            'required' => true,
            'source' => 'clinical_data'
        ],
        'diagnosis_description' => [
            'label' => 'Diagnosis Description',
            'type' => 'text',
            'required' => false,
            'source' => 'clinical_data'
        ],
        'product_name' => [
            'label' => 'Product Name',
            'type' => 'text',
            'required' => true,
            'source' => 'product_data'
        ],
        'product_hcpcs' => [
            'label' => 'HCPCS Code',
            'type' => 'text',
            'required' => true,
            'source' => 'product_data'
        ],
        'product_size' => [
            'label' => 'Size',
            'type' => 'text',
            'required' => true,
            'source' => 'product_data'
        ],
        'product_quantity' => [
            'label' => 'Quantity',
            'type' => 'number',
            'required' => true,
            'source' => 'product_data'
        ],
        'wound_location' => [
            'label' => 'Wound Location',
            'type' => 'text',
            'required' => true,
            'source' => 'clinical_data'
        ],
        'wound_type' => [
            'label' => 'Wound Type',
            'type' => 'text',
            'required' => true,
            'source' => 'clinical_data'
        ],
        'wound_size' => [
            'label' => 'Wound Size',
            'type' => 'text',
            'required' => true,
            'source' => 'clinical_data'
        ],
        'date_of_service' => [
            'label' => 'Date of Service',
            'type' => 'date',
            'required' => true,
            'source' => 'order_data'
        ],
        'authorization_number' => [
            'label' => 'Authorization Number',
            'type' => 'text',
            'required' => false,
            'source' => 'order_data'
        ],
        'urgency_level' => [
            'label' => 'Urgency Level',
            'type' => 'select',
            'options' => ['Standard', 'Urgent', 'Emergency'],
            'required' => false,
            'source' => 'order_data'
        ],
        'special_instructions' => [
            'label' => 'Special Instructions',
            'type' => 'textarea',
            'required' => false,
            'source' => 'order_data'
        ],
        'shipping_address' => [
            'label' => 'Shipping Address',
            'type' => 'text',
            'required' => false,
            'source' => 'shipping_data'
        ],
        'shipping_city' => [
            'label' => 'Shipping City',
            'type' => 'text',
            'required' => false,
            'source' => 'shipping_data'
        ],
        'shipping_state' => [
            'label' => 'Shipping State',
            'type' => 'text',
            'required' => false,
            'source' => 'shipping_data'
        ],
        'shipping_zip' => [
            'label' => 'Shipping Zip',
            'type' => 'text',
            'required' => false,
            'source' => 'shipping_data'
        ]
    ]
];
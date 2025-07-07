<?php

return [
    'id' => 10,
    'name' => 'CENTURION THERAPEUTICS',
    'signature_required' => true,
    'has_order_form' => false,
    'docuseal_template_id' => '1233918',
    'docuseal_field_names' => [
        // Basic Contact Information
        'phone' => 'Phone',
        
        // Checkboxes for wound status
        'check_new_wound' => 'Check: New Wound',
        'check_additional_application' => 'chkAdditionalApplication',
        'check_reverification' => 'Check: Reverification',
        'check_new_insurance' => 'Check: New Insurance',
        
        // Patient Information
        'patient_name' => 'Patient Name',
        'patient_dob' => 'DOB',
        'patient_gender' => 'Gender',
        'patient_address' => 'Address',
        'patient_city' => 'City',
        'patient_state' => 'State',
        'patient_zip' => 'Zip',
        'patient_home_phone' => 'Home Phone',
        'patient_mobile' => 'Mobile',
        
        // SNF/Nursing Home Question
        'snf_days_admitted' => 'If YES how many days has the patient been admitted to the skilled nursing facility or nursing home',
        
        // Insurance Information
        'primary_insurance' => 'Primary Insurance',
        'secondary_insurance' => 'Secondary Insurance',
        'payer_phone' => 'Payer Phone',
        'secondary_payer_phone' => 'Secondary Payor Phone',
        'policy_number' => 'Policy Number',
        'secondary_policy_number' => 'Secondary Policy Number',
        'subscriber_name' => 'Suscriber Name',
        'secondary_subscriber_name' => 'Secondary Subscriber Name',
        
        // Provider Information
        'provider_name' => 'Provider Name',
        'provider_specialty' => 'Provider Specialty',
        'provider_ptan' => 'Provider PTAN',
        'provider_npi' => 'Provider NPI',
        'provider_tax_id' => 'Provider Tax ID',
        'provider_medicare_number' => 'Provider Medicare Number',
        
        // Facility Information
        'facility_name' => 'Facility Name',
        'facility_address' => 'Facility Address',
        'facility_city' => 'Facility City',
        'facility_state' => 'Facility State',
        'facility_zip' => 'Facility Zip',
        'facility_npi' => 'Facility NPI',
        'facility_contact' => 'Facility Contact',
        'facility_contact_phone' => 'Facility Contact Phone',
        'facility_contact_fax' => 'Facility Contact Fax',
        'facility_contact_email' => 'Facility Contact Email',
        
        // Place of Service Checkboxes
        'pos_22' => 'Check: POS-22',
        'pos_11' => 'Check: POS-11',
        'pos_12' => 'Check: POS-12',
        'pos_13' => 'Check: POS-13',
        'pos_31' => 'Check: POS-31',
        'pos_32' => 'Check: POS-32',
        
        // Product Checkboxes
        'amnioband_q4151' => 'AmnioBand Q4151',
        'allopatch_q4128' => 'Allopatch Q4128',
        
        // Additional Insurance Fields
        'primary_insurance_repeat' => 'Primary Insurance',
        'secondary_insurance_repeat' => 'Secondary Insurance',
        'tertiary_insurance' => 'Tertiary',
        
        // Clinical Information
        'known_conditions' => 'Known Conditions',
        'wound_size' => 'Wound Size',
        'anticipated_treatment_start_date' => 'Anticipated Treatment Start Date',
        'frequency' => 'Frequency',
        'number_of_applications' => 'Number of Applications',
        
        // Pre-Auth and Signature
        'no_preauth_assistance' => 'No: Pre-Auth Assistance',
        'signature_date' => 'Signature Date',
        'sales_representative' => 'Sales Representative',
    ],
    'fields' => [
        // Basic Contact Information
        'name' => [
            'source' => 'contact_name || office_contact_name || sales_rep_name',
            'required' => false,
            'type' => 'string'
        ],
        'email' => [
            'source' => 'contact_email || office_contact_email || sales_rep_email',
            'required' => false,
            'type' => 'email'
        ],
        'phone' => [
            'source' => 'contact_phone || office_contact_phone || sales_rep_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        
        // Wound Status Checkboxes
        'check_new_wound' => [
            'source' => 'computed',
            'computation' => 'wound_status == "new"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'check_additional_application' => [
            'source' => 'computed',
            'computation' => 'wound_status == "additional_application"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'check_reverification' => [
            'source' => 'computed',
            'computation' => 'wound_status == "reverification"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'check_new_insurance' => [
            'source' => 'computed',
            'computation' => 'wound_status == "new_insurance"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        
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
            'source' => 'patient_dob',
            'transform' => 'date:m/d/Y',
            'required' => true,
            'type' => 'date'
        ],
        'patient_gender' => [
            'source' => 'patient_gender',
            'required' => true,
            'type' => 'string'
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
        'patient_home_phone' => [
            'source' => 'patient_home_phone || patient_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'patient_mobile' => [
            'source' => 'patient_mobile || patient_cell_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        
        // SNF/Nursing Home Status
        'snf_days_admitted' => [
            'source' => 'snf_days_admitted || nursing_home_days',
            'required' => false,
            'type' => 'string'
        ],
        
        // Insurance Information
        'primary_insurance' => [
            'source' => 'primary_insurance_name || insurance_name',
            'required' => true,
            'type' => 'string'
        ],
        'secondary_insurance' => [
            'source' => 'secondary_insurance_name',
            'required' => false,
            'type' => 'string'
        ],
        'payer_phone' => [
            'source' => 'primary_insurance_phone || payer_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'secondary_payer_phone' => [
            'source' => 'secondary_insurance_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'policy_number' => [
            'source' => 'primary_member_id || policy_number',
            'required' => true,
            'type' => 'string'
        ],
        'secondary_policy_number' => [
            'source' => 'secondary_member_id || secondary_policy_number',
            'required' => false,
            'type' => 'string'
        ],
        'subscriber_name' => [
            'source' => 'primary_subscriber_name || subscriber_name',
            'required' => true,
            'type' => 'string'
        ],
        'secondary_subscriber_name' => [
            'source' => 'secondary_subscriber_name',
            'required' => false,
            'type' => 'string'
        ],
        
        // Provider Information
        'provider_name' => [
            'source' => 'provider_name || physician_name',
            'required' => true,
            'type' => 'string'
        ],
        'provider_specialty' => [
            'source' => 'provider_specialty || physician_specialty',
            'required' => false,
            'type' => 'string'
        ],
        'provider_ptan' => [
            'source' => 'provider_ptan || physician_ptan',
            'required' => false,
            'type' => 'string'
        ],
        'provider_npi' => [
            'source' => 'provider_npi || physician_npi',
            'required' => true,
            'type' => 'string'
        ],
        'provider_tax_id' => [
            'source' => 'provider_tax_id || physician_tax_id',
            'required' => false,
            'type' => 'string'
        ],
        'provider_medicare_number' => [
            'source' => 'provider_medicare_number || physician_medicare_number',
            'required' => false,
            'type' => 'string'
        ],
        
        // Facility Information
        'facility_name' => [
            'source' => 'facility_name || practice_name',
            'required' => true,
            'type' => 'string'
        ],
        'facility_address' => [
            'source' => 'computed',
            'computation' => 'facility_address_line1 + facility_address_line2',
            'required' => true,
            'type' => 'string'
        ],
        'facility_city' => [
            'source' => 'facility_city || practice_city',
            'required' => true,
            'type' => 'string'
        ],
        'facility_state' => [
            'source' => 'facility_state || practice_state',
            'required' => true,
            'type' => 'string'
        ],
        'facility_zip' => [
            'source' => 'facility_zip || practice_zip',
            'required' => true,
            'type' => 'zip'
        ],
        'facility_npi' => [
            'source' => 'facility_npi',
            'required' => false,
            'type' => 'string'
        ],
        'facility_contact' => [
            'source' => 'facility_contact || practice_contact',
            'required' => false,
            'type' => 'string'
        ],
        'facility_contact_phone' => [
            'source' => 'facility_contact_phone || practice_contact_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'facility_contact_fax' => [
            'source' => 'facility_contact_fax || practice_contact_fax',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'facility_contact_email' => [
            'source' => 'facility_contact_email || practice_contact_email',
            'required' => false,
            'type' => 'email'
        ],
        
        // Place of Service Checkboxes
        'pos_22' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "22"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_11' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "11"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_12' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "12"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_13' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "13"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_31' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "31"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'pos_32' => [
            'source' => 'computed',
            'computation' => 'place_of_service == "32"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        
        // Product Checkboxes
        'amnioband_q4151' => [
            'source' => 'computed',
            'computation' => 'selected_product_codes.includes("Q4151") || product_name.includes("AmnioBand")',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        'allopatch_q4128' => [
            'source' => 'computed',
            'computation' => 'selected_product_codes.includes("Q4128") || product_name.includes("Allopatch")',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean'
        ],
        
        // Additional Insurance Fields (repeat fields)
        'primary_insurance_repeat' => [
            'source' => 'primary_insurance_name || insurance_name',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_insurance_repeat' => [
            'source' => 'secondary_insurance_name',
            'required' => false,
            'type' => 'string'
        ],
        'tertiary_insurance' => [
            'source' => 'tertiary_insurance_name',
            'required' => false,
            'type' => 'string'
        ],
        
        // Clinical Information
        'known_conditions' => [
            'source' => 'known_conditions || medical_conditions || diagnosis_description',
            'required' => false,
            'type' => 'string'
        ],
        'wound_size' => [
            'source' => 'computed',
            'computation' => 'wound_size_length + " x " + wound_size_width + " cm"',
            'required' => true,
            'type' => 'string'
        ],
        'anticipated_treatment_start_date' => [
            'source' => 'expected_service_date || treatment_start_date',
            'transform' => 'date:m/d/Y',
            'required' => true,
            'type' => 'date'
        ],
        'frequency' => [
            'source' => 'treatment_frequency || application_frequency',
            'required' => false,
            'type' => 'string'
        ],
        'number_of_applications' => [
            'source' => 'number_of_applications || expected_applications',
            'required' => false,
            'type' => 'string'
        ],
        
        // Pre-Auth and Signature
        'no_preauth_assistance' => [
            'source' => 'computed',
            'computation' => 'preauth_assistance == false ? "No" : ""',
            'required' => false,
            'type' => 'string'
        ],
        'signature_date' => [
            'source' => 'signature_date || today',
            'transform' => 'date:m/d/Y',
            'required' => false,
            'type' => 'date'
        ],
        'sales_representative' => [
            'source' => 'sales_rep_name || representative_name',
            'required' => false,
            'type' => 'string'
        ],
    ]
];
<?php

return [
    'id' => 20,
    'name' => 'Celularity',
    'signature_required' => true,
    'has_order_form' => true,
    'supports_insurance_upload_in_ivr' => true,
    'duration_requirement' => null,
    'docuseal_template_id' => null, // Will need to be set based on actual template ID
    'docuseal_field_names' => [
        // Patient Information
        'patient_name' => 'Patient name',
        'patient_dob' => 'DOB',
        'patient_address' => 'Address',
        'patient_city_state_zip' => 'City/State/Zip',
        'primary_insurance' => 'Primary Insurance',
        'primary_insurance_id' => 'Ins ID#',
        'primary_insurance_phone' => 'Ins. Phone',
        'secondary_insurance' => 'Secondary Insurance',
        'secondary_insurance_id' => 'Ins ID# (Secondary)',
        'secondary_insurance_phone' => 'Ins. Phone (Secondary)',
        'surgical_global_period' => 'Is Patient currently in a surgical global period?',
        'surgical_cpt_code' => 'If yes, what is the CPT surgery code?',
        'skilled_nursing_facility' => 'Is Patient currently residing in a Skilled Nursing Facility, Nursing Home or any inpatient facility?',
        
        // Provider Information
        'place_of_service' => 'Place of Service',
        'place_of_service_other' => 'Other (Please Write In)',
        'rendering_physician_name' => 'Rendering Physician Name',
        'provider_npi' => 'NPI',
        'provider_tax_id' => 'Tax ID',
        'medicare_ptan' => 'Medicare PTAN',
        'provider_address' => 'Address',
        'provider_phone' => 'Provider Phone',
        'provider_city_state' => 'City / State',
        'provider_fax' => 'Provider Fax',
        'primary_contact_person' => 'Primary Contact Person',
        'contact_phone' => 'Contact Phone',
        'contact_email' => 'Contact email address',
        'contact_fax' => 'Contact Fax',
        
        // Facility Information
        'facility_name' => 'Facility Name',
        'facility_phone' => 'Facility Phone',
        'facility_address' => 'Facility Address',
        'facility_fax' => 'Facility Fax',
        'facility_npi' => 'Facility NPI',
        'facility_tax_id' => 'Tax ID (Facility)',
        'group_ptan' => 'Group PTAN',
        'facility_primary_contact' => 'Primary Contact Person (Facility)',
        'facility_contact_phone' => 'Contact Phone (Facility)',
        'facility_contact_email' => 'Contact Email Address',
        'facility_contact_fax' => 'Contact Fax (Facility)',
        
        // Procedure Information
        'procedure_date' => 'Procedure Date',
        'cpt_hcpcs_codes' => 'CPT / HCPCS Code(s)',
        'diagnosis_icd10_codes' => 'Diagnosis ICD-10 Codes',
        'wound_sizes' => 'Wound Size(s)',
        'wound_location' => 'Wound Location',
        'additional_patient_notes' => 'Additional Patient Notes',
        'number_of_grafts' => 'Number of Grafts Intended',
        'size_of_initial_application' => 'Size of Initial Application (in sq. cm or ml/mg)',
        'physician_signature' => 'Physician Signature',
        'signature_date' => 'Date',
        
        // Account Executive
        'account_executive_info' => 'Account Executive contact information (name and email)'
    ],
    'fields' => [
        // Patient Information
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
        'patient_address' => [
            'source' => 'patient_address_line1',
            'required' => true,
            'type' => 'string'
        ],
        'patient_city_state_zip' => [
            'source' => 'computed',
            'computation' => 'patient_city + ", " + patient_state + " " + patient_zip',
            'required' => true,
            'type' => 'string'
        ],
        'primary_insurance' => [
            'source' => 'primary_insurance_name',
            'required' => true,
            'type' => 'string'
        ],
        'primary_insurance_id' => [
            'source' => 'primary_member_id',
            'required' => true,
            'type' => 'string'
        ],
        'primary_insurance_phone' => [
            'source' => 'primary_insurance_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'secondary_insurance' => [
            'source' => 'secondary_insurance_name',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_insurance_id' => [
            'source' => 'secondary_member_id',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_insurance_phone' => [
            'source' => 'secondary_insurance_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'surgical_global_period' => [
            'source' => 'surgical_global_period',
            'transform' => 'boolean:yes_no',
            'required' => false,
            'type' => 'checkbox'
        ],
        'surgical_cpt_code' => [
            'source' => 'surgical_cpt_code',
            'required' => false,
            'type' => 'string'
        ],
        'skilled_nursing_facility' => [
            'source' => 'skilled_nursing_facility',
            'transform' => 'boolean:yes_no',
            'required' => false,
            'type' => 'checkbox'
        ],
        
        // Provider Information
        'place_of_service' => [
            'source' => 'place_of_service_code',
            'transform' => 'place_of_service_map',
            'required' => false,
            'type' => 'radio',
            'options' => [
                '11' => 'Physician Office (11)',
                '22' => 'HOPD (22)',
                '24' => 'Ambulatory Surgical Center (24)',
                'other' => 'Other'
            ]
        ],
        'place_of_service_other' => [
            'source' => 'place_of_service_other',
            'required' => false,
            'type' => 'string'
        ],
        'rendering_physician_name' => [
            'source' => 'provider_name',
            'required' => true,
            'type' => 'string'
        ],
        'provider_npi' => [
            'source' => 'provider_npi',
            'required' => true,
            'type' => 'npi'
        ],
        'provider_tax_id' => [
            'source' => 'provider_tax_id',
            'required' => false,
            'type' => 'string'
        ],
        'medicare_ptan' => [
            'source' => 'provider_medicare_ptan',
            'required' => false,
            'type' => 'string'
        ],
        'provider_address' => [
            'source' => 'provider_address_line1',
            'required' => false,
            'type' => 'string'
        ],
        'provider_phone' => [
            'source' => 'provider_phone',
            'transform' => 'phone:US',
            'required' => true,
            'type' => 'phone'
        ],
        'provider_city_state' => [
            'source' => 'computed',
            'computation' => 'provider_city + ", " + provider_state',
            'required' => false,
            'type' => 'string'
        ],
        'provider_fax' => [
            'source' => 'provider_fax',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'primary_contact_person' => [
            'source' => 'provider_contact_name',
            'required' => false,
            'type' => 'string'
        ],
        'contact_phone' => [
            'source' => 'provider_contact_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'contact_email' => [
            'source' => 'provider_email',
            'required' => false,
            'type' => 'email'
        ],
        'contact_fax' => [
            'source' => 'provider_contact_fax',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        
        // Facility Information
        'facility_name' => [
            'source' => 'facility_name',
            'required' => true,
            'type' => 'string'
        ],
        'facility_phone' => [
            'source' => 'facility_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'facility_address' => [
            'source' => 'facility_address_line1',
            'required' => false,
            'type' => 'string'
        ],
        'facility_fax' => [
            'source' => 'facility_fax',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'facility_npi' => [
            'source' => 'facility_npi',
            'required' => false,
            'type' => 'npi'
        ],
        'facility_tax_id' => [
            'source' => 'facility_tax_id',
            'required' => false,
            'type' => 'string'
        ],
        'group_ptan' => [
            'source' => 'facility_group_ptan',
            'required' => false,
            'type' => 'string'
        ],
        'facility_primary_contact' => [
            'source' => 'facility_contact_name',
            'required' => false,
            'type' => 'string'
        ],
        'facility_contact_phone' => [
            'source' => 'facility_contact_phone',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        'facility_contact_email' => [
            'source' => 'facility_contact_email',
            'required' => false,
            'type' => 'email'
        ],
        'facility_contact_fax' => [
            'source' => 'facility_contact_fax',
            'transform' => 'phone:US',
            'required' => false,
            'type' => 'phone'
        ],
        
        // Procedure Information
        'procedure_date' => [
            'source' => 'expected_service_date',
            'transform' => 'date:m/d/Y',
            'required' => true,
            'type' => 'date'
        ],
        'cpt_hcpcs_codes' => [
            'source' => 'product_hcpcs_code',
            'required' => true,
            'type' => 'string'
        ],
        'diagnosis_icd10_codes' => [
            'source' => 'primary_diagnosis_code',
            'required' => true,
            'type' => 'string'
        ],
        'wound_sizes' => [
            'source' => 'computed',
            'computation' => 'wound_size_length + "x" + wound_size_width + "x" + wound_size_depth + " cm"',
            'required' => true,
            'type' => 'string'
        ],
        'wound_location' => [
            'source' => 'wound_location',
            'required' => true,
            'type' => 'string'
        ],
        'additional_patient_notes' => [
            'source' => 'clinical_notes',
            'required' => false,
            'type' => 'text'
        ],
        'number_of_grafts' => [
            'source' => 'product_quantity',
            'required' => true,
            'type' => 'number'
        ],
        'size_of_initial_application' => [
            'source' => 'product_size',
            'required' => true,
            'type' => 'string'
        ],
        'physician_signature' => [
            'source' => 'provider_name',
            'required' => true,
            'type' => 'signature'
        ],
        'signature_date' => [
            'source' => 'current_date',
            'transform' => 'date:m/d/Y',
            'required' => true,
            'type' => 'date'
        ],
        
        // Account Executive
        'account_executive_info' => [
            'source' => 'account_executive_info',
            'required' => false,
            'type' => 'string'
        ]
    ],
    // Product selection options for Celularity
    'product_options' => [
        'Biovance®',
        'Biovance 3L®',
        'Interfyl®'
    ]
];
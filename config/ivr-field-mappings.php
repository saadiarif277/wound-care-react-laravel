<?php

return [
    /*
    |--------------------------------------------------------------------------
    | IVR Field Mappings Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the comprehensive field mappings for all manufacturer
    | IVR forms. Each manufacturer has specific field names that map to our
    | standardized field structure.
    |
    */

    'manufacturers' => [
        
        'ACZ_Distribution' => [
            'name' => 'ACZ Distribution',
            'template_id' => env('DOCUSEAL_ACZ_TEMPLATE_ID', '852440'),
            'folder_id' => env('DOCUSEAL_ACZ_FOLDER_ID', '75423'),
            'pdf_template' => 'Updated Q2 IVR ACZ.pdf',
            'field_mappings' => [
                // Request Information
                'Sales Rep Name' => 'sales_rep_name',
                'Additional Emails' => 'additional_notification_emails',
                
                // Physician Information
                'Physician Name' => 'provider_name',
                'Physician Specialty' => 'provider_specialty',
                'NPI' => 'provider_npi',
                'Tax ID' => 'provider_tax_id',
                'PTAN' => 'provider_ptan',
                'Medicaid #' => 'provider_medicaid_number',
                'Phone #' => 'provider_phone',
                'Fax #' => 'provider_fax',
                
                // Facility Information
                'Facility Name' => 'facility_name',
                'Facility Address' => 'facility_address',
                'City' => 'facility_city',
                'State' => 'facility_state',
                'ZIP' => 'facility_zip',
                'Contact Name' => 'facility_contact_name',
                'Contact Phone' => 'facility_contact_phone',
                
                // Patient Information
                'Patient Name' => 'patient_name',
                'Patient DOB' => 'patient_dob',
                'Patient Address' => 'patient_address',
                'Patient City' => 'patient_city',
                'Patient State' => 'patient_state',
                'Patient ZIP' => 'patient_zip',
                'Patient Phone' => 'patient_phone',
                
                // Insurance Information
                'Primary Insurance' => 'primary_insurance_name',
                'Primary Policy Number' => 'primary_policy_number',
                'Primary Payer Phone' => 'primary_payer_phone',
                'Secondary Insurance' => 'secondary_insurance_name',
                'Secondary Policy Number' => 'secondary_policy_number',
                'Secondary Payer Phone' => 'secondary_payer_phone',
                
                // Clinical Information
                'Place of Service' => 'place_of_service',
                'Is Patient in Hospice' => 'hospice_status',
                'Is Patient in Part A' => 'part_a_status',
                'Global Period Status' => 'global_period_status',
                'CPT Codes' => 'application_cpt_codes',
                'Surgery Date' => 'global_period_surgery_date',
                'Location of Wound' => 'wound_location',
                'ICD-10 Codes' => 'diagnosis_codes',
                'Total Wound Size' => 'wound_size_total',
                'Product' => 'selected_products',
                
                // Authorization
                'Prior Auth Permission' => 'authorization_permission',
            ]
        ],

        'Advanced_Health' => [
            'name' => 'Advanced Health Solutions',
            'template_id' => env('DOCUSEAL_ADVANCED_TEMPLATE_ID'),
            'folder_id' => env('DOCUSEAL_ADVANCED_FOLDER_ID'),
            'pdf_template' => 'Template IVR Advanced Solution Universal REV2.0 copy (2).pdf',
            'field_mappings' => [
                // Basic Information
                'Sales Rep' => 'sales_rep_name',
                'Place of Service' => 'place_of_service',
                
                // Facility Information
                'Facility Name' => 'facility_name',
                'Address' => 'facility_address',
                'Contact Name' => 'facility_contact_name',
                'Phone' => 'facility_contact_phone',
                'Fax' => 'facility_contact_fax',
                'Medicare Admin Contractor' => 'facility_medicare_contractor',
                'NPI' => 'facility_npi',
                'TIN' => 'facility_tax_id',
                'PTAN' => 'facility_ptan',
                
                // Physician Information
                'Physician Name' => 'provider_name',
                'Physician Address' => 'provider_address',
                'Physician Phone' => 'provider_phone',
                'Physician Fax' => 'provider_fax',
                'Physician NPI' => 'provider_npi',
                'Physician TIN' => 'provider_tax_id',
                
                // Patient Information
                'Patient Name' => 'patient_name',
                'Patient Address' => 'patient_address',
                'Date of Birth' => 'patient_dob',
                'Patient Phone' => 'patient_phone',
                'OK to Contact Patient?' => 'patient_contact_permission',
                
                // Insurance Information
                'Primary Insurance' => 'primary_insurance_name',
                'Subscriber Name (Primary)' => 'primary_subscriber_name',
                'Policy Number (Primary)' => 'primary_policy_number',
                'Subscriber DOB (Primary)' => 'primary_subscriber_dob',
                'Type of Plan (Primary)' => 'primary_plan_type',
                'Insurance Phone Number (Primary)' => 'primary_payer_phone',
                'Provider Network Status (Primary)' => 'primary_network_status',
                
                'Secondary Insurance' => 'secondary_insurance_name',
                'Subscriber Name (Secondary)' => 'secondary_subscriber_name',
                'Policy Number (Secondary)' => 'secondary_policy_number',
                'Subscriber DOB (Secondary)' => 'secondary_subscriber_dob',
                'Type of Plan (Secondary)' => 'secondary_plan_type',
                'Insurance Phone Number (Secondary)' => 'secondary_payer_phone',
                'Provider Network Status (Secondary)' => 'secondary_network_status',
                
                // Clinical Information
                'Wound Type' => 'wound_type',
                'Wound Size(s)' => 'wound_size_total',
                'Application CPT(s)' => 'application_cpt_codes',
                'Date of Procedure' => 'anticipated_treatment_date',
                'ICD-10 Diagnosis Code(s)' => 'diagnosis_codes',
                'Product Information' => 'selected_products',
                'Is patient in SNF?' => 'snf_status',
                'Global Period Status' => 'global_period_status',
                'CPT Code' => 'global_period_cpt_codes',
                'Prior Auth Required' => 'request_prior_auth_assistance',
                
                // Signature
                'Physician Signature' => 'signature',
                'Date' => 'signature_date',
            ]
        ],

        'Amnio_Amp' => [
            'name' => 'Amnio AMP / MedLife Solutions',
            'template_id' => env('DOCUSEAL_AMNIOAMP_TEMPLATE_ID'),
            'folder_id' => env('DOCUSEAL_AMNIOAMP_FOLDER_ID'),
            'pdf_template' => 'AMNIO AMP MedLife IVR-fillable .pdf',
            'field_mappings' => [
                // Header Information
                'Distributor / Company' => 'sales_rep_name',
                
                // Physician Information
                'Physician Name' => 'provider_name',
                'Physician PTAN' => 'provider_ptan',
                'Physician NPI' => 'provider_npi',
                
                // Practice Information
                'Practice Name' => 'facility_name',
                'Practice PTAN' => 'facility_ptan',
                'Practice NPI' => 'facility_npi',
                'TAX ID#' => 'facility_tax_id',
                'Office Contact Name' => 'facility_contact_name',
                'Office Contact Email' => 'facility_contact_email',
                
                // Patient Information
                'Patient Name' => 'patient_name',
                'Patient DOB' => 'patient_dob',
                
                // Insurance Information
                'Primary Insurance' => 'primary_insurance_name',
                'Member ID (Primary)' => 'primary_policy_number',
                'Secondary Insurance' => 'secondary_insurance_name',
                'Member ID (Secondary)' => 'secondary_policy_number',
                'Insurance Cards Attached' => 'cards_attached',
                
                // Clinical Information
                'Place of Service' => 'place_of_service',
                'Is patient in SNF' => 'snf_status',
                'Over 100 days?' => 'snf_over_100_days',
                'Post-op period?' => 'global_period_status',
                'Previous CPT codes' => 'global_period_cpt_codes',
                'Surgery Date' => 'global_period_surgery_date',
                'Procedure Date' => 'anticipated_treatment_date',
                'Wound Size L' => 'wound_size_length',
                'Wound Size W' => 'wound_size_width',
                'Wound Size Total' => 'wound_size_total',
                'Wound location' => 'wound_location_details',
                'Size of Graft Requested' => 'graft_size_requested',
                'ICD-10 (1)' => 'primary_diagnosis_code',
                'ICD-10 (2,3,4)' => 'secondary_diagnosis_codes',
                'CPT codes' => 'application_cpt_codes',
            ]
        ],

        'AmnioBand' => [
            'name' => 'AmnioBand / Centurion',
            'template_id' => env('DOCUSEAL_AMNIOBAND_TEMPLATE_ID'),
            'folder_id' => env('DOCUSEAL_AMNIOBAND_FOLDER_ID'),
            'pdf_template' => 'Centurion AmnioBand IVR (Only used for STAT IVRS after hours).pdf',
            'field_mappings' => [
                // Request Type
                'Request Type' => 'request_type',
                
                // Patient Information
                'Patient Name' => 'patient_name',
                'DOB' => 'patient_dob',
                'Male/Female' => 'patient_gender',
                'Address' => 'patient_address',
                'City' => 'patient_city',
                'State' => 'patient_state',
                'Zip' => 'patient_zip',
                'Home Phone #' => 'patient_phone',
                'Mobile #' => 'patient_mobile',
                
                // Facility Status
                'Is patient in skilled facility?' => 'snf_status',
                'Days admitted' => 'snf_days',
                
                // Insurance Information
                'Primary Insurance' => 'primary_insurance_name',
                'Payer Phone # (Primary)' => 'primary_payer_phone',
                'Policy Number (Primary)' => 'primary_policy_number',
                'Subscriber Name (Primary)' => 'primary_subscriber_name',
                'Secondary Insurance' => 'secondary_insurance_name',
                'Payer Phone (Secondary)' => 'secondary_payer_phone',
                'Policy Number (Secondary)' => 'secondary_policy_number',
                'Subscriber Name (Secondary)' => 'secondary_subscriber_name',
                
                // Provider Information
                'Provider Name' => 'provider_name',
                'Specialty' => 'provider_specialty',
                'Provider NPI' => 'provider_npi',
                'Tax ID' => 'provider_tax_id',
                'Medicaid Provider #' => 'provider_medicaid_number',
                
                // Facility Information
                'Facility Name' => 'facility_name',
                'Facility Address' => 'facility_address',
                'Facility City' => 'facility_city',
                'Facility State' => 'facility_state',
                'Facility Zip' => 'facility_zip',
                'Facility NPI' => 'facility_npi',
                'Facility Tax ID' => 'facility_tax_id',
                'PTAN #' => 'facility_ptan',
                'Facility Contact' => 'facility_contact_name',
                'Phone #' => 'facility_contact_phone',
                'Fax #' => 'facility_contact_fax',
                'Email Address' => 'facility_contact_email',
                
                // Clinical Information
                'Treatment Setting' => 'place_of_service',
                'Product' => 'selected_products',
                'Primary' => 'primary_diagnosis_code',
                'Secondary/Tertiary' => 'secondary_diagnosis_codes',
                'Anticipated Treatment Date' => 'anticipated_treatment_date',
                'Number of Applications' => 'anticipated_applications',
                'Prior Auth Required' => 'request_prior_auth_assistance',
                
                // Signature
                'Provider Signature' => 'signature',
                'Date' => 'signature_date',
            ]
        ],

        'BioWerX' => [
            'name' => 'BioWerX',
            'template_id' => env('DOCUSEAL_BIOWERX_TEMPLATE_ID'),
            'folder_id' => env('DOCUSEAL_BIOWERX_FOLDER_ID'),
            'pdf_template' => 'BioWerX Fillable IVR Apr 2024.pdf',
            'field_mappings' => [
                // This template would need field identification from the actual PDF
                // Using common field patterns for now
                'Sales Rep' => 'sales_rep_name',
                'Provider Name' => 'provider_name',
                'Provider NPI' => 'provider_npi',
                'Facility Name' => 'facility_name',
                'Patient Name' => 'patient_name',
                'Patient DOB' => 'patient_dob',
                'Primary Insurance' => 'primary_insurance_name',
                'Product' => 'selected_products',
                'ICD-10' => 'diagnosis_codes',
                'CPT' => 'application_cpt_codes',
            ]
        ],

        'BioWound' => [
            'name' => 'BioWound Solutions',
            'template_id' => env('DOCUSEAL_BIOWOUND_TEMPLATE_ID'),
            'folder_id' => env('DOCUSEAL_BIOWOUND_FOLDER_ID'),
            'pdf_template' => 'BioWound IVR v3 (2).pdf',
            'field_mappings' => [
                // Request Information
                'Request Type' => 'request_type',
                'Sales Rep' => 'sales_rep_name',
                'Rep Email' => 'sales_rep_email',
                
                // Physician Information
                'Physician Name' => 'provider_name',
                'Physician Specialty' => 'provider_specialty',
                'NPI (Physician)' => 'provider_npi',
                'Tax ID (Physician)' => 'provider_tax_id',
                'Medicaid # (Physician)' => 'provider_medicaid_number',
                
                // Facility Information
                'Facility Name' => 'facility_name',
                'Facility Address' => 'facility_address',
                'City' => 'facility_city',
                'State' => 'facility_state',
                'Zip' => 'facility_zip',
                'Contact Name' => 'facility_contact_name',
                'Phone #' => 'facility_contact_phone',
                'Fax #' => 'facility_contact_fax',
                'Contact Email' => 'facility_contact_email',
                'NPI (Facility)' => 'facility_npi',
                'Tax ID (Facility)' => 'facility_tax_id',
                'PTAN (Facility)' => 'facility_ptan',
                'Medicaid # (Facility)' => 'facility_medicaid_number',
                
                // Patient Information
                'Patient Name' => 'patient_name',
                'Patient Date of Birth' => 'patient_dob',
                'Patient Address' => 'patient_address',
                
                // Clinical Information
                'Place of Service' => 'place_of_service',
                'Is patient in SNF?' => 'snf_status',
                'Number of Days in SNF?' => 'snf_days',
                
                // Insurance Information
                'Payer Name (Primary)' => 'primary_insurance_name',
                'Policy # (Primary)' => 'primary_policy_number',
                'Payer Phone # (Primary)' => 'primary_payer_phone',
                'Payer Name (Secondary)' => 'secondary_insurance_name',
                'Policy # (Secondary)' => 'secondary_policy_number',
                'Payer Phone # (Secondary)' => 'secondary_payer_phone',
                'Prior Auth Request' => 'request_prior_auth_assistance',
                
                // Product and Wound Information
                'Product' => 'selected_products',
                'Wound Type' => 'wound_type',
                'Primary ICD-10 Code' => 'primary_diagnosis_code',
                'Secondary ICD-10 Code' => 'secondary_diagnosis_codes',
                'Wound Location' => 'wound_location_details',
                'Wound Duration' => 'wound_duration',
                'Co-Morbities' => 'comorbidities',
                'Post Debridement Total Size' => 'wound_size_total',
                'Previously Used Therapies' => 'previous_treatments',
                
                // Authorization
                'Authorized Signature' => 'signature',
                'Date' => 'signature_date',
            ]
        ],

        'Extremity_Care' => [
            'name' => 'Extremity Care',
            'template_id' => env('DOCUSEAL_EXTREMITY_TEMPLATE_ID'),
            'folder_id' => env('DOCUSEAL_EXTREMITY_FOLDER_ID'),
            'pdf_template' => 'Q2 CompleteFT IVR.pdf',
            'field_mappings' => [
                // Request Information
                'Request Type' => 'request_type',
                'Sales Rep' => 'sales_rep_name',
                'Product Requested' => 'product_sizes',
                'Place of Service' => 'place_of_service',
                
                // Patient Information
                'Patient Name' => 'patient_name',
                'DOB' => 'patient_dob',
                'Male/Female' => 'patient_gender',
                'Address' => 'patient_address',
                'City' => 'patient_city',
                'State' => 'patient_state',
                'Zip' => 'patient_zip',
                
                // Facility Status
                'Is patient in SNF?' => 'snf_status',
                'Days admitted' => 'snf_days',
                
                // Insurance Information
                'Primary Insurance' => 'primary_insurance_name',
                'Payer Phone # (Primary)' => 'primary_payer_phone',
                'Policy Number (Primary)' => 'primary_policy_number',
                'Secondary Insurance' => 'secondary_insurance_name',
                'Payer Phone # (Secondary)' => 'secondary_payer_phone',
                'Policy Number (Secondary)' => 'secondary_policy_number',
                
                // Provider Information
                'Provider Name' => 'provider_name',
                'Provider NPI' => 'provider_npi',
                'Provider Tax ID#' => 'provider_tax_id',
                'Medicare Provider #' => 'provider_medicaid_number',
                
                // Facility Information
                'Facility Name' => 'facility_name',
                'Facility Address' => 'facility_address',
                'Facility City' => 'facility_city',
                'Facility State' => 'facility_state',
                'Facility Zip' => 'facility_zip',
                'Facility NPI' => 'facility_npi',
                'Facility Tax ID#' => 'facility_tax_id',
                'Facility Contact' => 'facility_contact_name',
                'Facility Phone#' => 'facility_contact_phone',
                'Facility Fax#' => 'facility_contact_fax',
                'Facility Contact Email' => 'facility_contact_email',
                
                // Clinical Information
                'Product' => 'selected_products',
                'CPT Codes' => 'application_cpt_codes',
                'Anticipated Application Date' => 'anticipated_treatment_date',
                'Number of Applications' => 'anticipated_applications',
                'Wound Type' => 'wound_type',
                'ICD-10 Primary Code' => 'primary_diagnosis_code',
                'ICD-10 Secondary Codes' => 'secondary_diagnosis_codes',
            ]
        ],

        'SKYE' => [
            'name' => 'SKYE Biologics',
            'template_id' => env('DOCUSEAL_SKYE_TEMPLATE_ID'),
            'folder_id' => env('DOCUSEAL_SKYE_FOLDER_ID'),
            'pdf_template' => 'WoundPlus.Patient.Insurance.Verification.Form.September2023R1 (2) (1).pdf',
            'field_mappings' => [
                // This would need to be mapped based on the actual PDF fields
                'Sales Rep' => 'sales_rep_name',
                'Provider Name' => 'provider_name',
                'Facility Name' => 'facility_name',
                'Patient Name' => 'patient_name',
                'Insurance' => 'primary_insurance_name',
                'Product' => 'selected_products',
            ]
        ],

        'Total_Ancillary' => [
            'name' => 'Total Ancillary',
            'template_id' => env('DOCUSEAL_TOTAL_TEMPLATE_ID'),
            'folder_id' => env('DOCUSEAL_TOTAL_FOLDER_ID'),
            'pdf_template' => 'Copy of Universal_Benefits_Verification_April_23_V2 (1).pdf',
            'field_mappings' => [
                // Universal benefits verification form
                'Sales Rep' => 'sales_rep_name',
                'Provider' => 'provider_name',
                'Facility' => 'facility_name',
                'Patient' => 'patient_name',
                'Insurance' => 'primary_insurance_name',
                'Product' => 'selected_products',
            ]
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Common Field Mappings
    |--------------------------------------------------------------------------
    |
    | These are common fields that appear across multiple forms with different
    | names but map to the same data in our system.
    |
    */
    'common_fields' => [
        'provider_fields' => [
            'provider_name' => ['Physician Name', 'Provider Name', 'Surgeon Name', 'Doctor Name'],
            'provider_npi' => ['NPI', 'Physician NPI', 'Provider NPI', 'NPI (Physician)'],
            'provider_tax_id' => ['Tax ID', 'TAX ID', 'TIN', 'Physician Tax ID', 'Provider Tax ID#'],
            'provider_ptan' => ['PTAN', 'Physician PTAN', 'Provider PTAN'],
            'provider_specialty' => ['Specialty', 'Physician Specialty', 'Provider Specialty'],
        ],
        
        'facility_fields' => [
            'facility_name' => ['Facility Name', 'Practice Name', 'Office Name', 'Clinic Name'],
            'facility_npi' => ['Facility NPI', 'Practice NPI', 'NPI (Facility)'],
            'facility_address' => ['Facility Address', 'Address', 'Office Address'],
            'facility_city' => ['City', 'Facility City'],
            'facility_state' => ['State', 'Facility State'],
            'facility_zip' => ['ZIP', 'Zip', 'Facility Zip', 'Facility ZIP'],
        ],
        
        'patient_fields' => [
            'patient_name' => ['Patient Name', 'Name', 'Patient'],
            'patient_dob' => ['Patient DOB', 'DOB', 'Date of Birth', 'Patient Date of Birth'],
            'patient_gender' => ['Gender', 'Male/Female', 'Sex'],
        ],
        
        'insurance_fields' => [
            'primary_insurance_name' => ['Primary Insurance', 'Insurance Name (Primary)', 'Payer Name (Primary)'],
            'primary_policy_number' => ['Policy Number (Primary)', 'Member ID (Primary)', 'Policy # (Primary)'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Types and Validation Rules
    |--------------------------------------------------------------------------
    */
    'field_types' => [
        'text' => ['name', 'address', 'specialty'],
        'date' => ['dob', 'surgery_date', 'treatment_date', 'procedure_date'],
        'phone' => ['phone', 'fax', 'contact_phone'],
        'email' => ['email', 'contact_email'],
        'select' => ['place_of_service', 'wound_type', 'plan_type', 'gender'],
        'checkbox' => ['snf_status', 'hospice_status', 'prior_auth_required'],
        'number' => ['wound_size', 'days', 'applications'],
        'textarea' => ['notes', 'comorbidities', 'previous_treatments'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Values
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'request_date' => 'today',
        'signature_date' => 'today',
        'send_email' => false,
    ],
];
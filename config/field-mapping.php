<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Field Mapping Configuration
    |--------------------------------------------------------------------------
    |
    | This is the single source of truth for all field mapping configurations
    | in the wound care application. It defines how data from various sources
    | (FHIR, forms, database) maps to manufacturer-specific IVR templates.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Canonical Field Names
    |--------------------------------------------------------------------------
    |
    | These are the standardized field names that all manufacturer forms map to.
    | This allows us to have a single data structure regardless of how each
    | manufacturer names their fields.
    |
    */
    'canonical_fields' => [
        'patient_name', 'patient_first_name', 'patient_last_name', 'patient_dob',
        'patient_gender', 'patient_phone', 'patient_email', 'patient_address',
        'patient_city', 'patient_state', 'patient_zip', 'physician_name',
        'physician_npi', 'physician_ptan', 'facility_name', 'facility_npi',
        'facility_ptan', 'facility_address', 'insurance_name', 'policy_number',
        'member_id', 'plan_type', 'place_of_service', 'wound_location',
        'wound_size', 'wound_type', 'diagnosis_code', 'service_date'
    ],

    /*
    |--------------------------------------------------------------------------
    | Field Aliases
    |--------------------------------------------------------------------------
    |
    | Maps various field name variations to canonical names
    |
    */
    'field_aliases' => [
        'provider_name' => 'physician_name',
        'provider_npi' => 'physician_npi',
        'practice_name' => 'facility_name',
        'clinic_name' => 'facility_name',
        'insurance_company' => 'insurance_name',
        'policy_id' => 'policy_number',
        'member_number' => 'member_id',
        'dos' => 'service_date',
        'date_of_service' => 'service_date',
    ],

    'manufacturers' => [
        'ACZ' => [
            'id' => 1,
            'name' => 'ACZ & Associates',
            'signature_required' => true,
            'has_order_form' => false,
            'duration_requirement' => 'greater_than_4_weeks',
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
                    'computation' => 'patient_first_name + patient_last_name',
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
        ],
        
        'Advanced Health' => [
            'id' => 2,
            'name' => 'Advanced Health',
            'signature_required' => true,
            'has_order_form' => true,
            'fields' => [
                // Similar structure to ACZ with manufacturer-specific fields
                'patient_name' => [
                    'source' => 'computed',
                    'computation' => 'patient_first_name + patient_last_name',
                    'required' => true,
                    'type' => 'string'
                ],
                // ... additional fields specific to Advanced Health
            ]
        ],
        
        // MedLife - moved to config/manufacturers/medlife-solutions.php
        'MedLife' => [
            'id' => 5,
            'name' => 'MEDLIFE SOLUTIONS',
            'signature_required' => true,
            'has_order_form' => true, // Order forms use separate config file
            'docuseal_field_names' => [
                // Map canonical names to MedLife's exact DocuSeal field names
                
                // Basic Contact Information
                'name' => 'Name',
                'email' => 'Email',
                'phone' => 'Phone',
                'distributor_company' => 'Distributor/Company',
                
                // Provider Information (Physician)
                'physician_name' => 'Physician Name',
                'physician_ptan' => 'Physician PTAN',
                'physician_npi' => 'Physician NPI',
                
                // Practice/Facility Information
                'practice_name' => 'Practice Name',
                'practice_ptan' => 'Practice PTAN',
                'practice_npi' => 'Practice NPI',
                'tax_id' => 'TAX ID',
                
                // Office Contact Information
                'office_contact_name' => 'Office Contact Name',
                'office_contact_email' => 'Office Contact Email',
                
                // Patient Information
                'patient_name' => 'Patient Name',
                'patient_dob' => 'Patient DOB',
                
                // Insurance Information
                'primary_insurance_name' => 'Primary Insurance',
                'primary_member_id' => 'Member ID',
                'secondary_insurance_name' => 'Secondary Insurance',
                'secondary_member_id' => 'Secondary Member ID',
                
                // Place of Service checkboxes - exact field names from DocuSeal template
                'place_of_service_office' => 'Office: POS-11',
                'place_of_service_home' => 'Home: POS 12', 
                'place_of_service_assisted' => 'Assisted Living: POS-13',
                'place_of_service_snf' => 'SNF: POS 31',
                'place_of_service_long_term' => 'Long Term Care: POS 32',
                'place_of_service_other' => 'Other',
                
                // SNF/Nursing Home questions - exact field names from DocuSeal template
                'snf_nursing_home_status' => 'Is the patient currently residing in a Nursing Home OR Skilled Nursing Facility',
                'snf_over_100_days' => 'If yes, has it been over 100 days',
                
                // Post-op period - exact field name from DocuSeal template
                'post_op_status' => 'Is this patient currently under a post-op period',
                'previous_surgery_cpt' => 'If yes, please list CPT codes of previous surgery',
                'surgery_date' => 'Surgery Date',
                
                // Procedure Information
                'procedure_date' => 'Procedure Date',
                'wound_size_length' => 'L',
                'wound_size_width' => 'W',
                'wound_size_total' => 'Wound Size Total',
                'wound_location' => 'Wound location',
                'graft_size_requested' => 'Size of Graft Requested',
                
                // ICD-10, CPT, HCPCS codes - Using exact field names from DocuSeal template
                // Updated field names with #1, #2, #3, #4 suffixes
                'icd10_code_1' => 'ICD-10 #1',
                'icd10_code_2' => 'ICD-10 #2',
                'icd10_code_3' => 'ICD-10 #3',
                'icd10_code_4' => 'ICD-10 #4',
                'cpt_code_1' => 'CPT #1',
                'cpt_code_2' => 'CPT #2',
                'cpt_code_3' => 'CPT #3',
                'cpt_code_4' => 'CPT #4',
                'hcpcs_code_1' => 'HCPCS #1',
                'hcpcs_code_2' => 'HCPCS #2',
                'hcpcs_code_3' => 'HCPCS #3',
                'hcpcs_code_4' => 'HCPCS #4'
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
                'distributor_company' => [
                    'source' => 'distributor_company || organization_name || "MSC Wound Care"',
                    'required' => false,
                    'type' => 'string'
                ],
                
                // Patient Information
                'patient_name' => [
                    'source' => 'computed',
                    'computation' => 'patient_first_name + patient_last_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'patient_dob' => [
                    'source' => 'patient_dob',
                    'transform' => 'date:m/d/Y',
                    'required' => true,
                    'type' => 'date'
                ],
                
                // Provider Information (Physician) - from provider profile
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
                'physician_ptan' => [
                    'source' => 'provider.ptan || provider_ptan || physician_ptan || ptan',
                    'required' => false,
                    'type' => 'string'
                ],
                
                // Practice/Facility Information - from facility profile
                'practice_name' => [
                    'source' => 'facility_name || practice_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'practice_npi' => [
                    'source' => 'facility_npi || practice_npi || provider_npi || "TBD"',
                    'required' => false,
                    'type' => 'string'
                ],
                'practice_ptan' => [
                    'source' => 'facility_ptan || practice_ptan || provider_ptan',
                    'required' => false,
                    'type' => 'string'
                ],
                'tax_id' => [
                    'source' => 'facility_tax_id || organization_tax_id || tax_id || "TBD"',
                    'required' => false,
                    'type' => 'string'
                ],
                
                // Office Contact Information - from facility contact
                'office_contact_name' => [
                    'source' => 'facility.contact_name || facility_contact_name || office_contact_name || contact_name || provider_name',
                    'required' => false,
                    'type' => 'string'
                ],
                'office_contact_email' => [
                    'source' => 'facility.email || facility_email || office_contact_email || contact_email || provider_email',
                    'required' => false,
                    'type' => 'email'
                ],
                
                // Insurance Information
                'primary_insurance_name' => [
                    'source' => 'primary_insurance_name || insurance_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'primary_member_id' => [
                    'source' => 'primary_member_id || insurance_member_id',
                    'required' => true,
                    'type' => 'string'
                ],
                'secondary_insurance_name' => [
                    'source' => 'secondary_insurance_name',
                    'required' => false,
                    'type' => 'string'
                ],
                'secondary_member_id' => [
                    'source' => 'secondary_member_id',
                    'required' => false,
                    'type' => 'string'
                ],
                
                // SNF/Nursing Home Status
                'snf_nursing_home_status' => [
                    'source' => 'computed',
                    'computation' => 'place_of_service == "31" || place_of_service == "32" ? "Yes" : "No"',
                    'required' => false,
                    'type' => 'string'
                ],
                'snf_over_100_days' => [
                    'source' => 'computed',
                    'computation' => 'snf_days > 100 ? "Yes" : "No"',
                    'required' => false,
                    'type' => 'string'
                ],
                
                // Post-op Status
                'post_op_status' => [
                    'source' => 'global_period_status',
                    'transform' => 'boolean:yes_no',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'previous_surgery_cpt' => [
                    'source' => 'global_period_cpt',
                    'required' => false,
                    'type' => 'string'
                ],
                'surgery_date' => [
                    'source' => 'global_period_surgery_date',
                    'transform' => 'date:m/d/Y',
                    'required' => false,
                    'type' => 'date'
                ],
                
                // Procedure Information
                'procedure_date' => [
                    'source' => 'expected_service_date || service_date',
                    'transform' => 'date:m/d/Y',
                    'required' => true,
                    'type' => 'date'
                ],
                'wound_size_length' => [
                    'source' => 'wound_size_length',
                    'required' => true,
                    'type' => 'string'
                ],
                'wound_size_width' => [
                    'source' => 'wound_size_width',
                    'required' => true,
                    'type' => 'string'
                ],
                'wound_size_total' => [
                    'source' => 'computed',
                    'computation' => 'wound_size_length * wound_size_width',
                    'transform' => 'number:2',
                    'required' => true,
                    'type' => 'number'
                ],
                'wound_location' => [
                    'source' => 'wound_location',
                    'required' => true,
                    'type' => 'string'
                ],
                'graft_size_requested' => [
                    'source' => 'computed',
                    'computation' => 'selected_products[0].size || product_size || graft_size || selected_product_size || wound_size_total',
                    'required' => true,
                    'type' => 'string'
                ],
                
                // Diagnosis and Procedure Codes - Split arrays into individual fields
                'icd10_code_1' => [
                    'source' => 'computed',
                    'computation' => 'icd10_codes[0] || primary_diagnosis_code',
                    'required' => true,
                    'type' => 'string'
                ],
                'icd10_code_2' => [
                    'source' => 'computed',
                    'computation' => 'icd10_codes[1] || secondary_diagnosis_code',
                    'required' => false,
                    'type' => 'string'
                ],
                'icd10_code_3' => [
                    'source' => 'computed',
                    'computation' => 'icd10_codes[2]',
                    'required' => false,
                    'type' => 'string'
                ],
                'icd10_code_4' => [
                    'source' => 'computed',
                    'computation' => 'icd10_codes[3]',
                    'required' => false,
                    'type' => 'string'
                ],
                'cpt_code_1' => [
                    'source' => 'computed',
                    'computation' => 'application_cpt_codes[0] || cpt_codes[0] || primary_cpt_code || "15271"',
                    'required' => true,
                    'type' => 'string'
                ],
                'cpt_code_2' => [
                    'source' => 'computed',
                    'computation' => 'application_cpt_codes[1] || cpt_codes[1] || "15272"',
                    'required' => false,
                    'type' => 'string'
                ],
                'cpt_code_3' => [
                    'source' => 'computed',
                    'computation' => 'application_cpt_codes[2] || cpt_codes[2]',
                    'required' => false,
                    'type' => 'string'
                ],
                'cpt_code_4' => [
                    'source' => 'computed',
                    'computation' => 'application_cpt_codes[3] || cpt_codes[3]',
                    'required' => false,
                    'type' => 'string'
                ],
                'hcpcs_code_1' => [
                    'source' => 'computed',
                    'computation' => 'hcpcs_codes[0] || product_code || selected_product_codes[0]',
                    'required' => false,
                    'type' => 'string'
                ],
                'hcpcs_code_2' => [
                    'source' => 'computed',
                    'computation' => 'hcpcs_codes[1]',
                    'required' => false,
                    'type' => 'string'
                ],
                'hcpcs_code_3' => [
                    'source' => 'computed',
                    'computation' => 'hcpcs_codes[2]',
                    'required' => false,
                    'type' => 'string'
                ],
                'hcpcs_code_4' => [
                    'source' => 'computed',
                    'computation' => 'hcpcs_codes[3]',
                    'required' => false,
                    'type' => 'string'
                ],
                
                // Distributor/Company
                'distributor_company' => [
                    'source' => 'distributor_company || sales_rep_company || organization_name',
                    'required' => false,
                    'type' => 'string'
                ],
                
                // Document Requirements
                'insurance_card_attached' => [
                    'source' => 'computed',
                    'computation' => 'insurance_card_front || insurance_card_back ? Yes : No',
                    'transform' => 'boolean:yes_no',
                    'required' => false,
                    'type' => 'boolean'
                ],

                // Patient Gender - Split into separate checkbox fields
                'patient_gender_male' => [
                    'source' => 'computed',
                    'computation' => 'patient_gender == male',
                    'transform' => 'boolean:checkbox',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'patient_gender_female' => [
                    'source' => 'computed', 
                    'computation' => 'patient_gender == female',
                    'transform' => 'boolean:checkbox',
                    'required' => false,
                    'type' => 'boolean'
                ],
                
                // Place of Service - Individual checkboxes
                'place_of_service_office' => [
                    'source' => 'computed',
                    'computation' => 'place_of_service == "11" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'place_of_service_home' => [
                    'source' => 'computed',
                    'computation' => 'place_of_service == "12" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'place_of_service_assisted' => [
                    'source' => 'computed',
                    'computation' => 'place_of_service == "13" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'place_of_service_snf' => [
                    'source' => 'computed',
                    'computation' => 'place_of_service == "31" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'place_of_service_long_term' => [
                    'source' => 'computed',
                    'computation' => 'place_of_service == "32" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'place_of_service_other' => [
                    'source' => 'computed',
                    'computation' => 'place_of_service != "11" && place_of_service != "12" && place_of_service != "13" && place_of_service != "31" && place_of_service != "32" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
            ]
        ],
        

        
        'BioWerX' => [
            'id' => 5,
            'name' => 'BioWerX',
            'signature_required' => true,
            'has_order_form' => false,
            'fields' => [
                // BioWerX specific field mappings
            ]
        ],
        

        
        'Extremity Care' => [
            'id' => 7,
            'name' => 'Extremity Care',
            'signature_required' => true,
            'has_order_form' => true,
            'fields' => [
                // Extremity Care specific field mappings
            ]
        ],
        
        'SKYE Biologics' => [
            'id' => 8,
            'name' => 'SKYE Biologics',
            'signature_required' => true,
            'has_order_form' => false,
            'fields' => [
                // SKYE Biologics specific field mappings
            ]
        ],
        
        'Total Ancillary' => [
            'id' => 9,
            'name' => 'Total Ancillary',
            'signature_required' => true,
            'has_order_form' => false,
            'fields' => [
                // Total Ancillary specific field mappings
            ]
        ],
        
        // Centurion Therapeutics - moved to config/manufacturers/centurion-therapeutics.php
        
        // AdvancedSolutionIVR - moved to config/manufacturers/advanced-solution-ivr.php
            'id' => 11,
            'name' => 'ADVANCED SOLUTION IVR',
            'signature_required' => true,
            'has_order_form' => true,
            'docuseal_template_id' => '1199885',
            'docuseal_field_names' => [
                // Sales Information
                'sales_rep' => 'Sales Rep',
                'date_signed' => 'Date Signed',
                
                // Facility Information
                'facility_name' => 'Facility Name',
                'facility_address' => 'Facility Address',
                'facility_contact_name' => 'Factility Contact Name', // Note: typo in original
                'facility_phone' => 'Facility Phone Number',
                'facility_fax' => 'Facility Fax Number',
                'facility_npi' => 'Facility NPI',
                'facility_tin' => 'Facility TIN',
                'facility_ptan' => 'Facility PTAN',
                'mac' => 'MAC',
                
                // Place of Service checkboxes
                'pos_office' => 'Office',
                'pos_outpatient_hospital' => 'Outpatient Hospital',
                'pos_ambulatory_surgical_center' => 'Ambulatory Surgical Center',
                'pos_other' => 'Other',
                'pos_other_text' => 'POS Other',
                
                // Physician Information
                'physician_name' => 'Physician Name',
                'physician_address' => 'Physician Address',
                'physician_phone' => 'Physician Phone',
                'physician_fax' => 'Physician Fax',
                'physician_npi' => 'Physician NPI',
                'physician_tin' => 'Physician TIN',
                
                // Patient Information
                'patient_name' => 'Patient Name',
                'patient_address' => 'Patient Address',
                'patient_dob' => 'Patient DOB',
                'patient_phone' => 'Patient Phone',
                'contact_patient_yes' => 'Ok to Contact Patient Yes',
                'contact_patient_no' => 'OK to Contact Patient No',
                
                // Primary Insurance
                'primary_insurance' => 'Primary Insurance',
                'primary_subscriber_name' => 'Primary Subscriber Name',
                'primary_policy_number' => 'Policy Number',
                'primary_subscriber_dob' => 'Subscriber DOB',
                'primary_insurance_phone' => 'Primary Insurance Phone Number',
                'primary_hmo' => 'Primary Insurance HMO',
                'primary_ppo' => 'Primary Insurance PPO',
                'primary_other' => 'Primary Insurance Other',
                'primary_type_other' => 'Type of Plan Other',
                'primary_in_network_yes' => 'Does Provider Participate with Network Yes',
                'primary_in_network_no' => 'Does Provider Participate with Network No',
                'primary_in_network_not_sure' => 'In network Not Sure',
                
                // Secondary Insurance
                'secondary_insurance' => 'Secondary Insurance',
                'secondary_subscriber_name' => 'Subscriber Name 2nd',
                'secondary_policy_number' => 'Policy Number 2nd',
                'secondary_subscriber_dob' => 'Subscriber DOB 2nd',
                'secondary_insurance_phone' => 'Secondary Insurance Phone Number',
                'secondary_hmo' => 'Secondary Insurance HMO',
                'secondary_ppo' => 'Secondary Insurance PPO',
                'secondary_other' => 'Secondary Insurance Other',
                'secondary_type_other' => 'Type of Plan Other 2nd',
                'secondary_in_network_yes' => 'Does Provider Participate with Network Yes 2nd',
                'secondary_in_network_no' => 'Does Provider Participate with Network No 2nd',
                'secondary_in_network_not_sure' => 'In network Not Sure 2nd',
                
                // Wound Information
                'wound_diabetic_foot_ulcer' => 'Diabetic Foot Ulcer',
                'wound_venous_leg_ulcer' => 'Venous Leg Ulcer',
                'wound_pressure_ulcer' => 'Pressure Ulcer',
                'wound_traumatic_burns' => 'Traumatic Burns',
                'wound_radiation_burns' => 'Radiation Burns',
                'wound_necrotizing_fasciitis' => 'Necrotizing Facilitis', // Note: typo in original
                'wound_dehisced_surgical' => 'Dehisced Surigcal Wound', // Note: typo in original
                'wound_other' => 'Other Wound',
                'wound_type_other' => 'Type of Wound Other',
                'wound_size' => 'Wound Size',
                
                // Product Information
                'product_completeaa' => 'CompleteAA',
                'product_membrane_wrap_hydro' => 'Membrane Wrap Hydro',
                'product_membrane_wrap' => 'Membrane Wrap',
                'product_woundplus' => 'WoundPlus',
                'product_completeft' => 'CompleteFT',
                'product_other' => 'Other Product',
                'product_other_text' => 'Product Other',
                
                // Clinical Information
                'application_cpt' => 'Application CPT9S)', // Note: typo in original
                'procedure_date' => 'Date of Procedure',
                'icd10_codes' => 'ICD-1o Diagnosis Code(s)', // Note: typo in original
                'cpt_code' => 'CPT Code',
                'prior_auth' => 'Prior Auth',
                
                // SNF/Global Period Status
                'patient_snf_yes' => 'Patient in SNF Yes',
                'patient_snf_no' => 'Patient in SNF No',
                'patient_global_yes' => 'Patient Under Global Yes',
                'patient_global_no' => 'Patient Under Global No',
                
                // Specialty Site
                'specialty_site_name' => 'Specialty Site Name',
                
                // Contact Info
                'name' => 'Name',
                'email' => 'Email',
                'phone' => 'Phone'
            ],
            'fields' => [
                // Contact Information
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
                
                // Sales Information
                'sales_rep' => [
                    'source' => 'sales_rep_name || sales_rep || distributor_name',
                    'required' => false,
                    'type' => 'string'
                ],
                'date_signed' => [
                    'source' => 'signature_date || date_signed || created_at',
                    'transform' => 'date:m/d/Y',
                    'required' => false,
                    'type' => 'date'
                ],
                
                // Facility Information
                'facility_name' => [
                    'source' => 'facility_name || practice_name || location_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'facility_address' => [
                    'source' => 'facility_address || facility.address || practice_address',
                    'required' => true,
                    'type' => 'string'
                ],
                'facility_contact_name' => [
                    'source' => 'facility_contact_name || facility.contact_name || office_manager',
                    'required' => false,
                    'type' => 'string'
                ],
                'facility_phone' => [
                    'source' => 'facility_phone || facility.phone || practice_phone',
                    'transform' => 'phone:US',
                    'required' => true,
                    'type' => 'phone'
                ],
                'facility_fax' => [
                    'source' => 'facility_fax || facility.fax || practice_fax',
                    'transform' => 'phone:US',
                    'required' => false,
                    'type' => 'phone'
                ],
                'facility_npi' => [
                    'source' => 'facility_npi || practice_npi || location_npi',
                    'required' => true,
                    'type' => 'string'
                ],
                'facility_tin' => [
                    'source' => 'facility_tax_id || facility_tin || practice_tax_id || tax_id',
                    'required' => false,
                    'type' => 'string'
                ],
                'facility_ptan' => [
                    'source' => 'facility_ptan || practice_ptan || ptan',
                    'required' => false,
                    'type' => 'string'
                ],
                'mac' => [
                    'source' => 'medicare_admin_contractor || mac || medicare_contractor',
                    'required' => false,
                    'type' => 'string'
                ],
                
                // Place of Service checkboxes
                'pos_office' => [
                    'source' => 'computed',
                    'computation' => 'place_of_service == "11" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'pos_outpatient_hospital' => [
                    'source' => 'computed',
                    'computation' => 'place_of_service == "19" || place_of_service == "22" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'pos_ambulatory_surgical_center' => [
                    'source' => 'computed',
                    'computation' => 'place_of_service == "24" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'pos_other' => [
                    'source' => 'computed',
                    'computation' => 'place_of_service != "11" && place_of_service != "19" && place_of_service != "22" && place_of_service != "24" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'pos_other_text' => [
                    'source' => 'place_of_service_other || pos_other_description',
                    'required' => false,
                    'type' => 'string'
                ],
                
                // Physician Information
                'physician_name' => [
                    'source' => 'provider_name || physician_name || doctor_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'physician_address' => [
                    'source' => 'provider_address || physician_address || doctor_address',
                    'required' => false,
                    'type' => 'string'
                ],
                'physician_phone' => [
                    'source' => 'provider_phone || physician_phone || doctor_phone',
                    'transform' => 'phone:US',
                    'required' => false,
                    'type' => 'phone'
                ],
                'physician_fax' => [
                    'source' => 'provider_fax || physician_fax || doctor_fax',
                    'transform' => 'phone:US',
                    'required' => false,
                    'type' => 'phone'
                ],
                'physician_npi' => [
                    'source' => 'provider_npi || physician_npi || doctor_npi',
                    'required' => true,
                    'type' => 'string'
                ],
                'physician_tin' => [
                    'source' => 'provider_tin || physician_tin || doctor_tax_id',
                    'required' => false,
                    'type' => 'string'
                ],
                
                // Patient Information
                'patient_name' => [
                    'source' => 'computed',
                    'computation' => 'patient_first_name + " " + patient_last_name || patient_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'patient_address' => [
                    'source' => 'patient_address || patient.address',
                    'required' => false,
                    'type' => 'string'
                ],
                'patient_dob' => [
                    'source' => 'patient_dob || patient_date_of_birth',
                    'transform' => 'date:m/d/Y',
                    'required' => true,
                    'type' => 'date'
                ],
                'patient_phone' => [
                    'source' => 'patient_phone || patient.phone',
                    'transform' => 'phone:US',
                    'required' => false,
                    'type' => 'phone'
                ],
                'contact_patient_yes' => [
                    'source' => 'computed',
                    'computation' => 'ok_to_contact_patient == true || ok_to_contact == "yes" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'contact_patient_no' => [
                    'source' => 'computed',
                    'computation' => 'ok_to_contact_patient == false || ok_to_contact == "no" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                
                // Primary Insurance
                'primary_insurance' => [
                    'source' => 'primary_insurance_name || insurance_name || primary_insurance',
                    'required' => true,
                    'type' => 'string'
                ],
                'primary_subscriber_name' => [
                    'source' => 'primary_subscriber_name || primary_insurance_subscriber || patient_name',
                    'required' => false,
                    'type' => 'string'
                ],
                'primary_policy_number' => [
                    'source' => 'primary_member_id || primary_policy_number || insurance_member_id',
                    'required' => true,
                    'type' => 'string'
                ],
                'primary_subscriber_dob' => [
                    'source' => 'primary_subscriber_dob || patient_dob',
                    'transform' => 'date:m/d/Y',
                    'required' => false,
                    'type' => 'date'
                ],
                'primary_insurance_phone' => [
                    'source' => 'primary_insurance_phone || insurance_phone',
                    'transform' => 'phone:US',
                    'required' => false,
                    'type' => 'phone'
                ],
                'primary_hmo' => [
                    'source' => 'computed',
                    'computation' => 'primary_insurance_type == "HMO" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'primary_ppo' => [
                    'source' => 'computed',
                    'computation' => 'primary_insurance_type == "PPO" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'primary_other' => [
                    'source' => 'computed',
                    'computation' => 'primary_insurance_type != "HMO" && primary_insurance_type != "PPO" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'primary_type_other' => [
                    'source' => 'primary_insurance_type_other || primary_plan_type_other',
                    'required' => false,
                    'type' => 'string'
                ],
                'primary_in_network_yes' => [
                    'source' => 'computed',
                    'computation' => 'primary_in_network == "yes" || primary_in_network == true ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'primary_in_network_no' => [
                    'source' => 'computed',
                    'computation' => 'primary_in_network == "no" || primary_in_network == false ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'primary_in_network_not_sure' => [
                    'source' => 'computed',
                    'computation' => 'primary_in_network == "not_sure" || primary_in_network == null ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                
                // Secondary Insurance
                'secondary_insurance' => [
                    'source' => 'secondary_insurance_name || secondary_insurance',
                    'required' => false,
                    'type' => 'string'
                ],
                'secondary_subscriber_name' => [
                    'source' => 'secondary_subscriber_name || secondary_insurance_subscriber',
                    'required' => false,
                    'type' => 'string'
                ],
                'secondary_policy_number' => [
                    'source' => 'secondary_member_id || secondary_policy_number',
                    'required' => false,
                    'type' => 'string'
                ],
                'secondary_subscriber_dob' => [
                    'source' => 'secondary_subscriber_dob',
                    'transform' => 'date:m/d/Y',
                    'required' => false,
                    'type' => 'date'
                ],
                'secondary_insurance_phone' => [
                    'source' => 'secondary_insurance_phone',
                    'transform' => 'phone:US',
                    'required' => false,
                    'type' => 'phone'
                ],
                'secondary_hmo' => [
                    'source' => 'computed',
                    'computation' => 'secondary_insurance_type == "HMO" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'secondary_ppo' => [
                    'source' => 'computed',
                    'computation' => 'secondary_insurance_type == "PPO" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'secondary_other' => [
                    'source' => 'computed',
                    'computation' => 'secondary_insurance_type != "HMO" && secondary_insurance_type != "PPO" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'secondary_type_other' => [
                    'source' => 'secondary_insurance_type_other || secondary_plan_type_other',
                    'required' => false,
                    'type' => 'string'
                ],
                'secondary_in_network_yes' => [
                    'source' => 'computed',
                    'computation' => 'secondary_in_network == "yes" || secondary_in_network == true ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'secondary_in_network_no' => [
                    'source' => 'computed',
                    'computation' => 'secondary_in_network == "no" || secondary_in_network == false ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'secondary_in_network_not_sure' => [
                    'source' => 'computed',
                    'computation' => 'secondary_in_network == "not_sure" || secondary_in_network == null ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                
                // Wound Information checkboxes
                'wound_diabetic_foot_ulcer' => [
                    'source' => 'computed',
                    'computation' => 'wound_types.includes("diabetic_foot_ulcer") || wound_type == "DFU" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'wound_venous_leg_ulcer' => [
                    'source' => 'computed',
                    'computation' => 'wound_types.includes("venous_leg_ulcer") || wound_type == "VLU" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'wound_pressure_ulcer' => [
                    'source' => 'computed',
                    'computation' => 'wound_types.includes("pressure_ulcer") || wound_type == "PU" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'wound_traumatic_burns' => [
                    'source' => 'computed',
                    'computation' => 'wound_types.includes("traumatic_burns") ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'wound_radiation_burns' => [
                    'source' => 'computed',
                    'computation' => 'wound_types.includes("radiation_burns") ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'wound_necrotizing_fasciitis' => [
                    'source' => 'computed',
                    'computation' => 'wound_types.includes("necrotizing_fasciitis") ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'wound_dehisced_surgical' => [
                    'source' => 'computed',
                    'computation' => 'wound_types.includes("dehisced_surgical") ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'wound_other' => [
                    'source' => 'computed',
                    'computation' => 'wound_types.includes("other") || wound_type_other ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'wound_type_other' => [
                    'source' => 'wound_type_other || wound_other_description',
                    'required' => false,
                    'type' => 'string'
                ],
                'wound_size' => [
                    'source' => 'computed',
                    'computation' => 'wound_size_length + "x" + wound_size_width + " cm" || wound_size',
                    'required' => true,
                    'type' => 'string'
                ],
                
                // Product Information checkboxes
                'product_completeaa' => [
                    'source' => 'computed',
                    'computation' => 'selected_products.includes("CompleteAA") || product_name == "CompleteAA" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'product_membrane_wrap_hydro' => [
                    'source' => 'computed',
                    'computation' => 'selected_products.includes("Membrane Wrap Hydro") ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'product_membrane_wrap' => [
                    'source' => 'computed',
                    'computation' => 'selected_products.includes("Membrane Wrap") ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'product_woundplus' => [
                    'source' => 'computed',
                    'computation' => 'selected_products.includes("WoundPlus") ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'product_completeft' => [
                    'source' => 'computed',
                    'computation' => 'selected_products.includes("CompleteFT") ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'product_other' => [
                    'source' => 'computed',
                    'computation' => 'selected_products.includes("other") || product_other ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'product_other_text' => [
                    'source' => 'product_other || product_other_name',
                    'required' => false,
                    'type' => 'string'
                ],
                
                // Clinical Information
                'application_cpt' => [
                    'source' => 'application_cpt_codes || cpt_codes || primary_cpt',
                    'required' => false,
                    'type' => 'string'
                ],
                'procedure_date' => [
                    'source' => 'expected_service_date || procedure_date || service_date',
                    'transform' => 'date:m/d/Y',
                    'required' => true,
                    'type' => 'date'
                ],
                'icd10_codes' => [
                    'source' => 'computed',
                    'computation' => 'icd10_codes.join(", ") || diagnosis_codes',
                    'required' => true,
                    'type' => 'string'
                ],
                'cpt_code' => [
                    'source' => 'primary_cpt_code || cpt_code || "15271"',
                    'required' => true,
                    'type' => 'string'
                ],
                'prior_auth' => [
                    'source' => 'prior_authorization_number || auth_number || prior_auth',
                    'required' => false,
                    'type' => 'string'
                ],
                
                // SNF/Global Period Status
                'patient_snf_yes' => [
                    'source' => 'computed',
                    'computation' => 'patient_in_snf == true || place_of_service == "31" || place_of_service == "32" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'patient_snf_no' => [
                    'source' => 'computed',
                    'computation' => 'patient_in_snf == false || (place_of_service != "31" && place_of_service != "32") ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'patient_global_yes' => [
                    'source' => 'computed',
                    'computation' => 'global_period_status == true || in_global_period == "yes" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'patient_global_no' => [
                    'source' => 'computed',
                    'computation' => 'global_period_status == false || in_global_period == "no" ? true : false',
                    'required' => false,
                    'type' => 'boolean'
                ],
                
                // Specialty Site
                'specialty_site_name' => [
                    'source' => 'specialty_site || treatment_site || specialty_location',
                    'required' => false,
                    'type' => 'string'
                ]
            ]
        ],
        
        'AdvancedSolutionOrderForm' => [
            'id' => 12,
            'name' => 'ADVANCED SOLUTION ORDER FORM',
            'signature_required' => false,
            'has_order_form' => false,
            'docuseal_template_id' => '1299488',
            'docuseal_field_names' => [
                // Contact Information
                'name' => 'Name',
                'email' => 'Email',
                'phone' => 'Phone',
                
                // Shipping Information
                'facility_name' => 'Facility Name',
                'shipping_contact_name' => 'Shipping Contact Name',
                'shipping_address' => 'Shipping Address',
                'phone_number' => 'Phone Number',
                'fax_number' => 'Fax Number',
                'email_address' => 'Email Address',
                'date_of_case' => 'Date of Case',
                'product_arrival_date_time' => 'Product Arrival Date  Time', // Note: double space
                
                // Billing Information
                'billing_contact_name' => 'Billing Contact Name',
                
                // Order Details - Row 1
                'product_code_1' => 'Product CodeRow1',
                'manufacturer_1' => 'Manufacturer1',
                'cost_per_unit_1' => 'Cost Per UnitRow1',
                'quantity_1' => 'QuantityRow1',
                'total_cost_1' => 'Total CostRow1',
                
                // Order Details - Row 2
                'product_code_2' => 'Product CodeRow2',
                'manufacturer_2' => 'Manufacturer2',
                'cost_per_unit_2' => 'Cost Per UnitRow2',
                'quantity_2' => 'QuantityRow2',
                'total_cost_2' => 'Total CostRow2',
                
                // Order Details - Row 3
                'product_code_3' => 'Product CodeRow3',
                'manufacturer_3' => 'Manufacturer3',
                'cost_per_unit_3' => 'Cost Per UnitRow3',
                'quantity_3' => 'QuantityRow3',
                'total_cost_3' => 'Total CostRow3',
                
                // Order Details - Row 4
                'product_code_4' => 'Product CodeRow4',
                'manufacturer_4' => 'Manufacturer4',
                'cost_per_unit_4' => 'Cost Per UnitRow4',
                'quantity_4' => 'QuantityRow4',
                'total_cost_4' => 'Total CostRow4',
                
                // Order Details - Row 5
                'product_code_5' => 'Product CodeRow5',
                'manufacturer_5' => 'Manufacturer5',
                'cost_per_unit_5' => 'Cost Per UnitRow5',
                'quantity_5' => 'QuantityRow5',
                'total_cost_5' => 'Total CostRow5',
                
                // Order Details - Row 6
                'product_code_6' => 'Product CodeRow6',
                'manufacturer_6' => 'Manufacturer6',
                'cost_per_unit_6' => 'Cost Per UnitRow6',
                'quantity_6' => 'QuantityRow6',
                'total_cost_6' => 'Total CostRow6',
                
                // Purchase Order
                'purchase_order_number' => 'Purchase Order Number'
            ],
            'fields' => [
                // Contact Information
                'name' => [
                    'source' => 'contact_name || billing_contact_name || order_contact_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'email' => [
                    'source' => 'contact_email || billing_email || order_email',
                    'required' => true,
                    'type' => 'email'
                ],
                'phone' => [
                    'source' => 'contact_phone || billing_phone || order_phone',
                    'transform' => 'phone:US',
                    'required' => true,
                    'type' => 'phone'
                ],
                
                // Shipping Information
                'facility_name' => [
                    'source' => 'facility_name || shipping_facility || organization_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'shipping_contact_name' => [
                    'source' => 'shipping_contact || shipping_contact_name || receiver_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'shipping_address' => [
                    'source' => 'shipping_address || delivery_address || facility_address',
                    'required' => true,
                    'type' => 'string'
                ],
                'phone_number' => [
                    'source' => 'shipping_phone || facility_phone || contact_phone',
                    'transform' => 'phone:US',
                    'required' => true,
                    'type' => 'phone'
                ],
                'fax_number' => [
                    'source' => 'shipping_fax || facility_fax || fax',
                    'transform' => 'phone:US',
                    'required' => false,
                    'type' => 'phone'
                ],
                'email_address' => [
                    'source' => 'shipping_email || facility_email || contact_email',
                    'required' => false,
                    'type' => 'email'
                ],
                'date_of_case' => [
                    'source' => 'procedure_date || case_date || surgery_date',
                    'transform' => 'date:m/d/Y',
                    'required' => true,
                    'type' => 'date'
                ],
                'product_arrival_date_time' => [
                    'source' => 'requested_delivery || arrival_datetime || delivery_date',
                    'transform' => 'datetime:m/d/Y h:i A',
                    'required' => true,
                    'type' => 'string'
                ],
                
                // Billing Information
                'billing_contact_name' => [
                    'source' => 'billing_contact || accounts_payable_contact || billing_contact_name',
                    'required' => false,
                    'type' => 'string'
                ],
                
                // Order Details - Row 1
                'product_code_1' => [
                    'source' => 'computed',
                    'computation' => 'order_items[0].product_code || order_items[0].code || products[0].code',
                    'required' => false,
                    'type' => 'string'
                ],
                'manufacturer_1' => [
                    'source' => 'computed',
                    'computation' => 'order_items[0].manufacturer || order_items[0].brand || products[0].manufacturer',
                    'required' => false,
                    'type' => 'string'
                ],
                'cost_per_unit_1' => [
                    'source' => 'computed',
                    'computation' => 'order_items[0].unit_cost || order_items[0].price || products[0].price',
                    'transform' => 'currency',
                    'required' => false,
                    'type' => 'number'
                ],
                'quantity_1' => [
                    'source' => 'computed',
                    'computation' => 'order_items[0].quantity || order_items[0].qty || products[0].qty || 0',
                    'required' => false,
                    'type' => 'number'
                ],
                'total_cost_1' => [
                    'source' => 'computed',
                    'computation' => '(order_items[0].unit_cost || 0) * (order_items[0].quantity || 0)',
                    'transform' => 'currency',
                    'required' => false,
                    'type' => 'number'
                ],
                
                // Order Details - Row 2
                'product_code_2' => [
                    'source' => 'computed',
                    'computation' => 'order_items[1].product_code || order_items[1].code || products[1].code',
                    'required' => false,
                    'type' => 'string'
                ],
                'manufacturer_2' => [
                    'source' => 'computed',
                    'computation' => 'order_items[1].manufacturer || order_items[1].brand || products[1].manufacturer',
                    'required' => false,
                    'type' => 'string'
                ],
                'cost_per_unit_2' => [
                    'source' => 'computed',
                    'computation' => 'order_items[1].unit_cost || order_items[1].price || products[1].price',
                    'transform' => 'currency',
                    'required' => false,
                    'type' => 'number'
                ],
                'quantity_2' => [
                    'source' => 'computed',
                    'computation' => 'order_items[1].quantity || order_items[1].qty || products[1].qty || 0',
                    'required' => false,
                    'type' => 'number'
                ],
                'total_cost_2' => [
                    'source' => 'computed',
                    'computation' => '(order_items[1].unit_cost || 0) * (order_items[1].quantity || 0)',
                    'transform' => 'currency',
                    'required' => false,
                    'type' => 'number'
                ],
                
                // Order Details - Row 3
                'product_code_3' => [
                    'source' => 'computed',
                    'computation' => 'order_items[2].product_code || order_items[2].code || products[2].code',
                    'required' => false,
                    'type' => 'string'
                ],
                'manufacturer_3' => [
                    'source' => 'computed',
                    'computation' => 'order_items[2].manufacturer || order_items[2].brand || products[2].manufacturer',
                    'required' => false,
                    'type' => 'string'
                ],
                'cost_per_unit_3' => [
                    'source' => 'computed',
                    'computation' => 'order_items[2].unit_cost || order_items[2].price || products[2].price',
                    'transform' => 'currency',
                    'required' => false,
                    'type' => 'number'
                ],
                'quantity_3' => [
                    'source' => 'computed',
                    'computation' => 'order_items[2].quantity || order_items[2].qty || products[2].qty || 0',
                    'required' => false,
                    'type' => 'number'
                ],
                'total_cost_3' => [
                    'source' => 'computed',
                    'computation' => '(order_items[2].unit_cost || 0) * (order_items[2].quantity || 0)',
                    'transform' => 'currency',
                    'required' => false,
                    'type' => 'number'
                ],
                
                // Order Details - Row 4
                'product_code_4' => [
                    'source' => 'computed',
                    'computation' => 'order_items[3].product_code || order_items[3].code || products[3].code',
                    'required' => false,
                    'type' => 'string'
                ],
                'manufacturer_4' => [
                    'source' => 'computed',
                    'computation' => 'order_items[3].manufacturer || order_items[3].brand || products[3].manufacturer',
                    'required' => false,
                    'type' => 'string'
                ],
                'cost_per_unit_4' => [
                    'source' => 'computed',
                    'computation' => 'order_items[3].unit_cost || order_items[3].price || products[3].price',
                    'transform' => 'currency',
                    'required' => false,
                    'type' => 'number'
                ],
                'quantity_4' => [
                    'source' => 'computed',
                    'computation' => 'order_items[3].quantity || order_items[3].qty || products[3].qty || 0',
                    'required' => false,
                    'type' => 'number'
                ],
                'total_cost_4' => [
                    'source' => 'computed',
                    'computation' => '(order_items[3].unit_cost || 0) * (order_items[3].quantity || 0)',
                    'transform' => 'currency',
                    'required' => false,
                    'type' => 'number'
                ],
                
                // Order Details - Row 5
                'product_code_5' => [
                    'source' => 'computed',
                    'computation' => 'order_items[4].product_code || order_items[4].code || products[4].code',
                    'required' => false,
                    'type' => 'string'
                ],
                'manufacturer_5' => [
                    'source' => 'computed',
                    'computation' => 'order_items[4].manufacturer || order_items[4].brand || products[4].manufacturer',
                    'required' => false,
                    'type' => 'string'
                ],
                'cost_per_unit_5' => [
                    'source' => 'computed',
                    'computation' => 'order_items[4].unit_cost || order_items[4].price || products[4].price',
                    'transform' => 'currency',
                    'required' => false,
                    'type' => 'number'
                ],
                'quantity_5' => [
                    'source' => 'computed',
                    'computation' => 'order_items[4].quantity || order_items[4].qty || products[4].qty || 0',
                    'required' => false,
                    'type' => 'number'
                ],
                'total_cost_5' => [
                    'source' => 'computed',
                    'computation' => '(order_items[4].unit_cost || 0) * (order_items[4].quantity || 0)',
                    'transform' => 'currency',
                    'required' => false,
                    'type' => 'number'
                ],
                
                // Order Details - Row 6
                'product_code_6' => [
                    'source' => 'computed',
                    'computation' => 'order_items[5].product_code || order_items[5].code || products[5].code',
                    'required' => false,
                    'type' => 'string'
                ],
                'manufacturer_6' => [
                    'source' => 'computed',
                    'computation' => 'order_items[5].manufacturer || order_items[5].brand || products[5].manufacturer',
                    'required' => false,
                    'type' => 'string'
                ],
                'cost_per_unit_6' => [
                    'source' => 'computed',
                    'computation' => 'order_items[5].unit_cost || order_items[5].price || products[5].price',
                    'transform' => 'currency',
                    'required' => false,
                    'type' => 'number'
                ],
                'quantity_6' => [
                    'source' => 'computed',
                    'computation' => 'order_items[5].quantity || order_items[5].qty || products[5].qty || 0',
                    'required' => false,
                    'type' => 'number'
                ],
                'total_cost_6' => [
                    'source' => 'computed',
                    'computation' => '(order_items[5].unit_cost || 0) * (order_items[5].quantity || 0)',
                    'transform' => 'currency',
                    'required' => false,
                    'type' => 'number'
                ],
                
                // Purchase Order
                'purchase_order_number' => [
                    'source' => 'purchase_order || po_number || purchase_order_number',
                    'required' => false,
                    'type' => 'string'
                ]
            ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Field Transformers
    |--------------------------------------------------------------------------
    |
    | Define how different field types should be transformed.
    |
    */
    'transformers' => [
        'date' => [
            'm/d/Y' => 'convertToMDY',
            'Y-m-d' => 'convertToISO',
            'd/m/Y' => 'convertToDMY',
        ],
        'phone' => [
            'US' => 'formatUSPhone',
            'E164' => 'formatE164Phone',
        ],
        'boolean' => [
            'yes_no' => 'booleanToYesNo',
            '1_0' => 'booleanToNumeric',
            'true_false' => 'booleanToString',
        ],
        'number' => [
            '0' => 'roundToInteger',
            '2' => 'roundToTwoDecimals',
        ],
        'text' => [
            'upper' => 'toUpperCase',
            'lower' => 'toLowerCase',
            'title' => 'toTitleCase',
        ],
        'tax_id' => [
            'format' => 'formatTaxId',
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Field Aliases
    |--------------------------------------------------------------------------
    |
    | Common field name variations that should map to the same field.
    |
    */
    'field_aliases' => [
        'patient_first_name' => ['first_name', 'fname', 'patient_fname', 'firstName'],
        'patient_last_name' => ['last_name', 'lname', 'patient_lname', 'lastName'],
        'patient_dob' => ['date_of_birth', 'dob', 'birth_date', 'birthDate'],
        'patient_phone' => ['phone', 'phone_number', 'telephone', 'contact_phone'],
        'patient_email' => ['email', 'email_address', 'contact_email'],
        'primary_insurance_name' => ['insurance_name', 'payer_name', 'insurance_company'],
        'primary_member_id' => ['member_id', 'subscriber_id', 'insurance_id'],
        'provider_npi' => ['npi', 'provider_number', 'npi_number'],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Regular expressions for validating field formats.
    |
    */
    'validation_rules' => [
        'phone' => '/^\d{10}$/',
        'zip' => '/^\d{5}(-\d{4})?$/',
        'npi' => '/^\d{10}$/',
        'email' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
        'date' => '/^\d{4}-\d{2}-\d{2}$/',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Fuzzy Matching Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the fuzzy field matching algorithm.
    |
    */
    'fuzzy_matching' => [
        'confidence_threshold' => 0.7,
        'exact_match_boost' => 1.5,
        'semantic_match_boost' => 1.2,
        'fuzzy_match_boost' => 1.0,
        'pattern_match_boost' => 1.1,
        'cache_ttl' => 3600, // 1 hour
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Product to Manufacturer Mapping
    |--------------------------------------------------------------------------
    |
    | Maps product codes to their manufacturers.
    |
    */
    'product_mappings' => [
        'EMP001' => 'ACZ',
        'EMP002' => 'ACZ',
        'SKN001' => 'Advanced Health',
        'SKN002' => 'Advanced Health',
        'Q4250' => 'MEDLIFE SOLUTIONS',
        'CTN001' => 'Centurion Therapeutics',
        'Q4151' => 'Centurion Therapeutics', // AmnioBand
        'Q4128' => 'Centurion Therapeutics', // Allopatch
        'BWX001' => 'BioWerX',
        'BWD001' => 'BioWound Solutions',
        // BioWound Solutions Q-codes
        'Q4161' => 'BioWound Solutions',  // Bio-Connect
        'Q4205' => 'BioWound Solutions',  // Membrane Wrap
        'Q4290' => 'BioWound Solutions',  // Membrane Wrap - Hydro
        'Q4238' => 'BioWound Solutions',  // Derm-Maxx
        'Q4239' => 'BioWound Solutions',  // Amnio-maxx
        'Q4266' => 'BioWound Solutions',  // NeoStim SL
        'Q4267' => 'BioWound Solutions',  // NeoStim DL
        'Q4265' => 'BioWound Solutions',  // NeoStim TL
        'EXT001' => 'Extremity Care',
        'EXT002' => 'Extremity Care',
        'SKY001' => 'SKYE Biologics',
        'TAC001' => 'Total Ancillary',
        // Advanced Solution Products
        'ASL001' => 'AdvancedSolutionIVR', // CompleteAA
        'ASL002' => 'AdvancedSolutionIVR', // Membrane Wrap Hydro
        'ASL003' => 'AdvancedSolutionIVR', // Membrane Wrap
        'ASL004' => 'AdvancedSolutionIVR', // WoundPlus
        'ASL005' => 'AdvancedSolutionIVR', // CompleteFT
        'CompleteAA' => 'AdvancedSolutionIVR',
        'Membrane Wrap Hydro' => 'AdvancedSolutionIVR',
        'Membrane Wrap' => 'AdvancedSolutionIVR',
        'WoundPlus' => 'AdvancedSolutionIVR',
        'CompleteFT' => 'AdvancedSolutionIVR'
    ],
];
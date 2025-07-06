<?php

return [
    'id' => 5,
    'name' => 'MEDLIFE SOLUTIONS',
    'signature_required' => true,
    'has_order_form' => true,
    'supports_insurance_upload_in_ivr' => true, // Allow insurance re-upload in IVR section
    'docuseal_template_id' => '1233913', // IVR template
    'order_form_template_id' => '1234279', // Order form template
    
    // IVR form field mappings - complete mapping from field-mapping.php
    'docuseal_field_names' => [
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
        
        // Place of Service checkboxes - exact field names from Docuseal template
        'place_of_service_office' => 'Office: POS-11',
        'place_of_service_home' => 'Home: POS 12', 
        'place_of_service_assisted' => 'Assisted Living: POS-13',
        'place_of_service_other' => 'Other',
        
        // SNF/Nursing Home questions - exact field names from Docuseal template
        'snf_nursing_home_status' => 'Is the patient currently residing in a Nursing Home OR Skilled Nursing Facility',
        'snf_over_100_days' => 'If yes, has it been over 100 days',
        
        // Post-op period - exact field name from Docuseal template
        'post_op_status' => 'Is this patient currently under a post-op period',
        'previous_surgery_cpt' => 'If yes please list CPT codes of previous surgery', // Fixed field name
        'surgery_date' => 'Surgery Date',
        
        // Procedure Information
        'procedure_date' => 'Procedure Date',
        'wound_size_length' => 'L',
        'wound_size_width' => 'W',
        'wound_size_total' => 'Wound Size Total',
        'wound_location' => 'Wound location',
        'graft_size_requested' => 'Size of Graft Requested',
        
        // ICD-10, CPT, HCPCS codes - Using exact field names from Docuseal template
        'icd10_code_1' => 'ICD-10 #1',
        'icd10_code_2' => 'ICD-10 #2',
        'icd10_code_3' => 'ICD-10 #3',
        'icd10_code_4' => 'ICD-10 #4',
        'cpt_code_1' => 'CPT #1',
        'cpt_code_2' => 'CPT #2',
        'cpt_code_3' => 'CPT #3',
        'cpt_code_4' => 'CPT #4',
        'hcpcs_code_1' => 'HCPCS #1',
        'hcpcs_code_2' => 'HCPCS #2',  // Added missing fields
        'hcpcs_code_3' => 'HCPCS #3',
        'hcpcs_code_4' => 'HCPCS #4',
    ],
    
    // Order form field mappings using exact Docuseal field names
    'order_form_field_names' => [
        // Contact Information
        'name' => 'Name',
        'email' => 'Email', 
        'phone' => 'Phone',
        
        // Shipping Method Checkboxes
        'shipping_2_day' => 'Shipping: 2-Day',
        'shipping_overnight' => 'Shipping: Overnight', 
        'shipping_pick_up' => 'Shipping: Pick up',
        
        // Shipping Information Table
        'company_facility' => 'Company/Facility',
        'contact_name' => 'Contact Name',
        'title' => 'Title',
        'contact_phone' => 'Contact Phone',
        'address' => 'Address',
        'notes' => 'Notes',
        
        // AmnioAMP-MP Product Size Options (quantities)
        'size_2x2_cm' => '2x2 cm',
        'size_2x3_cm' => '2x3cm',
        'size_2x4_cm' => '2x4 cm', 
        'size_4x4_cm' => '4x4 cm',
        'size_4x6_cm' => '4x6 cm',
        'size_4x8_cm' => '4x8 cm',
        
        // Order Totals
        'total_units' => 'TOTAL UNITS',
        
        // Order Information
        'date' => 'Date',
        'sales_rep' => 'Sales Rep',
    ],
    
    // Field configuration and mapping logic
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
            'source' => 'provider.ptan || provider_ptan || physician_ptan || ptan || ""',
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
            'computation' => 'place_of_service == "31" ? "Yes" : "No"',
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
        // Removed HCPCS #2, #3, #4 mappings per user request
        
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
            'source' => 'place_of_service',
            'transform' => 'equals:11',
            'required' => false,
            'type' => 'boolean'
        ],
        'place_of_service_home' => [
            'source' => 'place_of_service',
            'transform' => 'equals:12',
            'required' => false,
            'type' => 'boolean'
        ],
        'place_of_service_assisted' => [
            'source' => 'place_of_service',
            'transform' => 'equals:13',
            'required' => false,
            'type' => 'boolean'
        ],
        'place_of_service_other' => [
            'source' => 'place_of_service',
            'transform' => 'not_in:11,12,13',
            'required' => false,
            'type' => 'boolean'
        ],
        
        // Clinical Attestations - Convert boolean to Yes/No
        'failed_conservative_treatment' => [
            'source' => 'previous_treatments || (failed_conservative_treatment ? "Yes" : "No")',
            'required' => true,
            'type' => 'string'
        ],
        'information_accurate' => [
            'source' => 'information_accurate ? "Yes" : "No"',
            'required' => true,
            'type' => 'string'
        ],
        'medical_necessity_established' => [
            'source' => 'medical_necessity_established ? "Yes" : "No"',
            'required' => true,
            'type' => 'string'
        ],
        'maintain_documentation' => [
            'source' => 'maintain_documentation ? "Yes" : "No"',
            'required' => true,
            'type' => 'string'
        ],
    ],
]; 
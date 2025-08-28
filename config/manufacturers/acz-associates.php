<?php

/**
 * ACZ & Associates Manufacturer Configuration
 *
 * This configuration follows DocuSeal API Field Mapping Rules:
 * - Radio button fields use single string values (e.g., "POS 12", "In-Network", "Yes")
 * - Field names must match DocuSeal template exactly (case-sensitive with spaces)
 * - Yes/No questions use "Yes" or "No" strings, not boolean values
 * - Place of Service uses single radio field with values: "POS 11", "POS 12", etc.
 * - Network Status uses "In-Network" or "Out-of-Network" values
 *
 * @see config/rules/docuseal-api-field-mapping.md for complete rules
 */

return [
    'id' => 1,
    'name' => 'ACZ & ASSOCIATES',
    'signature_required' => true,
    'has_order_form' => true,
    'supports_insurance_upload_in_ivr' => true,
    'docuseal_template_id' => 852440,
    'folder_id' => 75423, // TO BE FILLED with actual DocuSeal template ID
    'order_form_template_id' => 852554, // ACZ & Associates Order Form template

    // IVR form field mappings - EXACT field names from DocuSeal template API
    'docuseal_field_names' => [
        // Product Selection - Single radio field with all product codes
        'product_q_code' => 'Product Q Code',

        // Basic Information
        'representative_name' => 'Representative Name',
        'iso_if_applicable' => 'ISO if applicable',
        'additional_emails' => 'Additional Emails for Notification (requires BAA)',

        // Physician Information
        'physician_name' => 'Physician Name',
        'physician_npi' => 'Physician NPI',
        'physician_specialty' => 'Physician Specialty',
        'physician_tax_id' => 'Physician Tax ID',
        'physician_ptan' => 'Physician PTAN',
        'physician_medicaid' => 'Physician Medicaid #',
        'physician_phone' => 'Physician Phone #',
        'physician_fax' => 'Physician Fax #',
        'physician_organization' => 'Physician Organization',

        // Facility Information
        'facility_npi' => 'Facility NPI',
        'facility_tax_id' => 'Facility Tax ID',
        'facility_name' => 'Facility Name',
        'facility_ptan' => 'Facility PTAN',
        'facility_address' => 'Facility Address',
        'facility_medicaid' => 'Facility Medicaid #',
        'facility_city_state_zip' => 'Facility City, State, Zip',
        'facility_phone' => 'Facility Phone #',
        'facility_contact_name' => 'Facility Contact Name',
        'facility_fax' => 'Facility Fax #',
        'facility_contact_info' => 'Facility Contact Phone # / Facility Contact Email',
        'facility_organization' => 'Facility Organization',

        // Place of Service - Single radio field
        'place_of_service' => 'Place of Service',
        'pos_other_specify' => 'POS Other Specify',

        // Patient Information
        'patient_name' => 'Patient Name',
        'patient_dob' => 'Patient DOB',
        'patient_address' => 'Patient Address',
        'patient_city_state_zip' => 'Patient City, State, Zip',
        'patient_phone' => 'Patient Phone #',
        'patient_email' => 'Patient Email',
        'patient_caregiver_info' => 'Patient Caregiver Info',

        // Insurance Information
        'primary_insurance_name' => 'Primary Insurance Name',
        'secondary_insurance_name' => 'Secondary Insurance Name',
        'primary_policy_number' => 'Primary Policy Number',
        'secondary_policy_number' => 'Secondary Policy Number',
        'primary_payer_phone' => 'Primary Payer Phone #',
        'secondary_payer_phone' => 'Secondary Payer Phone #',

        // Network Status - Radio fields
        'physician_status_primary' => 'Physician Status With Primary',
        'physician_status_secondary' => 'Physician Status With Secondary',

        // Authorization Questions - Radio fields
        'permission_prior_auth' => 'Permission To Initiate And Follow Up On Prior Auth?',
        'patient_in_hospice' => 'Is The Patient Currently in Hospice?',
        'patient_part_a_stay' => 'Is The Patient In A Facility Under Part A Stay?',
        'patient_global_surgery' => 'Is The Patient Under Post-Op Global Surgery Period?',

        // Conditional Surgery Fields
        'surgery_cpts' => 'If Yes, List Surgery CPTs',
        'surgery_date' => 'Surgery Date',

        // Location and Clinical
        'location_of_wound' => 'Location of Wound',
        'icd_10_codes' => 'ICD-10 Codes',
        'total_wound_size' => 'Total Wound Size',
        'medical_history' => 'Medical History',
    ],

        // Order form field mappings - EXACT field names from DocuSeal template API (template 852554)
    'order_form_field_names' => [
        // Order Information
        'order_date' => 'Date of Order',
        'anticipated_application_date' => 'Anticipated Application Date',

        // Contact Information
        'physician_name' => 'Physican Name',
        'account_contact_email' => 'Account Contact E-mail',
        'account_contact_name' => 'Account Contact Name',
        'account_contact_phone' => 'Account Contact #',

        // User Information
        'user_npi' => 'User NPI',
        'user_tax_id' => 'User Tax ID',

        // Product Line Items (up to 5)
        'quantity_line_1' => 'Quantity Line 1',
        'description_line_1' => 'Description Line 1',
        'size_line_1' => 'Size Line 1',
        'unit_price_line_1' => 'Unit Price Line 1',
        'amount_line_1' => 'Amount Line 1',

        'quantity_line_2' => 'Quantity Line 2',
        'description_line_2' => 'Description Line 2',
        'size_line_2' => 'Size Line 2',
        'unit_price_line_2' => 'Unit Price Line 2',
        'amount_line_2' => 'Amount Line 2',

        'quantity_line_3' => 'Quantity Line 3',
        'description_line_3' => 'Description Line 3',
        'size_line_3' => 'Size Line 3',
        'unit_price_line_3' => 'Unit Price Line 3',
        'amount_line_3' => 'Amount Line 3',

        'quantity_line_4' => 'Quantity Line 4',
        'description_line_4' => 'Description Line 4',
        'size_line_4' => 'Size Line 4',
        'unit_price_line_4' => 'Unit Price Line 4',
        'amount_line_4' => 'Amount Line 4',

        'quantity_line_5' => 'Quantity Line 5',
        'description_line_5' => 'Description Line 5',
        'size_line_5' => 'Size Line 5',
        'unit_price_line_5' => 'Unit Price Line 5',
        'amount_line_5' => 'Amount Line 5',

        // Totals
        'sub_total' => 'Sub-Total',
        'discount' => 'Discount',
        'total' => 'Total',

        // Shipping Information
        'check_fedex' => 'Check FedEx',
        'date_to_receive' => 'Date to Recieve',
        'facility_name' => 'Facility or Office Name',
        'ship_to_address' => 'Ship to Address',
        'ship_to_address_2' => 'Ship to Address 2',
        'ship_to_city' => 'Ship to City',
        'ship_to_state' => 'Ship to State',
        'ship_to_zip' => 'Ship to Zip',
        'notes' => 'Notes',

        // Patient Information
        'patient_id' => 'Patient ID',
    ],

    // Field configuration with source mapping and transformations
    'fields' => [
        // Product Selection - Simplified to work with current condition evaluation
        'product_q_code' => [
            'source' => 'computed',
            'computation' => 'selected_products ? selected_products[0].product.q_code : ""',
            'required' => false,
            'type' => 'string' // Will be "Q4316", "Q4205", etc.
        ],

        // Basic Information - Fixed to work with available data
        'representative_name' => [
            'source' => 'computed',
            'computation' => 'sales_rep_name || sales_rep || organization_sales_rep_name || current_user.name || ""',
            'required' => false, // Changed from true to false to avoid blocking
            'type' => 'string'
        ],
        'iso_if_applicable' => [
            'source' => 'iso_number || iso_if_applicable',
            'required' => false,
            'type' => 'string'
        ],
        'additional_emails' => [
            'source' => 'additional_emails || additional_notification_emails',
            'required' => false,
            'type' => 'string'
        ],

        // Physician Information
        'physician_name' => [
            'source' => 'physician_name || provider_name || current_user.name',
            'required' => true,
            'type' => 'string'
        ],
        'physician_npi' => [
            'source' => 'physician_npi || provider_npi',
            'required' => false,
            'type' => 'string'
        ],
        'physician_specialty' => [
            'source' => 'physician_specialty || provider_specialty',
            'required' => false,
            'type' => 'string'
        ],
        'physician_tax_id' => [
            'source' => 'physician_tax_id || provider_tax_id',
            'required' => false,
            'type' => 'string'
        ],
        'physician_ptan' => [
            'source' => 'physician_ptan || provider_ptan',
            'required' => false,
            'type' => 'string'
        ],
        'physician_medicaid' => [
            'source' => 'physician_medicaid || provider_medicaid || physician_medicaid_number',
            'required' => false,
            'type' => 'string'
        ],
        'physician_phone' => [
            'source' => 'physician_phone || provider_phone',
            'required' => false,
            'type' => 'string'
        ],
        'physician_fax' => [
            'source' => 'physician_fax || provider_fax',
            'required' => false,
            'type' => 'string'
        ],
        'physician_organization' => [
            'source' => 'physician_organization || organization_name',
            'required' => false,
            'type' => 'string'
        ],

        // Facility Information
        'facility_npi' => [
            'source' => 'facility_npi',
            'required' => false,
            'type' => 'string'
        ],
        'facility_tax_id' => [
            'source' => 'facility_tax_id || facility_tin',
            'required' => false,
            'type' => 'string'
        ],
        'facility_name' => [
            'source' => 'facility_name',
            'required' => false,
            'type' => 'string'
        ],
        'facility_ptan' => [
            'source' => 'facility_ptan',
            'required' => false,
            'type' => 'string'
        ],
        'facility_address' => [
            'source' => 'facility_address || facility_address_line1',
            'required' => false,
            'type' => 'string'
        ],
        'facility_medicaid' => [
            'source' => 'facility_medicaid || facility_medicaid_number',
            'required' => false,
            'type' => 'string'
        ],
        'facility_city_state_zip' => [
            'source' => 'facility_city_state_zip',
            'required' => false,
            'type' => 'string'
        ],
        'facility_phone' => [
            'source' => 'facility_phone',
            'required' => false,
            'type' => 'string'
        ],
        'facility_contact_name' => [
            'source' => 'facility_contact_name',
            'required' => false,
            'type' => 'string'
        ],
        'facility_fax' => [
            'source' => 'facility_fax',
            'required' => false,
            'type' => 'string'
        ],
        'facility_contact_info' => [
            'source' => 'facility_contact_info',
            'required' => false,
            'type' => 'string'
        ],
        'facility_organization' => [
            'source' => 'facility_organization || organization_name',
            'required' => false,
            'type' => 'string'
        ],

        // Place of Service - Single radio field
        'place_of_service' => [
            'source' => 'place_of_service',
            'required' => true,
            'type' => 'string',
            'transform' => 'prefix:POS ' // Add POS prefix during transformation
        ],
        'pos_other_specify' => [
            'source' => 'pos_other_specify || place_of_service_other',
            'required' => false,
            'type' => 'string'
        ],

        // Patient Information
        'patient_name' => [
            'source' => 'computed',
            'computation' => 'fhir_patient_first_name && fhir_patient_last_name ? fhir_patient_first_name + " " + fhir_patient_last_name : (patient_name || "")',
            'required' => true,
            'type' => 'string'
        ],
        'patient_dob' => [
            'source' => 'fhir_patient_birth_date || patient_dob',
            'required' => false,
            'type' => 'string'
        ],
        'patient_address' => [
            'source' => 'computed',
            'computation' => 'fhir_patient_address_line1 || patient_address || patient_address_line1',
            'required' => false,
            'type' => 'string'
        ],
        'patient_city_state_zip' => [
            'source' => 'computed',
            'computation' => '(fhir_patient_address_city || patient_city) && (fhir_patient_address_state || patient_state) && (fhir_patient_address_postal_code || patient_zip) ? (fhir_patient_address_city || patient_city) + ", " + (fhir_patient_address_state || patient_state) + " " + (fhir_patient_address_postal_code || patient_zip) : (patient_city_state_zip || "")',
            'required' => false,
            'type' => 'string'
        ],
        'patient_phone' => [
            'source' => 'fhir_patient_phone || patient_phone',
            'required' => false,
            'type' => 'string'
        ],
        'patient_email' => [
            'source' => 'fhir_patient_email || patient_email',
            'required' => false,
            'type' => 'string'
        ],
        'patient_caregiver_info' => [
            'source' => 'patient_caregiver_info',
            'required' => false,
            'type' => 'string'
        ],

        // Insurance Information
        'primary_insurance_name' => [
            'source' => 'primary_insurance_name',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_insurance_name' => [
            'source' => 'secondary_insurance_name',
            'required' => false,
            'type' => 'string'
        ],
        'primary_policy_number' => [
            'source' => 'computed',
            'computation' => 'fhir_coverage_subscriber_id || primary_policy_number || primary_member_id || ""',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_policy_number' => [
            'source' => 'secondary_policy_number || secondary_member_id',
            'required' => false,
            'type' => 'string'
        ],
        'primary_payer_phone' => [
            'source' => 'primary_payer_phone || primary_insurance_phone',
            'required' => false,
            'type' => 'string'
        ],
        'secondary_payer_phone' => [
            'source' => 'secondary_payer_phone || secondary_insurance_phone',
            'required' => false,
            'type' => 'string'
        ],

        // Network Status - Radio fields with exact values
        'physician_status_primary' => [
            'source' => 'computed',
            'computation' => 'primary_physician_network_status === "in_network" ? "In-Network" : "Out-of-Network"',
            'required' => true,
            'type' => 'string' // Will be "In-Network" or "Out-of-Network"
        ],
        'physician_status_secondary' => [
            'source' => 'computed',
            'computation' => 'secondary_physician_network_status === "in_network" ? "In-Network" : "Out-of-Network"',
            'required' => true,
            'type' => 'string' // Will be "In-Network" or "Out-of-Network"
        ],

        // Authorization Questions - Radio fields with Yes/No values
        'permission_prior_auth' => [
            'source' => 'computed',
            'computation' => 'prior_auth_permission === true ? "Yes" : "No"',
            'required' => true,
            'type' => 'string' // Will be "Yes" or "No"
        ],
        'patient_in_hospice' => [
            'source' => 'computed',
            'computation' => 'hospice_status === true ? "Yes" : "No"',
            'required' => false,
            'type' => 'string' // Will be "Yes" or "No"
        ],
        'patient_part_a_stay' => [
            'source' => 'computed',
            'computation' => 'part_a_status === true ? "Yes" : "No"',
            'required' => false,
            'type' => 'string' // Will be "Yes" or "No"
        ],
        'patient_global_surgery' => [
            'source' => 'computed',
            'computation' => 'global_period_status === true ? "Yes" : "No"',
            'required' => false,
            'type' => 'string' // Will be "Yes" or "No"
        ],

        // Conditional Surgery Fields
        'surgery_cpts' => [
            'source' => 'surgery_cpts',
            'required' => false,
            'type' => 'string'
        ],
        'surgery_date' => [
            'source' => 'surgery_date',
            'required' => false,
            'type' => 'string'
        ],

        // Location and Clinical - Radio field with exact option values
        'location_of_wound' => [
            'source' => 'computed',
            'computation' => 'wound_location_details || "Legs/Arms/Trunk < 100 SQ CM"',
            'required' => true,
            'type' => 'string' // Will be one of the 4 specific options
        ],
        'icd_10_codes' => [
            'source' => 'computed',
            'computation' => 'primary_diagnosis_code && secondary_diagnosis_code ? primary_diagnosis_code + ", " + secondary_diagnosis_code : (primary_diagnosis_code || secondary_diagnosis_code || diagnosis_code || "")',
            'required' => false,
            'type' => 'string'
        ],
        'total_wound_size' => [
            'source' => 'computed',
            'computation' => 'wound_size_total || calculated_wound_area || total_wound_size || ""',
            'required' => false,
            'type' => 'string'
        ],
        'medical_history' => [
            'source' => 'medical_history || clinical_notes || ""',
            'required' => false,
            'type' => 'string'
        ],

        // Order Form Field Configurations
        'order_date' => [
            'source' => 'computed',
            'computation' => 'order_date || created_at || current_date',
            'required' => false,
            'type' => 'date'
        ],
        'anticipated_application_date' => [
            'source' => 'anticipated_application_date || expected_service_date || ""',
            'required' => false,
            'type' => 'date'
        ],

        // Contact Information
        'account_contact_email' => [
            'source' => 'facility_contact_email || organization_contact_email || contact_email || ""',
            'required' => false,
            'type' => 'email'
        ],
        'account_contact_name' => [
            'source' => 'facility_contact_name || organization_contact_name || contact_name || ""',
            'required' => false,
            'type' => 'string'
        ],
        'account_contact_phone' => [
            'source' => 'facility_contact_phone || organization_contact_phone || contact_phone || ""',
            'required' => true,
            'type' => 'phone'
        ],

        // User Information
        'user_npi' => [
            'source' => 'current_user.npi || ""',
            'required' => false,
            'type' => 'string'
        ],
        'user_tax_id' => [
            'source' => 'current_user.tax_id || ""',
            'required' => false,
            'type' => 'string'
        ],

        // Product Line Items - Line 1
        'quantity_line_1' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[0] ? selected_products[0].quantity || 1 : ""',
            'required' => false,
            'type' => 'number'
        ],
        'description_line_1' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[0] ? selected_products[0].product.name || "" : ""',
            'required' => false,
            'type' => 'string'
        ],
        'size_line_1' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[0] ? selected_products[0].size || "" : ""',
            'required' => false,
            'type' => 'string'
        ],
        'unit_price_line_1' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[0] ? selected_products[0].product.msc_price || "" : ""',
            'required' => false,
            'type' => 'currency'
        ],
        'amount_line_1' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[0] ? (selected_products[0].quantity || 1) * (selected_products[0].product.msc_price || 0) : ""',
            'required' => false,
            'type' => 'currency'
        ],

        // Product Line Items - Line 2
        'quantity_line_2' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[1] ? selected_products[1].quantity || 1 : ""',
            'required' => false,
            'type' => 'number'
        ],
        'description_line_2' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[1] ? selected_products[1].product.name || "" : ""',
            'required' => false,
            'type' => 'string'
        ],
        'size_line_2' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[1] ? selected_products[1].size || "" : ""',
            'required' => false,
            'type' => 'string'
        ],
        'unit_price_line_2' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[1] ? selected_products[1].product.msc_price || "" : ""',
            'required' => false,
            'type' => 'currency'
        ],
        'amount_line_2' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[1] ? (selected_products[1].quantity || 1) * (selected_products[1].product.msc_price || 0) : ""',
            'required' => false,
            'type' => 'currency'
        ],

        // Product Line Items - Line 3
        'quantity_line_3' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[2] ? selected_products[2].quantity || 1 : ""',
            'required' => false,
            'type' => 'number'
        ],
        'description_line_3' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[2] ? selected_products[2].product.name || "" : ""',
            'required' => false,
            'type' => 'string'
        ],
        'size_line_3' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[2] ? selected_products[2].size || "" : ""',
            'required' => false,
            'type' => 'string'
        ],
        'unit_price_line_3' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[2] ? selected_products[2].product.msc_price || "" : ""',
            'required' => false,
            'type' => 'currency'
        ],
        'amount_line_3' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[2] ? (selected_products[2].quantity || 1) * (selected_products[2].product.msc_price || 0) : ""',
            'required' => false,
            'type' => 'currency'
        ],

        // Product Line Items - Line 4
        'quantity_line_4' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[3] ? selected_products[3].quantity || 1 : ""',
            'required' => false,
            'type' => 'number'
        ],
        'description_line_4' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[3] ? selected_products[3].product.name || "" : ""',
            'required' => false,
            'type' => 'string'
        ],
        'size_line_4' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[3] ? selected_products[3].size || "" : ""',
            'required' => false,
            'type' => 'string'
        ],
        'unit_price_line_4' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[3] ? selected_products[3].product.msc_price || "" : ""',
            'required' => false,
            'type' => 'currency'
        ],
        'amount_line_4' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[3] ? (selected_products[3].quantity || 1) * (selected_products[3].product.msc_price || 0) : ""',
            'required' => false,
            'type' => 'currency'
        ],

        // Product Line Items - Line 5
        'quantity_line_5' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[4] ? selected_products[4].quantity || 1 : ""',
            'required' => false,
            'type' => 'number'
        ],
        'description_line_5' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[4] ? selected_products[4].product.name || "" : ""',
            'required' => false,
            'type' => 'string'
        ],
        'size_line_5' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[4] ? selected_products[4].size || "" : ""',
            'required' => false,
            'type' => 'string'
        ],
        'unit_price_line_5' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[4] ? selected_products[4].product.msc_price || "" : ""',
            'required' => false,
            'type' => 'currency'
        ],
        'amount_line_5' => [
            'source' => 'computed',
            'computation' => 'selected_products && selected_products[4] ? (selected_products[4].quantity || 1) * (selected_products[4].product.msc_price || 0) : ""',
            'required' => false,
            'type' => 'currency'
        ],

        // Totals
        'sub_total' => [
            'source' => 'computed',
            'computation' => 'selected_products ? selected_products.reduce((sum, item) => sum + ((item.quantity || 1) * (item.product.msc_price || 0)), 0) : ""',
            'required' => false,
            'type' => 'currency'
        ],
        'discount' => [
            'source' => 'discount_amount || discount_percentage || ""',
            'required' => false,
            'type' => 'currency'
        ],
        'total' => [
            'source' => 'computed',
            'computation' => 'sub_total && discount ? sub_total - discount : sub_total',
            'required' => false,
            'type' => 'currency'
        ],

        // Shipping Information
        'check_fedex' => [
            'source' => 'shipping_method === "fedex" ? "true" : "false"',
            'required' => false,
            'type' => 'checkbox'
        ],
        'date_to_receive' => [
            'source' => 'expected_delivery_date || delivery_date || ""',
            'required' => false,
            'type' => 'date'
        ],
        'ship_to_address' => [
            'source' => 'facility_address || organization_address || shipping_address || ""',
            'required' => false,
            'type' => 'string'
        ],
        'ship_to_address_2' => [
            'source' => 'facility_address_line2 || organization_address_line2 || shipping_address_line2 || ""',
            'required' => false,
            'type' => 'string'
        ],
        'ship_to_city' => [
            'source' => 'facility_city || organization_city || shipping_city || ""',
            'required' => false,
            'type' => 'string'
        ],
        'ship_to_state' => [
            'source' => 'facility_state || organization_state || shipping_state || ""',
            'required' => false,
            'type' => 'string'
        ],
        'ship_to_zip' => [
            'source' => 'facility_zip || organization_zip || shipping_zip || ""',
            'required' => false,
            'type' => 'string'
        ],
        'notes' => [
            'source' => 'order_notes || special_instructions || notes || ""',
            'required' => false,
            'type' => 'string'
        ],

        // Patient Information
        'patient_id' => [
            'source' => 'patient_id || fhir_patient_id || ""',
            'required' => false,
            'type' => 'string'
        ],
    ]
];

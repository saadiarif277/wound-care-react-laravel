<?php

return [
    'id' => 5,
    'name' => 'MEDLIFE SOLUTIONS',
    'signature_required' => true,
    'has_order_form' => true,
    'docuseal_template_id' => '1233913', // IVR template
    'order_form_template_id' => '1234279', // Order form template
    
    // IVR form field mappings (if needed - keeping basic structure)
    'docuseal_field_names' => [
        // Basic fields for IVR form
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
    ],
    
    // Order form field mappings using exact DocuSeal field names
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
        // Contact Information - Enhanced
        'name' => [
            'source' => 'contact_name || sales_contact_name || representative_name',
            'required' => true,
            'type' => 'string',
            'forms' => ['IVR', 'OrderForm'] // Available for both forms
        ],
        'email' => [
            'source' => 'contact_email || sales_contact_email || representative_email',
            'required' => true,
            'type' => 'email',
            'forms' => ['IVR', 'OrderForm'] // Available for both forms
        ],
        'phone' => [
            'source' => 'contact_phone || sales_contact_phone || representative_phone',
            'transform' => 'phone:US',
            'required' => true,
            'type' => 'phone',
            'forms' => ['IVR', 'OrderForm'] // Available for both forms
        ],
        
        // Shipping Method (computed from shipping preference)
        'shipping_2_day' => [
            'source' => 'computed',
            'computation' => 'shipping_method == "2_day" || shipping_speed == "2_day"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean',
            'forms' => ['OrderForm'] // Only for Order forms
        ],
        'shipping_overnight' => [
            'source' => 'computed', 
            'computation' => 'shipping_method == "overnight" || shipping_speed == "overnight"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean',
            'forms' => ['OrderForm'] // Only for Order forms
        ],
        'shipping_pick_up' => [
            'source' => 'computed',
            'computation' => 'shipping_method == "pickup" || shipping_speed == "pickup"',
            'transform' => 'boolean:checkbox',
            'required' => false,
            'type' => 'boolean',
            'forms' => ['OrderForm'] // Only for Order forms
        ],
        
        // Shipping Information
        'company_facility' => [
            'source' => 'facility_name || practice_name || company_name',
            'required' => true,
            'type' => 'string',
            'forms' => ['OrderForm'] // Only for Order forms, not IVR
        ],
        'contact_name' => [
            'source' => 'facility_contact_name || office_contact_name || contact_name',
            'required' => true,
            'type' => 'string',
            'forms' => ['OrderForm'] // Only for Order forms
        ],
        'title' => [
            'source' => 'contact_title || title',
            'required' => false,
            'type' => 'string',
            'forms' => ['OrderForm'] // Only for Order forms
        ],
        'contact_phone' => [
            'source' => 'facility_phone || office_phone || contact_phone',
            'transform' => 'phone:US',
            'required' => true,
            'type' => 'phone',
            'forms' => ['OrderForm'] // Only for Order forms
        ],
        'address' => [
            'source' => 'computed',
            'computation' => 'facility_address || (facility_street + ", " + facility_city + ", " + facility_state + " " + facility_zip)',
            'required' => true,
            'type' => 'string',
            'forms' => ['OrderForm'] // Only for Order forms
        ],
        'notes' => [
            'source' => 'order_notes || special_instructions || comments',
            'required' => false,
            'type' => 'string',
            'forms' => ['OrderForm'] // Only for Order forms
        ],
        
        // AmnioAMP Product Quantities (computed from selected products)
        'size_2x2_cm' => [
            'source' => 'computed',
            'computation' => 'getProductQuantityBySize("2x2")',
            'required' => false,
            'type' => 'number'
        ],
        'size_2x3_cm' => [
            'source' => 'computed',
            'computation' => 'getProductQuantityBySize("2x3")',
            'required' => false,
            'type' => 'number'
        ],
        'size_2x4_cm' => [
            'source' => 'computed',
            'computation' => 'getProductQuantityBySize("2x4")',
            'required' => false,
            'type' => 'number'
        ],
        'size_4x4_cm' => [
            'source' => 'computed',
            'computation' => 'getProductQuantityBySize("4x4")',
            'required' => false,
            'type' => 'number'
        ],
        'size_4x6_cm' => [
            'source' => 'computed',
            'computation' => 'getProductQuantityBySize("4x6")',
            'required' => false,
            'type' => 'number'
        ],
        'size_4x8_cm' => [
            'source' => 'computed',
            'computation' => 'getProductQuantityBySize("4x8")',
            'required' => false,
            'type' => 'number'
        ],
        
        // Total Units (computed)
        'total_units' => [
            'source' => 'computed',
            'computation' => 'sum(selected_products.*.quantity)',
            'required' => false,
            'type' => 'number'
        ],
        
        // Order Information
        'date' => [
            'source' => 'computed',
            'computation' => 'order_date || now()',
            'transform' => 'date:m/d/Y',
            'required' => true,
            'type' => 'date'
        ],
        'sales_rep' => [
            'source' => 'sales_rep_name || rep_name || "MSC Wound Care"',
            'required' => false,
            'type' => 'string'
        ],
    ],
]; 
<?php

return [
    /**
     * Order Form Field Mappings Configuration
     * 
     * This file contains field mappings specifically for manufacturer order forms,
     * separate from IVR form mappings to avoid conflicts.
     */

    'manufacturers' => [
        'MedLife' => [
            'id' => 5,
            'name' => 'MEDLIFE SOLUTIONS',
            'order_form_template_id' => '1234279',
            'signature_required' => true,
            'active' => true,
            
            // Order form specific field mappings
            'fields' => [
                // Shipping Information Section
                'company_facility' => [
                    'source' => 'facility_name || practice_name || company_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'contact_name' => [
                    'source' => 'contact_name || facility_contact_name || office_contact_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'title' => [
                    'source' => 'contact_title || title',
                    'required' => false,
                    'type' => 'string'
                ],
                'contact_phone' => [
                    'source' => 'contact_phone || facility_phone || office_phone',
                    'required' => true,
                    'type' => 'phone'
                ],
                'address' => [
                    'source' => 'computed',
                    'computation' => 'facility_address || (facility_street + ", " + facility_city + ", " + facility_state + " " + facility_zip)',
                    'required' => true,
                    'type' => 'string'
                ],
                'notes' => [
                    'source' => 'order_notes || special_instructions || notes',
                    'required' => false,
                    'type' => 'string'
                ],
                
                // Product Quantities - AmnioAMP-MP sizes
                'product_2x2cm' => [
                    'source' => 'computed',
                    'computation' => 'getProductQuantityBySize("2x2")',
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0
                ],
                'product_2x3cm' => [
                    'source' => 'computed',
                    'computation' => 'getProductQuantityBySize("2x3")',
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0
                ],
                'product_2x4cm' => [
                    'source' => 'computed',
                    'computation' => 'getProductQuantityBySize("2x4")',
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0
                ],
                'product_3x3cm' => [
                    'source' => 'computed',
                    'computation' => 'getProductQuantityBySize("3x3")',
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0
                ],
                'product_3x4cm' => [
                    'source' => 'computed',
                    'computation' => 'getProductQuantityBySize("3x4")',
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0
                ],
                'product_4x4cm' => [
                    'source' => 'computed',
                    'computation' => 'getProductQuantityBySize("4x4")',
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0
                ],
                'product_4x5cm' => [
                    'source' => 'computed',
                    'computation' => 'getProductQuantityBySize("4x5")',
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0
                ],
                'product_5x5cm' => [
                    'source' => 'computed',
                    'computation' => 'getProductQuantityBySize("5x5")',
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0
                ],
                'product_5x6cm' => [
                    'source' => 'computed',
                    'computation' => 'getProductQuantityBySize("5x6")',
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0
                ],
                'product_6x6cm' => [
                    'source' => 'computed',
                    'computation' => 'getProductQuantityBySize("6x6")',
                    'required' => false,
                    'type' => 'integer',
                    'default' => 0
                ],
                
                // Totals
                'total_units' => [
                    'source' => 'computed',
                    'computation' => 'getTotalProductUnits()',
                    'required' => true,
                    'type' => 'integer'
                ],
                
                // Shipping Options (checkboxes)
                'shipping_standard' => [
                    'source' => 'computed',
                    'computation' => 'shipping_speed == "standard"',
                    'transform' => 'boolean:checkbox',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'shipping_2_day' => [
                    'source' => 'computed',
                    'computation' => 'shipping_speed == "2_day"',
                    'transform' => 'boolean:checkbox',
                    'required' => false,
                    'type' => 'boolean'
                ],
                'shipping_overnight' => [
                    'source' => 'computed',
                    'computation' => 'shipping_speed == "overnight"',
                    'transform' => 'boolean:checkbox',
                    'required' => false,
                    'type' => 'boolean'
                ],
                
                // Date
                'date' => [
                    'source' => 'computed',
                    'computation' => 'now()',
                    'transform' => 'date:m/d/Y',
                    'required' => true,
                    'type' => 'date'
                ],
                
                // Sales Rep Information (from MSC)
                'sales_rep_name' => [
                    'source' => 'sales_rep_name || rep_name || "MSC Wound Care"',
                    'required' => false,
                    'type' => 'string'
                ],
                'sales_rep_signature' => [
                    'source' => 'sales_rep_signature || rep_signature',
                    'required' => false,
                    'type' => 'string'
                ]
            ],
            
            // Docuseal field name mappings (order form template fields)
            'docuseal_field_names' => [
                'company_facility' => 'Company/Facility',
                'contact_name' => 'Contact Name',
                'title' => 'Title',
                'contact_phone' => 'Contact Phone',
                'address' => 'Address',
                'notes' => 'Notes',
                
                // Product quantities
                'product_2x2cm' => '2x2 cm',
                'product_2x3cm' => '2x3cm',
                'product_2x4cm' => '2x4 cm',
                'product_3x3cm' => '3x3 cm',
                'product_3x4cm' => '3x4 cm',
                'product_4x4cm' => '4x4 cm',
                'product_4x5cm' => '4x5 cm',
                'product_5x5cm' => '5x5 cm',
                'product_5x6cm' => '5x6 cm',
                'product_6x6cm' => '6x6 cm',
                
                'total_units' => 'TOTAL UNITS',
                
                // Shipping options
                'shipping_standard' => 'Shipping: Standard',
                'shipping_2_day' => 'Shipping: 2-Day',
                'shipping_overnight' => 'Shipping: Overnight',
                
                'date' => 'Date',
                'sales_rep_name' => 'Sales Rep Name',
                'sales_rep_signature' => 'Sales Rep Signature'
            ]
        ],
        
        // Additional manufacturers can be added here
        // 'AnotherManufacturer' => [...],
    ],
    
    /**
     * Product size mappings for quantity calculations
     */
    'product_size_mappings' => [
        '2x2' => ['2x2', '2x2cm', '2 x 2'],
        '2x3' => ['2x3', '2x3cm', '2 x 3'],
        '2x4' => ['2x4', '2x4cm', '2 x 4'],
        '3x3' => ['3x3', '3x3cm', '3 x 3'],
        '3x4' => ['3x4', '3x4cm', '3 x 4'],
        '4x4' => ['4x4', '4x4cm', '4 x 4'],
        '4x5' => ['4x5', '4x5cm', '4 x 5'],
        '5x5' => ['5x5', '5x5cm', '5 x 5'],
        '5x6' => ['5x6', '5x6cm', '5 x 6'],
        '6x6' => ['6x6', '6x6cm', '6 x 6'],
    ],
    
    /**
     * Shipping speed options
     */
    'shipping_options' => [
        'standard' => 'Standard Shipping (5-7 business days)',
        '2_day' => '2-Day Shipping',
        'overnight' => 'Overnight Shipping'
    ]
]; 
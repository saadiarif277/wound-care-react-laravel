<?php

return [
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
];
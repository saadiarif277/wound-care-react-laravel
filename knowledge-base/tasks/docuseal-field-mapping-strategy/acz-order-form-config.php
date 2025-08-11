<?php

/**
 * ACZ & Associates OrderForm Template Field Mapping Configuration
 *
 * This configuration maps form data to Docuseal template fields for OrderForm
 * Template: "ACZ & Associates Order Form"
 *
 * Field mapping strategy for OrderForm completion
 */

return [
    'template_id' => 'acz-order-form-template', // This will be replaced with actual Docuseal template ID
    'template_name' => 'ACZ & Associates Order Form',
    'manufacturer' => 'ACZ & ASSOCIATES',

    // Field mappings organized by section
    'field_mappings' => [

        // ========================================
        // ORDER INFORMATION
        // ========================================
        'Order Number' => [
            'source' => 'order_number',
            'type' => 'text',
            'required' => true,
            'transform' => 'trim'
        ],

        'Order Date' => [
            'source' => 'order_date',
            'type' => 'date',
            'required' => true,
            'transform' => 'format_date',
            'fallback' => 'current_date'
        ],

        // ========================================
        // MANUFACTURER INFORMATION
        // ========================================
        'Manufacturer Name' => [
            'source' => 'manufacturer_name',
            'type' => 'text',
            'required' => true,
            'transform' => 'title_case',
            'fallback' => 'ACZ & Associates'
        ],

        // ========================================
        // PATIENT INFORMATION
        // ========================================
        'Patient Name' => [
            'source' => 'patient_name',
            'type' => 'text',
            'required' => true,
            'transform' => 'title_case'
        ],

        'Patient Email' => [
            'source' => 'patient_email',
            'type' => 'email',
            'required' => false,
            'transform' => 'trim'
        ],

        'Patient Phone' => [
            'source' => 'patient_phone',
            'type' => 'phone',
            'required' => false,
            'transform' => 'format_phone'
        ],

        'Patient Address' => [
            'source' => 'patient_address',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        // ========================================
        // PROVIDER INFORMATION
        // ========================================
        'Provider Name' => [
            'source' => 'provider_name',
            'type' => 'text',
            'required' => true,
            'transform' => 'title_case'
        ],

        'Provider NPI' => [
            'source' => 'provider_npi',
            'type' => 'text',
            'required' => false,
            'transform' => 'trim'
        ],

        'Provider Email' => [
            'source' => 'provider_email',
            'type' => 'email',
            'required' => false,
            'transform' => 'trim'
        ],

        // ========================================
        // FACILITY INFORMATION
        // ========================================
        'Facility Name' => [
            'source' => 'facility_name',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Facility NPI' => [
            'source' => 'facility_npi',
            'type' => 'text',
            'required' => false,
            'transform' => 'trim'
        ],

        // ========================================
        // PRODUCT INFORMATION
        // ========================================
        'Product Code' => [
            'source' => 'product_code',
            'type' => 'text',
            'required' => true,
            'transform' => 'trim'
        ],

        'Product Description' => [
            'source' => 'product_description',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Quantity' => [
            'source' => 'quantity',
            'type' => 'number',
            'required' => true,
            'transform' => 'to_integer',
            'fallback' => '1'
        ],

        // ========================================
        // SHIPPING INFORMATION
        // ========================================
        'Shipping Address' => [
            'source' => 'shipping_address',
            'type' => 'text',
            'required' => false,
            'transform' => 'title_case'
        ],

        'Shipping Method' => [
            'source' => 'shipping_method',
            'type' => 'select',
            'required' => false,
            'transform' => 'trim',
            'options' => ['Standard', 'Express', 'Overnight']
        ],

        // ========================================
        // INTEGRATION INFORMATION
        // ========================================
        'Integration Email' => [
            'source' => 'integration_email',
            'type' => 'email',
            'required' => true,
            'transform' => 'trim',
            'fallback' => 'integration@mscwoundcare.com'
        ],

        'Episode ID' => [
            'source' => 'episode_id',
            'type' => 'text',
            'required' => false,
            'transform' => 'trim'
        ]
    ],

    // Transformations for field values
    'transformations' => [
        'title_case' => function($value) {
            return ucwords(strtolower(trim($value)));
        },
        
        'trim' => function($value) {
            return trim($value);
        },
        
        'format_date' => function($value) {
            if (empty($value)) {
                return date('Y-m-d');
            }
            return date('Y-m-d', strtotime($value));
        },
        
        'format_phone' => function($value) {
            if (empty($value)) return '';
            // Remove all non-numeric characters
            $phone = preg_replace('/[^0-9]/', '', $value);
            // Format as (XXX) XXX-XXXX
            if (strlen($phone) === 10) {
                return '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
            }
            return $value;
        },
        
        'to_integer' => function($value) {
            return (int) $value;
        },
        
        'current_date' => function() {
            return date('Y-m-d');
        }
    ],

    // Validation rules
    'validation' => [
        'required_fields' => [
            'Order Number',
            'Order Date',
            'Manufacturer Name',
            'Patient Name',
            'Provider Name',
            'Product Code',
            'Quantity',
            'Integration Email'
        ],
        
        'email_fields' => [
            'Patient Email',
            'Provider Email',
            'Integration Email'
        ],
        
        'phone_fields' => [
            'Patient Phone'
        ],
        
        'numeric_fields' => [
            'Quantity',
            'Provider NPI',
            'Facility NPI'
        ]
    ],

    // Template metadata
    'metadata' => [
        'version' => '1.0.0',
        'created_date' => '2025-01-11',
        'last_updated' => '2025-01-11',
        'manufacturer_id' => 1,
        'manufacturer_name' => 'ACZ & Associates',
        'document_type' => 'OrderForm',
        'is_active' => true
    ]
];

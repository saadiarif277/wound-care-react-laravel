<?php

/**
 * Sample Manufacturer Configuration - 2025 Best Practices
 * 
 * This is a reference implementation showing modern best practices for:
 * - Declarative field mapping
 * - Type safety and validation
 * - Business rule configuration
 * - DocuSeal API integration
 * - Web accessibility standards
 * - Performance optimization
 * - Maintainability patterns
 */

return [
    // Configuration metadata
    'version' => '2.0',
    'schema_version' => '2025.1',
    'created_at' => '2025-01-01T00:00:00Z',
    'last_updated' => '2025-01-01T00:00:00Z',
    'author' => 'MSC Wound Portal Team',
    
    // Basic manufacturer information
    'id' => 999,
    'name' => 'Sample Medical Corp',
    'slug' => 'sample-medical-corp',
    'display_name' => 'Sample Medical Corporation',
    'short_name' => 'SMC',
    
    // Capabilities and features
    'capabilities' => [
        'signature_required' => true,
        'has_order_form' => true,
        'supports_insurance_upload_in_ivr' => true,
        'supports_electronic_signatures' => true,
        'supports_bulk_uploads' => false,
        'supports_real_time_validation' => true,
        'supports_webhook_notifications' => true,
        'supports_multi_language' => false,
        'requires_prior_authorization' => true,
        'supports_telehealth' => true,
    ],
    
    // DocuSeal integration configuration - Following 2025 API standards
    'docuseal' => [
        // Template configuration
        'template_id' => 1000001, // Numeric template ID from DocuSeal API
        'template_slug' => 'sample-medical-corp-ivr', // Used for direct embedding
        'folder_id' => null, // Optional folder organization
        'order_form_template_id' => 1000002,
        
        // API configuration
        'api_key' => env('DOCUSEAL_API_KEY'),
        'api_url' => env('DOCUSEAL_API_URL', 'https://api.docuseal.com'),
        'webhook_secret' => env('DOCUSEAL_WEBHOOK_SECRET_SAMPLE'),
        
        // Submission settings (following createSubmission API)
        'send_email' => true, // Auto-send email invitations
        'send_sms' => false, // SMS notifications
        'embed_mode' => 'iframe', // 'iframe', 'redirect', 'modal'
        'completion_redirect_url' => null,
        'expiry_days' => 30,
        
        // Webhook configuration (following 2025 webhook standards)
        'webhook_events' => [
            'form.viewed',    // When signer first views the form
            'form.started',   // When signer starts filling the form
            'form.completed'  // When all signers complete the form
        ],
        
        // Field validation and formatting
        'field_validation' => [
            'enable_real_time' => true,
            'custom_patterns' => true,
            'mask_sensitive_data' => true
        ],
        
        // Branding and UI customization
        'branding' => [
            'company_logo_url' => 'https://example.com/logo.png',
            'primary_color' => '#2563eb',
            'font_family' => 'Inter, system-ui, sans-serif',
            'custom_css_enabled' => true
        ],
        
        // Security settings
        'security' => [
            'require_authentication' => false,
            'ip_restrictions' => [],
            'two_factor_auth' => false,
            'audit_trail' => true,
            'document_seal' => true // Prevent tampering
        ],
        
        // Field types supported (from DocuSeal 2025 docs)
        'supported_field_types' => [
            'text-field',      // Text input
            'signature-field', // Signature
            'initials-field',  // Initials
            'date-field',      // Date picker
            'image-field',     // Image upload
            'file-field',      // File upload
            'checkbox-field',  // Checkbox
            'radio-field',     // Radio buttons
            'select-field',    // Dropdown
            'multi-select-field', // Multi-select
            'phone-field',     // Phone with 2FA
            'stamp-field'      // Read-only stamp
        ],
        
        // React + Inertia.js embedding configuration (following @docuseal/react)
        'react_integration' => [
            'framework' => 'react_inertia', // React with Inertia.js SSR
            'component_library' => '@docuseal/react', // Official DocuSeal React components
            'installation_command' => 'npm install @docuseal/react',
            'slug_based_embedding' => true, // Use slug URLs from API response
            
            // Component usage patterns
            'components' => [
                'DocusealForm' => [
                    'props' => ['src', 'email', 'onComplete', 'customCss'],
                    'usage' => 'Document signing form',
                    'example' => 'import { DocusealForm } from "@docuseal/react"; <DocusealForm src="https://docuseal.com/s/{slug}" onComplete={handleComplete} />'
                ],
                'DocusealBuilder' => [
                    'props' => ['token'],
                    'usage' => 'Form builder (requires JWT token)',
                    'example' => '<DocusealBuilder token={jwtToken} />'
                ]
            ],
            
            // Event callbacks (React patterns)
            'event_callbacks' => [
                'onComplete' => '(data) => console.log("Document completed", data)',
                'onDecline' => '() => console.log("Document declined")', 
                'onError' => '(error) => console.error("Signing error", error)',
                'onLoad' => '() => console.log("Form loaded")'
            ],
            
            // Inertia.js specific patterns
            'inertia_patterns' => [
                'server_side_slug_generation' => true,
                'csrf_token_handling' => 'automatic', // Laravel Sanctum handles this
                'form_data_binding' => 'inertia_forms',
                'redirect_after_completion' => 'inertia_visit',
                'ssr_compatible' => true
            ],
            
            // Styling options
            'styling' => [
                'tailwind_compatible' => true,
                'css_in_js_support' => true,
                'custom_themes' => true,
                'dark_mode_support' => true,
                'responsive_design' => true,
                'accessibility_compliant' => true // WCAG 2.1 AA
            ]
        ]
    ],
    
    // Business rules and validation logic
    'business_rules' => [
        // Wound duration requirement
        'wound_duration_validation' => [
            'condition' => 'wound_duration_weeks <= 4',
            'severity' => 'warning',
            'message' => 'Manufacturer typically requires wound duration > 4 weeks',
            'actions' => [
                ['type' => 'add_warning', 'field' => 'wound_duration_weeks'],
                ['type' => 'flag_for_review', 'reason' => 'short_wound_duration']
            ]
        ],
        
        // Medicare-specific validation
        'medicare_requirements' => [
            'condition' => 'primary_insurance_name ~= "Medicare"',
            'actions' => [
                ['type' => 'require_field', 'field' => 'physician_npi'],
                ['type' => 'require_field', 'field' => 'facility_npi'],
                ['type' => 'validate_npi', 'field' => 'physician_npi'],
                ['type' => 'validate_npi', 'field' => 'facility_npi']
            ]
        ],
        
        // Prior authorization check
        'prior_auth_required' => [
            'condition' => 'total_wound_size > 100 OR primary_insurance_name ~= "Aetna"',
            'actions' => [
                ['type' => 'set_field', 'field' => 'requires_prior_auth', 'value' => true],
                ['type' => 'add_note', 'message' => 'Prior authorization may be required']
            ]
        ],
        
        // Product-specific rules
        'product_restrictions' => [
            'condition' => 'wound_location == "hands_feet_head_large" AND selected_products.q4205 == true',
            'severity' => 'error',
            'message' => 'Q4205 not approved for large head/hands/feet wounds',
            'actions' => [
                ['type' => 'block_submission'],
                ['type' => 'suggest_alternative', 'products' => ['q4290', 'q4344']]
            ]
        ]
    ],
    
    // Field mapping configuration with modern patterns
    'field_mappings' => [
        // Contact Information - Following 2025 accessibility standards
        'contact_name' => [
            'sources' => ['contact_name', 'submitter_name', 'sales_rep_name'],
            'type' => 'string',
            'required' => true,
            'docuseal_field' => 'Contact Name',
            'ui_label' => 'Contact Person',
            'ui_description' => 'Primary contact for this submission',
            'accessibility' => [
                'aria_label' => 'Primary contact person name',
                'required_message' => 'Contact name is required for processing'
            ],
            'validations' => [
                ['type' => 'required', 'message' => 'Contact name is required'],
                ['type' => 'min_length', 'value' => 2, 'message' => 'Name must be at least 2 characters'],
                ['type' => 'max_length', 'value' => 100, 'message' => 'Name cannot exceed 100 characters'],
                ['type' => 'pattern', 'pattern' => '/^[a-zA-Z\s\-\'\.]+$/', 'message' => 'Invalid characters in name']
            ],
            'transformations' => [
                ['type' => 'trim'],
                ['type' => 'title_case'],
                ['type' => 'normalize_whitespace']
            ]
        ],
        
        'contact_email' => [
            'sources' => ['contact_email', 'submitter_email', 'sales_rep_email'],
            'type' => 'email',
            'required' => true,
            'docuseal_field' => 'Email Address',
            'ui_label' => 'Email Address',
            'ui_description' => 'We\'ll send updates to this email',
            'validations' => [
                ['type' => 'required'],
                ['type' => 'email', 'message' => 'Please enter a valid email address'],
                ['type' => 'max_length', 'value' => 255]
            ],
            'transformations' => [
                ['type' => 'trim'],
                ['type' => 'lowercase']
            ]
        ],
        
        'contact_phone' => [
            'sources' => ['contact_phone', 'submitter_phone', 'sales_rep_phone'],
            'type' => 'phone',
            'required' => true,
            'docuseal_field' => 'Phone Number',
            'ui_label' => 'Phone Number',
            'ui_placeholder' => '(555) 123-4567',
            'validations' => [
                ['type' => 'required'],
                ['type' => 'phone', 'format' => 'US', 'message' => 'Please enter a valid US phone number']
            ],
            'transformations' => [
                ['type' => 'normalize_phone', 'format' => 'US'],
                ['type' => 'format_phone', 'format' => '(XXX) XXX-XXXX']
            ]
        ],
        
        // Product Selection - Modern checkbox handling
        'selected_products' => [
            'sources' => ['selected_products', 'product_requests'],
            'type' => 'product_array',
            'required' => true,
            'ui_label' => 'Selected Products',
            'ui_description' => 'Choose all applicable products',
            'products' => [
                'q4205' => [
                    'name' => 'Q4205 - Advanced Wound Matrix',
                    'docuseal_field' => 'Q4205',
                    'description' => 'Collagen-based wound dressing'
                ],
                'q4290' => [
                    'name' => 'Q4290 - BioSkin Plus',
                    'docuseal_field' => 'Q4290',
                    'description' => 'Acellular dermal matrix'
                ],
                'q4344' => [
                    'name' => 'Q4344 - WoundHeal Pro',
                    'docuseal_field' => 'Q4344',
                    'description' => 'Advanced tissue matrix'
                ]
            ],
            'validations' => [
                ['type' => 'min_selection', 'value' => 1, 'message' => 'At least one product must be selected'],
                ['type' => 'max_selection', 'value' => 5, 'message' => 'Maximum 5 products can be selected']
            ]
        ],
        
        // Patient Information - HIPAA compliant handling
        'patient_name' => [
            'sources' => ['patient_first_name', 'patient_last_name'],
            'type' => 'string',
            'required' => true,
            'docuseal_field' => 'Patient Name',
            'ui_label' => 'Patient Full Name',
            'phi_field' => true, // Mark as PHI for special handling
            'transformations' => [
                ['type' => 'concat', 'separator' => ' '],
                ['type' => 'trim'],
                ['type' => 'title_case']
            ],
            'validations' => [
                ['type' => 'required'],
                ['type' => 'min_length', 'value' => 2],
                ['type' => 'max_length', 'value' => 100]
            ]
        ],
        
        'patient_dob' => [
            'sources' => ['patient_date_of_birth', 'patient_dob'],
            'type' => 'date',
            'required' => true,
            'docuseal_field' => 'Patient DOB',
            'ui_label' => 'Date of Birth',
            'ui_placeholder' => 'MM/DD/YYYY',
            'phi_field' => true,
            'transformations' => [
                ['type' => 'parse_date', 'input_formats' => ['Y-m-d', 'm/d/Y', 'd/m/Y']],
                ['type' => 'format_date', 'output_format' => 'm/d/Y']
            ],
            'validations' => [
                ['type' => 'required'],
                ['type' => 'date'],
                ['type' => 'date_before', 'value' => 'today', 'message' => 'Date of birth must be in the past'],
                ['type' => 'date_after', 'value' => '1900-01-01', 'message' => 'Invalid date of birth']
            ]
        ],
        
        // Provider Information - Professional validation
        'physician_name' => [
            'sources' => ['provider_name', 'physician_name', 'prescriber_name'],
            'type' => 'string',
            'required' => true,
            'docuseal_field' => 'Physician Name',
            'ui_label' => 'Prescribing Physician',
            'validations' => [
                ['type' => 'required'],
                ['type' => 'min_length', 'value' => 2],
                ['type' => 'professional_name_format']
            ],
            'transformations' => [
                ['type' => 'trim'],
                ['type' => 'title_case'],
                ['type' => 'normalize_whitespace']
            ]
        ],
        
        'physician_npi' => [
            'sources' => ['provider_npi', 'physician_npi', 'prescriber_npi'],
            'type' => 'npi',
            'required' => true,
            'docuseal_field' => 'Physician NPI',
            'ui_label' => 'Physician NPI Number',
            'ui_placeholder' => '1234567890',
            'validations' => [
                ['type' => 'required'],
                ['type' => 'npi', 'message' => 'Please enter a valid 10-digit NPI number'],
                ['type' => 'npi_checksum', 'message' => 'Invalid NPI checksum']
            ],
            'transformations' => [
                ['type' => 'digits_only'],
                ['type' => 'pad_left', 'length' => 10, 'char' => '0']
            ]
        ],
        
        // Insurance Information - Complex business logic
        'primary_insurance_name' => [
            'sources' => ['primary_insurance_name', 'insurance_company', 'payer_name'],
            'type' => 'string',
            'required' => true,
            'docuseal_field' => 'Primary Insurance',
            'ui_label' => 'Primary Insurance Company',
            'ui_autocomplete' => 'insurance_companies', // Reference to autocomplete dataset
            'validations' => [
                ['type' => 'required'],
                ['type' => 'min_length', 'value' => 2],
                ['type' => 'insurance_company_exists', 'message' => 'Please select a valid insurance company']
            ],
            'transformations' => [
                ['type' => 'trim'],
                ['type' => 'normalize_insurance_name'] // Custom transformation
            ]
        ],
        
        'primary_member_id' => [
            'sources' => ['primary_member_id', 'primary_policy_number', 'insurance_id'],
            'type' => 'string',
            'required' => true,
            'docuseal_field' => 'Member ID',
            'ui_label' => 'Member/Policy ID',
            'phi_field' => true,
            'validations' => [
                ['type' => 'required'],
                ['type' => 'min_length', 'value' => 3],
                ['type' => 'max_length', 'value' => 50]
            ],
            'transformations' => [
                ['type' => 'trim'],
                ['type' => 'uppercase']
            ]
        ],
        
        // Network Status - Modern dropdown handling
        'physician_network_status' => [
            'sources' => ['primary_physician_network_status', 'network_status'],
            'type' => 'select',
            'required' => false,
            'docuseal_field' => 'Network Status',
            'ui_label' => 'Physician Network Status',
            'ui_description' => 'Provider\'s status with primary insurance',
            'options' => [
                'in_network' => 'In-Network',
                'out_of_network' => 'Out-of-Network',
                'unknown' => 'Unknown/Pending Verification'
            ],
            'default_value' => 'unknown',
            'validations' => [
                ['type' => 'in_list', 'values' => ['in_network', 'out_of_network', 'unknown']]
            ]
        ],
        
        // Clinical Information - Advanced wound care
        'wound_location_category' => [
            'sources' => ['wound_location', 'anatomical_location'],
            'type' => 'select',
            'required' => true,
            'docuseal_field' => 'Wound Location Category',
            'ui_label' => 'Wound Location',
            'ui_description' => 'Select the anatomical category and size',
            'options' => [
                'trunk_arms_legs_small' => 'Trunk/Arms/Legs < 100 sq cm',
                'trunk_arms_legs_large' => 'Trunk/Arms/Legs ≥ 100 sq cm',
                'hands_feet_head_small' => 'Hands/Feet/Head < 100 sq cm',
                'hands_feet_head_large' => 'Hands/Feet/Head ≥ 100 sq cm'
            ],
            'validations' => [
                ['type' => 'required'],
                ['type' => 'in_list', 'values' => ['trunk_arms_legs_small', 'trunk_arms_legs_large', 'hands_feet_head_small', 'hands_feet_head_large']]
            ]
        ],
        
        'wound_size_total' => [
            'sources' => ['wound_size_length', 'wound_size_width', 'wound_area_calculated'],
            'type' => 'number',
            'required' => true,
            'docuseal_field' => 'Total Wound Size',
            'ui_label' => 'Total Wound Size (sq cm)',
            'ui_suffix' => 'cm²',
            'computation' => [
                'formula' => 'wound_size_length * wound_size_width',
                'fallback_sources' => ['wound_area_calculated', 'wound_size_total']
            ],
            'validations' => [
                ['type' => 'required'],
                ['type' => 'numeric'],
                ['type' => 'min', 'value' => 0.1, 'message' => 'Wound size must be greater than 0'],
                ['type' => 'max', 'value' => 10000, 'message' => 'Wound size seems unreasonably large']
            ],
            'transformations' => [
                ['type' => 'round', 'decimals' => 2]
            ]
        ],
        
        // Diagnosis Codes - Medical coding standards
        'diagnosis_codes' => [
            'sources' => ['primary_diagnosis_code', 'secondary_diagnosis_code', 'diagnosis_code'],
            'type' => 'medical_codes',
            'required' => true,
            'docuseal_field' => 'ICD-10 Codes',
            'ui_label' => 'Diagnosis Codes (ICD-10)',
            'ui_description' => 'Enter primary and secondary diagnosis codes',
            'code_system' => 'ICD10',
            'computation' => [
                'formula' => 'JOIN(FILTER([primary_diagnosis_code, secondary_diagnosis_code]), ", ")',
                'fallback_sources' => ['diagnosis_code']
            ],
            'validations' => [
                ['type' => 'required'],
                ['type' => 'icd10_format', 'message' => 'Invalid ICD-10 code format'],
                ['type' => 'icd10_exists', 'message' => 'ICD-10 code not found in current codebook']
            ],
            'transformations' => [
                ['type' => 'normalize_icd10'],
                ['type' => 'validate_icd10_codes']
            ]
        ]
    ],
    
    // Field groups for UI organization
    'field_groups' => [
        'contact_info' => [
            'label' => 'Contact Information',
            'description' => 'Primary contact for this submission',
            'icon' => 'user-circle',
            'order' => 1,
            'fields' => ['contact_name', 'contact_email', 'contact_phone'],
            'ui_layout' => 'grid',
            'ui_columns' => 2
        ],
        
        'product_selection' => [
            'label' => 'Product Selection',
            'description' => 'Select applicable wound care products',
            'icon' => 'squares-plus',
            'order' => 2,
            'fields' => ['selected_products'],
            'ui_layout' => 'checkbox_grid',
            'ui_columns' => 3
        ],
        
        'patient_info' => [
            'label' => 'Patient Information',
            'description' => 'Patient demographics and contact details',
            'icon' => 'identification',
            'order' => 3,
            'fields' => ['patient_name', 'patient_dob'],
            'ui_layout' => 'form',
            'phi_section' => true
        ],
        
        'provider_info' => [
            'label' => 'Provider Information',
            'description' => 'Prescribing physician details',
            'icon' => 'user-md',
            'order' => 4,
            'fields' => ['physician_name', 'physician_npi'],
            'ui_layout' => 'form'
        ],
        
        'insurance_info' => [
            'label' => 'Insurance Information',
            'description' => 'Primary insurance and network status',
            'icon' => 'shield-check',
            'order' => 5,
            'fields' => ['primary_insurance_name', 'primary_member_id', 'physician_network_status'],
            'ui_layout' => 'form',
            'phi_section' => true
        ],
        
        'clinical_info' => [
            'label' => 'Clinical Information',
            'description' => 'Wound details and diagnosis',
            'icon' => 'heart-pulse',
            'order' => 6,
            'fields' => ['wound_location_category', 'wound_size_total', 'diagnosis_codes'],
            'ui_layout' => 'form'
        ]
    ],
    
    // UI/UX Configuration - 2025 Design Standards
    'ui_config' => [
        'theme' => 'modern',
        'color_scheme' => 'blue',
        'form_layout' => 'stepped', // 'single', 'stepped', 'tabbed'
        'progress_indicator' => true,
        'auto_save' => true,
        'auto_save_interval' => 30, // seconds
        'field_validation' => 'real_time', // 'on_submit', 'real_time', 'on_blur'
        'error_display' => 'inline', // 'inline', 'toast', 'modal'
        'success_animation' => true,
        'keyboard_navigation' => true,
        'screen_reader_support' => true,
        'high_contrast_mode' => false,
        'mobile_optimized' => true,
        'responsive_breakpoints' => [
            'mobile' => '640px',
            'tablet' => '768px',
            'desktop' => '1024px'
        ]
    ],
    
    // Performance and caching
    'performance' => [
        'cache_ttl' => 300, // 5 minutes
        'preload_dependencies' => true,
        'lazy_load_sections' => false,
        'optimize_images' => true,
        'enable_compression' => true
    ],
    
    // Analytics and monitoring
    'analytics' => [
        'track_field_interactions' => true,
        'track_completion_time' => true,
        'track_abandonment_points' => true,
        'track_error_rates' => true,
        'track_validation_failures' => true
    ],
    
    // Integration endpoints
    'integrations' => [
        'webhook_url' => env('SAMPLE_MANUFACTURER_WEBHOOK_URL'),
        'api_endpoints' => [
            'eligibility_check' => '/api/v1/eligibility',
            'prior_auth' => '/api/v1/prior-authorization',
            'order_status' => '/api/v1/orders/{order_id}/status'
        ],
        'notification_preferences' => [
            'email_on_submission' => true,
            'email_on_completion' => true,
            'sms_notifications' => false,
            'real_time_updates' => true
        ]
    ],
    
    // Compliance and security
    'compliance' => [
        'hipaa_compliant' => true,
        'audit_logging' => true,
        'data_retention_days' => 2555, // 7 years
        'encryption_at_rest' => true,
        'encryption_in_transit' => true,
        'access_logging' => true,
        'phi_handling' => 'strict'
    ],
    
    // Testing and validation
    'testing' => [
        'test_mode_available' => true,
        'sample_data_sets' => [
            'basic_submission',
            'complex_wound_case',
            'medicare_patient',
            'prior_auth_required'
        ],
        'validation_rules_test' => true,
        'business_rules_test' => true
    ],
    
    // Version history and changelog
    'changelog' => [
        '2.0.0' => [
            'date' => '2025-01-01',
            'changes' => [
                'Migrated to V2 configuration format',
                'Added comprehensive validation rules',
                'Implemented modern UI patterns',
                'Enhanced accessibility features',
                'Added performance optimizations'
            ]
        ]
    ],
    
    // Development and debugging
    'debug' => [
        'log_field_mappings' => env('APP_DEBUG', false),
        'validate_on_load' => env('APP_DEBUG', false),
        'show_debug_info' => env('APP_DEBUG', false),
        'performance_profiling' => env('APP_DEBUG', false)
    ]
]; 
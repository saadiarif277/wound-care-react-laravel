<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DocuSeal Dynamic Field Mapping Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the dynamic field mapping system that uses LLM
    | intelligence to map manufacturer data to DocuSeal form fields.
    |
    */

    'docuseal' => [
        'api_key' => env('DOCUSEAL_API_KEY'),
        'base_url' => env('DOCUSEAL_BASE_URL', 'https://api.docuseal.com'),
        'timeout' => env('DOCUSEAL_TIMEOUT', 30),
        'max_retries' => env('DOCUSEAL_MAX_RETRIES', 3),
        'cache_ttl' => env('DOCUSEAL_CACHE_TTL', 3600), // 1 hour for template caching
    ],

    'llm' => [
        'provider' => env('LLM_PROVIDER', 'openai'), // 'openai' or 'anthropic'
        'api_key' => env('LLM_API_KEY'),
        'model' => env('LLM_MODEL', 'gpt-4o'),
        'temperature' => env('LLM_TEMPERATURE', 0.1),
        'max_tokens' => env('LLM_MAX_TOKENS', 2000),
        'timeout' => env('LLM_TIMEOUT', 30),
        'min_confidence_threshold' => env('LLM_MIN_CONFIDENCE', 0.7),
    ],

    'mapping' => [
        'enable_caching' => env('DYNAMIC_MAPPING_CACHE', true),
        'cache_prefix' => 'docuseal_dynamic_mapping',
        'cache_ttl' => env('DYNAMIC_MAPPING_CACHE_TTL', 1800), // 30 minutes
        'enable_fallback_to_static' => env('ENABLE_STATIC_FALLBACK', true),
        'log_all_mappings' => env('LOG_ALL_MAPPINGS', true),
        'performance_threshold_seconds' => env('PERFORMANCE_THRESHOLD', 5),
    ],

    'ai_service_url' => env('AI_SERVICE_URL', 'http://localhost:8081'),

    'validation' => [
        'required_response_keys' => ['mapped_fields', 'confidence_score'],
        'optional_response_keys' => ['unmapped_fields', 'missing_data', 'mapping_notes'],
        'max_response_size' => 10000, // bytes
        'validate_field_names' => true,
    ],

    'error_handling' => [
        'retry_on_llm_failure' => true,
        'retry_on_api_failure' => true,
        'max_api_retries' => 3,
        'max_llm_retries' => 2,
        'exponential_backoff' => true,
        'base_delay_ms' => 1000,
    ],

    'logging' => [
        'log_channel' => env('DOCUSEAL_LOG_CHANNEL', 'single'),
        'log_template_retrievals' => true,
        'log_llm_calls' => true,
        'log_submissions' => true,
        'log_performance_metrics' => true,
        'log_validation_errors' => true,
        'sensitive_fields' => [
            'patient_name', 'patient_dob', 'patient_address', 'patient_phone',
            'member_id', 'policy_number', 'subscriber_name'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM Field Mapping Prompt Template
    |--------------------------------------------------------------------------
    */
    'llm_prompt_template' => "
You are a field mapping specialist for DocuSeal form filling. Your job is to intelligently map manufacturer data to DocuSeal form field names with 100% accuracy.

## Input Data Structure
You will receive:
1. **Available Fields**: Array of exact field names from the DocuSeal template
2. **Manufacturer Data**: Object containing all available manufacturer information
3. **Form Context**: Template name/type to understand the form's purpose

## Output Requirements
Return ONLY valid JSON in this exact format:
{
  \"mapped_fields\": {
    \"Exact_Field_Name\": \"mapped_value\",
    \"Another_Field_Name\": \"another_value\"
  },
  \"unmapped_fields\": [\"Field_Name_1\", \"Field_Name_2\"],
  \"missing_data\": [\"data_type_1\", \"data_type_2\"],
  \"confidence_score\": 0.95,
  \"mapping_notes\": \"Brief explanation of any complex mappings\"
}

## Critical Mapping Rules

### Field Name Matching Priority
1. **Exact Match**: If manufacturer data key exactly matches field name
2. **Semantic Match**: Map based on meaning (e.g., \"company\" → \"Company_Name\")
3. **Pattern Match**: Handle variations (e.g., \"addr1\" → \"Address_Line_1\")
4. **Type-based Match**: Match data types (e.g., phone numbers, emails, dates)

### Data Type Handling
**Dates**: Convert to MM/DD/YYYY format unless specified otherwise
**Phone Numbers**: Clean formatting, remove spaces/dashes/parentheses
**Addresses**: Split combined addresses into separate fields
**Names**: Split full names into first/last when needed

### Common Field Patterns
- \"company\", \"business_name\" → \"Company_Name\"
- \"model\", \"model_number\" → \"Model_Number\"
- \"serial\", \"serial_number\" → \"Serial_Number\"
- \"install_date\", \"installation_date\" → \"Installation_Date\"
- Split addresses into \"Address_Line_1\", \"City\", \"State\", \"ZIP_Code\"

Never guess. If uncertain about a mapping, leave it unmapped rather than risk incorrect data.

## Template Context
Template Name: {template_name}
Template ID: {template_id}

## Available Fields
{available_fields}

## Manufacturer Data
{manufacturer_data}

Map the manufacturer data to the available fields following the rules above.",

    /*
    |--------------------------------------------------------------------------
    | Common Field Type Patterns
    |--------------------------------------------------------------------------
    */
    'field_patterns' => [
        'date' => [
            'patterns' => ['date', 'dob', 'birth', 'created', 'updated', 'signed'],
            'formats' => ['m/d/Y', 'Y-m-d', 'M d, Y'],
            'default_format' => 'm/d/Y',
        ],
        'phone' => [
            'patterns' => ['phone', 'tel', 'mobile', 'cell', 'fax'],
            'formats' => ['(###) ###-####', '###-###-####', '##########'],
            'default_format' => '(###) ###-####',
        ],
        'email' => [
            'patterns' => ['email', 'mail', 'contact'],
            'validation' => 'email',
        ],
        'name' => [
            'patterns' => ['name', 'contact', 'person', 'provider', 'physician'],
            'split_patterns' => ['first_name', 'last_name', 'full_name'],
        ],
        'address' => [
            'patterns' => ['address', 'street', 'location'],
            'components' => ['line1', 'line2', 'city', 'state', 'zip', 'country'],
        ],
        'boolean' => [
            'patterns' => ['check', 'is_', 'has_', 'enable', 'allow'],
            'true_values' => ['yes', 'true', '1', 'on', 'checked'],
            'false_values' => ['no', 'false', '0', 'off', 'unchecked'],
        ],
    ],
]; 
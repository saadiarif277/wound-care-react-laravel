<?php

/**
 * Test script to analyze real Quick Request data mapping to Advanced Solution IVR
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\DocusealService;

echo "=== Real Data Mapping Analysis ===\n\n";

// Real data from Quick Request form
$realData = [
    "request_type" => "new_request",
    "provider_id" => 3,
    "facility_id" => 1,
    "organization_id" => 22,
    "organization_name" => "Test Healthcare Network",
    "patient_name" => "John Doe",
    "patient_first_name" => "John",
    "patient_last_name" => "Doe",
    "patient_dob" => "1965-03-15",
    "patient_gender" => "male",
    "patient_is_subscriber" => true,
    "primary_insurance_name" => "Humana",
    "primary_member_id" => "MED123456789",
    "primary_plan_type" => "ffs",
    "has_secondary_insurance" => false,
    "prior_auth_permission" => true,
    "wound_type" => "diabetic_foot_ulcer",
    "wound_types" => ["diabetic_foot_ulcer"],
    "wound_location" => "right_foot",
    "wound_size_length" => "4",
    "wound_size_width" => "4",
    "wound_size_depth" => "0",
    "application_cpt_codes" => ["", "15271", "15272"],
    "place_of_service" => "11",
    "shipping_speed" => "standard_next_day",
    "expected_service_date" => "2025-08-03",
    "order_items" => [],
    "failed_conservative_treatment" => true,
    "information_accurate" => true,
    "medical_necessity_established" => true,
    "maintain_documentation" => true,
    "authorize_prior_auth" => true,
    "provider_npi" => "12345",
    "selected_products" => [
        [
            "product_id" => 4,
            "quantity" => 1,
            "size" => "1.54",
            "product" => [
                "id" => 4,
                "code" => "Q4316",
                "name" => "Amchoplast",
                "manufacturer" => "ADVANCED SOLUTIONS",
                "manufacturer_id" => 7
            ]
        ],
        [
            "product_id" => 4,
            "quantity" => 1,
            "size" => "25.00",
            "product" => [
                "id" => 4,
                "name" => "Amchoplast",
                "sku" => "ACZ-AMCHOPLAST",
                "q_code" => "Q4316",
                "manufacturer" => "ADVANCED SOLUTIONS",
                "manufacturer_id" => 7
            ]
        ]
    ],
    "manufacturer_fields" => [],
    "docuseal_submission_id" => "",
    "delivery_date" => "2025-08-03",
    "patient_member_id" => "MED123456789",
    "patient_address_line1" => "123 Main Street",
    "patient_address_line2" => "Apt 4B",
    "patient_city" => "New York",
    "patient_state" => "NY",
    "patient_zip" => "10001",
    "patient_phone" => "(555) 123-4567",
    "patient_email" => "john.doe@email.com",
    "primary_payer_phone" => "1-800-457-4708",
    "wound_location_details" => "Plantar surface, first metatarsal head",
    "primary_diagnosis_code" => "E11.621",
    "secondary_diagnosis_code" => "L97.103",
    "wound_duration_weeks" => "6",
    "wound_duration_days" => "2",
    "prior_applications" => "0",
    "prior_application_product" => "Standard dressing",
    "prior_application_within_12_months" => false,
    "anticipated_applications" => "4",
    "medicare_part_b_authorized" => false,
    "hospice_status" => true,
    "part_a_status" => false,
    "global_period_status" => false,
    "primary_payer_id" => "5",
    "application_cpt_codes_other" => "223",
    "previous_treatments_selected" => [
        "wound_bed_preparation__wbp_" => true,
        "infection_control" => true
    ],
    "hospice_clinically_necessary" => true,
    "hospice_family_consent" => true
];

echo "Real Data Structure Analysis:\n";
echo "============================\n";
echo "Total fields: " . count($realData) . "\n\n";

// Show key fields that should map to Advanced Solution IVR
echo "Key Fields for Advanced Solution IVR:\n";
echo "=====================================\n";
$keyFields = [
    'patient_name', 'patient_first_name', 'patient_last_name', 'patient_dob',
    'patient_phone', 'patient_address_line1', 'patient_address_line2', 'patient_city', 'patient_state', 'patient_zip',
    'primary_insurance_name', 'primary_member_id', 'primary_plan_type',
    'provider_npi', 'place_of_service', 'wound_type', 'wound_types',
    'wound_size_length', 'wound_size_width', 'wound_size_depth',
    'application_cpt_codes', 'primary_diagnosis_code', 'secondary_diagnosis_code',
    'expected_service_date', 'selected_products'
];

foreach ($keyFields as $field) {
    if (isset($realData[$field])) {
        $value = $realData[$field];
        if (is_array($value)) {
            echo "  {$field}: " . json_encode($value) . "\n";
        } else {
            echo "  {$field}: {$value}\n";
        }
    } else {
        echo "  {$field}: MISSING\n";
    }
}
echo "\n";

// Test the transformation with real data
echo "=== Testing Real Data Transformation ===\n";
$docusealService = app(DocusealService::class);

try {
    $mappedFields = $docusealService->debugAdvancedSolutionIVRMapping($realData);
    
    echo "✅ Transformation completed successfully!\n";
    echo "Mapped fields count: " . count($mappedFields) . "\n\n";
    
    // Show what fields were actually mapped
    echo "Mapped Fields:\n";
    echo "==============\n";
    foreach ($mappedFields as $fieldName => $value) {
        echo "  '{$fieldName}' => '{$value}'\n";
    }
    echo "\n";
    
    // Check for missing critical fields
    $criticalFields = [
        'Patient Name', 'Patient DOB', 'Patient Phone', 'Patient Address',
        'Primary Insurance Name', 'Primary Policy Number', 'Physician NPI',
        'Wound Size', 'CPT Codes', 'Date of Service', 'ICD-10 Diagnosis Codes'
    ];
    
    echo "Critical Fields Check:\n";
    echo "=====================\n";
    foreach ($criticalFields as $field) {
        if (isset($mappedFields[$field])) {
            echo "✅ {$field}: '{$mappedFields[$field]}'\n";
        } else {
            echo "❌ {$field}: MISSING\n";
        }
    }
    echo "\n";
    
    // Analyze data mapping issues
    echo "Data Mapping Issues:\n";
    echo "===================\n";
    
    // Check field name mismatches
    $fieldMappings = [
        'patient_name' => 'Patient Name',
        'patient_dob' => 'Patient DOB',
        'patient_phone' => 'Patient Phone',
        'patient_address_line1' => 'Patient Address',
        'primary_insurance_name' => 'Primary Insurance Name',
        'primary_member_id' => 'Primary Policy Number',
        'provider_npi' => 'Physician NPI',
        'wound_type' => 'Wound Type',
        'application_cpt_codes' => 'CPT Codes',
        'expected_service_date' => 'Date of Service',
        'primary_diagnosis_code' => 'ICD-10 Diagnosis Codes'
    ];
    
    foreach ($fieldMappings as $sourceField => $targetField) {
        if (isset($realData[$sourceField])) {
            $sourceValue = $realData[$sourceField];
            if (isset($mappedFields[$targetField])) {
                $targetValue = $mappedFields[$targetField];
                echo "✅ {$sourceField} -> {$targetField}: '{$sourceValue}' -> '{$targetValue}'\n";
            } else {
                echo "❌ {$sourceField} -> {$targetField}: '{$sourceValue}' -> MISSING\n";
            }
        } else {
            echo "❌ {$sourceField}: SOURCE MISSING\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Transformation failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Analysis Complete ===\n"; 
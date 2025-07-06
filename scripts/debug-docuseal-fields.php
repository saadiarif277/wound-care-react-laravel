#!/usr/bin/env php
<?php

/*
 * Debug Script: Check what fields are being sent to Docuseal
 * 
 * This script simulates the Docuseal field mapping to see what
 * data is actually being prepared and sent to the Docuseal API.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Sample form data similar to what would come from the frontend
$sampleFormData = [
    // Patient Information
    'patient_first_name' => 'John',
    'patient_last_name' => 'Doe',
    'patient_name' => 'John Doe',
    'patient_dob' => '1980-01-15',
    'patient_gender' => 'Male',
    'patient_phone' => '555-123-4567',
    'patient_email' => 'john.doe@example.com',
    'patient_address_line1' => '123 Main St',
    'patient_city' => 'Anytown',
    'patient_state' => 'NY',
    'patient_zip' => '12345',

    // Provider Information
    'provider_name' => 'Dr. Jane Smith',
    'provider_npi' => '1234567890',
    'provider_ptan' => 'ABC123',
    'provider_email' => 'dr.smith@clinic.com',
    'provider_credentials' => 'MD',

    // Facility Information
    'facility_name' => 'Main Street Clinic',
    'facility_npi' => '0987654321',
    'facility_ptan' => 'XYZ789',
    'facility_address' => '456 Oak Ave, Anytown, NY 12345',
    'facility_phone' => '555-987-6543',

    // Insurance Information
    'primary_insurance_name' => 'Blue Cross Blue Shield',
    'primary_member_id' => 'BC123456789',
    'primary_plan_type' => 'PPO',

    // Clinical Information
    'wound_type' => 'Diabetic Ulcer',
    'wound_location' => 'Left Foot',
    'wound_size_length' => '3.5',
    'wound_size_width' => '2.0',
    'wound_size_depth' => '0.5',
    'wound_size_total' => 7.0,
    'wound_dimensions' => '3.5 × 2.0 × 0.5 cm',

    // Diagnosis Codes
    'primary_diagnosis_code' => 'E11.621',
    'secondary_diagnosis_code' => 'L97.421',
    'diagnosis_code' => 'E11.621',

    // Product Information
    'product_name' => 'Amnio AMP',
    'product_code' => 'Q4162',
    'product_manufacturer' => 'MEDLIFE SOLUTIONS',
    'manufacturer_id' => 5,

    // Service Information
    'expected_service_date' => '2024-01-20',
    'service_date' => '2024-01-20',

    // Additional fields that might be in the form
    'wound_duration_days' => '',
    'wound_duration_weeks' => '3',
    'wound_duration_months' => '',
    'wound_duration_years' => '',
    'prior_applications' => '2',
    'prior_application_product' => 'Previous Graft',
    'prior_application_within_12_months' => true,
    'hospice_status' => false,
    'hospice_family_consent' => false,
    'hospice_clinically_necessary' => false,

    // Manufacturer specific fields
    'manufacturer_fields' => [
        'field1' => 'Yes',
        'field2' => 'No',
        'special_requirement' => true
    ]
];

echo "=== DOCUSEAL FIELD MAPPING DEBUG ===\n\n";

echo "1. Sample Form Data (as it comes from frontend):\n";
echo "   Total fields: " . count($sampleFormData) . "\n";
echo "   Sample fields:\n";
foreach (array_slice($sampleFormData, 0, 10, true) as $key => $value) {
    $displayValue = is_array($value) ? '[Array]' : (string)$value;
    echo "     {$key}: {$displayValue}\n";
}
echo "\n";

// Load MEDLIFE SOLUTIONS configuration
$medlifeConfig = include __DIR__ . '/../config/manufacturers/medlife-solutions.php';

echo "2. MEDLIFE SOLUTIONS Template Configuration:\n";
echo "   Template ID: {$medlifeConfig['docuseal_template_id']}\n";
echo "   Signature Required: " . ($medlifeConfig['signature_required'] ? 'Yes' : 'No') . "\n";
echo "   Supports Insurance Upload: " . ($medlifeConfig['supports_insurance_upload_in_ivr'] ? 'Yes' : 'No') . "\n";
echo "   Configured Fields: " . count($medlifeConfig['docuseal_field_names']) . "\n\n";

echo "3. Field Mapping Analysis:\n";
$mappedFields = [];
$unmappedFields = [];

// Check which form fields would be mapped to Docuseal fields
foreach ($medlifeConfig['docuseal_field_names'] as $sourceField => $docusealField) {
    if (array_key_exists($sourceField, $sampleFormData)) {
        $mappedFields[$sourceField] = [
            'docuseal_field' => $docusealField,
            'value' => $sampleFormData[$sourceField],
            'type' => gettype($sampleFormData[$sourceField])
        ];
    } else {
        $unmappedFields[] = [
            'source_field' => $sourceField,
            'docuseal_field' => $docusealField,
            'status' => 'NO DATA'
        ];
    }
}

echo "   Successfully Mapped Fields (" . count($mappedFields) . "):\n";
foreach ($mappedFields as $sourceField => $mapping) {
    $value = is_bool($mapping['value']) ? ($mapping['value'] ? 'true' : 'false') : $mapping['value'];
    echo "     {$sourceField} → '{$mapping['docuseal_field']}' = {$value}\n";
}

echo "\n   Fields Missing Data (" . count($unmappedFields) . "):\n";
foreach (array_slice($unmappedFields, 0, 10) as $field) {
    echo "     {$field['source_field']} → '{$field['docuseal_field']}' = [MISSING]\n";
}
if (count($unmappedFields) > 10) {
    echo "     ... and " . (count($unmappedFields) - 10) . " more missing fields\n";
}

echo "\n4. Form Data Not Mapped to Template:\n";
$extraFields = [];
foreach ($sampleFormData as $key => $value) {
    if (!array_key_exists($key, $medlifeConfig['docuseal_field_names'])) {
        $extraFields[] = $key;
    }
}

echo "   Unmapped Form Fields (" . count($extraFields) . "):\n";
foreach (array_slice($extraFields, 0, 15) as $field) {
    $value = is_array($sampleFormData[$field]) ? '[Array]' : $sampleFormData[$field];
    echo "     {$field} = {$value}\n";
}
if (count($extraFields) > 15) {
    echo "     ... and " . (count($extraFields) - 15) . " more unmapped fields\n";
}

echo "\n5. Summary:\n";
echo "   Total form fields: " . count($sampleFormData) . "\n";
echo "   Successfully mapped: " . count($mappedFields) . "\n";
echo "   Missing data: " . count($unmappedFields) . "\n";
echo "   Unmapped form fields: " . count($extraFields) . "\n";
echo "   Mapping efficiency: " . round((count($mappedFields) / count($medlifeConfig['docuseal_field_names'])) * 100, 1) . "%\n";

echo "\n6. Docuseal Payload Preview:\n";
$docusealPayload = [];
foreach ($mappedFields as $sourceField => $mapping) {
    $value = $mapping['value'];
    
    // Apply transformations that would happen in the actual service
    if ($value === null) {
        $value = '';
    } elseif (is_array($value)) {
        $value = implode(', ', $value);
    } elseif (is_bool($value)) {
        $value = $value ? 'Yes' : 'No';
    }
    
    $docusealPayload[$mapping['docuseal_field']] = $value;
}

echo "   Fields that would be sent to Docuseal (" . count($docusealPayload) . "):\n";
foreach (array_slice($docusealPayload, 0, 20, true) as $field => $value) {
    echo "     '{$field}': {$value}\n";
}
if (count($docusealPayload) > 20) {
    echo "     ... and " . (count($docusealPayload) - 20) . " more fields\n";
}

echo "\n=== END DEBUG ===\n";

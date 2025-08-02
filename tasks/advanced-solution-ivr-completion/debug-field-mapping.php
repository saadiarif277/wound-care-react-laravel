<?php

/**
 * Debug script to see exactly what field names are being produced
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\DocusealService;

echo "=== Advanced Solution Field Mapping Debug ===\n\n";

// Test data
$testData = [
    'patient_first_name' => 'John',
    'patient_last_name' => 'Doe',
    'patient_name' => 'John Doe',
    'patient_dob' => '1985-03-15',
    'patient_phone' => '555-123-4567',
    'patient_address_line1' => '123 Main St',
    'provider_name' => 'Dr. Smith',
    'provider_npi' => '1234567890',
    'facility_name' => 'Test Facility',
    'facility_npi' => '0987654321',
    'place_of_service' => 'office',
    'primary_insurance_name' => 'Blue Cross',
    'primary_subscriber_name' => 'John Doe',
    'primary_policy_number' => 'POL123456',
    'primary_plan_type' => 'ppo',
    'physician_status_primary' => 'in_network',
    'wound_type' => 'diabetic_foot_ulcer',
    'wound_size_cm2' => '25.5',
    'cpt_codes' => '97597, 97602',
    'date_of_service' => '2025-01-15',
    'icd10_diagnosis_codes' => 'E11.621, L97.509',
    'selected_products' => ['complete_aa', 'membrane_wrap_hydro'],
    'ok_to_contact_patient' => true,
    'patient_in_snf' => false,
    'patient_under_global' => false,
    'prior_auth_required' => true,
];

echo "Test Data:\n";
foreach ($testData as $key => $value) {
    echo "  {$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
}
echo "\n";

// Test 1: Test the Advanced Solution transformation method directly
echo "=== Test 1: Direct Advanced Solution Transformation ===\n";
$docusealService = app(DocusealService::class);

try {
    $mappedFields = $docusealService->debugAdvancedSolutionIVRMapping($testData);
    
    echo "✅ Advanced Solution transformation completed successfully\n";
    echo "Mapped fields count: " . count($mappedFields) . "\n\n";
    
    // Show all mapped fields
    echo "All mapped fields:\n";
    foreach ($mappedFields as $fieldName => $value) {
        echo "  '{$fieldName}' => '{$value}'\n";
    }
    
    // Check for specific expected fields
    echo "\n=== Expected Field Check ===\n";
    $expectedFields = [
        'Patient Name',
        'Patient DOB',
        'Patient Phone',
        'Patient Address',
        'Physician Name',
        'Facility Name',
        'Primary Insurance Name',
        'Diabetic Foot Ulcer',
        'Complete AA',
        'Membrane Wrap Hydro'
    ];
    
    foreach ($expectedFields as $expectedField) {
        if (isset($mappedFields[$expectedField])) {
            echo "✅ Found '{$expectedField}': '{$mappedFields[$expectedField]}'\n";
        } else {
            echo "❌ Missing '{$expectedField}'\n";
        }
    }
    
    // Check for any "Patient Full Name" references
    echo "\n=== Patient Full Name Check ===\n";
    $fullNameFound = false;
    foreach ($mappedFields as $fieldName => $value) {
        if (strpos($fieldName, 'Patient Full Name') !== false) {
            echo "❌ Found 'Patient Full Name' reference: '{$fieldName}' => '{$value}'\n";
            $fullNameFound = true;
        }
    }
    
    if (!$fullNameFound) {
        echo "✅ No 'Patient Full Name' references found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Advanced Solution transformation failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

// Test 2: Test the specific mappings method
echo "\n=== Test 2: Specific Mappings Method ===\n";
try {
    $reflection = new ReflectionClass($docusealService);
    $method = $reflection->getMethod('applyAdvancedSolutionSpecificMappings');
    $method->setAccessible(true);
    
    $docusealFields = [];
    $specificMappings = $method->invoke($docusealService, $docusealFields, $testData);
    
    echo "✅ Specific mappings method completed successfully\n";
    echo "Specific mappings count: " . count($specificMappings) . "\n\n";
    
    // Show all specific mappings
    echo "All specific mappings:\n";
    foreach ($specificMappings as $fieldName => $value) {
        echo "  '{$fieldName}' => '{$value}'\n";
    }
    
    // Check for Patient Name in specific mappings
    if (isset($specificMappings['Patient Name'])) {
        echo "\n✅ Patient Name found in specific mappings: '{$specificMappings['Patient Name']}'\n";
    } else {
        echo "\n❌ Patient Name NOT found in specific mappings\n";
    }
    
} catch (Exception $e) {
    echo "❌ Specific mappings method failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

// Test 3: Check the configuration field mappings
echo "\n=== Test 3: Configuration Field Mappings ===\n";
$config = config('manufacturers.advanced-solution');
if ($config && isset($config['docuseal_field_names'])) {
    echo "Configuration field mappings:\n";
    foreach ($config['docuseal_field_names'] as $canonicalField => $docusealField) {
        if (strpos($canonicalField, 'patient') !== false) {
            echo "  '{$canonicalField}' => '{$docusealField}'\n";
        }
    }
} else {
    echo "❌ Configuration not found or missing docuseal_field_names\n";
}

echo "\n=== Debug Complete ===\n"; 
<?php

/**
 * Test script to verify Advanced Solution IVR field mapping
 * This will help identify where "Patient Full Name" error is coming from
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\DocusealService;
use Illuminate\Support\Facades\Log;

echo "=== Advanced Solution IVR Field Mapping Test ===\n\n";

// Test data that should work with Advanced Solution IVR
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

// Test 1: Check manufacturer mapping
echo "=== Test 1: Manufacturer Mapping ===\n";
$docusealService = app(DocusealService::class);

// Use reflection to access private method
$reflection = new ReflectionClass($docusealService);
$method = $reflection->getMethod('findManufacturerByTemplateId');
$method->setAccessible(true);
$manufacturerName = $method->invoke($docusealService, '1199885');
echo "Template ID 1199885 maps to manufacturer: {$manufacturerName}\n\n";

// Test 2: Check manufacturer config
echo "=== Test 2: Manufacturer Config ===\n";
$manufacturerConfig = config('manufacturers.advanced-solution');
if ($manufacturerConfig) {
    echo "Advanced Solution config found\n";
    echo "Manufacturer ID: {$manufacturerConfig['id']}\n";
    echo "Manufacturer Name: {$manufacturerConfig['name']}\n";
    echo "IVR Template ID: " . ($manufacturerConfig['ivr_template_id'] ?? 'NOT SET') . "\n";
    echo "Field mappings count: " . count($manufacturerConfig['docuseal_field_names'] ?? []) . "\n";

    // Check patient name mapping
    $patientNameMapping = $manufacturerConfig['docuseal_field_names']['patient_name'] ?? 'NOT FOUND';
    echo "Patient name maps to: {$patientNameMapping}\n";
} else {
    echo "ERROR: Advanced Solution config not found!\n";
}
echo "\n";

// Test 3: Test the transformation method directly
echo "=== Test 3: Direct Transformation Test ===\n";
try {
    $mappedFields = $docusealService->debugAdvancedSolutionIVRMapping($testData);
    echo "Transformation successful!\n";
    echo "Mapped fields count: " . count($mappedFields) . "\n";

    // Check for Patient Name field
    if (isset($mappedFields['Patient Name'])) {
        echo "✅ Patient Name field found: {$mappedFields['Patient Name']}\n";
    } else {
        echo "❌ Patient Name field NOT found in mapped fields\n";
    }

    // Check for any "Patient Full Name" references
    $fullNameFound = false;
    foreach ($mappedFields as $fieldName => $value) {
        if (strpos($fieldName, 'Patient Full Name') !== false) {
            echo "❌ Found 'Patient Full Name' reference: {$fieldName} = {$value}\n";
            $fullNameFound = true;
        }
    }

    if (!$fullNameFound) {
        echo "✅ No 'Patient Full Name' references found in mapped fields\n";
    }

    // Show first 10 mapped fields
    echo "\nFirst 10 mapped fields:\n";
    $count = 0;
    foreach ($mappedFields as $fieldName => $value) {
        if ($count >= 10) break;
        echo "  {$fieldName}: {$value}\n";
        $count++;
    }

} catch (Exception $e) {
    echo "❌ Transformation failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
echo "\n";

// Test 4: Test the transformQuickRequestData method
echo "=== Test 4: transformQuickRequestData Test ===\n";
try {
    // Use reflection to access private method
    $reflection = new ReflectionClass($docusealService);
    $method = $reflection->getMethod('transformQuickRequestData');
    $method->setAccessible(true);

    $mappedFields = $method->invoke($docusealService, $testData, '1199885', 'ADVANCED SOLUTION');
    echo "transformQuickRequestData successful!\n";
    echo "Mapped fields count: " . count($mappedFields) . "\n";

    // Check for Patient Name field
    if (isset($mappedFields['Patient Name'])) {
        echo "✅ Patient Name field found: {$mappedFields['Patient Name']}\n";
    } else {
        echo "❌ Patient Name field NOT found in mapped fields\n";
    }

    // Check for any "Patient Full Name" references
    $fullNameFound = false;
    foreach ($mappedFields as $fieldName => $value) {
        if (strpos($fieldName, 'Patient Full Name') !== false) {
            echo "❌ Found 'Patient Full Name' reference: {$fieldName} = {$value}\n";
            $fullNameFound = true;
        }
    }

    if (!$fullNameFound) {
        echo "✅ No 'Patient Full Name' references found in mapped fields\n";
    }

} catch (Exception $e) {
    echo "❌ transformQuickRequestData failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
echo "\n";

// Test 5: Check if there are any other manufacturer configs that might be interfering
echo "=== Test 5: Check for Conflicting Configs ===\n";
$configFiles = glob(__DIR__ . '/../../config/manufacturers/*.php');
foreach ($configFiles as $configFile) {
    $configName = basename($configFile, '.php');
    $config = require $configFile;

    if (isset($config['docuseal_field_names']['patient_name'])) {
        $patientNameMapping = $config['docuseal_field_names']['patient_name'];
        echo "Config {$configName}: patient_name maps to '{$patientNameMapping}'\n";

        if ($patientNameMapping === 'Patient Full Name') {
            echo "❌ CONFLICT FOUND: {$configName} uses 'Patient Full Name'!\n";
        }
    }
}
echo "\n";

echo "=== Test Complete ===\n";

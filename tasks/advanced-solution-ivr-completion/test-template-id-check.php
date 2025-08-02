<?php

/**
 * Test script to verify template ID check and transformation method selection
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\DocusealService;

echo "=== Template ID Check Test ===\n\n";

// Test data
$testData = [
    'patient_name' => 'John Doe',
    'patient_dob' => '1985-03-15',
    'provider_name' => 'Dr. Smith',
    'facility_name' => 'Test Facility',
];

echo "Test Data:\n";
foreach ($testData as $key => $value) {
    echo "  {$key}: {$value}\n";
}
echo "\n";

// Test 1: Check template ID mapping
echo "=== Test 1: Template ID Mapping ===\n";
$docusealService = app(DocusealService::class);

// Use reflection to access private method
$reflection = new ReflectionClass($docusealService);
$method = $reflection->getMethod('findManufacturerByTemplateId');
$method->setAccessible(true);

$templateId = '1199885';
$manufacturerName = $method->invoke($docusealService, $templateId);
echo "Template ID {$templateId} maps to manufacturer: '{$manufacturerName}'\n";

// Test 2: Check the condition in transformQuickRequestData
echo "\n=== Test 2: Condition Check ===\n";
$condition1 = $templateId == '1199885';
$condition2 = $manufacturerName === 'ADVANCED SOLUTION';
$combinedCondition = $condition1 && $condition2;

echo "Template ID == '1199885': " . ($condition1 ? '✅ TRUE' : '❌ FALSE') . "\n";
echo "Manufacturer Name === 'ADVANCED SOLUTION': " . ($condition2 ? '✅ TRUE' : '❌ FALSE') . "\n";
echo "Combined condition: " . ($combinedCondition ? '✅ TRUE' : '❌ FALSE') . "\n";

if ($combinedCondition) {
    echo "✅ The Advanced Solution specific transformation method should be called\n";
} else {
    echo "❌ The system will fall back to generic field mappings\n";
}

// Test 3: Test transformQuickRequestData with exact parameters
echo "\n=== Test 3: transformQuickRequestData Test ===\n";
try {
    $method = $reflection->getMethod('transformQuickRequestData');
    $method->setAccessible(true);
    
    $mappedFields = $method->invoke($docusealService, $testData, $templateId, $manufacturerName);
    
    echo "✅ transformQuickRequestData completed successfully\n";
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
    echo "❌ transformQuickRequestData failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

// Test 4: Test with different manufacturer name variations
echo "\n=== Test 4: Manufacturer Name Variations ===\n";
$variations = [
    'ADVANCED SOLUTION',
    'Advanced Solution',
    'advanced solution',
    'ADVANCED_SOLUTION',
    'Advanced-Solution'
];

foreach ($variations as $variation) {
    $condition = $variation === 'ADVANCED SOLUTION';
    echo "Manufacturer: '{$variation}' === 'ADVANCED SOLUTION': " . ($condition ? '✅ TRUE' : '❌ FALSE') . "\n";
}

// Test 5: Check if there are any other conditions that might be interfering
echo "\n=== Test 5: Other Template Conditions ===\n";
$otherTemplates = [
    '852440' => 'ACZ & ASSOCIATES',
    '1233913' => 'MEDLIFE SOLUTIONS',
    '1330769' => 'CELULARITY',
    '1234285' => 'EXTREMITY CARE LLC'
];

foreach ($otherTemplates as $templateId => $expectedManufacturer) {
    $actualManufacturer = $method->invoke($docusealService, $templateId);
    $match = $actualManufacturer === $expectedManufacturer;
    echo "Template {$templateId}: expected '{$expectedManufacturer}', got '{$actualManufacturer}' - " . ($match ? '✅ MATCH' : '❌ MISMATCH') . "\n";
}

echo "\n=== Test Complete ===\n"; 
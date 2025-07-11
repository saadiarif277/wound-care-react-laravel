<?php

/**
 * Test Advanced Solution manufacturer field mapping
 * Run: php tests/scripts/test-advanced-solution.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Medical\OptimizedMedicalAiService;
use App\Services\DocuSeal\DocuSealTemplateDiscoveryService;
use App\Services\DocusealService;

echo "Testing Advanced Solution Field Mapping\n";
echo "======================================\n\n";

// Advanced Solution template ID from logs
$templateId = '1199885';
$manufacturerName = 'ADVANCED SOLUTION';

// Step 1: Test template discovery
echo "1. Testing template discovery for Advanced Solution:\n";
$templateDiscovery = app(DocuSealTemplateDiscoveryService::class);
try {
    $templateFields = $templateDiscovery->getCachedTemplateStructure($templateId);
    echo "   ✅ Template fetched successfully\n";
    echo "   Total fields: " . count($templateFields['field_names']) . "\n";
    echo "   Template name: " . $templateFields['name'] . "\n\n";
    
    // Show first 10 fields
    echo "   Sample fields:\n";
    $sampleFields = array_slice($templateFields['field_names'], 0, 10);
    foreach ($sampleFields as $field) {
        echo "     - $field\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "   ❌ Failed to fetch template: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 2: Load manufacturer config
echo "2. Loading manufacturer config:\n";
$configPath = config_path('manufacturers/advanced-solution.php');
if (file_exists($configPath)) {
    $manufacturerConfig = require $configPath;
    echo "   ✅ Config loaded successfully\n";
    echo "   Field mappings: " . count($manufacturerConfig['docuseal_field_names'] ?? []) . "\n";
    echo "   Field definitions: " . count($manufacturerConfig['fields'] ?? []) . "\n\n";
} else {
    echo "   ❌ Config file not found\n";
    exit(1);
}

// Step 3: Validate field mappings
echo "3. Validating field mappings:\n";
$validMappings = 0;
$invalidMappings = [];

foreach ($manufacturerConfig['docuseal_field_names'] as $key => $docusealFieldName) {
    if (in_array($docusealFieldName, $templateFields['field_names'])) {
        $validMappings++;
    } else {
        $invalidMappings[$key] = $docusealFieldName;
    }
}

echo "   Valid mappings: $validMappings\n";
echo "   Invalid mappings: " . count($invalidMappings) . "\n";

if (count($invalidMappings) > 0 && count($invalidMappings) <= 10) {
    echo "\n   Invalid field names:\n";
    foreach ($invalidMappings as $key => $fieldName) {
        echo "     - $key => '$fieldName' (NOT in template)\n";
    }
}

// Step 4: Test with DocusealService
echo "\n4. Testing with DocusealService:\n";
$docusealService = app(DocusealService::class);

// Prepare test data
$testData = [
    'patient_first_name' => 'John',
    'patient_last_name' => 'Doe',
    'patient_dob' => '1980-01-15',
    'patient_phone' => '5551234567',
    'patient_address_line1' => '123 Main St',
    'patient_city' => 'New York',
    'patient_state' => 'NY',
    'patient_zip' => '10001',
    'primary_insurance_name' => 'Blue Cross Blue Shield',
    'primary_member_id' => 'ABC123456',
    'facility_name' => 'Main Street Medical Center',
    'facility_npi' => '1234567890',
    'facility_address_line1' => '456 Healthcare Blvd',
    'facility_city' => 'New York',
    'facility_state' => 'NY',
    'facility_zip' => '10002',
    'facility_phone' => '5559876543',
    'provider_name' => 'Dr. Smith',
    'provider_npi' => '9876543210',
    'wound_type' => 'Diabetic Foot Ulcer',
    'wound_size_length' => '2',
    'wound_size_width' => '3',
    'wound_size_depth' => '0.5',
    'primary_diagnosis_code' => 'L97.521',
    'procedure_date' => '2024-01-20',
    'place_of_service' => '11'
];

// Skip direct field preparation test since method doesn't exist
echo "   Skipping direct field preparation test\n";

// Step 5: Test AI enhancement
echo "\n5. Testing AI enhancement:\n";
$aiService = app(OptimizedMedicalAiService::class);
try {
    $aiResult = $aiService->enhanceWithDynamicTemplate(
        $testData,
        $templateId,
        $manufacturerName,
        ['test_mode' => true]
    );
    
    echo "   ✅ AI enhancement completed\n";
    echo "   Method: " . ($aiResult['_ai_method'] ?? 'unknown') . "\n";
    echo "   Confidence: " . ($aiResult['_ai_confidence'] ?? 0) . "\n";
    
    $enhancedFields = $aiResult['enhanced_fields'] ?? $aiResult;
    $actualFields = array_filter($enhancedFields, function($key) {
        return !str_starts_with($key, '_');
    }, ARRAY_FILTER_USE_KEY);
    
    echo "   Enhanced fields: " . count($actualFields) . "\n";
    echo "   Fill rate: " . round((count($actualFields) / count($templateFields['field_names'])) * 100, 1) . "%\n";
    
} catch (Exception $e) {
    echo "   ❌ AI enhancement failed: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
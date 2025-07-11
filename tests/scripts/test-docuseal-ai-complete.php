<?php

/**
 * Complete test of DocuSeal AI enhancement flow
 * Run: php tests/scripts/test-docuseal-ai-complete.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Medical\OptimizedMedicalAiService;
use App\Services\DocuSeal\DocuSealTemplateDiscoveryService;

echo "Testing Complete DocuSeal AI Enhancement Flow\n";
echo "============================================\n\n";

// Step 1: Use known Celularity template
echo "1. Using Celularity template:\n";
$manufacturerName = 'Celularity';
$templateId = '1233913'; // Known Celularity template ID from previous tests
echo "   ✅ Manufacturer: $manufacturerName\n";
echo "   Template ID: $templateId\n\n";

// Step 2: Test template discovery
echo "2. Testing template discovery:\n";
$templateDiscovery = app(DocuSealTemplateDiscoveryService::class);
try {
    $templateFields = $templateDiscovery->getCachedTemplateStructure($templateId);
    echo "   ✅ Template fetched successfully\n";
    echo "   Total fields: " . count($templateFields['field_names']) . "\n";
    echo "   Required fields: " . count($templateFields['required_fields']) . "\n\n";
} catch (Exception $e) {
    echo "   ❌ Failed to fetch template: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 3: Prepare form data
echo "3. Preparing comprehensive form data:\n";
$formData = [
    // Patient information
    'patient_first_name' => 'John',
    'patient_last_name' => 'Doe',
    'patient_dob' => '1980-01-15',
    'patient_phone' => '5551234567',
    'patient_address_line1' => '123 Main St',
    'patient_address_line2' => 'Apt 4B',
    'patient_city' => 'New York',
    'patient_state' => 'NY',
    'patient_zip' => '10001',
    'patient_gender' => 'Male',
    'patient_ssn_last4' => '1234',
    
    // Insurance information
    'primary_insurance_name' => 'Blue Cross Blue Shield',
    'primary_member_id' => 'ABC123456789',
    'primary_plan_type' => 'PPO',
    'primary_group_number' => 'GRP9876',
    'secondary_insurance_name' => 'Medicare',
    'secondary_member_id' => '1EG4-TE5-MK72',
    
    // Provider information
    'provider_name' => 'Dr. Sarah Smith',
    'provider_npi' => '1234567890',
    'provider_phone' => '5559876543',
    'provider_fax' => '5559876544',
    'provider_ptan' => 'A12345',
    
    // Facility information
    'facility_name' => 'Main Street Medical Center',
    'facility_npi' => '9876543210',
    'facility_address' => '456 Healthcare Blvd',
    'facility_city' => 'New York',
    'facility_state' => 'NY',
    'facility_zip' => '10002',
    'facility_phone' => '5551112222',
    'facility_fax' => '5551112223',
    'facility_tax_id' => '12-3456789',
    'facility_ptan' => 'B67890',
    
    // Clinical information
    'wound_location' => 'Left Lower Extremity - Foot',
    'wound_type' => 'Diabetic Foot Ulcer',
    'wound_size_length' => '2.5',
    'wound_size_width' => '3.0',
    'wound_size_depth' => '0.5',
    'primary_diagnosis_code' => 'L97.521',
    'secondary_diagnosis_code' => 'E11.621',
    'wound_duration_weeks' => '6',
    'procedure_date' => '2024-01-20',
    
    // Product information
    'product_name' => 'Biovance',
    'product_quantity' => '1',
    'product_size' => '2x3 cm',
    'product_code' => 'Q4102',
    
    // Additional fields
    'place_of_service' => '11',
    'expected_service_date' => '2024-01-25',
    'special_instructions' => 'Rush order - patient scheduled for procedure'
];

echo "   ✅ Prepared " . count($formData) . " form fields\n\n";

// Step 4: Test AI enhancement
echo "4. Testing AI enhancement:\n";
$aiService = app(OptimizedMedicalAiService::class);
try {
    $aiResult = $aiService->enhanceWithDynamicTemplate(
        $formData,
        $templateId,
        $manufacturerName,
        [
            'source' => 'test_script',
            'test_mode' => true
        ]
    );
    
    echo "   ✅ AI enhancement completed\n";
    echo "   Method: " . ($aiResult['_ai_method'] ?? 'unknown') . "\n";
    echo "   Confidence: " . ($aiResult['_ai_confidence'] ?? 0) . "\n";
    
    // Count enhanced fields
    $enhancedFields = $aiResult['enhanced_fields'] ?? $aiResult;
    $actualFields = array_filter($enhancedFields, function($key) {
        return !str_starts_with($key, '_');
    }, ARRAY_FILTER_USE_KEY);
    
    echo "   Enhanced fields: " . count($actualFields) . "\n";
    echo "   Fill rate: " . round((count($actualFields) / count($templateFields['field_names'])) * 100, 1) . "%\n\n";
    
    // Show sample of enhanced fields
    echo "   Sample enhanced fields:\n";
    $sample = array_slice($actualFields, 0, 10, true);
    foreach ($sample as $field => $value) {
        echo "     - $field: $value\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ AI enhancement failed: " . $e->getMessage() . "\n";
    echo "   Class: " . get_class($e) . "\n";
}

// Step 5: Check what template fields were targeted
echo "\n5. Template field coverage analysis:\n";
if (isset($templateFields['field_names']) && !empty($actualFields)) {
    $coveredFields = 0;
    $missingFields = [];
    
    foreach ($templateFields['field_names'] as $templateField) {
        if (isset($actualFields[$templateField])) {
            $coveredFields++;
        } else {
            $missingFields[] = $templateField;
        }
    }
    
    echo "   Template fields covered: $coveredFields / " . count($templateFields['field_names']) . "\n";
    echo "   Coverage: " . round(($coveredFields / count($templateFields['field_names'])) * 100, 1) . "%\n";
    
    if (count($missingFields) > 0 && count($missingFields) <= 10) {
        echo "\n   Missing fields:\n";
        foreach (array_slice($missingFields, 0, 10) as $field) {
            echo "     - $field\n";
        }
    }
}

// Step 6: Check logs for any errors
echo "\n6. Recent log entries:\n";
$logFile = storage_path('logs/laravel-' . date('Y-m-d') . '.log');
if (file_exists($logFile)) {
    $recentLogs = `tail -50 $logFile | grep -E "DocuSeal|AI|enhance" | tail -10`;
    if ($recentLogs) {
        echo $recentLogs;
    } else {
        echo "   No recent relevant logs found.\n";
    }
}

echo "\nTest completed.\n";
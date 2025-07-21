#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\UnifiedFieldMappingService;
use App\Services\DocusealService;
use App\Models\Order\Manufacturer;

echo "=== COMPREHENSIVE RADIO BUTTON DEBUGGING ===\n\n";

// 1. SIMULATE FRONTEND DATA (exactly as sent from React)
echo "STEP 1: FRONTEND DATA (from React components)\n";
echo "=" . str_repeat("=", 60) . "\n";

$frontendData = [
    // Radio button data as sent from frontend
    'hospice_status' => false,  // Boolean from React
    'part_a_status' => false,
    'global_period_status' => false,
    'place_of_service' => '11',  // String value
    
    // Diagnosis data
    'primary_diagnosis_code' => 'E11.621',
    'secondary_diagnosis_code' => 'L97.104',
    'wound_location' => 'trunk_arms_legs_small',
    
    // Contact info
    'contact_name' => 'Test Contact',
    'contact_email' => 'test@example.com',
    
    // Q-codes (checkboxes)
    'q4205' => false,
    'q4290' => false,
    'q4344' => false,
];

echo "Frontend sends (JSON format):\n";
echo json_encode($frontendData, JSON_PRETTY_PRINT) . "\n\n";

// 2. UNIFIED FIELD MAPPING SERVICE
echo "\nSTEP 2: UNIFIED FIELD MAPPING SERVICE\n";
echo "=" . str_repeat("=", 60) . "\n";

$mappingService = app(UnifiedFieldMappingService::class);
$mappingResult = $mappingService->mapEpisodeToTemplate(null, 'ACZ & Associates', $frontendData);

$mappedData = $mappingResult['data'] ?? [];

// Show specifically radio button mappings
$radioButtonFields = [
    'is_the_patient_currently_in_hospice',
    'is_the_patient_in_a_facility_under_part_a_stay',
    'is_the_patient_under_post_op_global_surgery_period',
    'pos_11', 'pos_12', 'pos_22', 'pos_24', 'pos_32', 'pos_other'
];

echo "Radio Button Mappings:\n";
foreach ($radioButtonFields as $field) {
    if (isset($mappedData[$field])) {
        $value = $mappedData[$field];
        $type = gettype($value);
        echo "  ✓ $field => " . var_export($value, true) . " (type: $type)\n";
    } else {
        echo "  ✗ $field => NOT MAPPED\n";
    }
}

// 3. DOCUSEAL FIELD CONVERSION
echo "\n\nSTEP 3: CONVERSION TO DOCUSEAL FORMAT\n";
echo "=" . str_repeat("=", 60) . "\n";

$reflection = new ReflectionClass($mappingService);
$convertMethod = $reflection->getMethod('convertToDocusealFields');
$convertMethod->setAccessible(true);

$manufacturer = Manufacturer::where('name', 'LIKE', '%ACZ%')->first();
$manufacturerConfig = $mappingService->getManufacturerConfig('ACZ & Associates', 'IVR');

$docusealFields = $convertMethod->invoke($mappingService, $mappedData, $manufacturerConfig);

echo "DocuSeal Field Format:\n";
foreach ($docusealFields as $field) {
    if (in_array($field['name'], ['Is the patient currently in hospice?', 'Is the patient in a facility under Part A stay?', 'Is the patient currently under a post-op global surgical period?'])) {
        echo "  Radio: {$field['name']} => {$field['default_value']} (type: " . gettype($field['default_value']) . ")\n";
    }
}

// 4. DOCUSEAL SERVICE TRANSFORMATION
echo "\n\nSTEP 4: DOCUSEAL SERVICE TRANSFORMATION\n";
echo "=" . str_repeat("=", 60) . "\n";

$docusealService = app(DocusealService::class);

// Test the transformQuickRequestData method
try {
    $reflection = new ReflectionClass($docusealService);
    $transformMethod = $reflection->getMethod('transformQuickRequestData');
    $transformMethod->setAccessible(true);
    
    $transformedData = $transformMethod->invoke(
        $docusealService,
        $mappedData,
        '852440', // ACZ template ID
        'ACZ & Associates'
    );
    
    echo "Transformed for DocuSeal API:\n";
    foreach ($transformedData as $fieldName => $value) {
        if (strpos(strtolower($fieldName), 'hospice') !== false || 
            strpos(strtolower($fieldName), 'part a') !== false || 
            strpos(strtolower($fieldName), 'global') !== false) {
            echo "  $fieldName => " . var_export($value, true) . "\n";
        }
    }
} catch (\Exception $e) {
    echo "Error in transformation: " . $e->getMessage() . "\n";
}

// 5. FINAL API FORMAT
echo "\n\nSTEP 5: FINAL DOCUSEAL API FORMAT\n";
echo "=" . str_repeat("=", 60) . "\n";

// Simulate what createDocusealSubmission would send
$finalApiData = [
    'template_id' => '852440',
    'submitters' => [
        [
            'email' => 'provider@example.com',
            'role' => 'First Party',
            'values' => $transformedData ?? []
        ]
    ]
];

echo "What gets sent to DocuSeal API:\n";
echo json_encode($finalApiData, JSON_PRETTY_PRINT) . "\n\n";

// 6. CHECK CONFIG FIELD NAMES
echo "\nSTEP 6: CONFIG FIELD NAME MAPPINGS\n";
echo "=" . str_repeat("=", 60) . "\n";

if ($manufacturerConfig && isset($manufacturerConfig['docuseal_field_names'])) {
    $fieldNames = $manufacturerConfig['docuseal_field_names'];
    
    echo "Key radio button field mappings from config:\n";
    $radioKeys = [
        'is_the_patient_currently_in_hospice',
        'is_the_patient_in_a_facility_under_part_a_stay',
        'is_the_patient_under_post_op_global_surgery_period'
    ];
    
    foreach ($radioKeys as $key) {
        if (isset($fieldNames[$key])) {
            echo "  $key => '{$fieldNames[$key]}'\n";
        } else {
            echo "  $key => NOT IN CONFIG!\n";
        }
    }
}

// 7. VERIFY ACTUAL TEMPLATE FIELDS
echo "\n\nSTEP 7: TEMPLATE FIELD VALIDATION\n";
echo "=" . str_repeat("=", 60) . "\n";

echo "Template ID: 852440\n";
echo "To verify these field names match the actual DocuSeal template:\n";
echo "1. Log into DocuSeal\n";
echo "2. Find template 852440\n";
echo "3. Check exact field names for radio buttons\n";
echo "4. Ensure they match what's in our config\n"; 
#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\UnifiedFieldMappingService;

echo "=== DEBUGGING POS FIELD FILTERING ===\n\n";

$mappingService = app(UnifiedFieldMappingService::class);
$manufacturer = 'ACZ & Associates';
$manufacturerConfig = $mappingService->getManufacturerConfig($manufacturer, 'IVR');

// Test data with just POS fields
$posData = [
    'pos_11' => 'true',
    'pos_12' => 'false',
    'pos_22' => 'false',
    'pos_24' => 'false',
    'pos_32' => 'false',
    'pos_other' => 'false'
];

echo "1. Manufacturer Config Check:\n";
echo "Has docuseal_field_names? " . (isset($manufacturerConfig['docuseal_field_names']) ? 'YES' : 'NO') . "\n";

if (isset($manufacturerConfig['docuseal_field_names'])) {
    echo "\nALL fields in docuseal_field_names:\n";
    $count = 0;
    foreach ($manufacturerConfig['docuseal_field_names'] as $canonical => $docuseal) {
        echo "  [$count] $canonical => '$docuseal'\n";
        $count++;
        if ($count > 70) {
            echo "  ... (truncated)\n";
            break;
        }
    }
    
    echo "\nSpecifically looking for POS fields:\n";
    $posFields = ['pos_11', 'pos_12', 'pos_22', 'pos_24', 'pos_32', 'pos_other'];
    foreach ($posFields as $posField) {
        if (isset($manufacturerConfig['docuseal_field_names'][$posField])) {
            echo "  ✓ $posField => '" . $manufacturerConfig['docuseal_field_names'][$posField] . "'\n";
        } else {
            echo "  ✗ $posField => NOT FOUND\n";
        }
    }
}

echo "\n2. Testing convertToDocusealFields with POS data:\n";
echo "Input data:\n";
foreach ($posData as $key => $value) {
    echo "  $key => '$value'\n";
}

// Enable detailed logging
ini_set('error_reporting', E_ALL);

// Convert fields
$docusealFields = $mappingService->convertToDocusealFields($posData, $manufacturerConfig);

echo "\nOutput fields:\n";
if (empty($docusealFields)) {
    echo "  (empty - all fields were filtered out!)\n";
} else {
    foreach ($docusealFields as $field) {
        echo "  {$field['name']} => {$field['default_value']}\n";
    }
}

// Check if template validation is filtering them out
echo "\n3. Checking template validation:\n";
if (isset($manufacturerConfig['docuseal_template_id'])) {
    echo "Template ID: " . $manufacturerConfig['docuseal_template_id'] . "\n";
    
    // Check if TemplateFieldValidationService is active
    $reflection = new ReflectionClass($mappingService);
    $fieldValidatorProp = $reflection->getProperty('fieldValidator');
    $fieldValidatorProp->setAccessible(true);
    $fieldValidator = $fieldValidatorProp->getValue($mappingService);
    
    if ($fieldValidator) {
        echo "Template field validation is ACTIVE\n";
        echo "This might be filtering out fields not in the actual DocuSeal template\n";
    } else {
        echo "Template field validation is INACTIVE\n";
    }
}

echo "\n✅ Debug complete!\n"; 
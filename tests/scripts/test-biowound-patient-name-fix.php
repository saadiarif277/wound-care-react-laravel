#!/usr/bin/env php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';

// use App\Services\DocusealService; // Removed - replaced with PDF system
use App\Services\UnifiedFieldMappingService;
use Illuminate\Support\Facades\Log;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test data that causes the issue
$testData = [
    'patient_first_name' => 'John',
    'patient_last_name' => 'Doe',
    'patient_dob' => '1965-03-15',
    'patient_gender' => 'male',
    'provider_name' => 'Dr. Smith',
    'provider_npi' => '1234567890',
    'facility_name' => 'Test Facility',
    'primary_insurance_name' => 'Medicare',
    'primary_member_id' => '123456789',
    'wound_location' => 'Left foot',
    'wound_size_length' => '2',
    'wound_size_width' => '3',
    'procedure_date' => '2025-07-15',
    'icd10_codes' => ['E11.621'],
    'cpt_codes' => ['15271'],
];

// Test with BioWound Solutions
$manufacturerName = 'BioWound Solutions';

echo "Testing patient name field mapping for $manufacturerName\n";
echo "==========================================\n\n";

// Initialize services
// $docuSealService = app(DocusealService::class); // Removed - replaced with PDF system
$fieldMappingService = app(UnifiedFieldMappingService::class);

// Get manufacturer config
$manufacturerConfig = $fieldMappingService->getManufacturerConfig($manufacturerName);

if (!$manufacturerConfig) {
    echo "ERROR: Could not find manufacturer config for $manufacturerName\n";
    exit(1);
}

echo "Manufacturer config found:\n";
echo "- Template ID: " . ($manufacturerConfig['docuseal_template_id'] ?? 'NOT SET') . "\n";
echo "- Has patient_name in docuseal_field_names: " . (isset($manufacturerConfig['docuseal_field_names']['patient_name']) ? 'YES' : 'NO') . "\n";
echo "- Patient name computation: " . ($manufacturerConfig['fields']['patient_name']['computation'] ?? 'NOT SET') . "\n\n";

// Map fields
echo "Mapping fields...\n";
$mappingResult = $fieldMappingService->mapEpisodeToTemplate(null, $manufacturerName, $testData);

echo "Mapping result:\n";
echo "- Has patient_name: " . (isset($mappingResult['data']['patient_name']) ? 'YES (' . $mappingResult['data']['patient_name'] . ')' : 'NO') . "\n";
echo "- Has patient_first_name: " . (isset($mappingResult['data']['patient_first_name']) ? 'YES' : 'NO') . "\n";
echo "- Has patient_last_name: " . (isset($mappingResult['data']['patient_last_name']) ? 'YES' : 'NO') . "\n\n";

// Convert to DocuSeal fields
echo "Converting to DocuSeal fields...\n";
$docuSealFields = $fieldMappingService->convertToDocusealFields($mappingResult['data'], $manufacturerConfig);

echo "DocuSeal fields:\n";
$foundPatientName = false;
$foundFirstName = false;
$foundLastName = false;

foreach ($docuSealFields as $field) {
    if ($field['name'] === 'Patient Name') {
        $foundPatientName = true;
        echo "- Found 'Patient Name': " . $field['default_value'] . "\n";
    }
    if (str_contains(strtolower($field['name']), 'first_name')) {
        $foundFirstName = true;
        echo "- Found first name field: " . $field['name'] . " = " . $field['default_value'] . "\n";
    }
    if (str_contains(strtolower($field['name']), 'last_name')) {
        $foundLastName = true;
        echo "- Found last name field: " . $field['name'] . " = " . $field['default_value'] . "\n";
    }
}

echo "\nSummary:\n";
echo "- Patient Name field found: " . ($foundPatientName ? 'YES' : 'NO') . "\n";
echo "- First Name field found: " . ($foundFirstName ? 'YES' : 'NO') . "\n";
echo "- Last Name field found: " . ($foundLastName ? 'YES' : 'NO') . "\n";

if ($foundFirstName || $foundLastName) {
    echo "\n⚠️  WARNING: Individual name fields found that may cause DocuSeal errors!\n";
}

echo "\nDone.\n";
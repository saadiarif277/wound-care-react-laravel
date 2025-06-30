<?php

/**
 * Manual test script to verify DocuSeal field mapping
 * 
 * Run this with: php tests/Manual/test-docuseal-field-mapping.php
 */

require __DIR__ . '/../../vendor/autoload.php';

use App\Services\UnifiedFieldMappingService;
use Illuminate\Support\Facades\Config;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Create service instance
$service = new UnifiedFieldMappingService();

// Test data simulating a quick request form submission
$testFormData = [
    // Patient Information
    'patient_name' => 'John Doe',
    'patient_first_name' => 'John',
    'patient_last_name' => 'Doe',
    'patient_dob' => '1980-01-15',
    'patient_gender' => 'Male',
    'patient_phone' => '555-123-4567',
    'patient_email' => 'john.doe@example.com',
    'patient_address' => '123 Main St',
    'patient_city' => 'Anytown',
    'patient_state' => 'CA',
    'patient_zip' => '12345',
    
    // Provider Information
    'physician_name' => 'Dr. Jane Smith',
    'physician_npi' => '1234567890',
    'physician_phone' => '555-987-6543',
    'physician_fax' => '555-987-6544',
    'physician_specialty' => 'Wound Care',
    
    // Facility Information
    'facility_name' => 'Anytown Medical Center',
    'facility_address' => '456 Hospital Way',
    'facility_city' => 'Anytown',
    'facility_state' => 'CA',
    'facility_zip' => '12345',
    
    // Wound Information
    'wound_type' => 'Diabetic Foot Ulcer',
    'wound_location' => 'Left Foot',
    'wound_size' => '3x4 cm',
    'wound_depth' => '0.5 cm',
    'wound_duration' => '3 months',
    'icd10_codes' => ['E11.621', 'L97.529'],
    
    // Product Information
    'product_name' => 'Amnio AMP',
    'product_size' => '2x2 cm',
    'product_quantity' => 1,
    'manufacturer_name' => 'MedLife Solutions',
    
    // Insurance Information
    'insurance_type' => 'Medicare',
    'insurance_provider' => 'Medicare Part B',
    'insurance_member_id' => '1EG4-TE5-MK72',
    'insurance_group_number' => 'GROUP123',
];

// Test manufacturers
$manufacturers = [
    'MedLife Solutions',
    'ACZ & Associates',
    'Biowound',
    'Centurion',
    'Extremity Care'
];

echo "=== DocuSeal Field Mapping Test ===\n\n";

foreach ($manufacturers as $manufacturer) {
    echo "Testing manufacturer: $manufacturer\n";
    echo str_repeat('-', 50) . "\n";
    
    try {
        // Get manufacturer config
        $config = $service->getManufacturerConfig($manufacturer);
        
        if (!$config) {
            echo "❌ No configuration found for $manufacturer\n\n";
            continue;
        }
        
        echo "✅ Configuration found\n";
        echo "   Template ID: " . ($config['docuseal_template_id'] ?? 'Not set') . "\n";
        echo "   Field mappings: " . count($config['docuseal_field_names'] ?? []) . "\n";
        
        // Map to canonical fields
        $mappingResult = $service->mapEpisodeToTemplate(
            null, // No episode ID for this test
            $manufacturer,
            $testFormData
        );
        
        echo "   Canonical fields mapped: " . count($mappingResult['data'] ?? []) . "\n";
        echo "   Validation: " . ($mappingResult['validation']['valid'] ? '✅ Valid' : '❌ Invalid') . "\n";
        echo "   Completeness: " . ($mappingResult['completeness']['percentage'] ?? 0) . "%\n";
        
        // Convert to DocuSeal format
        $docuSealFields = $service->convertToDocuSealFields(
            $mappingResult['data'],
            $config
        );
        
        echo "   DocuSeal fields generated: " . count($docuSealFields) . "\n";
        
        // Show sample field mappings
        echo "\n   Sample field mappings:\n";
        $sampleFields = array_slice($docuSealFields, 0, 5);
        foreach ($sampleFields as $field) {
            echo "     - {$field['name']}: {$field['value']}\n";
        }
        
        // Check for critical fields
        $criticalFields = ['Patient Name', 'Physician NPI', 'Product Name'];
        $missingCritical = [];
        
        $fieldNames = array_column($docuSealFields, 'name');
        foreach ($criticalFields as $critical) {
            $found = false;
            foreach ($fieldNames as $fieldName) {
                if (stripos($fieldName, str_replace(' ', '', $critical)) !== false ||
                    stripos($fieldName, $critical) !== false) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missingCritical[] = $critical;
            }
        }
        
        if (empty($missingCritical)) {
            echo "\n   ✅ All critical fields mapped\n";
        } else {
            echo "\n   ⚠️  Missing critical fields: " . implode(', ', $missingCritical) . "\n";
        }
        
    } catch (\Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// Test the actual DocuSeal field format
echo "\n=== DocuSeal Field Format Test ===\n";
echo str_repeat('-', 50) . "\n";

$sampleConfig = $service->getManufacturerConfig('MedLife Solutions');
if ($sampleConfig) {
    $sampleFields = $service->convertToDocuSealFields(
        ['patient_name' => 'John Doe', 'physician_npi' => '1234567890'],
        $sampleConfig
    );
    
    echo "Expected DocuSeal field format:\n";
    echo json_encode($sampleFields, JSON_PRETTY_PRINT);
    echo "\n\nEach field should have:\n";
    echo "- name: The exact field name from the DocuSeal template\n";
    echo "- value: The value to pre-fill\n";
    echo "- readonly: (optional) Whether the field should be read-only\n";
}

echo "\n\n=== Test Complete ===\n"; 
<?php

use App\Models\Order\Manufacturer;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Order\Product;
use App\Services\DocuSealService;
use App\Services\Templates\UnifiedTemplateMappingEngine;

echo "=== Quick Request DocuSeal Diagnostic ===" . PHP_EOL . PHP_EOL;

// 1. Check Manufacturers
echo "1. MANUFACTURERS:" . PHP_EOL;
$manufacturers = Manufacturer::orderBy('id')->get();
foreach ($manufacturers as $manufacturer) {
    echo "   - ID: {$manufacturer->id}, Name: {$manufacturer->name}" . PHP_EOL;
}
echo PHP_EOL;

// 2. Check Templates
echo "2. DOCUSEAL TEMPLATES:" . PHP_EOL;
$templates = DocusealTemplate::with('manufacturer')->orderBy('created_at', 'desc')->get();
foreach ($templates as $template) {
    $mfgName = $template->manufacturer->name ?? 'NO MANUFACTURER';
    $mappingCount = is_array($template->field_mappings) ? count($template->field_mappings) : 0;
    echo "   - ID: {$template->id}" . PHP_EOL;
    echo "     DocuSeal ID: {$template->docuseal_template_id}" . PHP_EOL;
    echo "     Name: {$template->template_name}" . PHP_EOL;
    echo "     Manufacturer: {$mfgName} (ID: {$template->manufacturer_id})" . PHP_EOL;
    echo "     Document Type: {$template->document_type}" . PHP_EOL;
    echo "     Active: " . ($template->is_active ? 'YES' : 'NO') . PHP_EOL;
    echo "     Field Mappings: {$mappingCount}" . PHP_EOL;

    if ($mappingCount > 0 && $mappingCount < 10) {
        echo "     Sample Mappings:" . PHP_EOL;
        $sampleMappings = array_slice($template->field_mappings, 0, 3, true);
        foreach ($sampleMappings as $docusealField => $mapping) {
            $systemField = is_array($mapping) ? ($mapping['system_field'] ?? $mapping['local_field'] ?? 'N/A') : $mapping;
            echo "       * {$docusealField} -> {$systemField}" . PHP_EOL;
        }
    }
    echo PHP_EOL;
}

// 3. Check Template Selection Logic
echo "3. TEMPLATE SELECTION TEST:" . PHP_EOL;
$testManufacturers = [1, 2, 3]; // Test first few manufacturers
foreach ($testManufacturers as $mfgId) {
    $manufacturer = Manufacturer::find($mfgId);
    if (!$manufacturer) {
        echo "   - Manufacturer ID {$mfgId}: NOT FOUND" . PHP_EOL;
        continue;
    }

    echo "   - Testing Manufacturer: {$manufacturer->name} (ID: {$mfgId})" . PHP_EOL;

    // Test current template selection logic
    $template = DocusealTemplate::where('manufacturer_id', $mfgId)
        ->where('is_active', true)
        ->where('document_type', 'IVR')
        ->orderBy('created_at', 'desc')
        ->first();

    if ($template) {
        echo "     ✅ Found template: {$template->template_name} (DocuSeal ID: {$template->docuseal_template_id})" . PHP_EOL;
    } else {
        echo "     ❌ No active IVR template found" . PHP_EOL;

        // Check if there are any templates for this manufacturer
        $anyTemplates = DocusealTemplate::where('manufacturer_id', $mfgId)->count();
        if ($anyTemplates > 0) {
            echo "     ⚠️  Found {$anyTemplates} templates for this manufacturer but none are active IVR templates" . PHP_EOL;
        }
    }
}
echo PHP_EOL;

// 4. Check Field Mapping Issues
echo "4. FIELD MAPPING DIAGNOSTIC:" . PHP_EOL;

// Create sample Quick Request data
$sampleData = [
    'patient_first_name' => 'John',
    'patient_last_name' => 'Doe',
    'patient_dob' => '1980-01-01',
    'patient_phone' => '5551234567',
    'patient_email' => 'john@example.com',
    'patient_address_line1' => '123 Main St',
    'patient_city' => 'Anytown',
    'patient_state' => 'NY',
    'patient_zip' => '12345',
    'patient_member_id' => 'MEM123456',
    'provider_name' => 'Dr. Smith',
    'provider_npi' => '1234567890',
    'facility_name' => 'Test Clinic',
    'facility_npi' => '0987654321',
    'sales_rep_name' => 'Sales Rep',
    'service_date' => '2024-01-15',
    'wound_type' => 'Diabetic Foot Ulcer',
    'payer_name' => 'Medicare',
];

echo "   Sample data fields: " . count($sampleData) . PHP_EOL;
echo "   Sample fields: " . implode(', ', array_keys(array_slice($sampleData, 0, 10))) . PHP_EOL . PHP_EOL;

// Test mapping with each active template
$activeTemplates = DocusealTemplate::where('is_active', true)->with('manufacturer')->get();
foreach ($activeTemplates as $template) {
    $mfgName = $template->manufacturer->name ?? 'NO MANUFACTURER';
    echo "   Testing template: {$template->template_name} ({$mfgName})" . PHP_EOL;

    try {
        $docuSealService = app(DocuSealService::class);
        $mappedFields = $docuSealService->mapFieldsUsingTemplate($sampleData, $template);

        echo "     ✅ Mapped {" . count($mappedFields) . "} fields" . PHP_EOL;

        if (count($mappedFields) > 0) {
            echo "     Sample mapped fields:" . PHP_EOL;
            foreach (array_slice($mappedFields, 0, 5) as $field) {
                $value = is_string($field['value']) ? substr($field['value'], 0, 30) : $field['value'];
                echo "       * {$field['name']}: {$value}" . PHP_EOL;
            }
        } else {
            echo "     ❌ NO FIELDS MAPPED" . PHP_EOL;
            $mappingCount = is_array($template->field_mappings) ? count($template->field_mappings) : 0;
            echo "     Template has {$mappingCount} field mappings configured" . PHP_EOL;
        }

    } catch (Exception $e) {
        echo "     ❌ Mapping failed: " . $e->getMessage() . PHP_EOL;
    }
    echo PHP_EOL;
}

// 5. Product to Manufacturer Mapping
echo "5. PRODUCT TO MANUFACTURER MAPPING:" . PHP_EOL;
$sampleProducts = Product::with('manufacturer')->take(10)->get();
foreach ($sampleProducts as $product) {
    $mfgName = $product->manufacturer->name ?? 'NO MANUFACTURER';
    echo "   - Product: {$product->name} (Q-Code: {$product->q_code}) -> Manufacturer: {$mfgName}" . PHP_EOL;
}
echo PHP_EOL;

echo "=== End Diagnostic ===" . PHP_EOL;

<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Log;
use App\Services\UnifiedFieldMappingService;
use App\Services\FhirDocusealIntegrationService;
use App\Services\DocusealService;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Order\Manufacturer;
use App\Services\CurrentOrganization;
use App\Services\QuickRequest\QuickRequestOrchestrator;

echo "🔍 IVR Field Mapping Diagnostic Tool\n";
echo "=====================================\n\n";

// 1. Get sample data from a recent QuickRequest workflow
echo "1. Analyzing Recent QuickRequest Data Sources:\n";

$sampleFormData = [
    // Patient data (comes from form)
    'patient_first_name' => 'Jane',
    'patient_last_name' => 'Doe',
    'patient_dob' => '1985-05-15',
    'patient_gender' => 'Female',
    'patient_phone' => '555-123-4567',
    'patient_email' => 'jane.doe@example.com',
    
    // Insurance data (comes from form)
    'primary_insurance_name' => 'Blue Cross Blue Shield',
    'primary_member_id' => 'BC123456789',
    'secondary_insurance_name' => 'Medicaid',
    'secondary_member_id' => 'MD987654321',
    
    // Clinical data (comes from form)
    'wound_type' => 'Diabetic Foot Ulcer',
    'wound_location' => 'Left Foot',
    'wound_size_length' => '3.5',
    'wound_size_width' => '2.0',
    'wound_size_depth' => '0.5',
    'diagnosis_code' => 'E11.621',
    
    // Product selection (comes from form)
    'selected_products' => [
        ['product_id' => 1, 'quantity' => 2, 'size' => 'Medium']
    ],
    'manufacturer_id' => 1,
    
    // Provider data (should come from provider profile/auth)
    'provider_name' => 'Dr. John Smith',
    'provider_npi' => '1234567890',
    'provider_email' => 'dr.smith@clinic.com',
    'provider_phone' => '555-987-6543',
    
    // Facility data (should come from selected facility)
    'facility_name' => 'Main Street Clinic',
    'facility_address' => '123 Main St, Anytown, ST 12345',
    'facility_npi' => '0987654321',
    'facility_phone' => '555-555-5555',
    
    // Organization data (should come from CurrentOrganization service)
    'organization_name' => 'MSC Wound Care',
    'organization_tax_id' => '12-3456789',
    'organization_address' => '456 Medical Ave, Healthcare City, ST 67890',
];

echo "   ✓ Sample form data prepared with " . count($sampleFormData) . " fields\n";

// 2. Test Current Organization Service
echo "\n2. Testing Current Organization Service:\n";
try {
    $currentOrgService = app(CurrentOrganization::class);
    $organization = $currentOrgService->getOrganization();
    if ($organization) {
        echo "   ✓ Organization found: {$organization->name}\n";
        echo "   ✓ Tax ID: " . ($organization->tax_id ?? 'Not set') . "\n";
        echo "   ✓ Address: " . ($organization->address ?? 'Not set') . "\n";
        
        // Add organization data to sample
        $sampleFormData['organization_name'] = $organization->name;
        $sampleFormData['organization_tax_id'] = $organization->tax_id ?? '';
        $sampleFormData['organization_address'] = $organization->address ?? '';
    } else {
        echo "   ⚠️ No current organization found (ID: " . ($currentOrgService->getId() ?? 'not set') . ")\n";
        // Set a default organization for testing
        $sampleFormData['organization_name'] = 'MSC Wound Care';
        $sampleFormData['organization_tax_id'] = '12-3456789';
        $sampleFormData['organization_address'] = '456 Medical Ave, Healthcare City, ST 67890';
    }
} catch (\Exception $e) {
    echo "   ❌ Error getting organization: " . $e->getMessage() . "\n";
    // Set a default organization for testing
    $sampleFormData['organization_name'] = 'MSC Wound Care';
    $sampleFormData['organization_tax_id'] = '12-3456789';
    $sampleFormData['organization_address'] = '456 Medical Ave, Healthcare City, ST 67890';
}

// 3. Test UnifiedFieldMappingService
echo "\n3. Testing UnifiedFieldMappingService:\n";
try {
    $fieldMappingService = app(UnifiedFieldMappingService::class);
    
    // Test with MEDLIFE SOLUTIONS manufacturer
    $manufacturer = Manufacturer::where('name', 'MEDLIFE SOLUTIONS')->first();
    if (!$manufacturer) {
        echo "   ❌ MEDLIFE SOLUTIONS manufacturer not found\n";
        return;
    }
    
    echo "   ✓ Testing with manufacturer: {$manufacturer->name} (ID: {$manufacturer->id})\n";
    
    // Map the fields
    $mappingResult = $fieldMappingService->mapEpisodeToTemplate(
        null, // No episode ID since this is direct data
        $manufacturer->name,
        $sampleFormData,
        'IVR'
    );
    
    echo "   ✓ Field mapping completed\n";
    echo "   ✓ Mapped fields: " . count($mappingResult['data']) . "\n";
    echo "   ✓ Validation valid: " . ($mappingResult['validation']['valid'] ? 'Yes' : 'No') . "\n";
    echo "   ✓ Completeness: " . round($mappingResult['completeness']['percentage'], 1) . "%\n";
    
    // Show mapped fields
    echo "\n   📋 Mapped Fields:\n";
    foreach ($mappingResult['data'] as $field => $value) {
        $displayValue = is_array($value) ? json_encode($value) : (string)$value;
        if (strlen($displayValue) > 50) {
            $displayValue = substr($displayValue, 0, 47) . '...';
        }
        echo "      {$field}: {$displayValue}\n";
    }
    
} catch (\Exception $e) {
    echo "   ❌ Error in field mapping: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
    return;
}

// 4. Test Docuseal field conversion
echo "\n4. Testing Docuseal Field Conversion:\n";
try {
    $docusealService = app(DocusealService::class);
    
    // Get MEDLIFE template
    $template = DocusealTemplate::where('manufacturer_id', $manufacturer->id)
        ->where('document_type', 'IVR')
        ->first();
    
    if (!$template) {
        echo "   ❌ No IVR template found for MEDLIFE SOLUTIONS\n";
        return;
    }
    
    echo "   ✓ Using template: {$template->template_name} (DocuSeal ID: {$template->docuseal_template_id})\n";
    
    // Convert mapped fields to DocuSeal format
    $manufacturerConfig = $fieldMappingService->getManufacturerConfig($manufacturer->name, 'IVR');
    $docusealFields = $fieldMappingService->convertToDocusealFields(
        $mappingResult['data'],
        $manufacturerConfig,
        'IVR'
    );
    
    echo "   ✓ DocuSeal fields prepared: " . count($docusealFields) . "\n";
    
    echo "\n   📋 DocuSeal Fields:\n";
    foreach ($docusealFields as $field => $value) {
        $displayValue = is_array($value) ? json_encode($value) : (string)$value;
        if (strlen($displayValue) > 50) {
            $displayValue = substr($displayValue, 0, 47) . '...';
        }
        echo "      {$field}: {$displayValue}\n";
    }
    
} catch (\Exception $e) {
    echo "   ❌ Error in DocuSeal conversion: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
    return;
}

// 5. Check what fields are missing
echo "\n5. Field Coverage Analysis:\n";

$expectedCriticalFields = [
    // Patient fields
    'patient_name', 'patient_first_name', 'patient_last_name', 'patient_dob', 'patient_gender', 'patient_phone',
    
    // Provider fields  
    'provider_name', 'physician_name', 'provider_npi', 'physician_npi', 'provider_phone',
    
    // Facility fields
    'facility_name', 'clinic_name', 'facility_address', 'facility_npi', 'facility_phone',
    
    // Organization fields
    'organization_name', 'organization_tax_id', 'tax_id', 'organization_address',
    
    // Insurance fields
    'primary_insurance_name', 'primary_member_id', 'insurance_name', 'member_id',
    'secondary_insurance_name', 'secondary_member_id',
    
    // Clinical fields
    'wound_type', 'wound_location', 'wound_size_length', 'wound_size_width', 'diagnosis_code',
];

$missingFromMapped = [];
$missingFromDocuseal = [];

foreach ($expectedCriticalFields as $field) {
    if (!isset($mappingResult['data'][$field]) && !array_key_exists($field, $mappingResult['data'])) {
        $missingFromMapped[] = $field;
    }
    if (!isset($docusealFields[$field]) && !array_key_exists($field, $docusealFields)) {
        $missingFromDocuseal[] = $field;
    }
}

echo "   📊 Critical Field Coverage:\n";
echo "      Total expected: " . count($expectedCriticalFields) . "\n";
echo "      Found in mapped data: " . (count($expectedCriticalFields) - count($missingFromMapped)) . "\n";
echo "      Found in DocuSeal data: " . (count($expectedCriticalFields) - count($missingFromDocuseal)) . "\n";

if (!empty($missingFromMapped)) {
    echo "\n   ❌ Missing from mapped data:\n";
    foreach ($missingFromMapped as $field) {
        echo "      - {$field}\n";
    }
}

if (!empty($missingFromDocuseal)) {
    echo "\n   ❌ Missing from DocuSeal data:\n";
    foreach ($missingFromDocuseal as $field) {
        echo "      - {$field}\n";
    }
}

// 6. Test QuickRequest Orchestrator data flow
echo "\n6. Testing QuickRequest Orchestrator Data Flow:\n";
try {
    $orchestrator = app(QuickRequestOrchestrator::class);
    
    // Simulate orchestrator data extraction
    $mockData = [
        'patient' => [
            'first_name' => $sampleFormData['patient_first_name'],
            'last_name' => $sampleFormData['patient_last_name'],
            'dob' => $sampleFormData['patient_dob'],
            'gender' => $sampleFormData['patient_gender'],
            'phone' => $sampleFormData['patient_phone'],
        ],
        'provider' => [
            'id' => 1,
            'name' => $sampleFormData['provider_name'],
            'npi' => $sampleFormData['provider_npi'],
        ],
        'facility' => [
            'id' => 1,
            'name' => $sampleFormData['facility_name'],
            'address' => $sampleFormData['facility_address'],
            'npi' => $sampleFormData['facility_npi'],
        ],
        'clinical' => [
            'wound_type' => $sampleFormData['wound_type'],
            'wound_location' => $sampleFormData['wound_location'],
            'wound_size_length' => $sampleFormData['wound_size_length'],
            'wound_size_width' => $sampleFormData['wound_size_width'],
        ],
        'insurance' => [
            ['policy_type' => 'primary', 'payer_name' => $sampleFormData['primary_insurance_name'], 'member_id' => $sampleFormData['primary_member_id']],
            ['policy_type' => 'secondary', 'payer_name' => $sampleFormData['secondary_insurance_name'], 'member_id' => $sampleFormData['secondary_member_id']],
        ],
        'manufacturer' => $manufacturer,
    ];
    
    echo "   ✓ Orchestrator data structure prepared\n";
    echo "   ✓ Patient data: " . count($mockData['patient']) . " fields\n";
    echo "   ✓ Provider data: " . count($mockData['provider']) . " fields\n";
    echo "   ✓ Facility data: " . count($mockData['facility']) . " fields\n";
    echo "   ✓ Clinical data: " . count($mockData['clinical']) . " fields\n";
    echo "   ✓ Insurance policies: " . count($mockData['insurance']) . "\n";
    
} catch (\Exception $e) {
    echo "   ❌ Error testing orchestrator: " . $e->getMessage() . "\n";
}

// 7. Recommendations
echo "\n7. Recommendations for IVR Field Mapping:\n";
echo "==========================================\n";

if (!empty($missingFromMapped) || !empty($missingFromDocuseal)) {
    echo "🔧 ISSUES IDENTIFIED:\n\n";
    
    if (!empty($missingFromMapped)) {
        echo "1. **Field Mapping Issues**: Some expected fields are not being mapped by UnifiedFieldMappingService\n";
        echo "   - Check manufacturer config files in config/manufacturers/\n";
        echo "   - Verify field mapping rules in UnifiedFieldMappingService\n";
        echo "   - Consider adding fallback field mappings\n\n";
    }
    
    if (!empty($missingFromDocuseal)) {
        echo "2. **DocuSeal Conversion Issues**: Mapped fields are not being converted to DocuSeal format\n";
        echo "   - Check docuseal_field_names mapping in manufacturer config\n";
        echo "   - Verify convertToDocusealFields method in UnifiedFieldMappingService\n";
        echo "   - Check if DocuSeal template has matching field names\n\n";
    }
    
    echo "🛠️ SUGGESTED FIXES:\n\n";
    
    echo "1. **Update Data Sources**: Ensure proper data flow in Step7DocusealIVR.tsx\n";
    echo "   - Provider data should come from authenticated user profile\n";
    echo "   - Facility data should come from selected facility in workflow\n";
    echo "   - Organization data should come from CurrentOrganization service\n";
    echo "   - Form data should only include patient/clinical information\n\n";
    
    echo "2. **Fix Backend Field Mapping**: Update the orchestrator to properly aggregate data\n";
    echo "   - Modify QuickRequestOrchestrator to collect data from all sources\n";
    echo "   - Ensure provider profile data is included in mapping\n";
    echo "   - Add organization data from CurrentOrganization service\n";
    echo "   - Fix facility data extraction from selected facility\n\n";
    
    echo "3. **Verify DocuSeal Template Fields**: Check actual template field names\n";
    echo "   - Run: php artisan debug:docuseal-fields {template_id}\n";
    echo "   - Compare with manufacturer config docuseal_field_names mapping\n";
    echo "   - Update mapping if field names don't match\n\n";
    
} else {
    echo "✅ NO CRITICAL ISSUES DETECTED\n\n";
    echo "The field mapping appears to be working correctly. If fields are still\n";
    echo "missing in the actual IVR, the issue may be in:\n\n";
    echo "1. **Frontend Data Collection**: Check Step7DocusealIVR.tsx data preparation\n";
    echo "2. **API Endpoints**: Verify the submission creation API is getting all data\n";
    echo "3. **DocuSeal API**: Check if DocuSeal is accepting and displaying the fields\n\n";
}

echo "📝 NEXT STEPS:\n";
echo "1. Run this script with actual QuickRequest data\n";
echo "2. Compare results with actual DocuSeal IVR output\n";
echo "3. Check logs during actual submission process\n";
echo "4. Test with different manufacturers if available\n\n";

echo "✅ Diagnostic complete!\n";

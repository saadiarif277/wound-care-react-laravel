<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\UnifiedFieldMappingService;
use App\Services\DocusealService;
use App\Services\QuickRequest\QuickRequestOrchestrator;
use App\Models\PatientManufacturerIVREpisode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing BioWound Solutions IVR submission flow...\n\n";

try {
    // Simulate authentication as user ID 1
    Auth::loginUsingId(1);
    
    // Step 1: Create a test episode
    echo "Step 1: Creating test episode...\n";
    $episode = PatientManufacturerIVREpisode::create([
        'patient_id' => 'test-patient-001',
        'patient_fhir_id' => 'fhir-test-001',
        'patient_display_id' => 'TEST001',
        'manufacturer_id' => 3, // BioWound Solutions
        'status' => PatientManufacturerIVREpisode::STATUS_DRAFT,
        'ivr_status' => PatientManufacturerIVREpisode::IVR_STATUS_NA,
        'created_by' => 1,
        'metadata' => [
            'patient_data' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'dob' => '1980-01-01',
                'gender' => 'Male',
                'phone' => '555-123-4567',
                'email' => 'john.doe@example.com',
                'display_id' => 'TEST001'
            ],
            'provider_data' => [
                'name' => 'Dr. Jane Smith',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'npi' => '1234567890',
                'email' => 'dr.smith@clinic.com',
                'phone' => '555-987-6543',
                'specialty' => 'Wound Care',
                'credentials' => 'MD',
                'ptan' => 'ABC123',
                'tax_id' => '12-3456789'
            ],
            'facility_data' => [
                'name' => 'Test Hospital',
                'address' => '123 Medical Way',
                'city' => 'Testville',
                'state' => 'CA',
                'zip' => '90210',
                'phone' => '555-111-2222',
                'npi' => '9876543210'
            ],
            'clinical_data' => [
                'wound_type' => 'DFU',
                'wound_location' => 'Left foot',
                'wound_size_length' => '2.5',
                'wound_size_width' => '3.0',
                'wound_size_depth' => '0.5',
                'primary_diagnosis_code' => 'L97.413',
                'wound_duration' => '6 weeks'
            ],
            'insurance_data' => [
                'primary_name' => 'Medicare',
                'primary_member_id' => 'M12345678'
            ],
            'order_details' => [
                'products' => [
                    [
                        'product_id' => 1, // Assuming this is Amnio-maxx
                        'product' => [
                            'code' => 'Q4239',
                            'name' => 'Amnio-Maxx',
                            'manufacturer' => 'BioWound Solutions'
                        ],
                        'quantity' => 1,
                        'size' => '4x4cm'
                    ]
                ],
                'expected_service_date' => date('Y-m-d', strtotime('+2 days')),
                'place_of_service' => '11'
            ]
        ]
    ]);
    
    echo "✅ Episode created: ID = " . $episode->id . "\n\n";
    
    // Step 2: Test the orchestrator's prepareDocusealData
    echo "Step 2: Testing orchestrator data preparation...\n";
    $orchestrator = app(QuickRequestOrchestrator::class);
    $comprehensiveData = $orchestrator->prepareDocusealData($episode);
    
    echo "✅ Comprehensive data prepared\n";
    echo "  - Fields count: " . count($comprehensiveData) . "\n";
    echo "  - Has patient name: " . (!empty($comprehensiveData['patient_name']) ? 'Yes' : 'No') . "\n";
    echo "  - Has provider NPI: " . (!empty($comprehensiveData['provider_npi']) ? 'Yes' : 'No') . "\n";
    echo "  - Has product code: " . (!empty($comprehensiveData['product_code']) ? 'Yes' : 'No') . "\n";
    echo "  - Product code: " . ($comprehensiveData['product_code'] ?? 'NOT SET') . "\n";
    echo "  - Has Q4239 checkbox: " . (isset($comprehensiveData['q4239']) ? 'Yes' : 'No') . "\n\n";
    
    // Step 3: Test the DocuSeal service mapping
    echo "Step 3: Testing DocuSeal service mapping (without actual API call)...\n";
    
    // We'll manually test the mapping part without making actual API calls
    $mappingService = app(UnifiedFieldMappingService::class);
    $mappingResult = $mappingService->mapEpisodeToTemplate(
        null,
        'BioWound Solutions',
        $comprehensiveData
    );
    
    echo "✅ Mapping result:\n";
    echo "  - Validation: " . ($mappingResult['validation']['valid'] ? 'VALID' : 'INVALID') . "\n";
    echo "  - Manufacturer name: " . ($mappingResult['manufacturer']['name'] ?? 'NOT SET') . "\n";
    echo "  - Has docuseal_template_id: " . (isset($mappingResult['manufacturer']['docuseal_template_id']) ? 'Yes' : 'No') . "\n";
    echo "  - Template ID: " . ($mappingResult['manufacturer']['docuseal_template_id'] ?? 'NOT SET') . "\n";
    echo "  - Manufacturer config keys: " . implode(', ', array_keys($mappingResult['manufacturer'])) . "\n";
    echo "  - Completeness: " . $mappingResult['completeness']['percentage'] . "%\n\n";
    
    // Step 4: Simulate what DocuSeal service would do
    echo "Step 4: Simulating DocuSeal submission creation...\n";
    
    $templateId = $mappingResult['manufacturer']['template_id'] ?? 
                 $mappingResult['manufacturer']['docuseal_template_id'] ?? 
                 null;
    
    if (!$templateId) {
        echo "❌ ERROR: No template ID found in mapping result!\n";
        echo "  Available keys: " . implode(', ', array_keys($mappingResult['manufacturer'] ?? [])) . "\n";
    } else {
        echo "✅ Template ID found: " . $templateId . "\n";
        echo "  This would be used to create the DocuSeal submission\n";
    }
    
    // Clean up
    $episode->forceDelete();
    echo "\n✅ Test episode cleaned up\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    
    // Clean up on error
    if (isset($episode)) {
        $episode->forceDelete();
    }
}

echo "\nTest completed.\n";
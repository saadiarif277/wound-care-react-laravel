<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\DocusealService;
use App\Models\PatientManufacturerIVREpisode;
use App\Services\QuickRequest\QuickRequestOrchestrator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing Amnio-maxx (Q4239) IVR submission...\n\n";

try {
    // Simulate authentication
    Auth::loginUsingId(1);
    
    // Create test data simulating Quick Request form submission
    $quickRequestData = [
        // Patient Information
        'patientInfo' => [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'dateOfBirth' => '1980-01-01',
            'gender' => 'Male',
            'phone' => '555-123-4567',
            'email' => 'john.doe@example.com',
            'street' => '123 Main St',
            'city' => 'Anytown',
            'state' => 'CA',
            'zipCode' => '90210'
        ],
        
        // Provider Information
        'providerInfo' => [
            'name' => 'Dr. Jane Smith',
            'npi' => '1234567890',
            'email' => 'dr.smith@clinic.com',
            'phone' => '555-987-6543',
            'specialty' => 'Wound Care',
            'credentials' => 'MD',
            'ptan' => 'ABC123',
            'taxId' => '12-3456789'
        ],
        
        // Facility Information
        'facilityInfo' => [
            'name' => 'Test Hospital',
            'address' => '123 Medical Way',
            'city' => 'Testville',
            'state' => 'CA',
            'zip' => '90210',
            'phone' => '555-111-2222',
            'npi' => '9876543210'
        ],
        
        // Insurance Information
        'insuranceInfo' => [
            'primaryInsurance' => [
                'name' => 'Medicare',
                'memberId' => 'M12345678',
                'groupNumber' => 'GRP123'
            ]
        ],
        
        // Clinical Information
        'clinicalInfo' => [
            'primaryDiagnosis' => 'L97.413',
            'woundType' => 'DFU',
            'woundLocation' => 'Left foot',
            'woundSizeLength' => '2.5',
            'woundSizeWidth' => '3.0',
            'woundSizeDepth' => '0.5',
            'woundDuration' => '6 weeks'
        ],
        
        // Product Selection - Amnio-maxx
        'selectedProducts' => [
            ['code' => 'Q4239', 'name' => 'Amnio-Maxx', 'manufacturer' => 'BioWound Solutions']
        ],
        
        // Additional Data
        'placeOfService' => '11',
        'expectedServiceDate' => date('Y-m-d', strtotime('+2 days')),
        'requestType' => 'new_request'
    ];
    
    echo "1. Testing QuickRequestOrchestrator aggregation...\n";
    $orchestrator = app(QuickRequestOrchestrator::class);
    
    // Use reflection to test the aggregation method
    $reflection = new ReflectionClass($orchestrator);
    $method = $reflection->getMethod('aggregateDataForManufacturer');
    $method->setAccessible(true);
    
    $aggregatedData = $method->invoke(
        $orchestrator,
        $quickRequestData,
        'BioWound Solutions',
        ['Q4239' => ['code' => 'Q4239', 'name' => 'Amnio-Maxx']]
    );
    
    echo "  ✅ Data aggregated successfully\n";
    echo "  - Has q4239 checkbox: " . (isset($aggregatedData['q4239']) && $aggregatedData['q4239'] ? 'Yes' : 'No') . "\n";
    echo "  - Has amnio_maxx checkbox: " . (isset($aggregatedData['amnio_maxx']) && $aggregatedData['amnio_maxx'] ? 'Yes' : 'No') . "\n";
    echo "  - Product code: " . ($aggregatedData['product_code'] ?? 'NOT SET') . "\n\n";
    
    // Create a test episode
    $episode = PatientManufacturerIVREpisode::create([
        'patient_id' => 'test-patient-amnio',
        'patient_fhir_id' => 'fhir-test-amnio',
        'patient_display_id' => 'AMNIO001',
        'manufacturer_id' => 3, // BioWound Solutions
        'status' => PatientManufacturerIVREpisode::STATUS_DRAFT,
        'ivr_status' => PatientManufacturerIVREpisode::IVR_STATUS_NA,
        'created_by' => 1,
        'metadata' => [
            'patient_data' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'dob' => '1980-01-01',
                'gender' => 'Male'
            ],
            'provider_data' => [
                'name' => 'Dr. Jane Smith',
                'npi' => '1234567890'
            ],
            'order_details' => [
                'products' => [
                    ['product' => ['code' => 'Q4239', 'name' => 'Amnio-Maxx']]
                ]
            ]
        ]
    ]);
    
    echo "2. Testing DocuSeal service mapping...\n";
    $docusealService = app(DocusealService::class);
    
    // Test the field mapping
    $fieldMappingService = new ReflectionProperty($docusealService, 'fieldMappingService');
    $fieldMappingService->setAccessible(true);
    $mappingService = $fieldMappingService->getValue($docusealService);
    
    $mappingResult = $mappingService->mapEpisodeToTemplate(
        null,
        'BioWound Solutions',
        $aggregatedData
    );
    
    echo "  ✅ Mapping completed\n";
    echo "  - Template ID: " . ($mappingResult['manufacturer']['docuseal_template_id'] ?? 'NOT SET') . "\n";
    echo "  - Manufacturer: " . ($mappingResult['manufacturer']['name'] ?? 'NOT SET') . "\n";
    echo "  - Has q4239 field: " . (isset($mappingResult['data']['q4239']) && $mappingResult['data']['q4239'] ? 'Yes' : 'No') . "\n";
    echo "  - Validation: " . ($mappingResult['validation']['valid'] ? 'Valid' : 'Invalid') . "\n";
    echo "  - Completeness: " . ($mappingResult['completeness']['percentage'] ?? 0) . "%\n\n";
    
    // Test what would happen in createSubmissionFromOrchestratorData
    echo "3. Testing template ID extraction (as DocuSeal service would)...\n";
    $templateId = $mappingResult['manufacturer']['template_id'] ?? 
                 $mappingResult['manufacturer']['docuseal_template_id'] ?? 
                 null;
    
    if (!$templateId) {
        echo "  ❌ No template ID found! Available keys: " . implode(', ', array_keys($mappingResult['manufacturer'] ?? [])) . "\n";
    } else {
        echo "  ✅ Template ID extracted successfully: " . $templateId . "\n";
        echo "  🎉 Amnio-maxx IVR submission would succeed!\n";
    }
    
    // Clean up
    $episode->forceDelete();
    echo "\n✅ Test completed successfully\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    
    if (isset($episode)) {
        $episode->forceDelete();
    }
}

echo "\nAmnio-maxx IVR test completed.\n";
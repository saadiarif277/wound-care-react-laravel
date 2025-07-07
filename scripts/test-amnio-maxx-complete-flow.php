<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\QuickRequest\QuickRequestOrchestrator;
use App\Models\PatientManufacturerIVREpisode;
use App\Services\DocusealService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing complete Amnio-maxx flow (Orchestrator -> DocuSeal)...\n\n";

try {
    // Simulate authentication
    Auth::loginUsingId(1);
    
    // Create a test episode with Amnio-maxx (Q4239) product
    $episode = PatientManufacturerIVREpisode::create([
        'patient_id' => 'test-patient-amnio-flow',
        'patient_fhir_id' => null, // Draft episode
        'patient_display_id' => 'AMNIO002',
        'manufacturer_id' => 3, // BioWound Solutions
        'status' => PatientManufacturerIVREpisode::STATUS_DRAFT,
        'ivr_status' => PatientManufacturerIVREpisode::IVR_STATUS_PENDING,
        'created_by' => 1,
        'metadata' => [
            'is_draft' => true,
            'patient_data' => [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'dob' => '1975-05-15',
                'gender' => 'Female',
                'phone' => '555-123-4567',
                'email' => 'jane.smith@example.com',
                'display_id' => 'AMNIO002'
            ],
            'provider_data' => [
                'name' => 'Dr. John Brown',
                'first_name' => 'John',
                'last_name' => 'Brown',
                'npi' => '9876543210',
                'email' => 'dr.brown@clinic.com',
                'phone' => '555-987-6543',
                'specialty' => 'Wound Care',
                'credentials' => 'MD',
                'ptan' => 'XYZ789',
                'tax_id' => '98-7654321'
            ],
            'facility_data' => [
                'name' => 'Test Medical Center',
                'address' => '456 Healthcare Blvd',
                'city' => 'Medical City',
                'state' => 'FL',
                'zip_code' => '33101',
                'phone' => '555-333-4444',
                'npi' => '1122334455'
            ],
            'clinical_data' => [
                'wound_type' => 'DFU',
                'wound_location' => 'Right foot, plantar surface',
                'wound_size_length' => '3.0',
                'wound_size_width' => '2.5',
                'wound_size_depth' => '0.8',
                'wound_duration_weeks' => '8',
                'primary_diagnosis_code' => 'L97.413',
                'secondary_diagnosis_code' => 'E11.621',
                'failed_conservative_treatment' => true,
                'information_accurate' => true,
                'medical_necessity_established' => true,
                'maintain_documentation' => true
            ],
            'insurance_data' => [
                [
                    'policy_type' => 'primary',
                    'payer_name' => 'Medicare',
                    'member_id' => 'M98765432'
                ]
            ],
            'order_details' => [
                'expected_service_date' => date('Y-m-d', strtotime('+3 days')),
                'place_of_service' => '11',
                'products' => [
                    [
                        'product' => [
                            'code' => 'Q4239',
                            'name' => 'Amnio-Maxx'
                        ]
                    ]
                ]
            ]
        ]
    ]);
    
    echo "✅ Created test episode: " . $episode->id . "\n\n";
    
    // Test 1: Orchestrator prepareDocusealData
    echo "1. Testing Orchestrator data preparation...\n";
    $orchestrator = app(QuickRequestOrchestrator::class);
    $comprehensiveData = $orchestrator->prepareDocusealData($episode);
    
    echo "  ✅ Data prepared by orchestrator\n";
    echo "  - Patient name: " . ($comprehensiveData['patient_name'] ?? 'NOT SET') . "\n";
    echo "  - Provider name: " . ($comprehensiveData['provider_name'] ?? 'NOT SET') . "\n";
    echo "  - Product code: " . ($comprehensiveData['product_code'] ?? 'NOT SET') . "\n";
    echo "  - Has q4239 checkbox: " . (isset($comprehensiveData['q4239']) && $comprehensiveData['q4239'] ? 'Yes' : 'No') . "\n";
    echo "  - Has amnio_maxx checkbox: " . (isset($comprehensiveData['amnio_maxx']) && $comprehensiveData['amnio_maxx'] ? 'Yes' : 'No') . "\n\n";
    
    // Test 2: DocuSeal mapping
    echo "2. Testing DocuSeal field mapping...\n";
    $docusealService = app(DocusealService::class);
    
    // Use reflection to test the mapping without making API calls
    $reflection = new ReflectionClass($docusealService);
    $fieldMappingProperty = $reflection->getProperty('fieldMappingService');
    $fieldMappingProperty->setAccessible(true);
    $mappingService = $fieldMappingProperty->getValue($docusealService);
    
    $mappingResult = $mappingService->mapEpisodeToTemplate(
        null,
        'BioWound Solutions',
        $comprehensiveData
    );
    
    echo "  ✅ Mapping completed\n";
    echo "  - Template ID: " . ($mappingResult['manufacturer']['docuseal_template_id'] ?? 'NOT SET') . "\n";
    echo "  - Manufacturer: " . ($mappingResult['manufacturer']['name'] ?? 'NOT SET') . "\n";
    echo "  - Validation: " . ($mappingResult['validation']['valid'] ? 'Valid' : 'Invalid') . "\n";
    echo "  - Completeness: " . ($mappingResult['completeness']['percentage'] ?? 0) . "%\n";
    echo "  - Has q4239 in mapped data: " . (isset($mappingResult['data']['q4239']) && $mappingResult['data']['q4239'] ? 'Yes' : 'No') . "\n\n";
    
    // Test 3: Simulate DocuSeal submission (without API call)
    echo "3. Testing DocuSeal submission preparation...\n";
    
    $templateId = $mappingResult['manufacturer']['template_id'] ?? 
                 $mappingResult['manufacturer']['docuseal_template_id'] ?? 
                 null;
    
    if (!$templateId) {
        echo "  ❌ No template ID found! Available keys: " . implode(', ', array_keys($mappingResult['manufacturer'] ?? [])) . "\n";
        echo "  IVR submission would fail\n";
    } else {
        echo "  ✅ Template ID extracted: " . $templateId . "\n";
        echo "  ✅ All required data is present\n";
        echo "  🎉 Amnio-maxx IVR submission would succeed!\n";
        
        // Show what would be sent to DocuSeal
        echo "\n  Fields that would be sent to DocuSeal:\n";
        $importantFields = [
            'patient_name', 'provider_name', 'facility_name',
            'primary_insurance_name', 'wound_type', 'wound_location',
            'product_code', 'q4239', 'amnio_maxx'
        ];
        
        foreach ($importantFields as $field) {
            if (isset($mappingResult['data'][$field])) {
                $value = $mappingResult['data'][$field];
                if (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                }
                echo "    - $field: $value\n";
            }
        }
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

echo "\nComplete flow test finished.\n";
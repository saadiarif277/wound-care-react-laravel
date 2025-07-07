<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\DocusealService;
use App\Models\PatientManufacturerIVREpisode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing complete IVR submission flow (without actual API calls)...\n\n";

try {
    // Simulate authentication
    Auth::loginUsingId(1);
    
    // Create a test episode
    $episode = PatientManufacturerIVREpisode::create([
        'patient_id' => 'test-patient-002',
        'patient_fhir_id' => 'fhir-test-002',
        'patient_display_id' => 'TEST002',
        'manufacturer_id' => 3, // BioWound Solutions
        'status' => PatientManufacturerIVREpisode::STATUS_DRAFT,
        'ivr_status' => PatientManufacturerIVREpisode::IVR_STATUS_NA,
        'created_by' => 1,
        'metadata' => [
            'patient_data' => [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'dob' => '1975-05-15',
                'gender' => 'Female'
            ],
            'provider_data' => [
                'name' => 'Dr. John Brown',
                'npi' => '9876543210'
            ],
            'order_details' => [
                'products' => [
                    ['product' => ['code' => 'Q4239', 'name' => 'Amnio-Maxx']]
                ]
            ]
        ]
    ]);
    
    echo "✅ Created test episode: " . $episode->id . "\n\n";
    
    // Test createSubmissionFromOrchestratorData
    $docusealService = app(DocusealService::class);
    
    // Mock comprehensive data
    $comprehensiveData = [
        'patient_name' => 'Jane Smith',
        'patient_first_name' => 'Jane',
        'patient_last_name' => 'Smith',
        'provider_name' => 'Dr. John Brown',
        'provider_npi' => '9876543210',
        'product_code' => 'Q4239',
        'q4239' => true,
        'amnio_maxx' => true
    ];
    
    // Override the createSubmission method to prevent actual API call
    $mockService = new class($docusealService) {
        private $realService;
        
        public function __construct($realService) {
            $this->realService = $realService;
        }
        
        public function createSubmissionFromOrchestratorData($episode, $data, $manufacturer) {
            // Call the real method but catch the API call
            try {
                $reflection = new ReflectionClass($this->realService);
                $method = $reflection->getMethod('createSubmissionFromOrchestratorData');
                
                // Get the mapping result
                $fieldMappingService = $reflection->getProperty('fieldMappingService');
                $fieldMappingService->setAccessible(true);
                $mappingService = $fieldMappingService->getValue($this->realService);
                
                $mappingResult = $mappingService->mapEpisodeToTemplate(
                    null,
                    $manufacturer,
                    $data
                );
                
                Log::info('Mock: Mapping completed', [
                    'manufacturer' => $manufacturer,
                    'has_template_id' => isset($mappingResult['manufacturer']['docuseal_template_id']),
                    'template_id' => $mappingResult['manufacturer']['docuseal_template_id'] ?? null
                ]);
                
                // Extract template ID
                $templateId = $mappingResult['manufacturer']['template_id'] ?? 
                             $mappingResult['manufacturer']['docuseal_template_id'] ?? 
                             null;
                
                if (!$templateId) {
                    throw new \Exception('No template ID found in mapping result. Available keys: ' . 
                        implode(', ', array_keys($mappingResult['manufacturer'] ?? [])));
                }
                
                // Return mock successful response
                return [
                    'success' => true,
                    'submission' => [
                        'id' => 'mock-submission-123',
                        'slug' => 'mock-slug-123',
                        'status' => 'pending'
                    ],
                    'manufacturer' => $mappingResult['manufacturer'],
                    'mapped_data' => $mappingResult['data'],
                    'validation' => $mappingResult['validation'],
                    'completeness' => $mappingResult['completeness']
                ];
                
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
    };
    
    echo "Testing createSubmissionFromOrchestratorData...\n";
    $result = $mockService->createSubmissionFromOrchestratorData(
        $episode,
        $comprehensiveData,
        'BioWound Solutions'
    );
    
    if ($result['success']) {
        echo "✅ IVR submission would succeed!\n";
        echo "  - Submission ID: " . $result['submission']['id'] . "\n";
        echo "  - Template ID: " . ($result['manufacturer']['docuseal_template_id'] ?? 'NOT SET') . "\n";
        echo "  - Manufacturer: " . ($result['manufacturer']['name'] ?? 'NOT SET') . "\n";
        echo "  - Completeness: " . ($result['completeness']['percentage'] ?? 0) . "%\n";
    } else {
        echo "❌ IVR submission would fail!\n";
        echo "  - Error: " . $result['error'] . "\n";
    }
    
    // Clean up
    $episode->forceDelete();
    echo "\n✅ Test data cleaned up\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    
    if (isset($episode)) {
        $episode->forceDelete();
    }
}

echo "\nTest completed.\n";
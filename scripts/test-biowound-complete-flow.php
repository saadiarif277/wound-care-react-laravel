<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\UnifiedFieldMappingService;
use App\Services\DocusealService;
use App\Services\QuickRequest\QuickRequestOrchestrator;
use App\Models\PatientManufacturerIVREpisode;
use Illuminate\Support\Facades\Log;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Testing Complete BioWound Solutions Flow ===\n\n";

try {
    // Create a test episode
    echo "1. Creating test episode...\n";
    $episode = new PatientManufacturerIVREpisode();
    $episode->id = 'test-' . uniqid();
    $episode->patient_id = 'test-patient-123';
    $episode->manufacturer_id = 3; // BioWound Solutions
    $episode->manufacturer_name = 'BioWound Solutions';
    $episode->created_by = 1;
    $episode->metadata = [
        'patient_data' => [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'dob' => '1980-01-01',
            'gender' => 'Male',
            'display_id' => 'TEST-123'
        ],
        'provider_data' => [
            'name' => 'Dr. Smith',
            'npi' => '1234567890',
            'specialty' => 'Wound Care'
        ],
        'facility_data' => [
            'name' => 'Test Hospital',
            'address' => '123 Main St'
        ],
        'insurance_data' => [
            'primary_name' => 'Medicare',
            'primary_member_id' => 'M12345'
        ],
        'clinical_data' => [
            'wound_type' => 'DFU',
            'wound_location' => 'Left foot'
        ],
        'order_details' => [
            'products' => [
                ['code' => 'Q4239', 'name' => 'Amnio-Maxx', 'manufacturer' => 'BioWound Solutions']
            ]
        ]
    ];
    
    echo "   Episode created with ID: {$episode->id}\n";
    echo "   Manufacturer: {$episode->manufacturer_name}\n";
    
    // Test orchestrator data preparation
    echo "\n2. Testing orchestrator data preparation...\n";
    $orchestrator = app(QuickRequestOrchestrator::class);
    $comprehensiveData = $orchestrator->prepareDocusealData($episode);
    
    echo "   Data prepared, field count: " . count($comprehensiveData) . "\n";
    echo "   Has manufacturer field: " . (isset($comprehensiveData['manufacturer']) ? 'YES' : 'NO') . "\n";
    echo "   Manufacturer value: " . ($comprehensiveData['manufacturer'] ?? 'NOT SET') . "\n";
    echo "   Has manufacturer_name field: " . (isset($comprehensiveData['manufacturer_name']) ? 'YES' : 'NO') . "\n";
    echo "   Manufacturer_name value: " . ($comprehensiveData['manufacturer_name'] ?? 'NOT SET') . "\n";
    
    // Test field mapping
    echo "\n3. Testing field mapping...\n";
    $mappingService = app(UnifiedFieldMappingService::class);
    $mappingResult = $mappingService->mapEpisodeToTemplate(
        null,
        'BioWound Solutions',
        $comprehensiveData
    );
    
    echo "   Mapping completed\n";
    echo "   Has manufacturer key: " . (isset($mappingResult['manufacturer']) ? 'YES' : 'NO') . "\n";
    
    if (isset($mappingResult['manufacturer'])) {
        echo "   Manufacturer array type: " . gettype($mappingResult['manufacturer']) . "\n";
        echo "   Manufacturer keys: " . implode(', ', array_keys($mappingResult['manufacturer'])) . "\n";
        echo "   Manufacturer name: " . ($mappingResult['manufacturer']['name'] ?? 'NOT SET') . "\n";
        echo "   Docuseal template ID: " . ($mappingResult['manufacturer']['docuseal_template_id'] ?? 'NOT SET') . "\n";
        
        // Test template ID extraction
        $templateId = $mappingResult['manufacturer']['template_id'] ?? 
                     $mappingResult['manufacturer']['docuseal_template_id'] ?? 
                     null;
        echo "   Extracted template ID: " . ($templateId ?: 'NULL') . "\n";
        
        // Check if it would pass DocusealService validation
        if (!$templateId) {
            echo "   ❌ ERROR: Would fail in DocusealService - no template ID!\n";
            echo "   Available keys: " . implode(', ', array_keys($mappingResult['manufacturer'])) . "\n";
        } else {
            echo "   ✅ Template ID extraction successful\n";
        }
    } else {
        echo "   ❌ ERROR: No manufacturer key in mapping result!\n";
        echo "   Available keys: " . implode(', ', array_keys($mappingResult)) . "\n";
    }
    
    // Test the complete DocusealService flow
    echo "\n4. Testing complete DocusealService flow...\n";
    $docusealService = app(DocusealService::class);
    
    try {
        // This is the exact method that's failing
        $result = $docusealService->createSubmissionFromOrchestratorData(
            $episode,
            $comprehensiveData,
            'BioWound Solutions'
        );
        
        echo "   ✅ DocuSeal submission created successfully!\n";
        echo "   Submission ID: " . ($result['submission']['id'] ?? 'NOT SET') . "\n";
        echo "   Slug: " . ($result['submission']['slug'] ?? 'NOT SET') . "\n";
        
    } catch (\Exception $e) {
        echo "   ❌ DocuSeal submission failed: " . $e->getMessage() . "\n";
        
        // Check if it's the template ID error
        if (strpos($e->getMessage(), 'No template ID found') !== false) {
            echo "   This is the exact error happening in production!\n";
        }
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
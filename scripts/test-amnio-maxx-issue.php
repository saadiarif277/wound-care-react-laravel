<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\UnifiedFieldMappingService;
use App\Services\DocusealService;
use App\Models\PatientManufacturerIVREpisode;
use Illuminate\Support\Facades\Log;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Testing Amnio-Maxx IVR Submission Issue ===\n\n";

try {
    // Test 1: Check if manufacturer config loads correctly
    echo "1. Testing manufacturer config loading...\n";
    $mappingService = app(UnifiedFieldMappingService::class);
    
    $config = $mappingService->getManufacturerConfig('BioWound Solutions');
    echo "   Config loaded: " . ($config ? 'YES' : 'NO') . "\n";
    if ($config) {
        echo "   - Name: " . ($config['name'] ?? 'NOT SET') . "\n";
        echo "   - Docuseal Template ID: " . ($config['docuseal_template_id'] ?? 'NOT SET') . "\n";
        echo "   - Has 'template_id' key: " . (isset($config['template_id']) ? 'YES' : 'NO') . "\n";
        echo "   - Has 'docuseal_template_id' key: " . (isset($config['docuseal_template_id']) ? 'YES' : 'NO') . "\n";
    }
    
    // Test 2: Test mapEpisodeToTemplate method
    echo "\n2. Testing mapEpisodeToTemplate method...\n";
    
    $testData = [
        'patient_first_name' => 'John',
        'patient_last_name' => 'Doe',
        'patient_dob' => '1980-01-01',
        'provider_name' => 'Dr. Smith',
        'provider_npi' => '1234567890',
        'facility_name' => 'Test Hospital',
        'primary_insurance_name' => 'Medicare',
        'primary_member_id' => 'M12345',
        'wound_type' => 'DFU',
        'wound_location' => 'Left foot',
        'selected_products' => [
            ['code' => 'Q4239', 'name' => 'Amnio-Maxx', 'manufacturer' => 'BioWound Solutions']
        ],
        'manufacturer' => 'BioWound Solutions',
        'manufacturer_name' => 'BioWound Solutions'
    ];
    
    $mappingResult = $mappingService->mapEpisodeToTemplate(
        null,
        'BioWound Solutions',
        $testData
    );
    
    echo "   Mapping result received\n";
    echo "   - Has 'manufacturer' key: " . (isset($mappingResult['manufacturer']) ? 'YES' : 'NO') . "\n";
    
    if (isset($mappingResult['manufacturer'])) {
        echo "   - Manufacturer keys: " . implode(', ', array_keys($mappingResult['manufacturer'])) . "\n";
        echo "   - Manufacturer name: " . ($mappingResult['manufacturer']['name'] ?? 'NOT SET') . "\n";
        echo "   - Template ID: " . ($mappingResult['manufacturer']['template_id'] ?? 'NOT SET') . "\n";
        echo "   - Docuseal Template ID: " . ($mappingResult['manufacturer']['docuseal_template_id'] ?? 'NOT SET') . "\n";
        
        // Check if we can extract the template ID the same way DocusealService does
        $templateId = $mappingResult['manufacturer']['template_id'] ?? 
                     $mappingResult['manufacturer']['docuseal_template_id'] ?? 
                     null;
        echo "   - Extracted template ID: " . ($templateId ?: 'NULL') . "\n";
    }
    
    // Test 3: Simulate the exact scenario from DocusealService
    echo "\n3. Simulating DocusealService scenario...\n";
    
    // Create a mock episode
    $episode = new PatientManufacturerIVREpisode();
    $episode->id = 'test-episode-123';
    $episode->manufacturer_name = 'BioWound Solutions';
    
    try {
        // This simulates what happens in DocusealService::createSubmissionFromOrchestratorData
        $mappingResult2 = $mappingService->mapEpisodeToTemplate(
            null,
            $episode->manufacturer_name,
            $testData
        );
        
        // Check if manufacturer array exists
        if (!isset($mappingResult2['manufacturer'])) {
            echo "   ERROR: No 'manufacturer' key in mapping result!\n";
            echo "   Available keys: " . implode(', ', array_keys($mappingResult2)) . "\n";
        } else {
            // Try to extract template ID
            $templateId = $mappingResult2['manufacturer']['template_id'] ?? 
                         $mappingResult2['manufacturer']['docuseal_template_id'] ?? 
                         null;
            
            if (!$templateId) {
                echo "   ERROR: No template ID found in mapping result!\n";
                echo "   Available manufacturer keys: " . implode(', ', array_keys($mappingResult2['manufacturer'])) . "\n";
            } else {
                echo "   SUCCESS: Template ID found: $templateId\n";
            }
        }
        
    } catch (\Exception $e) {
        echo "   EXCEPTION: " . $e->getMessage() . "\n";
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
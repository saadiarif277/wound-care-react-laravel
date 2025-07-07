<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\DocusealService;
use App\Services\UnifiedFieldMappingService;
use App\Services\QuickRequest\QuickRequestOrchestrator;
use App\Models\PatientManufacturerIVREpisode;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Authenticate as test user
$user = \App\Models\User::first();
if ($user) {
    \Illuminate\Support\Facades\Auth::login($user);
}

echo "=== Testing DocuSeal Submission Creation ===\n\n";

try {
    // Create a test episode
    $episode = new PatientManufacturerIVREpisode();
    $episode->id = 999999; // Use integer ID
    $episode->patient_id = 'test-patient-123';
    $episode->manufacturer_id = 3;
    $episode->manufacturer_name = 'BioWound Solutions';
    $episode->created_by = 1;
    $episode->metadata = [
        'patient_data' => [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'dob' => '1980-01-01',
            'phone' => '555-123-4567',
            'email' => 'john.doe@example.com',
            'address' => '123 Main St',
            'city' => 'Dallas',
            'state' => 'TX',
            'zip' => '75201'
        ],
        'provider_data' => [
            'name' => 'Dr. Jane Smith',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'npi' => '1234567890',
            'email' => 'dr.smith@hospital.com',
            'phone' => '555-987-6543',
        ],
        'facility_data' => [
            'name' => 'Dallas Medical Center',
            'address' => '456 Hospital Blvd',
            'city' => 'Dallas',
            'state' => 'TX',
            'zip_code' => '75202',
            'phone' => '555-111-2222',
            'email' => 'info@dallasmedical.com',
        ],
        'clinical_data' => [
            'wound_type' => 'Diabetic Foot Ulcer',
            'wound_duration_weeks' => '8',
        ],
        'insurance_data' => [
            'primary_name' => 'Medicare',
            'primary_member_id' => 'M123456789A',
        ],
        'order_details' => [
            'products' => [
                [
                    'product' => [
                        'code' => 'Q4239',
                        'name' => 'Amnio-Maxx'
                    ],
                    'quantity' => 1,
                    'size' => '4x4'
                ]
            ]
        ]
    ];
    
    echo "1. Preparing orchestrator data...\n";
    $orchestrator = app(QuickRequestOrchestrator::class);
    $orchestratorData = $orchestrator->prepareDocusealData($episode);
    echo "   Prepared " . count($orchestratorData) . " fields\n";
    
    echo "\n2. Running field mapping...\n";
    $mappingService = app(UnifiedFieldMappingService::class);
    $mappingResult = $mappingService->mapEpisodeToTemplate(
        null,
        'BioWound Solutions',
        $orchestratorData
    );
    
    echo "   Mapping result:\n";
    echo "   - Valid: " . ($mappingResult['validation']['valid'] ? 'YES' : 'NO') . "\n";
    echo "   - Completeness: " . $mappingResult['completeness']['percentage'] . "%\n";
    echo "   - Fields mapped: " . count($mappingResult['data']) . "\n";
    
    // Show what would be sent to DocuSeal
    echo "\n3. Fields that would be sent to DocuSeal:\n";
    $config = $mappingResult['manufacturer'];
    $convertedFields = [];
    if (!empty($config['docuseal_field_names'])) {
        $convertedFields = $mappingService->convertToDocusealFields($mappingResult['data'], $config);
        echo "   Converted " . count($convertedFields) . " fields\n";
        
        // Show first 10 fields
        foreach (array_slice($convertedFields, 0, 10) as $field) {
            $value = is_array($field['default_value']) ? json_encode($field['default_value']) : $field['default_value'];
            echo "   - '{$field['name']}' = '" . substr($value, 0, 50) . "'\n";
        }
    } else {
        echo "   No docuseal_field_names mapping found!\n";
    }
    
    // Test actual submission creation
    echo "\n4. Testing actual submission creation...\n";
    $docusealService = app(DocusealService::class);
    
    try {
        // Test createSubmission directly
        $reflection = new ReflectionClass($docusealService);
        $method = $reflection->getMethod('createSubmission');
        $method->setAccessible(true);
        
        $result = $method->invoke(
            $docusealService,
            '1254774', // template ID
            $convertedFields, // the converted fields
            (string)$episode->id, // episode ID as string
            $config, // manufacturer config
            'test@example.com', // submitter email
            'Test User' // submitter name
        );
        
        echo "   SUCCESS! Submission created:\n";
        echo "   Response structure: " . json_encode(array_keys($result)) . "\n";
        echo "   - Submission ID: " . ($result['id'] ?? $result['submission']['id'] ?? 'N/A') . "\n";
        echo "   - Slug: " . ($result['slug'] ?? $result['submission']['slug'] ?? 'N/A') . "\n";
        echo "   - Submitter ID: " . ($result['submitter_id'] ?? 'N/A') . "\n";
        
    } catch (\Exception $e) {
        echo "   ERROR: " . $e->getMessage() . "\n";
        
        // Try to get more specific error info
        if (strpos($e->getMessage(), 'Unknown field') !== false) {
            echo "\n   This error suggests field name mismatch. Check the field names being sent.\n";
        }
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
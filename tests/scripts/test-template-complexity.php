<?php

/**
 * Test how template complexity affects AI responses
 * Run: php tests/scripts/test-template-complexity.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use App\Services\DocuSeal\DocuSealTemplateDiscoveryService;

echo "Testing Template Complexity Impact on AI Service\n";
echo "===============================================\n\n";

$aiServiceUrl = env('MEDICAL_AI_SERVICE_URL', 'http://localhost:8081');
$aiServiceKey = env('MEDICAL_AI_SERVICE_API_KEY', '');

// Base form data
$formData = [
    'patient_first_name' => 'John',
    'patient_last_name' => 'Doe',
    'patient_dob' => '1980-01-15',
    'patient_phone' => '5551234567',
    'patient_address_line1' => '123 Main St',
    'patient_city' => 'New York',
    'patient_state' => 'NY',
    'patient_zip' => '10001',
    'primary_insurance_name' => 'Blue Cross Blue Shield',
    'primary_member_id' => 'ABC123456'
];

// Test 1: With full template structure from DocuSeal
echo "1. Testing with FULL template structure (43 fields):\n";
$templateDiscovery = app(DocuSealTemplateDiscoveryService::class);
$fullTemplate = $templateDiscovery->getCachedTemplateStructure('1233913');

$fullContext = [
    'template_structure' => [
        'template_fields' => $fullTemplate
    ],
    'fhir_context' => [],
    'base_data' => $formData,
    'manufacturer_context' => [
        'name' => 'Celularity',
        'template_id' => '1233913'
    ]
];

try {
    $response = Http::timeout(30)
        ->withHeaders([
            'Authorization' => 'Bearer ' . $aiServiceKey,
            'Content-Type' => 'application/json'
        ])
        ->post($aiServiceUrl . '/api/v1/enhance-mapping', [
            'context' => $fullContext,
            'optimization_level' => 'high',
            'confidence_threshold' => 0.7
        ]);

    $result = $response->json();
    echo "   Method: " . ($result['method'] ?? 'unknown') . "\n";
    echo "   Confidence: " . ($result['confidence'] ?? 0) . "\n";
    echo "   Enhanced fields: " . count($result['enhanced_fields'] ?? []) . "\n\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n\n";
}

// Test 2: With simplified template structure
echo "2. Testing with SIMPLIFIED template structure (field_names only):\n";
$simplifiedContext = [
    'template_structure' => [
        'template_fields' => [
            'field_names' => $fullTemplate['field_names'] ?? [],
            'required_fields' => $fullTemplate['required_fields'] ?? []
        ]
    ],
    'fhir_context' => [],
    'base_data' => $formData,
    'manufacturer_context' => [
        'name' => 'Celularity',
        'template_id' => '1233913'
    ]
];

try {
    $response = Http::timeout(30)
        ->withHeaders([
            'Authorization' => 'Bearer ' . $aiServiceKey,
            'Content-Type' => 'application/json'
        ])
        ->post($aiServiceUrl . '/api/v1/enhance-mapping', [
            'context' => $simplifiedContext,
            'optimization_level' => 'high',
            'confidence_threshold' => 0.7
        ]);

    $result = $response->json();
    echo "   Method: " . ($result['method'] ?? 'unknown') . "\n";
    echo "   Confidence: " . ($result['confidence'] ?? 0) . "\n";
    echo "   Enhanced fields: " . count($result['enhanced_fields'] ?? []) . "\n\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n\n";
}

// Test 3: Check what fields are in the full template that might cause issues
echo "3. Analyzing template structure:\n";
echo "   Total fields: " . count($fullTemplate['fields'] ?? []) . "\n";
echo "   Field names count: " . count($fullTemplate['field_names'] ?? []) . "\n";
echo "   Required fields: " . count($fullTemplate['required_fields'] ?? []) . "\n";
echo "   Has field_types: " . (isset($fullTemplate['field_types']) ? 'Yes' : 'No') . "\n";
echo "   Template name: " . ($fullTemplate['name'] ?? 'Unknown') . "\n";
echo "   Fetched at: " . ($fullTemplate['fetched_at'] ?? 'Unknown') . "\n\n";

// Test 4: Send minimal context to see AI behavior
echo "4. Testing with MINIMAL context:\n";
$minimalContext = [
    'base_data' => $formData,
    'manufacturer_context' => [
        'name' => 'Celularity'
    ]
];

try {
    $response = Http::timeout(30)
        ->withHeaders([
            'Authorization' => 'Bearer ' . $aiServiceKey,
            'Content-Type' => 'application/json'
        ])
        ->post($aiServiceUrl . '/api/v1/enhance-mapping', [
            'context' => $minimalContext,
            'optimization_level' => 'high',
            'confidence_threshold' => 0.7
        ]);

    $result = $response->json();
    echo "   Method: " . ($result['method'] ?? 'unknown') . "\n";
    echo "   Confidence: " . ($result['confidence'] ?? 0) . "\n";
    echo "   Enhanced fields: " . count($result['enhanced_fields'] ?? []) . "\n";
    
    // Show some enhanced fields
    if (!empty($result['enhanced_fields'])) {
        echo "   Sample fields:\n";
        $sample = array_slice($result['enhanced_fields'], 0, 5, true);
        foreach ($sample as $field => $value) {
            echo "     - $field: $value\n";
        }
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
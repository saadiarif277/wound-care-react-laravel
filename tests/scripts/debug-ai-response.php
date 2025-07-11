<?php

/**
 * Debug AI service responses in detail
 * Run: php tests/scripts/debug-ai-response.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

echo "Debugging AI Service Responses\n";
echo "==============================\n\n";

$aiServiceUrl = env('MEDICAL_AI_SERVICE_URL', 'http://localhost:8081');
$aiServiceKey = env('MEDICAL_AI_SERVICE_API_KEY', '');

// Minimal test data
$minimalContext = [
    'base_data' => [
        'patient_first_name' => 'John',
        'patient_last_name' => 'Doe',
        'patient_dob' => '1980-01-15'
    ],
    'manufacturer_context' => [
        'name' => 'Celularity'
    ]
];

// Test 1: Minimal context
echo "1. Testing with minimal context:\n";
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

    echo "Response status: " . $response->status() . "\n";
    $body = $response->json();
    echo "Response:\n" . json_encode($body, JSON_PRETTY_PRINT) . "\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Test 2: With template_structure but empty template_fields
echo "2. Testing with empty template structure:\n";
$contextWithEmptyTemplate = $minimalContext;
$contextWithEmptyTemplate['template_structure'] = [
    'template_fields' => []
];

try {
    $response = Http::timeout(30)
        ->withHeaders([
            'Authorization' => 'Bearer ' . $aiServiceKey,
            'Content-Type' => 'application/json'
        ])
        ->post($aiServiceUrl . '/api/v1/enhance-mapping', [
            'context' => $contextWithEmptyTemplate,
            'optimization_level' => 'high',
            'confidence_threshold' => 0.7
        ]);

    echo "Response status: " . $response->status() . "\n";
    $body = $response->json();
    echo "Method: " . ($body['method'] ?? 'unknown') . "\n";
    echo "Enhanced fields: " . count($body['enhanced_fields'] ?? []) . "\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Test 3: With actual template fields
echo "3. Testing with actual template fields:\n";
$contextWithTemplate = $minimalContext;
$contextWithTemplate['template_structure'] = [
    'template_fields' => [
        'field_names' => ['Patient name', 'DOB', 'Address'],
        'required_fields' => ['Patient name', 'DOB']
    ]
];

try {
    $response = Http::timeout(30)
        ->withHeaders([
            'Authorization' => 'Bearer ' . $aiServiceKey,
            'Content-Type' => 'application/json'
        ])
        ->post($aiServiceUrl . '/api/v1/enhance-mapping', [
            'context' => $contextWithTemplate,
            'optimization_level' => 'high',
            'confidence_threshold' => 0.7
        ]);

    echo "Response status: " . $response->status() . "\n";
    $body = $response->json();
    echo "Method: " . ($body['method'] ?? 'unknown') . "\n";
    echo "Confidence: " . ($body['confidence'] ?? 0) . "\n";
    echo "Enhanced fields: " . count($body['enhanced_fields'] ?? []) . "\n";
    
    if (!empty($body['enhanced_fields'])) {
        echo "Fields:\n";
        foreach ($body['enhanced_fields'] as $field => $value) {
            echo "  - $field: $value\n";
        }
    }
    echo "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Test 4: Check what the Python service expects
echo "4. Dumping full context structure that works:\n";
$workingContext = [
    'base_data' => [
        'patient_first_name' => 'John',
        'patient_last_name' => 'Doe',
        'patient_dob' => '1980-01-15',
        'patient_phone' => '5551234567',
        'patient_address_line1' => '123 Main St',
        'patient_city' => 'New York',
        'patient_state' => 'NY',
        'patient_zip' => '10001'
    ],
    'fhir_context' => [],
    'episode' => [],
    'template_structure' => [
        'template_fields' => [
            'field_names' => [
                'Patient name',
                'DOB',
                'Address',
                'City/State/Zip',
                'Primary Insurance',
                'Ins ID#'
            ],
            'required_fields' => [
                'Patient name',
                'DOB'
            ]
        ]
    ],
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
            'context' => $workingContext,
            'optimization_level' => 'high',
            'confidence_threshold' => 0.7
        ]);

    echo "Response status: " . $response->status() . "\n";
    $body = $response->json();
    echo "Method: " . ($body['method'] ?? 'unknown') . "\n";
    echo "Confidence: " . ($body['confidence'] ?? 0) . "\n";
    echo "Enhanced fields: " . count($body['enhanced_fields'] ?? []) . "\n";
    
    if (!empty($body['enhanced_fields'])) {
        echo "Fields:\n";
        foreach ($body['enhanced_fields'] as $field => $value) {
            echo "  - $field: $value\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
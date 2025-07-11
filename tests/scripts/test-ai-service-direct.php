<?php

/**
 * Test script to call AI service directly
 * Run: php tests/scripts/test-ai-service-direct.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$aiServiceUrl = env('MEDICAL_AI_SERVICE_URL', 'http://localhost:8081');
$aiServiceKey = env('MEDICAL_AI_SERVICE_API_KEY', '');

echo "Testing AI Service at: $aiServiceUrl\n";
echo "===========================================\n\n";

// Test 1: Health Check
echo "1. Testing Health Check...\n";
try {
    $response = Http::timeout(10)->get("$aiServiceUrl/health");
    if ($response->successful()) {
        echo "✅ Health check passed:\n";
        echo json_encode($response->json(), JSON_PRETTY_PRINT) . "\n\n";
    } else {
        echo "❌ Health check failed: Status " . $response->status() . "\n";
        echo $response->body() . "\n\n";
    }
} catch (Exception $e) {
    echo "❌ Health check error: " . $e->getMessage() . "\n\n";
}

// Test 2: API Test Endpoint
echo "2. Testing API v1 Test Endpoint...\n";
try {
    $response = Http::timeout(10)->get("$aiServiceUrl/api/v1/test");
    if ($response->successful()) {
        echo "✅ API test passed:\n";
        echo json_encode($response->json(), JSON_PRETTY_PRINT) . "\n\n";
    } else {
        echo "❌ API test failed: Status " . $response->status() . "\n";
        echo $response->body() . "\n\n";
    }
} catch (Exception $e) {
    echo "❌ API test error: " . $e->getMessage() . "\n\n";
}

// Test 3: Field Enhancement with Sample Data
echo "3. Testing Field Enhancement...\n";

// Sample form data that would come from frontend
$sampleFormData = [
    'patient_first_name' => 'John',
    'patient_last_name' => 'Doe',
    'patient_dob' => '1980-01-15',
    'patient_phone' => '5551234567',
    'patient_address_line1' => '123 Main St',
    'patient_city' => 'New York',
    'patient_state' => 'NY',
    'patient_zip' => '10001',
    'primary_insurance_name' => 'Blue Cross Blue Shield',
    'primary_member_id' => 'ABC123456',
    'provider_name' => 'Dr. Smith',
    'provider_npi' => '1234567890',
    'facility_name' => 'Main Street Clinic',
    'wound_location' => 'Left Foot',
    'wound_size_length' => '2',
    'wound_size_width' => '3',
    'wound_size_depth' => '0.5',
    'primary_diagnosis_code' => 'L97.321'
];

// Template fields (simulating what would come from DocuSeal)
$templateFields = [
    'field_names' => [
        'Patient name',
        'DOB',
        'Address',
        'City/State/Zip',
        'Primary Insurance',
        'Ins ID#',
        'Rendering Physician Name',
        'NPI',
        'Facility Name',
        'Wound Location',
        'Wound Size(s)',
        'Diagnosis ICD-10 Codes'
    ],
    'required_fields' => [
        'Patient name',
        'DOB',
        'Primary Insurance',
        'Ins ID#'
    ]
];

// Build context for AI service
$context = [
    'base_data' => $sampleFormData,
    'fhir_context' => [], // Empty for this test
    'episode' => [],
    'template_structure' => [
        'template_fields' => $templateFields
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
        ->post("$aiServiceUrl/api/v1/enhance-mapping", [
            'context' => $context,
            'optimization_level' => 'high',
            'confidence_threshold' => 0.7
        ]);

    if ($response->successful()) {
        echo "✅ Field enhancement successful:\n";
        $result = $response->json();
        echo "Method: " . ($result['method'] ?? 'unknown') . "\n";
        echo "Confidence: " . ($result['confidence'] ?? 0) . "\n";
        echo "Enhanced fields count: " . count($result['enhanced_fields'] ?? []) . "\n";
        echo "\nEnhanced fields:\n";
        foreach ($result['enhanced_fields'] ?? [] as $field => $value) {
            echo "  $field: $value\n";
        }
        echo "\n";
    } else {
        echo "❌ Field enhancement failed: Status " . $response->status() . "\n";
        echo $response->body() . "\n\n";
    }
} catch (Exception $e) {
    echo "❌ Field enhancement error: " . $e->getMessage() . "\n";
    echo "Error class: " . get_class($e) . "\n";
    if ($e instanceof \Illuminate\Http\Client\RequestException) {
        echo "Response: " . $e->response->body() . "\n";
    }
    echo "\n";
}

// Test 4: Test with Celularity manufacturer config
echo "4. Testing with Celularity manufacturer config...\n";

$contextWithManufacturer = $context;
$contextWithManufacturer['manufacturer_context'] = [
    'name' => 'Celularity',
    'template_id' => '1233913'
];

// Add more Celularity-specific fields
$contextWithManufacturer['base_data']['procedure_date'] = '2024-01-20';
$contextWithManufacturer['base_data']['product_name'] = 'Biovance';
$contextWithManufacturer['base_data']['product_quantity'] = '1';
$contextWithManufacturer['base_data']['product_size'] = '2x3 cm';

try {
    $response = Http::timeout(30)
        ->withHeaders([
            'Authorization' => 'Bearer ' . $aiServiceKey,
            'Content-Type' => 'application/json'
        ])
        ->post("$aiServiceUrl/api/v1/enhance-mapping", [
            'context' => $contextWithManufacturer,
            'optimization_level' => 'high',
            'confidence_threshold' => 0.7
        ]);

    if ($response->successful()) {
        echo "✅ Celularity enhancement successful:\n";
        $result = $response->json();
        echo "Method: " . ($result['method'] ?? 'unknown') . "\n";
        echo "Confidence: " . ($result['confidence'] ?? 0) . "\n";
        echo "Enhanced fields count: " . count($result['enhanced_fields'] ?? []) . "\n";
        
        // Count how many expected fields were mapped
        $expectedFields = [
            'Patient name', 'DOB', 'Address', 'City/State/Zip',
            'Primary Insurance', 'Ins ID#', 'Rendering Physician Name',
            'NPI', 'Facility Name', 'Wound Location', 'Wound Size(s)',
            'Diagnosis ICD-10 Codes', 'Procedure Date'
        ];
        
        $mappedCount = 0;
        foreach ($expectedFields as $field) {
            if (isset($result['enhanced_fields'][$field])) {
                $mappedCount++;
            }
        }
        
        echo "Mapped $mappedCount out of " . count($expectedFields) . " expected fields\n";
        echo "Fill rate: " . round(($mappedCount / count($expectedFields)) * 100, 1) . "%\n";
        
        echo "\nSample enhanced fields:\n";
        $sample = array_slice($result['enhanced_fields'] ?? [], 0, 10, true);
        foreach ($sample as $field => $value) {
            echo "  $field: $value\n";
        }
        echo "\n";
    } else {
        echo "❌ Celularity enhancement failed: Status " . $response->status() . "\n";
        echo $response->body() . "\n\n";
    }
} catch (Exception $e) {
    echo "❌ Celularity enhancement error: " . $e->getMessage() . "\n\n";
}

echo "\nTest completed.\n";
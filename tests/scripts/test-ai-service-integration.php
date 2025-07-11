<?php

/**
 * Test AI service integration with OptimizedMedicalAiService
 * Run: php tests/scripts/test-ai-service-integration.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Medical\OptimizedMedicalAiService;
use App\Models\Manufacturer;

echo "Testing AI Service Integration\n";
echo "==============================\n\n";

// Get the AI service
$aiService = app(OptimizedMedicalAiService::class);

// Test status
echo "1. AI Service Status:\n";
$status = $aiService->getStatus();
echo json_encode($status, JSON_PRETTY_PRINT) . "\n\n";

// Sample form data that would come from frontend
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

// Test with Celularity
echo "2. Testing enhanceWithDynamicTemplate for Celularity:\n";

try {
    // Add more fields for Celularity
    $formData['procedure_date'] = '2024-01-20';
    $formData['product_name'] = 'Biovance';
    $formData['product_quantity'] = '1';
    $formData['product_size'] = '2x3 cm';
    
    $result = $aiService->enhanceWithDynamicTemplate(
        $formData,           // FHIR data (using form data)
        '1233913',           // Template ID (example)
        'Celularity',        // Manufacturer name
        []                   // Additional data
    );
    
    echo "✅ Enhancement completed:\n";
    echo "Method: " . ($result['_ai_method'] ?? 'unknown') . "\n";
    echo "Confidence: " . ($result['_ai_confidence'] ?? 0) . "\n";
    echo "Enhanced fields count: " . count($result['enhanced_fields'] ?? $result) . "\n";
    
    // Show enhanced fields
    echo "\nEnhanced fields:\n";
    $enhanced = $result['enhanced_fields'] ?? $result;
    $count = 0;
    foreach ($enhanced as $field => $value) {
        if (!str_starts_with($field, '_')) {
            echo "  $field: $value\n";
            $count++;
            if ($count >= 10) {
                echo "  ... (" . (count($enhanced) - 10) . " more fields)\n";
                break;
            }
        }
    }
    
    // Check for error
    if (isset($result['_error'])) {
        echo "\n⚠️ Error occurred: " . $result['_error'] . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Enhancement failed:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Class: " . get_class($e) . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n3. Testing with different manufacturer:\n";

// Test with a different manufacturer
$manufacturers = Manufacturer::limit(5)->get();
echo "Available manufacturers:\n";
foreach ($manufacturers as $mfr) {
    echo "  - ID: {$mfr->id}, Name: {$mfr->name}\n";
}

// Test with first available manufacturer
if ($manufacturers->isNotEmpty()) {
    $testManufacturer = $manufacturers->first();
    echo "\nTesting with manufacturer: {$testManufacturer->name}\n";
    
    try {
        $result = $aiService->enhanceWithDynamicTemplate(
            $formData,
            '123456',  // Dummy template ID
            $testManufacturer->name,
            []
        );
        
        echo "Result method: " . ($result['_ai_method'] ?? 'unknown') . "\n";
        echo "Enhanced fields: " . count($result['enhanced_fields'] ?? $result) . "\n";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "\n4. Checking logs:\n";
// Check if there are any recent log entries
$logFile = storage_path('logs/laravel-' . date('Y-m-d') . '.log');
if (file_exists($logFile)) {
    $recentLogs = `tail -20 $logFile | grep -E "AI|enhance|medical" | tail -10`;
    if ($recentLogs) {
        echo "Recent relevant log entries:\n";
        echo $recentLogs;
    } else {
        echo "No recent AI-related log entries found.\n";
    }
} else {
    echo "Log file not found: $logFile\n";
}

echo "\nTest completed.\n";
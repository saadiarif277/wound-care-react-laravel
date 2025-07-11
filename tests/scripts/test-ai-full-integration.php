<?php

/**
 * Test full AI integration with template discovery
 * Run: php tests/scripts/test-ai-full-integration.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Medical\OptimizedMedicalAiService;
use App\Services\DocuSeal\DocuSealTemplateDiscoveryService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

echo "Testing Full AI Integration with Template Discovery\n";
echo "==================================================\n\n";

// Get services
$aiService = app(OptimizedMedicalAiService::class);
$templateDiscovery = app(DocuSealTemplateDiscoveryService::class);

$testTemplateId = '1233913';
$manufacturerName = 'Celularity';

// Sample form data
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
    'primary_diagnosis_code' => 'L97.321',
    'procedure_date' => '2024-01-20',
    'product_name' => 'Biovance',
    'product_quantity' => '1',
    'product_size' => '2x3 cm'
];

// Step 1: Get template fields
echo "1. Fetching template fields from DocuSeal:\n";
try {
    $templateFields = $templateDiscovery->getCachedTemplateStructure($testTemplateId);
    echo "✅ Template fields fetched successfully\n";
    echo "   Total fields: " . count($templateFields['field_names']) . "\n";
    echo "   Fields: " . implode(', ', array_slice($templateFields['field_names'], 0, 5)) . "...\n\n";
} catch (Exception $e) {
    echo "❌ Failed to fetch template: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Step 2: Build the exact context that will be sent to AI
echo "2. Building context for AI service:\n";
$context = [
    'template_structure' => [
        'template_fields' => $templateFields
    ],
    'fhir_context' => [],
    'base_data' => $formData,
    'manufacturer_context' => [
        'name' => $manufacturerName,
        'template_id' => $testTemplateId
    ],
    'field_names' => $templateFields['field_names'] ?? [],
    'required_fields' => $templateFields['required_fields'] ?? [],
    'mapping_mode' => 'dynamic_template'
];

echo "✅ Context built with:\n";
echo "   - " . count($context['base_data']) . " form fields\n";
echo "   - " . count($context['field_names']) . " template fields\n";
echo "   - Manufacturer: " . $context['manufacturer_context']['name'] . "\n\n";

// Step 3: Call AI service directly with HTTP client (mimicking OptimizedMedicalAiService)
echo "3. Calling AI service directly with HTTP client:\n";
$aiServiceUrl = env('MEDICAL_AI_SERVICE_URL', 'http://localhost:8081');
$aiServiceKey = env('MEDICAL_AI_SERVICE_API_KEY', '');

try {
    $response = Http::timeout(30)
        ->withHeaders([
            'Authorization' => 'Bearer ' . $aiServiceKey,
            'Content-Type' => 'application/json'
        ])
        ->post($aiServiceUrl . '/api/v1/enhance-mapping', [
            'context' => $context,
            'optimization_level' => 'high',
            'confidence_threshold' => 0.7
        ]);

    if ($response->successful()) {
        $result = $response->json();
        echo "✅ AI service response:\n";
        echo "   Method: " . ($result['method'] ?? 'unknown') . "\n";
        echo "   Confidence: " . ($result['confidence'] ?? 0) . "\n";
        echo "   Enhanced fields: " . count($result['enhanced_fields'] ?? []) . "\n";
        
        if (count($result['enhanced_fields'] ?? []) > 0) {
            echo "\n   Sample enhanced fields:\n";
            $sample = array_slice($result['enhanced_fields'] ?? [], 0, 5, true);
            foreach ($sample as $field => $value) {
                echo "     - $field: $value\n";
            }
        }
    } else {
        echo "❌ AI service failed: Status " . $response->status() . "\n";
        echo "   Body: " . $response->body() . "\n";
    }
} catch (Exception $e) {
    echo "❌ HTTP request failed: " . $e->getMessage() . "\n";
}

// Step 4: Call through OptimizedMedicalAiService
echo "\n4. Calling through OptimizedMedicalAiService:\n";
try {
    $result = $aiService->enhanceWithDynamicTemplate(
        $formData,
        $testTemplateId,
        $manufacturerName,
        []
    );
    
    echo "✅ Service response:\n";
    echo "   Method: " . ($result['_ai_method'] ?? 'unknown') . "\n";
    echo "   Confidence: " . ($result['_ai_confidence'] ?? 0) . "\n";
    
    $enhancedFields = $result['enhanced_fields'] ?? $result;
    // Remove metadata fields
    $actualFields = array_filter($enhancedFields, function($key) {
        return !str_starts_with($key, '_');
    }, ARRAY_FILTER_USE_KEY);
    
    echo "   Enhanced fields: " . count($actualFields) . "\n";
    
    if (count($actualFields) > 0) {
        echo "\n   Sample enhanced fields:\n";
        $sample = array_slice($actualFields, 0, 5, true);
        foreach ($sample as $field => $value) {
            echo "     - $field: $value\n";
        }
    }
    
    if (isset($result['_error'])) {
        echo "\n⚠️ Error reported: " . $result['_error'] . "\n";
    }
} catch (Exception $e) {
    echo "❌ Service call failed: " . $e->getMessage() . "\n";
    echo "   Class: " . get_class($e) . "\n";
}

// Step 5: Compare the two approaches
echo "\n5. Debugging differences:\n";

// Check if AI service is actually enabled
echo "   AI enabled: " . (config('services.medical_ai.enabled') ? 'Yes' : 'No') . "\n";
echo "   Use for DocuSeal: " . (config('services.medical_ai.use_for_docuseal') ? 'Yes' : 'No') . "\n";
echo "   Service URL: " . config('services.medical_ai.url') . "\n";
echo "   Fallback enabled: " . (config('services.medical_ai.fallback_enabled') ? 'Yes' : 'No') . "\n";

// Check recent logs
echo "\n6. Recent AI-related log entries:\n";
$logFile = storage_path('logs/laravel-' . date('Y-m-d') . '.log');
if (file_exists($logFile)) {
    $recentLogs = `tail -100 $logFile | grep -E "AI|enhance|medical" | tail -20`;
    if ($recentLogs) {
        echo $recentLogs;
    } else {
        echo "No recent AI-related logs found.\n";
    }
}

echo "\nTest completed.\n";
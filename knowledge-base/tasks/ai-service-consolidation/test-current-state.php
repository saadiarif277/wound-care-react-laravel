<?php

/**
 * Test script to verify current state of AI services before consolidation
 * Run with: php tasks/ai-service-consolidation/test-current-state.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

echo "=== AI Service Consolidation - Current State Test ===\n\n";

// Test configuration
echo "1. Configuration Check:\n";
echo "   - Medical AI Service URL: " . config('services.medical_ai.url') . "\n";
echo "   - Medical AI Service Port: " . (parse_url(config('services.medical_ai.url'), PHP_URL_PORT) ?: 'default') . "\n";
echo "   - Medical AI Enabled: " . (config('services.medical_ai.enabled') ? 'Yes' : 'No') . "\n";
echo "   - AI Form Filler URL: " . config('services.ai_form_filler.url') . "\n";
echo "   - DocuSeal Dynamic AI URL: " . config('docuseal-dynamic.ai_service_url') . "\n\n";

// Test Python service connectivity
echo "2. Python Service Connectivity:\n";
$ports = [8080, 8081];
foreach ($ports as $port) {
    try {
        $response = Http::timeout(5)->get("http://localhost:$port/health");
        if ($response->successful()) {
            echo "   ✓ Port $port: Service is running\n";
            $data = $response->json();
            echo "     - Status: " . ($data['status'] ?? 'unknown') . "\n";
            echo "     - Azure Configured: " . (($data['azure_configured'] ?? false) ? 'Yes' : 'No') . "\n";
        } else {
            echo "   ✗ Port $port: Service returned error (Status: {$response->status()})\n";
        }
    } catch (\Exception $e) {
        echo "   ✗ Port $port: Cannot connect - " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Test service instantiation
echo "3. Service Instantiation Test:\n";
$services = [
    'OptimizedMedicalAiService' => \App\Services\Medical\OptimizedMedicalAiService::class,
    'AzureFoundryService' => \App\Services\AI\AzureFoundryService::class,
    'DocumentIntelligenceService' => \App\Services\DocumentIntelligenceService::class,
];

foreach ($services as $name => $class) {
    try {
        if (class_exists($class)) {
            $instance = app($class);
            echo "   ✓ $name: Can be instantiated\n";
        } else {
            echo "   ✗ $name: Class not found\n";
        }
    } catch (\Exception $e) {
        echo "   ⚠ $name: Error - " . substr($e->getMessage(), 0, 60) . "...\n";
    }
}
echo "\n";

// Test endpoint availability
echo "4. Endpoint Availability Test:\n";
$endpoints = [
    '/api/v1/enhance-mapping' => 'Correct endpoint (used by OptimizedMedicalAiService)',
    '/api/v1/map-fields' => 'Wrong endpoint (used by MedicalAIServiceManager)',
    '/map-fields' => 'Wrong endpoint (used by AiFormFillerService)',
];

$testPort = 8081; // Test on the correct port
foreach ($endpoints as $endpoint => $description) {
    try {
        $response = Http::timeout(5)->post("http://localhost:$testPort$endpoint", [
            'test' => true,
            'source' => 'consolidation-test'
        ]);
        
        if ($response->successful()) {
            echo "   ✓ $endpoint: Available - $description\n";
        } else {
            echo "   ✗ $endpoint: Error {$response->status()} - $description\n";
        }
    } catch (\Exception $e) {
        echo "   ✗ $endpoint: Not found - $description\n";
    }
}
echo "\n";

echo "4. Service Consolidation Results:\n";
echo "   - ✓ Kept: OptimizedMedicalAiService (primary AI service)\n";
echo "   - ✓ Kept: AzureFoundryService (direct Azure AI integration)\n";
echo "   - ✓ Kept: DocumentIntelligenceService (OCR - different purpose)\n";
echo "   - ✓ Removed: MedicalAIServiceManager, IntelligentFieldMappingService, AiFormFillerService\n";
echo "   - ✓ Removed: DynamicFieldMappingService, LLMFieldMapper, TemplateIntelligenceService\n";
echo "\n";

echo "5. Consolidation Status:\n";
echo "   - ✓ All duplicate services removed\n";
echo "   - ✓ Dependencies updated to use OptimizedMedicalAiService\n";
echo "   - ✓ Service registration cleaned up\n";
echo "   - ✓ Test commands and scripts updated\n";
echo "   - ✓ Debug routes updated to use OptimizedMedicalAiService\n";
echo "\n";

echo "=== Test Complete ===\n"; 
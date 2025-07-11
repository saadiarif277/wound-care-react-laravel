<?php

/**
 * Test script for DocuSeal template discovery
 * Run: php tests/scripts/test-template-discovery.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\DocuSeal\DocuSealTemplateDiscoveryService;
use Illuminate\Support\Facades\Log;

echo "Testing DocuSeal Template Discovery Service\n";
echo "==========================================\n\n";

// Get the template discovery service
$templateDiscovery = app(DocuSealTemplateDiscoveryService::class);

// Test 1: Check API connectivity
echo "1. Testing DocuSeal API connectivity:\n";
try {
    $connectionTest = $templateDiscovery->testConnection();
    if ($connectionTest['connected']) {
        echo "✅ Connected to DocuSeal API\n";
        echo "   Status: " . $connectionTest['status'] . "\n";
        echo "   Templates available: " . $connectionTest['templates_count'] . "\n";
        echo "   API URL: " . $connectionTest['api_url'] . "\n\n";
    } else {
        echo "❌ Failed to connect to DocuSeal API\n";
        echo "   Error: " . $connectionTest['error'] . "\n\n";
    }
} catch (Exception $e) {
    echo "❌ Connection test failed: " . $e->getMessage() . "\n\n";
}

// Test 2: Try to fetch a specific template
$testTemplateId = '1233913'; // Celularity template ID from previous tests

echo "2. Testing template fetch for ID: $testTemplateId\n";
try {
    // Clear cache first to force fresh fetch
    $templateDiscovery->clearTemplateCache($testTemplateId);
    echo "   Cache cleared for template\n";
    
    // Try to fetch template fields
    $templateFields = $templateDiscovery->getTemplateFields($testTemplateId);
    
    echo "✅ Successfully fetched template fields:\n";
    echo "   Template name: " . $templateFields['name'] . "\n";
    echo "   Total fields: " . $templateFields['total_fields'] . "\n";
    echo "   Required fields: " . count($templateFields['required_fields']) . "\n";
    echo "   Field names: " . implode(', ', array_slice($templateFields['field_names'], 0, 5)) . "...\n\n";
    
    // Validate structure
    if ($templateDiscovery->validateTemplateStructure($templateFields)) {
        echo "✅ Template structure is valid\n\n";
    } else {
        echo "❌ Template structure validation failed\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Failed to fetch template fields: " . $e->getMessage() . "\n";
    echo "   Error class: " . get_class($e) . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n\n";
}

// Test 3: Test cached fetch
echo "3. Testing cached template fetch:\n";
try {
    $start = microtime(true);
    $cachedFields = $templateDiscovery->getCachedTemplateStructure($testTemplateId);
    $elapsed = microtime(true) - $start;
    
    echo "✅ Cached fetch completed in " . round($elapsed * 1000, 2) . "ms\n";
    echo "   Fields count: " . count($cachedFields['fields']) . "\n\n";
    
} catch (Exception $e) {
    echo "❌ Cached fetch failed: " . $e->getMessage() . "\n\n";
}

// Test 4: Check what the AI service would receive
echo "4. Building context for AI service:\n";
try {
    $sampleData = [
        'patient_first_name' => 'John',
        'patient_last_name' => 'Doe',
        'patient_dob' => '1980-01-15'
    ];
    
    // Build the context that would be sent to AI
    $context = [
        'template_structure' => [
            'template_fields' => $cachedFields ?? []
        ],
        'base_data' => $sampleData,
        'manufacturer_context' => [
            'name' => 'Celularity',
            'template_id' => $testTemplateId
        ]
    ];
    
    echo "✅ Context built successfully\n";
    echo "   Template has fields: " . (isset($context['template_structure']['template_fields']['field_names']) ? 'Yes' : 'No') . "\n";
    echo "   Field count in context: " . count($context['template_structure']['template_fields']['field_names'] ?? []) . "\n\n";
    
    // Show a sample of the context
    echo "   Sample field names from template:\n";
    $sampleFields = array_slice($context['template_structure']['template_fields']['field_names'] ?? [], 0, 10);
    foreach ($sampleFields as $field) {
        echo "     - $field\n";
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ Failed to build context: " . $e->getMessage() . "\n\n";
}

// Test 5: Check DocuSeal API configuration
echo "5. Checking DocuSeal API configuration:\n";
$apiKey = config('services.docuseal.api_key');
$apiUrl = config('services.docuseal.api_url');

echo "   API Key configured: " . (!empty($apiKey) ? 'Yes' : 'No') . "\n";
echo "   API URL: " . ($apiUrl ?: 'default') . "\n";
echo "   Timeout: " . config('services.docuseal.timeout', 30) . " seconds\n";
echo "   Cache enabled: " . (config('services.docuseal.cache_enabled', true) ? 'Yes' : 'No') . "\n";
echo "   Cache TTL: " . config('services.docuseal.cache_ttl', 3600) . " seconds\n\n";

// Test 6: Check recent logs for template discovery errors
echo "6. Checking recent logs for template discovery errors:\n";
$logFile = storage_path('logs/laravel-' . date('Y-m-d') . '.log');
if (file_exists($logFile)) {
    $recentLogs = `tail -50 $logFile | grep -i "template" | grep -E "error|failed|exception" | tail -10`;
    if ($recentLogs) {
        echo "Recent template-related errors:\n";
        echo $recentLogs . "\n";
    } else {
        echo "No recent template errors found in logs.\n";
    }
} else {
    echo "Log file not found.\n";
}

echo "\nTest completed.\n";
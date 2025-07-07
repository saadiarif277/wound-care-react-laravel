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

echo "=== Debugging DocuSeal Field Case Sensitivity ===\n\n";

try {
    // Get BioWound template ID
    $mappingService = app(UnifiedFieldMappingService::class);
    $config = $mappingService->getManufacturerConfig('BioWound Solutions');
    $templateId = $config['docuseal_template_id'] ?? null;
    
    echo "1. BioWound Solutions Template ID: $templateId\n\n";
    
    if (!$templateId) {
        echo "ERROR: No template ID found for BioWound Solutions\n";
        exit(1);
    }
    
    // Get fields from DocuSeal API
    echo "2. Fetching template fields from DocuSeal API...\n";
    $docusealService = app(DocusealService::class);
    
    // Test the API directly
    $apiKey = config('services.docuseal.api_key');
    $apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');
    
    $response = \Illuminate\Support\Facades\Http::withHeaders([
        'X-Auth-Token' => $apiKey,
        'Content-Type' => 'application/json',
    ])->get("{$apiUrl}/templates/{$templateId}");
    
    echo "   API Response Status: " . $response->status() . "\n";
    if (!$response->successful()) {
        echo "   API Error: " . $response->body() . "\n";
    } else {
        $templateData = $response->json();
        echo "   Template data keys: " . implode(', ', array_keys($templateData ?? [])) . "\n";
        
        // Check fields structure
        if (isset($templateData['fields'])) {
            echo "   Template has 'fields' array with " . count($templateData['fields']) . " items\n";
            
            // Look for fields containing "name" or "Name"
            echo "\n   Fields containing 'name':\n";
            foreach ($templateData['fields'] as $field) {
                if (stripos($field['name'], 'name') !== false) {
                    echo "   - '{$field['name']}' (type: {$field['type']})\n";
                }
            }
            
            // Look for first few text fields
            echo "\n   First 10 fields:\n";
            foreach (array_slice($templateData['fields'], 0, 10) as $i => $field) {
                echo "   " . ($i + 1) . ". '{$field['name']}' (type: {$field['type']})\n";
            }
        }
        
        // Check schema structure
        if (isset($templateData['schema'])) {
            echo "   Template has 'schema' with " . count($templateData['schema']) . " items\n";
        }
    }
    
    $templateFields = $docusealService->getTemplateFieldsFromAPI($templateId);
    echo "   Found " . count($templateFields) . " fields in template\n";
    
    // Check for fields with "Name" vs "name"
    echo "\n3. Checking for case-sensitive field names:\n";
    $caseIssues = [];
    foreach ($templateFields as $fieldName => $fieldInfo) {
        if (strtolower($fieldName) !== $fieldName) {
            $caseIssues[] = $fieldName;
            echo "   - Found uppercase field: '$fieldName'\n";
        }
    }
    
    if (empty($caseIssues)) {
        echo "   All fields are lowercase (no case issues found)\n";
    }
    
    // Show first 20 field names
    echo "\n4. First 20 field names from DocuSeal template:\n";
    $fieldNames = array_keys($templateFields);
    foreach (array_slice($fieldNames, 0, 20) as $i => $name) {
        echo "   " . ($i + 1) . ". $name\n";
    }
    
    // Check if specific fields exist
    echo "\n5. Checking for specific fields:\n";
    $checkFields = ['Name', 'name', 'Email', 'email', 'Phone', 'phone'];
    foreach ($checkFields as $field) {
        $exists = isset($templateFields[$field]);
        echo "   - '$field': " . ($exists ? "✓ EXISTS" : "✗ NOT FOUND") . "\n";
    }
    
    // Test mapping
    echo "\n6. Testing field mapping...\n";
    $testEpisode = new PatientManufacturerIVREpisode();
    $testEpisode->id = 'test-case-' . uniqid();
    $testEpisode->manufacturer_name = 'BioWound Solutions';
    $testEpisode->metadata = [
        'patient_data' => ['first_name' => 'Test', 'last_name' => 'User'],
        'provider_data' => ['name' => 'Dr. Test'],
        'facility_data' => ['name' => 'Test Facility'],
        'clinical_data' => [],
        'insurance_data' => [],
        'order_details' => ['products' => []]
    ];
    
    $orchestrator = app(QuickRequestOrchestrator::class);
    $orchestratorData = $orchestrator->prepareDocusealData($testEpisode);
    
    $mappingResult = $mappingService->mapEpisodeToTemplate(
        null,
        'BioWound Solutions',
        $orchestratorData
    );
    
    // Check what fields are being sent
    echo "\n7. Fields being sent to DocuSeal:\n";
    $mappedFields = $mappingResult['data'] ?? [];
    foreach (array_slice($mappedFields, 0, 10) as $key => $value) {
        echo "   - '$key' => '" . (is_bool($value) ? ($value ? 'true' : 'false') : substr($value, 0, 50)) . "'\n";
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Debug Complete ===\n";
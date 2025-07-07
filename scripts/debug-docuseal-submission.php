<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\DocusealService;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Testing DocuSeal Submission Field Validation ===\n\n";

try {
    // Get BioWound template fields
    $docusealService = app(DocusealService::class);
    $templateId = '1254774'; // BioWound Solutions template
    
    echo "1. Fetching template fields from DocuSeal...\n";
    $templateFields = $docusealService->getTemplateFieldsFromAPI($templateId);
    echo "   Found " . count($templateFields) . " fields\n\n";
    
    // Test data that would be sent
    $testFields = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '555-1234',
        'territory' => 'United States',
        'sales_rep' => 'Test Rep',
        'rep_email' => 'rep@example.com',
        'physician_name' => 'Dr. Test',
        'facility_name' => 'Test Facility',
        'contact_name' => 'Test Contact',
    ];
    
    echo "2. Checking which fields would be accepted:\n";
    foreach ($testFields as $key => $value) {
        $exists = isset($templateFields[$key]);
        if ($exists) {
            echo "   ✓ '$key' maps to template field '{$templateFields[$key]['id']}'\n";
        } else {
            // Try to find the DocuSeal field name from config
            $config = config('manufacturers.biowound-solutions.docuseal_field_names');
            $docusealName = $config[$key] ?? null;
            if ($docusealName && isset($templateFields[$docusealName])) {
                echo "   → '$key' should be sent as '$docusealName' (found in template)\n";
            } else {
                echo "   ✗ '$key' - NOT FOUND in template\n";
                // Try to find similar fields
                $similar = [];
                foreach (array_keys($templateFields) as $templateField) {
                    if (stripos($templateField, $key) !== false || stripos($key, strtolower(str_replace(' ', '_', $templateField))) !== false) {
                        $similar[] = $templateField;
                    }
                }
                if (!empty($similar)) {
                    echo "      Possible matches: " . implode(', ', $similar) . "\n";
                }
            }
        }
    }
    
    // Check what the prepareFieldsForDocuseal method would do
    echo "\n3. Testing prepareFieldsForDocuseal method:\n";
    $reflection = new ReflectionClass($docusealService);
    $method = $reflection->getMethod('prepareFieldsForDocuseal');
    $method->setAccessible(true);
    
    $preparedFields = $method->invoke($docusealService, $testFields, $templateId);
    echo "   Input fields: " . count($testFields) . "\n";
    echo "   Output fields: " . count($preparedFields) . "\n";
    
    if (count($preparedFields) < count($testFields)) {
        echo "\n   Fields that were skipped:\n";
        foreach ($testFields as $key => $value) {
            $found = false;
            foreach ($preparedFields as $field) {
                if ($field['name'] === $key || $field['name'] === ($templateFields[$key]['id'] ?? '')) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                echo "   - $key\n";
            }
        }
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
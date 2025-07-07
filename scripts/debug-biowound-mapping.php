<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\UnifiedFieldMappingService;
use Illuminate\Support\Facades\Log;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing BioWound Solutions field mapping...\n\n";

try {
    $mappingService = app(UnifiedFieldMappingService::class);
    
    // Test different variations of the manufacturer name
    $manufacturerNames = [
        'BioWound Solutions',
        'BIOWOUND SOLUTIONS',
        'Biowound Solutions',
        'biowound-solutions',
        'BioWound',
        'Biowound'
    ];
    
    foreach ($manufacturerNames as $name) {
        echo "Testing manufacturer name: '{$name}'\n";
        echo str_repeat('-', 50) . "\n";
        
        $config = $mappingService->getManufacturerConfig($name);
        
        if ($config) {
            echo "✅ Config found!\n";
            echo "  - ID: " . ($config['id'] ?? 'NOT SET') . "\n";
            echo "  - Name: " . ($config['name'] ?? 'NOT SET') . "\n";
            echo "  - Template ID: " . ($config['template_id'] ?? 'NOT SET') . "\n";
            echo "  - Docuseal Template ID: " . ($config['docuseal_template_id'] ?? 'NOT SET') . "\n";
            echo "  - Has Order Form: " . ($config['has_order_form'] ? 'Yes' : 'No') . "\n";
            echo "  - Field Count: " . count($config['fields'] ?? []) . "\n";
            echo "  - Available Keys: " . implode(', ', array_keys($config)) . "\n";
        } else {
            echo "❌ Config NOT found\n";
        }
        
        echo "\n";
    }
    
    // Test full mapping with sample data
    echo "\nTesting full field mapping for BioWound Solutions...\n";
    echo str_repeat('=', 60) . "\n";
    
    $sampleData = [
        'patient_first_name' => 'John',
        'patient_last_name' => 'Doe',
        'patient_dob' => '1980-01-01',
        'patient_gender' => 'Male',
        'provider_name' => 'Dr. Smith',
        'provider_npi' => '1234567890',
        'facility_name' => 'Test Hospital',
        'primary_insurance_name' => 'Medicare',
        'primary_member_id' => 'M12345',
        'wound_type' => 'DFU',
        'wound_location' => 'Left foot',
        'selected_products' => [
            ['code' => 'Q4239', 'name' => 'Amnio-Maxx']
        ],
        'q4239' => true, // Product selection checkbox
        'amnio_maxx' => true
    ];
    
    try {
        $mappingResult = $mappingService->mapEpisodeToTemplate(
            null, // No episode ID for test
            'BioWound Solutions',
            $sampleData
        );
        
        echo "✅ Mapping successful!\n";
        echo "  - Mapped fields: " . count($mappingResult['data']) . "\n";
        echo "  - Validation: " . ($mappingResult['validation']['valid'] ? 'VALID' : 'INVALID') . "\n";
        echo "  - Completeness: " . $mappingResult['completeness']['percentage'] . "%\n";
        echo "  - Manufacturer config keys: " . implode(', ', array_keys($mappingResult['manufacturer'])) . "\n";
        
        if (isset($mappingResult['manufacturer']['template_id'])) {
            echo "  - Template ID: " . $mappingResult['manufacturer']['template_id'] . "\n";
        }
        if (isset($mappingResult['manufacturer']['docuseal_template_id'])) {
            echo "  - Docuseal Template ID: " . $mappingResult['manufacturer']['docuseal_template_id'] . "\n";
        }
        
        if (!empty($mappingResult['validation']['errors'])) {
            echo "\nValidation Errors:\n";
            foreach ($mappingResult['validation']['errors'] as $error) {
                echo "  - $error\n";
            }
        }
        
        if (!empty($mappingResult['validation']['warnings'])) {
            echo "\nValidation Warnings:\n";
            foreach ($mappingResult['validation']['warnings'] as $warning) {
                echo "  - $warning\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Mapping failed: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nDebug script completed.\n";
<?php

/**
 * Debug script to identify why Advanced Solution is using "Patient Full Name"
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\UnifiedFieldMappingService;
use Illuminate\Support\Str;

echo "=== Advanced Solution Configuration Debug ===\n\n";

// Test 1: Check what filename would be generated
echo "=== Test 1: Filename Generation ===\n";
$manufacturerName = 'ADVANCED SOLUTION';
$filename = Str::slug($manufacturerName);
echo "Manufacturer: '{$manufacturerName}'\n";
echo "Generated filename: '{$filename}.php'\n";
echo "Full path: " . config_path("manufacturers/{$filename}.php") . "\n\n";

// Test 2: Check if file exists
echo "=== Test 2: File Existence ===\n";
$configPath = config_path("manufacturers/{$filename}.php");
if (file_exists($configPath)) {
    echo "✅ File exists: {$configPath}\n";
} else {
    echo "❌ File does not exist: {$configPath}\n";
}

// Check for other possible files
$possibleFiles = [
    'advanced-solution.php',
    'advanced-health.php',
    'advanced-solutions.php',
    'advanced_solution.php',
    'advanced_health.php'
];

foreach ($possibleFiles as $file) {
    $path = config_path("manufacturers/{$file}");
    if (file_exists($path)) {
        echo "Found file: {$file}\n";
    }
}
echo "\n";

// Test 3: Load configuration directly
echo "=== Test 3: Direct Configuration Loading ===\n";
try {
    $config = include $configPath;
    echo "✅ Configuration loaded successfully\n";
    echo "Configuration keys: " . implode(', ', array_keys($config)) . "\n";
    
    if (isset($config['docuseal_field_names']['patient_name'])) {
        echo "Patient name mapping: '{$config['docuseal_field_names']['patient_name']}'\n";
    } else {
        echo "❌ No patient_name mapping found in docuseal_field_names\n";
    }
    
    // Show all patient-related mappings
    echo "\nAll patient-related mappings:\n";
    foreach ($config['docuseal_field_names'] ?? [] as $key => $value) {
        if (strpos($key, 'patient') !== false) {
            echo "  {$key} => {$value}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Failed to load configuration: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Test UnifiedFieldMappingService
echo "=== Test 4: UnifiedFieldMappingService Test ===\n";
try {
    $service = app(UnifiedFieldMappingService::class);
    
    // Use reflection to access private method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('loadManufacturerFromFile');
    $method->setAccessible(true);
    
    $config = $method->invoke($service, $manufacturerName);
    
    if ($config) {
        echo "✅ Configuration loaded via service\n";
        echo "Configuration keys: " . implode(', ', array_keys($config)) . "\n";
        
        if (isset($config['docuseal_field_names']['patient_name'])) {
            echo "Patient name mapping: '{$config['docuseal_field_names']['patient_name']}'\n";
        } else {
            echo "❌ No patient_name mapping found\n";
        }
    } else {
        echo "❌ No configuration returned from service\n";
    }
    
} catch (Exception $e) {
    echo "❌ Service test failed: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Check for any cached configurations
echo "=== Test 5: Cache Check ===\n";
try {
    $cacheKey = "manufacturer_config_{$filename}";
    $cached = cache()->get($cacheKey);
    if ($cached) {
        echo "❌ Found cached configuration\n";
        if (isset($cached['docuseal_field_names']['patient_name'])) {
            echo "Cached patient name mapping: '{$cached['docuseal_field_names']['patient_name']}'\n";
        }
    } else {
        echo "✅ No cached configuration found\n";
    }
} catch (Exception $e) {
    echo "Cache check failed: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 6: Check if there are any other configurations that might be interfering
echo "=== Test 6: Check for Conflicting Configs ===\n";
$configFiles = glob(config_path('manufacturers/*.php'));
foreach ($configFiles as $configFile) {
    $configName = basename($configFile, '.php');
    
    // Only check files that might be related to Advanced Solution
    if (strpos($configName, 'advanced') !== false) {
        try {
            $config = include $configFile;
            if (isset($config['docuseal_field_names']['patient_name'])) {
                $patientNameMapping = $config['docuseal_field_names']['patient_name'];
                echo "Config {$configName}: patient_name maps to '{$patientNameMapping}'\n";
                
                if ($patientNameMapping === 'Patient Full Name') {
                    echo "❌ CONFLICT FOUND: {$configName} uses 'Patient Full Name'!\n";
                    echo "  File: {$configFile}\n";
                }
            }
        } catch (Exception $e) {
            echo "Failed to load {$configName}: " . $e->getMessage() . "\n";
        }
    }
}
echo "\n";

echo "=== Debug Complete ===\n"; 
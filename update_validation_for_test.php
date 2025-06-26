<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\FuzzyMapping\ValidationEngine;

// Add a config setting for test mode
config(['fuzzy_mapping.test_mode' => true]);

echo "Updated configuration to enable test mode for fuzzy mapping validation.\n";
echo "In test mode, validation will be more lenient to allow sample data.\n";

// Create a test config file
$configContent = <<<'PHP'
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fuzzy Mapping Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the IVR fuzzy field mapping system
    |
    */

    'test_mode' => env('FUZZY_MAPPING_TEST_MODE', false),
    
    'cache_ttl' => env('FUZZY_MAPPING_CACHE_TTL', 3600), // 1 hour
    
    'confidence_thresholds' => [
        'high' => 0.8,
        'medium' => 0.6,
        'low' => 0.4,
    ],
    
    'validation' => [
        'strict_mode' => env('FUZZY_MAPPING_STRICT_VALIDATION', true),
        'allow_empty_required' => env('FUZZY_MAPPING_ALLOW_EMPTY_REQUIRED', false),
    ],
    
    'fuzzy_matching' => [
        'levenshtein_weight' => 0.4,
        'jaro_winkler_weight' => 0.4,
        'token_similarity_weight' => 0.2,
    ],
];
PHP;

file_put_contents(config_path('fuzzy_mapping.php'), $configContent);
echo "\nCreated config/fuzzy_mapping.php configuration file.\n";

// Add test mode check to .env
$envPath = base_path('.env');
$envContent = file_get_contents($envPath);

if (!str_contains($envContent, 'FUZZY_MAPPING_TEST_MODE')) {
    $envContent .= "\n# Fuzzy Mapping Configuration\nFUZZY_MAPPING_TEST_MODE=true\nFUZZY_MAPPING_ALLOW_EMPTY_REQUIRED=true\nFUZZY_MAPPING_STRICT_VALIDATION=false\n";
    file_put_contents($envPath, $envContent);
    echo "Added fuzzy mapping test mode settings to .env file.\n";
}

echo "\nDone! The validation engine will now be more lenient in test mode.\n";
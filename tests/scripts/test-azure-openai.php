<?php

/**
 * Test Azure OpenAI configuration
 * Run: php tests/scripts/test-azure-openai.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing Azure OpenAI Configuration\n";
echo "==================================\n\n";

// Check environment variables
echo "1. Environment Variables:\n";
$azureVars = [
    'AZURE_OPENAI_ENDPOINT',
    'AZURE_OPENAI_API_KEY',
    'AZURE_OPENAI_DEPLOYMENT_NAME',
    'AZURE_OPENAI_API_VERSION'
];

foreach ($azureVars as $var) {
    $value = env($var);
    if ($value) {
        echo "   ✅ $var: " . (str_contains($var, 'KEY') ? '***' : $value) . "\n";
    } else {
        echo "   ❌ $var: Not set\n";
    }
}

// Check if variables are available to Python subprocess
echo "\n2. Testing Python subprocess access:\n";
$envCommand = "import os; print('ENDPOINT:', os.getenv('AZURE_OPENAI_ENDPOINT')); print('KEY:', 'Set' if os.getenv('AZURE_OPENAI_API_KEY') else 'Not set')";
$output = shell_exec("python3 -c \"$envCommand\" 2>&1");
echo "   Python sees: $output\n";

// Test with explicit environment passing
echo "\n3. Testing with explicit environment:\n";
$env = [
    'AZURE_OPENAI_ENDPOINT' => env('AZURE_OPENAI_ENDPOINT'),
    'AZURE_OPENAI_API_KEY' => env('AZURE_OPENAI_API_KEY'),
    'AZURE_OPENAI_DEPLOYMENT_NAME' => env('AZURE_OPENAI_DEPLOYMENT_NAME'),
    'AZURE_OPENAI_API_VERSION' => env('AZURE_OPENAI_API_VERSION')
];

$testScript = <<<'PYTHON'
import os
import sys

endpoint = os.getenv('AZURE_OPENAI_ENDPOINT')
api_key = os.getenv('AZURE_OPENAI_API_KEY')
deployment = os.getenv('AZURE_OPENAI_DEPLOYMENT_NAME')

print(f"Endpoint: {endpoint}")
print(f"API Key: {'Set' if api_key else 'Not set'}")
print(f"Deployment: {deployment}")

if all([endpoint, api_key, deployment]):
    print("✅ All required variables are set")
else:
    print("❌ Missing required variables")
PYTHON;

$descriptorspec = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"]
];

$process = proc_open(['python3', '-c', $testScript], $descriptorspec, $pipes, null, $env);

if (is_resource($process)) {
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    $return_value = proc_close($process);
    
    echo $output;
    if ($error) {
        echo "Errors: $error\n";
    }
}

// Check AI service startup script
echo "\n4. Checking AI service startup:\n";
$startupScript = base_path('scripts/start_medical_ai_service.sh');
if (file_exists($startupScript)) {
    echo "   ✅ Startup script exists at: $startupScript\n";
    $content = file_get_contents($startupScript);
    if (strpos($content, 'source') !== false || strpos($content, '.env') !== false) {
        echo "   ✅ Script appears to load .env file\n";
    } else {
        echo "   ⚠️ Script may not be loading .env file\n";
    }
} else {
    echo "   ❌ Startup script not found\n";
}

echo "\nTest completed.\n";
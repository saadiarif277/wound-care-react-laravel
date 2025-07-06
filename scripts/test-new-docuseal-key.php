<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔑 Testing New DocuSeal API Key\n";
echo "===============================\n\n";

// Get the API key from Laravel config
$apiKey = config('services.docuseal.api_key');
$apiUrl = config('services.docuseal.api_url');

echo "🔍 Configuration Check:\n";
echo "   API URL: {$apiUrl}\n";
echo "   API Key: " . substr($apiKey, 0, 10) . "... (length: " . strlen($apiKey) . ")\n";
echo "   Key starts with: " . substr($apiKey, 0, 3) . "\n";
echo "   Key format appears valid: " . (preg_match('/^[a-zA-Z0-9]{40,50}$/', $apiKey) ? 'Yes' : 'No') . "\n\n";

// Test different authentication formats
$testEndpoints = [
    'Account Info' => '/account',
    'Templates' => '/templates',
    'Webhooks' => '/webhooks',
];

$authFormats = [
    'API-Key format' => 'API-Key ' . $apiKey,
    'Bearer format' => 'Bearer ' . $apiKey,
    'Simple key' => $apiKey,
];

foreach ($authFormats as $formatName => $authHeader) {
    echo "🧪 Testing {$formatName}:\n";
    
    foreach ($testEndpoints as $endpointName => $endpoint) {
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => $authHeader,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get($apiUrl . $endpoint);
        
        $status = $response->status();
        $success = $status < 400;
        $icon = $success ? '✅' : '❌';
        
        echo "   {$icon} {$endpointName}: HTTP {$status}";
        if (!$success) {
            $body = $response->json();
            $error = $body['error'] ?? $body['message'] ?? 'Unknown error';
            echo " - {$error}";
        }
        echo "\n";
    }
    echo "\n";
}

// Test specific DocuSeal endpoints that might work differently
echo "🎯 Testing DocuSeal-specific patterns:\n";

// Test with User-Agent
$response = \Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => 'API-Key ' . $apiKey,
    'Content-Type' => 'application/json',
    'Accept' => 'application/json',
    'User-Agent' => 'WoundCare-Laravel/1.0',
])->get($apiUrl . '/templates');

echo "   With User-Agent: HTTP " . $response->status();
if ($response->status() >= 400) {
    $body = $response->json();
    echo " - " . ($body['error'] ?? $body['message'] ?? 'Unknown');
}
echo "\n";

// Test POST request (sometimes authentication works differently)
$response = \Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => 'API-Key ' . $apiKey,
    'Content-Type' => 'application/json',
])->post($apiUrl . '/templates', [
    'name' => 'Test Template'
]);

echo "   POST request: HTTP " . $response->status();
if ($response->status() >= 400) {
    $body = $response->json();
    echo " - " . ($body['error'] ?? $body['message'] ?? 'Unknown');
}
echo "\n\n";

// Raw cURL test
echo "🔧 Raw cURL test:\n";
$curlCommand = "curl -X GET '{$apiUrl}/templates' " .
    "-H 'Authorization: API-Key {$apiKey}' " .
    "-H 'Content-Type: application/json' " .
    "-H 'Accept: application/json' " .
    "-w 'HTTP_STATUS:%{http_code}' -s";

$curlResult = shell_exec($curlCommand);
echo "   cURL result: {$curlResult}\n\n";

echo "🎉 API Key Test Complete!\n";

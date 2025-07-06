<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Debugging DocuSeal Authentication\n";
echo "====================================\n\n";

$apiKey = config('services.docuseal.api_key');
$apiUrl = config('services.docuseal.api_url');

echo "🔑 API Key Length: " . strlen($apiKey) . " characters\n";
echo "🔑 API Key First 10 chars: " . substr($apiKey, 0, 10) . "...\n";
echo "🌐 API URL: {$apiUrl}\n\n";

// Test different authentication formats
$authFormats = [
    'API-Key ' . $apiKey,
    'Bearer ' . $apiKey,
    'Token ' . $apiKey,
    $apiKey
];

foreach ($authFormats as $index => $authHeader) {
    echo "🧪 Test " . ($index + 1) . ": Authorization format: " . substr($authHeader, 0, 20) . "...\n";
    
    try {
        $response = Http::withHeaders([
            'Authorization' => $authHeader,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ])->timeout(10)->get("{$apiUrl}/templates");
        
        echo "   Status: " . $response->status() . "\n";
        if ($response->successful()) {
            echo "   ✅ Success!\n";
            $data = $response->json();
            echo "   Templates count: " . (isset($data['data']) ? count($data['data']) : 'N/A') . "\n";
            break;
        } else {
            $error = $response->json();
            echo "   ❌ Error: " . ($error['error'] ?? $response->body()) . "\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Exception: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

// Test account info endpoint if available
echo "🔍 Testing account info endpoint...\n";
try {
    $response = Http::withHeaders([
        'Authorization' => 'API-Key ' . $apiKey,
        'Content-Type' => 'application/json'
    ])->timeout(10)->get("{$apiUrl}/account");
    
    echo "   Status: " . $response->status() . "\n";
    if ($response->successful()) {
        $data = $response->json();
        echo "   ✅ Account info retrieved\n";
        echo "   Account: " . ($data['name'] ?? 'N/A') . "\n";
        echo "   Email: " . ($data['email'] ?? 'N/A') . "\n";
    } else {
        echo "   Response: " . $response->body() . "\n";
    }
} catch (Exception $e) {
    echo "   Exception: " . $e->getMessage() . "\n";
}

echo "\n🔍 Debug Complete!\n";

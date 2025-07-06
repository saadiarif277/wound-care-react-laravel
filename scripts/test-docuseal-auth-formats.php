<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔐 Testing DocuSeal Authentication Header Formats\n";
echo "==============================================\n\n";

$apiKey = config('services.docuseal.api_key');
$apiUrl = config('services.docuseal.api_url');

echo "API Key: " . substr($apiKey, 0, 10) . "..." . substr($apiKey, -5) . " (Length: " . strlen($apiKey) . ")\n";
echo "API URL: {$apiUrl}\n\n";

// Test different authentication header formats
$authFormats = [
    'Bearer' => ['Authorization' => "Bearer {$apiKey}"],
    'API-Key' => ['Authorization' => "API-Key {$apiKey}"],
    'X-API-Key' => ['X-API-Key' => $apiKey],
    'Direct' => ['Authorization' => $apiKey],
    'Token' => ['Authorization' => "Token {$apiKey}"],
    'Key' => ['Authorization' => "Key {$apiKey}"],
];

foreach ($authFormats as $format => $headers) {
    echo "🧪 Testing format: {$format}\n";
    echo "   Headers: " . json_encode($headers) . "\n";
    
    try {
        $response = Http::withHeaders(array_merge($headers, [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ]))->timeout(10)->get("{$apiUrl}/account");
        
        echo "   Status: " . $response->status() . "\n";
        
        if ($response->successful()) {
            echo "   ✅ SUCCESS! This format works!\n";
            echo "   Response: " . $response->body() . "\n";
            break;
        } else {
            echo "   ❌ Failed: " . $response->body() . "\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ Exception: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

// Test with templates endpoint too
echo "\n📋 Testing /templates endpoint with successful format:\n";
foreach ($authFormats as $format => $headers) {
    echo "🧪 Testing templates with format: {$format}\n";
    
    try {
        $response = Http::withHeaders(array_merge($headers, [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ]))->timeout(10)->get("{$apiUrl}/templates");
        
        echo "   Status: " . $response->status() . "\n";
        
        if ($response->successful()) {
            echo "   ✅ Templates SUCCESS!\n";
            $data = $response->json();
            echo "   Templates found: " . (is_array($data) ? count($data) : 'N/A') . "\n";
            break;
        } else {
            echo "   ❌ Failed: " . $response->body() . "\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ Exception: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "🎯 Auth Format Test Complete!\n";

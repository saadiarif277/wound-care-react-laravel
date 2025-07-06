<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔐 Testing DocuSeal Authentication with X-Auth-Token Header\n";
echo "========================================================\n\n";

// Get API key from Laravel config
$apiKey = config('services.docuseal.api_key');
$apiUrl = config('services.docuseal.api_url');

echo "🔑 API Key: " . substr($apiKey, 0, 10) . "..." . substr($apiKey, -5) . " (Length: " . strlen($apiKey) . ")\n";
echo "🌐 API URL: {$apiUrl}\n\n";

echo "Testing X-Auth-Token header format...\n";

try {
    $response = \Illuminate\Support\Facades\Http::withHeaders([
        'X-Auth-Token' => $apiKey,
        'Content-Type' => 'application/json',
    ])->get("{$apiUrl}/templates");

    echo "📡 Response Status: " . $response->status() . "\n";
    
    if ($response->successful()) {
        echo "✅ Authentication successful!\n";
        $data = $response->json();
        echo "📋 Templates found: " . count($data) . "\n";
        
        if (!empty($data)) {
            echo "\n📄 First template:\n";
            $firstTemplate = $data[0];
            echo "   ID: " . ($firstTemplate['id'] ?? 'N/A') . "\n";
            echo "   Name: " . ($firstTemplate['name'] ?? 'N/A') . "\n";
            echo "   Created: " . ($firstTemplate['created_at'] ?? 'N/A') . "\n";
        }
    } else {
        echo "❌ Authentication failed!\n";
        echo "📝 Response Body: " . $response->body() . "\n";
        
        // Check if it's a 401 specifically
        if ($response->status() === 401) {
            echo "\n🚨 Still getting 401 - the X-Auth-Token header might not be correct either.\n";
            echo "💡 Let's check DocuSeal documentation for the exact authentication method.\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Request failed with exception:\n";
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n🎯 Authentication Test Complete!\n";

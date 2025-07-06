<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔑 Testing New DocuSeal API Key\n";
echo "===============================\n\n";

// Get the API configuration
$apiKey = config('services.docuseal.api_key');
$apiUrl = config('services.docuseal.api_url');

echo "🔍 Configuration Check:\n";
echo "   API URL: {$apiUrl}\n";
echo "   API Key Length: " . strlen($apiKey) . " characters\n";
echo "   API Key Preview: " . substr($apiKey, 0, 8) . "..." . substr($apiKey, -4) . "\n\n";

// Test 1: Account Information
echo "1️⃣ Testing Account Access...\n";
try {
    $response = Http::withHeaders([
        'Authorization' => 'API-Key ' . $apiKey,
        'Accept' => 'application/json',
    ])->get("{$apiUrl}/account");

    if ($response->successful()) {
        $data = $response->json();
        echo "✅ Account access successful!\n";
        echo "   Account Name: " . ($data['name'] ?? 'N/A') . "\n";
        echo "   Account Email: " . ($data['email'] ?? 'N/A') . "\n";
        echo "   Plan: " . ($data['plan'] ?? 'N/A') . "\n\n";
    } else {
        echo "❌ Account access failed: " . $response->status() . "\n";
        echo "   Response: " . $response->body() . "\n\n";
    }
} catch (Exception $e) {
    echo "❌ Account test failed: " . $e->getMessage() . "\n\n";
}

// Test 2: Templates List
echo "2️⃣ Testing Templates Access...\n";
try {
    $response = Http::withHeaders([
        'Authorization' => 'API-Key ' . $apiKey,
        'Accept' => 'application/json',
    ])->get("{$apiUrl}/templates");

    if ($response->successful()) {
        $data = $response->json();
        echo "✅ Templates access successful!\n";
        echo "   Templates count: " . count($data) . "\n";
        
        if (!empty($data)) {
            echo "   Available templates:\n";
            foreach ($data as $template) {
                echo "     - ID: {$template['id']}, Name: " . ($template['name'] ?? 'Unnamed') . "\n";
            }
        }
        echo "\n";
    } else {
        echo "❌ Templates access failed: " . $response->status() . "\n";
        echo "   Response: " . $response->body() . "\n\n";
    }
} catch (Exception $e) {
    echo "❌ Templates test failed: " . $e->getMessage() . "\n\n";
}

// Test 3: Specific Template (if we know the ID)
$testTemplateId = '1233913'; // MedLife template
echo "3️⃣ Testing Specific Template Access (ID: {$testTemplateId})...\n";
try {
    $response = Http::withHeaders([
        'Authorization' => 'API-Key ' . $apiKey,
        'Accept' => 'application/json',
    ])->get("{$apiUrl}/templates/{$testTemplateId}");

    if ($response->successful()) {
        $data = $response->json();
        echo "✅ Template access successful!\n";
        echo "   Template Name: " . ($data['name'] ?? 'N/A') . "\n";
        echo "   Fields Count: " . count($data['fields'] ?? []) . "\n";
        echo "   Created: " . ($data['created_at'] ?? 'N/A') . "\n\n";
    } else {
        echo "❌ Template access failed: " . $response->status() . "\n";
        echo "   Response: " . $response->body() . "\n\n";
    }
} catch (Exception $e) {
    echo "❌ Template test failed: " . $e->getMessage() . "\n\n";
}

// Test 4: Submissions List  
echo "4️⃣ Testing Submissions Access...\n";
try {
    $response = Http::withHeaders([
        'Authorization' => 'API-Key ' . $apiKey,
        'Accept' => 'application/json',
    ])->get("{$apiUrl}/submissions");

    if ($response->successful()) {
        $data = $response->json();
        echo "✅ Submissions access successful!\n";
        echo "   Submissions count: " . count($data) . "\n\n";
    } else {
        echo "❌ Submissions access failed: " . $response->status() . "\n";
        echo "   Response: " . $response->body() . "\n\n";
    }
} catch (Exception $e) {
    echo "❌ Submissions test failed: " . $e->getMessage() . "\n\n";
}

// Summary
echo "🎯 API Key Test Summary:\n";
echo "========================\n";
echo "If all tests passed, the new API key is working correctly!\n";
echo "You can now proceed with the DocuSeal integration workflow.\n\n";

echo "Next steps:\n";
echo "1. Run: php scripts/test-docuseal-complete-workflow.php\n";
echo "2. Test the frontend IVR workflow\n";
echo "3. Verify DocuSeal form embedding works\n\n";

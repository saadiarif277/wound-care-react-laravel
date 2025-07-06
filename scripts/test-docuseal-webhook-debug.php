<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔗 Testing DocuSeal Webhook Configuration\n";
echo "=========================================\n\n";

$apiKey = config('services.docuseal.api_key');
$apiUrl = config('services.docuseal.api_url');

echo "🔍 DocuSeal Configuration:\n";
echo "   API URL: {$apiUrl}\n";
echo "   API Key: " . substr($apiKey, 0, 10) . "..." . substr($apiKey, -5) . " (" . strlen($apiKey) . " chars)\n";
echo "   Webhook Secret: " . (config('services.docuseal.webhook_secret') ? 'Set' : 'Not set') . "\n\n";

// Test 1: Check account webhooks configuration
echo "1️⃣ Checking account webhooks...\n";
try {
    $response = Http::withHeaders([
        'Authorization' => 'API-Key ' . $apiKey,
        'Content-Type' => 'application/json',
    ])->get("{$apiUrl}/webhooks");

    echo "   Status: " . $response->status() . "\n";
    
    if ($response->successful()) {
        $webhooks = $response->json();
        echo "   ✅ Webhooks endpoint accessible\n";
        echo "   Configured webhooks: " . count($webhooks) . "\n";
        
        if (!empty($webhooks)) {
            foreach ($webhooks as $i => $webhook) {
                echo "   Webhook " . ($i + 1) . ":\n";
                echo "     URL: " . ($webhook['url'] ?? 'Not set') . "\n";
                echo "     Events: " . implode(', ', $webhook['events'] ?? []) . "\n";
                echo "     Active: " . ($webhook['active'] ?? false ? 'Yes' : 'No') . "\n";
            }
        } else {
            echo "   ⚠️  No webhooks configured\n";
        }
    } else {
        echo "   ❌ Failed to get webhooks: " . $response->body() . "\n";
    }
} catch (Exception $e) {
    echo "   ❌ Webhook check failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Try making API call without any webhook-related headers
echo "2️⃣ Testing basic API call without webhook headers...\n";
try {
    $response = Http::withHeaders([
        'Authorization' => 'API-Key ' . $apiKey,
    ])->get("{$apiUrl}/account");

    echo "   Status: " . $response->status() . "\n";
    echo "   Response: " . $response->body() . "\n";
    
    if ($response->successful()) {
        $account = $response->json();
        echo "   ✅ Account accessible without webhook headers\n";
        echo "   Account email: " . ($account['email'] ?? 'Not provided') . "\n";
        echo "   Account status: " . ($account['status'] ?? 'Not provided') . "\n";
    }
} catch (Exception $e) {
    echo "   ❌ Basic API call failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Check if there are any webhook validation requirements
echo "3️⃣ Testing template access (webhook-sensitive endpoint)...\n";
try {
    $response = Http::withHeaders([
        'Authorization' => 'API-Key ' . $apiKey,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json'
    ])->get("{$apiUrl}/templates");

    echo "   Status: " . $response->status() . "\n";
    
    if ($response->successful()) {
        $templates = $response->json();
        echo "   ✅ Templates accessible\n";
        echo "   Template count: " . count($templates) . "\n";
        
        // Look for our specific template
        foreach ($templates as $template) {
            if ($template['id'] == '1233913') {
                echo "   ✅ Found MedLife template (ID: 1233913)\n";
                echo "     Name: " . ($template['name'] ?? 'Unnamed') . "\n";
                echo "     Status: " . ($template['status'] ?? 'Unknown') . "\n";
                break;
            }
        }
    } else {
        echo "   ❌ Templates not accessible: " . $response->body() . "\n";
        
        // Check if it's specifically a webhook-related error
        $errorBody = $response->json();
        if (isset($errorBody['error']) && str_contains(strtolower($errorBody['error']), 'webhook')) {
            echo "   🔗 This appears to be a webhook-related issue!\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Template check failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Check if our webhook secret format is correct
echo "4️⃣ Checking webhook secret format...\n";
$webhookSecret = config('services.docuseal.webhook_secret');
if ($webhookSecret) {
    echo "   Webhook secret length: " . strlen($webhookSecret) . " characters\n";
    echo "   Contains special chars: " . (preg_match('/[^a-zA-Z0-9]/', $webhookSecret) ? 'Yes' : 'No') . "\n";
    
    // DocuSeal webhook secrets should typically be alphanumeric
    if (preg_match('/[^a-zA-Z0-9]/', $webhookSecret)) {
        echo "   ⚠️  Webhook secret contains special characters that might cause issues\n";
        echo "   Consider using only alphanumeric characters for webhook secrets\n";
    }
} else {
    echo "   ⚠️  No webhook secret configured\n";
}

echo "\n🎯 Recommendations:\n";
echo "1. Check DocuSeal console for webhook configuration\n";
echo "2. Ensure webhook URL (if configured) is accessible\n";
echo "3. Verify webhook secret format\n";
echo "4. Consider temporarily removing webhook configuration to test API access\n";

echo "\n🔗 DocuSeal Console: https://console.docuseal.com\n";
echo "📧 Account: " . config('services.docuseal.account_email', 'limitless@mscwoundcare.com') . "\n";

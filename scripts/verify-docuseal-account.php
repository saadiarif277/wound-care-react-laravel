<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Foundation\Application;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 DocuSeal Account Verification\n";
echo "===============================\n\n";

$accountEmail = config('services.docuseal.account_email', 'limitless@mscwoundcare.com');
$apiKey = config('services.docuseal.api_key');

echo "📧 Account Email: {$accountEmail}\n";
echo "🔑 API Key: " . substr($apiKey, 0, 10) . "..." . substr($apiKey, -5) . " (Length: " . strlen($apiKey) . ")\n\n";

echo "🔍 API Key Analysis:\n";
echo "   - Starts with: " . substr($apiKey, 0, 4) . "\n";
echo "   - Length: " . strlen($apiKey) . " characters\n";
echo "   - Contains only alphanumeric: " . (ctype_alnum($apiKey) ? 'Yes' : 'No') . "\n";
echo "   - Pattern matches DocuSeal format: " . (preg_match('/^[A-Za-z0-9]{40,50}$/', $apiKey) ? 'Yes' : 'No') . "\n\n";

echo "📚 What to check:\n";
echo "   1. Log into DocuSeal console at https://console.docuseal.com\n";
echo "   2. Go to Settings > API Keys\n";
echo "   3. Verify the API key is active and not expired\n";
echo "   4. Check if the account '{$accountEmail}' has access\n";
echo "   5. Ensure the account is not suspended or limited\n\n";

echo "🔧 Next steps:\n";
echo "   • If API key is invalid, generate a new one\n";
echo "   • If account is suspended, contact DocuSeal support\n";
echo "   • Consider testing with a fresh DocuSeal account\n\n";

// Check if we can ping DocuSeal service
echo "🌐 Testing DocuSeal service availability...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.docuseal.com');
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200 || $httpCode == 404) {
    echo "   ✅ DocuSeal API is reachable (HTTP {$httpCode})\n";
} else {
    echo "   ❌ DocuSeal API might be down (HTTP {$httpCode})\n";
}

echo "\n🔍 Account Verification Complete!\n";

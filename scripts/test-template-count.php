<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Load Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Testing Docuseal Template Count...\n";

$apiKey = config('docuseal.api_key');
$apiUrl = config('docuseal.api_url', 'https://api.docuseal.com');

if (!$apiKey) {
    echo "❌ No API key configured\n";
    exit(1);
}

echo "📡 API URL: {$apiUrl}\n";
echo "🔑 API Key: " . substr($apiKey, 0, 8) . "...\n\n";

// Test first page
echo "📄 Testing first page...\n";
$response = \Illuminate\Support\Facades\Http::withHeaders([
    'X-Auth-Token' => $apiKey,
])->timeout(15)->get("{$apiUrl}/templates", [
    'page' => 1,
    'per_page' => 20
]);

if (!$response->successful()) {
    echo "❌ API request failed: " . $response->status() . "\n";
    echo "Response: " . $response->body() . "\n";
    exit(1);
}

$responseData = $response->json();
$templates = $responseData['data'] ?? $responseData;

echo "✅ First page response:\n";
echo "  📊 Templates on page 1: " . count($templates) . "\n";
echo "  📋 Response structure: " . (isset($responseData['data']) ? 'Has data wrapper' : 'Direct array') . "\n";

if (isset($responseData['pagination'])) {
    $pagination = $responseData['pagination'];
    echo "  📄 Pagination info:\n";
    echo "    - Count: " . ($pagination['count'] ?? 'unknown') . "\n";
    echo "    - Next: " . ($pagination['next'] ?? 'none') . "\n";
    echo "    - Prev: " . ($pagination['prev'] ?? 'none') . "\n";
} else {
    echo "  📄 No pagination info found\n";
}

// Show sample templates
echo "\n📄 Sample templates:\n";
foreach (array_slice($templates, 0, 5) as $i => $template) {
    $name = $template['name'] ?? 'Unknown';
    $id = $template['id'] ?? 'unknown';
    $folder = $template['folder_name'] ?? 'No folder';
    echo "  " . ($i + 1) . ". {$name} (ID: {$id}, Folder: {$folder})\n";
}

// Test second page to see if there are more
echo "\n📄 Testing second page...\n";
$response2 = \Illuminate\Support\Facades\Http::withHeaders([
    'X-Auth-Token' => $apiKey,
])->timeout(15)->get("{$apiUrl}/templates", [
    'page' => 2,
    'per_page' => 20
]);

if ($response2->successful()) {
    $responseData2 = $response2->json();
    $templates2 = $responseData2['data'] ?? $responseData2;
    echo "✅ Second page: " . count($templates2) . " templates\n";

    if (count($templates2) > 0) {
        echo "  📄 More pages exist - you have more than 20 templates\n";
    } else {
        echo "  📄 No more templates - total is around " . count($templates) . "\n";
    }
} else {
    echo "❌ Second page failed: " . $response2->status() . "\n";
}

echo "\n🎯 Summary:\n";
echo "  - First page has " . count($templates) . " templates\n";
echo "  - " . (count($templates2 ?? []) > 0 ? "Multiple pages exist" : "Likely only one page") . "\n";
echo "  - Estimated total: " . (count($templates2 ?? []) > 0 ? "More than " . count($templates) : count($templates)) . " templates\n";

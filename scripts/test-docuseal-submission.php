<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing DocuSeal Submission Creation\n";
echo "=====================================\n\n";

$apiKey = config('services.docuseal.api_key');
$apiUrl = config('services.docuseal.api_url');

echo "🔑 API Key: " . substr($apiKey, 0, 10) . "... (" . strlen($apiKey) . " chars)\n";
echo "🌐 API URL: {$apiUrl}\n\n";

try {
    // Test creating a simple submission
    echo "1️⃣ Creating test submission...\n";
    
    $response = Http::withHeaders([
        'X-Auth-Token' => $apiKey,
        'Content-Type' => 'application/json',
    ])->post("{$apiUrl}/submissions", [
        'template_id' => '1233913',
        'send_email' => false,
        'submitters' => [
            [
                'email' => 'provider@example.com',
                'role' => 'First Party',
                'name' => 'Test Provider',
                'fields' => [
                    [
                        'name' => 'Patient Name',
                        'value' => 'John Doe Test'
                    ],
                    [
                        'name' => 'Patient DOB',
                        'value' => '05/15/1980'
                    ],
                    [
                        'name' => 'Physician Name',
                        'value' => 'Dr. Test'
                    ],
                    [
                        'name' => 'Physician NPI',
                        'value' => '1234567890'
                    ]
                ]
            ]
        ],
        'metadata' => [
            'episode_id' => 'test-episode-123',
            'created_at' => now()->toIso8601String(),
        ],
    ]);

    echo "📞 API Response Status: {$response->status()}\n";
    echo "📋 Response Headers:\n";
    foreach ($response->headers() as $header => $values) {
        echo "   {$header}: " . implode(', ', $values) . "\n";
    }
    echo "\n";

    if ($response->successful()) {
        $data = $response->json();
        echo "✅ Submission created successfully!\n";
        echo "📊 Response Structure:\n";
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
        
        // Check for expected fields
        $expectedFields = ['id', 'slug', 'submitters', 'status'];
        echo "🔍 Checking for expected fields:\n";
        foreach ($expectedFields as $field) {
            $exists = isset($data[$field]) ? '✅' : '❌';
            echo "   {$exists} {$field}: " . (isset($data[$field]) ? json_encode($data[$field]) : 'MISSING') . "\n";
        }
        echo "\n";
        
        // Check submitters structure
        if (isset($data['submitters']) && is_array($data['submitters'])) {
            echo "👥 Submitters structure:\n";
            foreach ($data['submitters'] as $index => $submitter) {
                echo "   [{$index}] " . json_encode($submitter, JSON_PRETTY_PRINT) . "\n";
            }
        }
        
    } else {
        echo "❌ Submission failed!\n";
        echo "📋 Error Response:\n";
        echo $response->body() . "\n";
    }

} catch (Exception $e) {
    echo "❌ Test failed with exception:\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "🎉 DocuSeal Submission Test Complete!\n";

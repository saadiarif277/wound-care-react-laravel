<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

echo "🔍 Debugging DocuSeal API Response Structure\n";
echo "============================================\n\n";

try {
    // Get API credentials from config
    $apiKey = config('services.docuseal.api_key');
    $apiUrl = config('services.docuseal.api_url');
    
    echo "Using API URL: {$apiUrl}\n";
    echo "API Key: " . substr($apiKey, 0, 10) . "...\n\n";
    
    // Test with a minimal submission to see response structure
    echo "🚀 Creating test submission to see response structure...\n";
    
    $response = Http::withHeaders([
        'X-Auth-Token' => $apiKey,
        'Content-Type' => 'application/json',
    ])->post("{$apiUrl}/submissions", [
        'template_id' => '1233913', // MEDLIFE SOLUTIONS template
        'send_email' => false,
        'submitters' => [
            [
                'email' => 'test@example.com',
                'role' => 'First Party',
                'name' => 'Test User',
                'fields' => [
                    ['name' => 'patient_name', 'value' => 'Test Patient']
                ]
            ]
        ],
        'metadata' => [
            'test' => 'debug_response_structure'
        ]
    ]);
    
    echo "Response Status: " . $response->status() . "\n";
    echo "Response Headers: " . json_encode($response->headers(), JSON_PRETTY_PRINT) . "\n\n";
    
    if ($response->successful()) {
        $responseData = $response->json();
        echo "✅ Response successful!\n";
        echo "Response Structure:\n";
        echo json_encode($responseData, JSON_PRETTY_PRINT) . "\n\n";
        
        // Show the keys available
        echo "Available top-level keys:\n";
        foreach (array_keys($responseData) as $key) {
            echo "  - {$key}\n";
        }
        
        // Check for ID field
        if (isset($responseData['id'])) {
            echo "\n✅ Found 'id' field: " . $responseData['id'] . "\n";
        } else {
            echo "\n❌ No 'id' field found at top level\n";
            
            // Check if it's in submitters
            if (isset($responseData['submitters']) && is_array($responseData['submitters'])) {
                echo "Checking submitters array...\n";
                foreach ($responseData['submitters'] as $index => $submitter) {
                    echo "Submitter {$index} keys: " . implode(', ', array_keys($submitter)) . "\n";
                    if (isset($submitter['id'])) {
                        echo "  - Found submitter ID: " . $submitter['id'] . "\n";
                    }
                }
            }
        }
        
    } else {
        echo "❌ Response failed!\n";
        echo "Error: " . $response->body() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

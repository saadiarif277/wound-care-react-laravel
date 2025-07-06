<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔗 Testing DocuSeal API Connection\n";
echo "==================================\n\n";

try {
    $apiKey = config('services.docuseal.api_key');
    $apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');
    
    if (!$apiKey) {
        throw new Exception('DocuSeal API key not configured');
    }
    
    echo "🔑 API Key: " . substr($apiKey, 0, 10) . "...\n";
    echo "🌐 API URL: {$apiUrl}\n\n";
    
    // Test basic API connection with templates endpoint
    echo "📋 Testing templates endpoint...\n";
    $response = Http::withHeaders([
        'Authorization' => 'API-Key ' . $apiKey,
        'Content-Type' => 'application/json',
    ])->get("{$apiUrl}/templates");
    
    if ($response->successful()) {
        $templates = $response->json();
        echo "✅ API connection successful!\n";
        echo "📊 Found " . count($templates) . " templates\n\n";
        
        // Look for MedLife template
        foreach ($templates as $template) {
            if ($template['id'] === '1233913') {
                echo "✅ Found MedLife template: {$template['name']}\n";
                break;
            }
        }
        
        // Test specific template details
        echo "\n🔍 Testing template details for MedLife (1233913)...\n";
        $templateResponse = Http::withHeaders([
            'Authorization' => 'API-Key ' . $apiKey,
        ])->get("{$apiUrl}/templates/1233913");
        
        if ($templateResponse->successful()) {
            $templateData = $templateResponse->json();
            echo "✅ Template details retrieved successfully\n";
            echo "   Name: {$templateData['name']}\n";
            echo "   Fields: " . count($templateData['documents'][0]['fields'] ?? []) . "\n";
        } else {
            echo "❌ Failed to get template details: " . $templateResponse->status() . "\n";
            echo "   Response: " . $templateResponse->body() . "\n";
        }
        
    } else {
        echo "❌ API connection failed\n";
        echo "   Status: " . $response->status() . "\n";
        echo "   Response: " . $response->body() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error testing DocuSeal API: " . $e->getMessage() . "\n";
}

echo "\n🎯 API Connection Test Complete!\n";

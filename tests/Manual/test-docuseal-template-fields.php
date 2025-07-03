<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Support\Facades\Http;

// Load environment
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apiKey = config('docuseal.api_key');
$apiUrl = config('docuseal.api_url', 'https://api.docuseal.com');

// MedLife template ID
$templateId = '1233913';

echo "Fetching fields for Docuseal template: $templateId\n";
echo "API URL: $apiUrl\n";
echo "API Key: " . substr($apiKey, 0, 8) . "...\n\n";

try {
    // Fetch template details
    $response = Http::withHeaders([
        'X-Auth-Token' => $apiKey,
        'Content-Type' => 'application/json',
    ])->get("$apiUrl/templates/$templateId");

    if ($response->successful()) {
        $template = $response->json();
        
        echo "Template Name: " . ($template['name'] ?? 'Unknown') . "\n";
        echo "Template ID: " . ($template['id'] ?? 'Unknown') . "\n\n";
        
        // Extract fields
        if (isset($template['fields']) && is_array($template['fields'])) {
            echo "Template Fields:\n";
            echo str_repeat("-", 80) . "\n";
            
            foreach ($template['fields'] as $index => $field) {
                echo sprintf(
                    "%d. Name: %s | Type: %s | Required: %s\n",
                    $index + 1,
                    $field['name'] ?? 'Unnamed',
                    $field['type'] ?? 'Unknown',
                    isset($field['required']) && $field['required'] ? 'Yes' : 'No'
                );
                
                if (isset($field['options']) && is_array($field['options']) && !empty($field['options'])) {
                    echo "   Options: " . implode(', ', $field['options']) . "\n";
                }
            }
            
            echo str_repeat("-", 80) . "\n";
            echo "Total fields: " . count($template['fields']) . "\n\n";
            
            // Look for post-op related fields
            echo "Searching for post-op related fields:\n";
            $postOpFields = array_filter($template['fields'], function($field) {
                $name = strtolower($field['name'] ?? '');
                return strpos($name, 'post') !== false || 
                       strpos($name, 'op') !== false ||
                       strpos($name, 'surgery') !== false ||
                       strpos($name, 'period') !== false;
            });
            
            if (!empty($postOpFields)) {
                foreach ($postOpFields as $field) {
                    echo "- " . $field['name'] . "\n";
                }
            } else {
                echo "No post-op related fields found.\n";
            }
            
        } else {
            echo "No fields found in template response.\n";
            echo "Full response:\n";
            print_r($template);
        }
        
    } else {
        echo "Failed to fetch template. Status: " . $response->status() . "\n";
        echo "Response: " . $response->body() . "\n";
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nDone.\n"; 
#!/usr/bin/env php
<?php

// Run this as an artisan command to access Laravel's context
require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Now we can use Laravel helpers
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Configuration
$templateId = config('manufacturers.acz-&-associates.docuseal_template_id');
$apiKey = config('services.docuseal.api_key');
$baseUrl = env('DOCUSEAL_API_URL', 'https://api.docuseal.com'); // Use env directly since config might be mismatched

echo "=== Fetching ACZ & Associates DocuSeal Template Fields ===\n";
echo "Template ID: $templateId\n";
echo "API URL: $baseUrl/templates/$templateId\n\n";

try {
    $response = Http::withHeaders([
        'X-Auth-Token' => $apiKey,
        'Accept' => 'application/json',
    ])->get("$baseUrl/templates/$templateId");

    if (!$response->successful()) {
        echo "Error: Failed to fetch template. Status: " . $response->status() . "\n";
        echo "Response: " . $response->body() . "\n";
        exit(1);
    }

    $template = $response->json();
    
    // Save the full template data for debugging
    $outputFile = __DIR__ . '/acz-template-debug.json';
    file_put_contents($outputFile, json_encode($template, JSON_PRETTY_PRINT));
    echo "Full template data saved to: " . $outputFile . "\n\n";
    
    echo "Template Name: " . $template['name'] . "\n";
    echo "Schema Version: " . ($template['schema_version'] ?? 'N/A') . "\n\n";

    // Group fields by submitter role - use the correct structure
    $fieldsByRole = [];
    $fields = $template['fields'] ?? [];
    
    foreach ($fields as $field) {
        // Fields seem to have submitter_uuid, not role, so we'll group them differently
        $role = 'First Party'; // Default role since fields don't have explicit role
        if (!isset($fieldsByRole[$role])) {
            $fieldsByRole[$role] = [];
        }
        $fieldsByRole[$role][] = $field;
    }

    // Display fields organized by role
    foreach ($fieldsByRole as $role => $fields) {
        echo "=== $role Fields ===\n";
        echo "Total fields: " . count($fields) . "\n\n";
        
        foreach ($fields as $index => $field) {
            echo ($index + 1) . ". Field Name: \"" . ($field['name'] ?? 'Unnamed') . "\"\n";
            echo "   Type: " . ($field['type'] ?? 'text') . "\n";
            
            if (isset($field['options']) && is_array($field['options'])) {
                echo "   Options:\n";
                foreach ($field['options'] as $option) {
                    $value = is_array($option) ? ($option['value'] ?? $option) : $option;
                    echo "     - \"$value\"\n";
                }
            }
            
            if (isset($field['required'])) {
                echo "   Required: " . ($field['required'] ? 'Yes' : 'No') . "\n";
            }
            
            if (isset($field['default_value'])) {
                echo "   Default: \"" . $field['default_value'] . "\"\n";
            }
            echo "\n";
        }
    }

    // Look specifically for patient-related fields
    echo "=== Searching for Patient-Related Fields ===\n";
    $patientFields = [];
    
    foreach ($fieldsByRole as $role => $fields) {
        foreach ($fields as $field) {
            $fieldName = strtolower($field['name'] ?? '');
            if (strpos($fieldName, 'patient') !== false || 
                strpos($fieldName, 'first') !== false || 
                strpos($fieldName, 'last') !== false ||
                strpos($fieldName, 'name') !== false) {
                $patientFields[] = [
                    'role' => $role,
                    'name' => $field['name'] ?? 'Unnamed',
                    'type' => $field['type'] ?? 'text'
                ];
            }
        }
    }
    
    if (!empty($patientFields)) {
        foreach ($patientFields as $pf) {
            echo "- Role: {$pf['role']}, Field: \"{$pf['name']}\", Type: {$pf['type']}\n";
        }
    } else {
        echo "No patient-related fields found.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    Log::error('Failed to fetch DocuSeal template', [
        'template_id' => $templateId,
        'error' => $e->getMessage()
    ]);
    exit(1);
}

echo "\nDone.\n"; 
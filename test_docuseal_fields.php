<?php

require_once 'vendor/autoload.php';

use App\Services\DocusealService;
use Illuminate\Support\Facades\Log;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    echo "Fetching DocuSeal template fields for BioWound Solutions (Template ID: 1254774)\n";
    echo "=" . str_repeat("=", 70) . "\n\n";
    
    $docusealService = app(DocusealService::class);
    $templateFields = $docusealService->getTemplateFieldsFromAPI('1254774');
    
    if (empty($templateFields)) {
        echo "❌ No fields found - check API connection or template ID\n";
        exit(1);
    }
    
    echo "✅ Found " . count($templateFields) . " fields in the template:\n\n";
    
    foreach ($templateFields as $fieldName => $fieldDetails) {
        echo "  📄 Field: {$fieldName}\n";
        echo "     Type: " . ($fieldDetails['type'] ?? 'unknown') . "\n";
        echo "     Required: " . ($fieldDetails['required'] ? 'Yes' : 'No') . "\n";
        echo "     Label: " . ($fieldDetails['label'] ?? 'N/A') . "\n";
        echo "\n";
    }
    
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "Now checking current BioWound configuration mappings...\n\n";
    
    // Load the current BioWound config
    $configPath = config_path('manufacturers/biowound-solutions.php');
    $config = require $configPath;
    
    $docusealFieldNames = $config['docuseal_field_names'] ?? [];
    
    echo "🔍 Checking field mappings from configuration:\n\n";
    
    $invalidFields = [];
    $validFields = [];
    
    foreach ($docusealFieldNames as $canonicalField => $docusealField) {
        if (isset($templateFields[$docusealField])) {
            $validFields[] = $docusealField;
            echo "  ✅ {$canonicalField} → {$docusealField} (EXISTS)\n";
        } else {
            $invalidFields[] = $docusealField;
            echo "  ❌ {$canonicalField} → {$docusealField} (NOT FOUND)\n";
        }
    }
    
    echo "\n" . str_repeat("-", 50) . "\n";
    echo "Summary:\n";
    echo "  Valid mappings: " . count($validFields) . "\n";
    echo "  Invalid mappings: " . count($invalidFields) . "\n";
    
    if (!empty($invalidFields)) {
        echo "\n❌ INVALID FIELDS CAUSING ERRORS:\n";
        foreach ($invalidFields as $field) {
            echo "  - {$field}\n";
        }
    }
    
    echo "\n✅ Script completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
} 
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Manufacturer;
use App\Models\Docuseal\DocusealTemplate;
use App\Services\Templates\UnifiedTemplateMappingEngine;
use Illuminate\Foundation\Application;

// Bootstrap Laravel
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧪 Testing template resolution for manufacturers...\n\n";

// Get manufacturers that we expect to have templates
$testManufacturers = [
    ['id' => 1, 'name' => 'ACZ & ASSOCIATES'],
    ['id' => 2, 'name' => 'Advanced Solution'],
    ['id' => 6, 'name' => 'IMBED']
];

$templateEngine = new UnifiedTemplateMappingEngine();

foreach ($testManufacturers as $manufacturerData) {
    echo "🏭 Testing manufacturer: {$manufacturerData['name']} (ID: {$manufacturerData['id']})\n";

    // Direct database lookup
    $dbTemplate = DocusealTemplate::where('manufacturer_id', $manufacturerData['id'])
        ->where('document_type', 'IVR')
        ->first();

    if ($dbTemplate) {
        echo "  ✅ Database template found: {$dbTemplate->template_name} (DocuSeal ID: {$dbTemplate->docuseal_template_id})\n";
    } else {
        echo "  ❌ No database template found\n";
    }

    // Test with UnifiedTemplateMappingEngine
    try {
        $templateId = $templateEngine->getDocusealTemplateId($manufacturerData['id']);
        echo "  ✅ Template Engine found: DocuSeal ID {$templateId}\n";
    } catch (Exception $e) {
        echo "  ❌ Template Engine error: {$e->getMessage()}\n";
    }

    echo "\n";
}

echo "🎯 Testing template resolution complete!\n";

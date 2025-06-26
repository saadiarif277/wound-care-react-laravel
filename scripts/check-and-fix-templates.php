<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Foundation\Application;

// Bootstrap Laravel
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 Checking current DocuSeal templates...\n\n";

// Get all current templates
$templates = DocusealTemplate::with('manufacturer')->get();

foreach ($templates as $template) {
    $manufacturerName = $template->manufacturer ? $template->manufacturer->name : 'No Manufacturer';
    echo "ID: {$template->id}\n";
    echo "Manufacturer: {$manufacturerName} (ID: {$template->manufacturer_id})\n";
    echo "Template Name: {$template->template_name}\n";
    echo "DocuSeal ID: {$template->docuseal_template_id}\n";
    echo "---\n";
}

echo "\n🧹 Cleaning up duplicate entries...\n";

// Find duplicates by docuseal_template_id
$duplicates = DocusealTemplate::select('docuseal_template_id')
    ->whereNotNull('docuseal_template_id')
    ->groupBy('docuseal_template_id')
    ->havingRaw('COUNT(*) > 1')
    ->pluck('docuseal_template_id');

foreach ($duplicates as $duplicateId) {
    echo "Found duplicate DocuSeal ID: {$duplicateId}\n";

    // Keep the first one, delete the rest
    $duplicateTemplates = DocusealTemplate::where('docuseal_template_id', $duplicateId)
        ->orderBy('created_at')
        ->get();

    $kept = $duplicateTemplates->first();
    $toDelete = $duplicateTemplates->skip(1);

    echo "Keeping: {$kept->template_name} (Manufacturer: {$kept->manufacturer_id})\n";

    foreach ($toDelete as $template) {
        echo "Deleting: {$template->template_name} (Manufacturer: {$template->manufacturer_id})\n";
        $template->delete();
    }
}

echo "\n✅ Database cleanup complete!\n";

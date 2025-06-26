<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Foundation\Application;

// Bootstrap Laravel
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🧹 Removing placeholder DocuSeal templates...\n\n";

// Remove placeholder templates (ones that end with '_temp')
$placeholderTemplates = DocusealTemplate::where('docuseal_template_id', 'LIKE', '%_temp%')->get();

foreach ($placeholderTemplates as $template) {
    $manufacturerName = $template->manufacturer ? $template->manufacturer->name : 'No Manufacturer';
    echo "Removing placeholder: {$template->template_name} (Manufacturer: {$manufacturerName}, DocuSeal ID: {$template->docuseal_template_id})\n";
    $template->delete();
}

// Also remove any other templates with non-numeric DocuSeal IDs that aren't real
$otherPlaceholders = DocusealTemplate::where('docuseal_template_id', 'LIKE', 'template_%')->get();

foreach ($otherPlaceholders as $template) {
    $manufacturerName = $template->manufacturer ? $template->manufacturer->name : 'No Manufacturer';
    echo "Removing placeholder: {$template->template_name} (Manufacturer: {$manufacturerName}, DocuSeal ID: {$template->docuseal_template_id})\n";
    $template->delete();
}

echo "\n✅ Placeholder templates removed!\n";

echo "🔍 Remaining templates with real DocuSeal IDs:\n\n";

// Show remaining templates
$realTemplates = DocusealTemplate::whereNotNull('docuseal_template_id')
    ->whereRaw('docuseal_template_id REGEXP "^[0-9]+$"')
    ->with('manufacturer')
    ->get();

foreach ($realTemplates as $template) {
    $manufacturerName = $template->manufacturer ? $template->manufacturer->name : 'No Manufacturer';
    echo "✅ {$template->template_name} (Manufacturer: {$manufacturerName}, DocuSeal ID: {$template->docuseal_template_id})\n";
}

echo "\n🎉 Template cleanup complete!\n";

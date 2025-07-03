<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Docuseal\DocusealTemplate;
use App\Models\Order\Manufacturer;

echo "Checking Docuseal Templates...\n\n";

// Get all manufacturers
$manufacturers = Manufacturer::all();
echo "Available Manufacturers:\n";
foreach ($manufacturers as $mfr) {
    echo "  ID: {$mfr->id}, Name: {$mfr->name}\n";
}

echo "\nDocuseal Templates:\n";
$templates = DocusealTemplate::with('manufacturer')->get();

if ($templates->isEmpty()) {
    echo "  No Docuseal templates found in the database.\n";
} else {
    foreach ($templates as $template) {
        echo "  Template ID: {$template->id}\n";
        echo "  Template Name: {$template->template_name}\n";
        echo "  Manufacturer ID: {$template->manufacturer_id}\n";
        echo "  Manufacturer Name: " . ($template->manufacturer ? $template->manufacturer->name : 'None') . "\n";
        echo "  ---\n";
    }
}

echo "\nTotal templates: " . $templates->count() . "\n";

// Check if the templates table exists
if (\Schema::hasTable('docuseal_templates')) {
    echo "\nTable 'docuseal_templates' exists.\n";
    $columns = \Schema::getColumnListing('docuseal_templates');
    echo "Columns: " . implode(', ', $columns) . "\n";
} else {
    echo "\nTable 'docuseal_templates' does not exist.\n";
}

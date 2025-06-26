<?php

use App\Models\Order\Manufacturer;
use App\Models\DocuSeal\DocuSealTemplate;

// Bootstrap Laravel without HTTP request
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get all manufacturers
$manufacturers = Manufacturer::all(['id', 'name', 'slug']);

echo "=== Manufacturers ===\n";
foreach ($manufacturers as $manufacturer) {
    echo "ID: {$manufacturer->id}, Name: {$manufacturer->name}, Slug: {$manufacturer->slug}\n";
}

// Check Centurion
echo "\n=== Centurion Therapeutics Check ===\n";
$centurion = Manufacturer::where('name', 'like', '%Centurion%')->first();
if ($centurion) {
    echo "Found Centurion: ID={$centurion->id}, Name={$centurion->name}\n";
    
    // Check template
    $template = DocuSealTemplate::where('manufacturer_id', $centurion->id)->first();
    if ($template) {
        echo "Template found: {$template->template_name}\n";
        echo "Field mappings count: " . count($template->field_mappings ?? []) . "\n";
    } else {
        echo "No template found for Centurion\n";
    }
}
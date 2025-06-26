<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order\Product;
use App\Models\Order\Manufacturer;
use App\Models\Docuseal\DocusealTemplate;

echo "Fixing Amnio AMP manufacturer assignment...\n\n";

// Find MedLife Solutions manufacturer
$medLifeManufacturer = Manufacturer::where('name', 'LIKE', '%MedLife%')
    ->orWhere('name', 'LIKE', '%MEDLIFE%')
    ->first();

if (!$medLifeManufacturer) {
    echo "❌ MedLife Solutions manufacturer not found!\n";
    exit;
}

echo "✓ Found manufacturer: {$medLifeManufacturer->name} (ID: {$medLifeManufacturer->id})\n";

// Find all products with MedLife in manufacturer field but no manufacturer_id
$medLifeProducts = Product::where('manufacturer', 'LIKE', '%MedLife%')
    ->whereNull('manufacturer_id')
    ->orWhere('manufacturer_id', 0)
    ->get();

echo "\nFound " . $medLifeProducts->count() . " MedLife products without proper manufacturer_id:\n";

foreach ($medLifeProducts as $product) {
    echo "  - {$product->name} (Q-Code: {$product->q_code})\n";
    $product->manufacturer_id = $medLifeManufacturer->id;
    $product->save();
    echo "    ✓ Updated manufacturer_id to {$medLifeManufacturer->id}\n";
}

// Check if MedLife has a DocuSeal template
echo "\nChecking DocuSeal templates for {$medLifeManufacturer->name}...\n";
$medLifeTemplate = DocusealTemplate::where('manufacturer_id', $medLifeManufacturer->id)->first();

if ($medLifeTemplate) {
    echo "✓ Template found: {$medLifeTemplate->template_name}\n";
} else {
    echo "❌ No DocuSeal template found for {$medLifeManufacturer->name}!\n";
    
    // Check if there's an unassigned MedLife template
    $unassignedMedLifeTemplate = DocusealTemplate::whereNull('manufacturer_id')
        ->where(function($query) {
            $query->where('template_name', 'LIKE', '%MedLife%')
                  ->orWhere('template_name', 'LIKE', '%MEDLIFE%');
        })
        ->first();
        
    if ($unassignedMedLifeTemplate) {
        echo "  Found unassigned template: {$unassignedMedLifeTemplate->template_name}\n";
        $unassignedMedLifeTemplate->manufacturer_id = $medLifeManufacturer->id;
        $unassignedMedLifeTemplate->save();
        echo "  ✓ Assigned template to {$medLifeManufacturer->name}\n";
    } else {
        echo "\n  Suggestions:\n";
        echo "  1. Import the MedLife IVR template from your JSON data\n";
        echo "  2. Check if one of these templates should be used:\n";
        
        $availableTemplates = DocusealTemplate::whereNull('manufacturer_id')->get();
        foreach ($availableTemplates as $template) {
            echo "     - {$template->template_name}\n";
        }
    }
}

// Verify Amnio AMP is now properly configured
echo "\n\nVerifying Amnio AMP configuration...\n";
$amnioAmp = Product::where('name', 'LIKE', '%Amnio%AMP%')->with('manufacturerRelation')->first();
if ($amnioAmp) {
    echo "Product: {$amnioAmp->name}\n";
    echo "Q-Code: {$amnioAmp->q_code}\n";
    echo "Manufacturer ID: {$amnioAmp->manufacturer_id}\n";
    echo "Manufacturer: " . ($amnioAmp->manufacturerRelation ? $amnioAmp->manufacturerRelation->name : 'None') . "\n";
}

echo "\nDone!\n";
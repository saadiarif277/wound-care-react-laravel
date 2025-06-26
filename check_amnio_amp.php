<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order\Manufacturer;
use App\Models\Order\Product;
use App\Models\Docuseal\DocusealTemplate;

echo "Checking for Amnio AMP product and manufacturer...\n\n";

// Search for Amnio AMP product
$products = Product::where('name', 'LIKE', '%Amnio%AMP%')
    ->orWhere('name', 'LIKE', '%AmnioAMP%')
    ->orWhere('name', 'LIKE', '%Amnio AMP%')
    ->with('manufacturer')
    ->get();

if ($products->isEmpty()) {
    echo "No products found matching 'Amnio AMP'\n";
    
    // Let's search more broadly
    $amnioProducts = Product::where('name', 'LIKE', '%Amnio%')
        ->with('manufacturer')
        ->get();
    
    echo "\nProducts containing 'Amnio':\n";
    foreach ($amnioProducts as $product) {
        echo "  - {$product->name} (HCPCS: {$product->hcpcs_code})\n";
        echo "    Manufacturer: " . ($product->manufacturer ? $product->manufacturer->name : 'None') . "\n";
    }
} else {
    echo "Found products matching 'Amnio AMP':\n";
    foreach ($products as $product) {
        echo "  Product: {$product->name}\n";
        echo "  HCPCS Code: {$product->hcpcs_code}\n";
        echo "  Q Code: {$product->q_code}\n";
        echo "  Manufacturer ID: {$product->manufacturer_id}\n";
        if (is_object($product->manufacturer)) {
            echo "  Manufacturer: " . $product->manufacturer->name . "\n";
        } else {
            echo "  Manufacturer (string): " . $product->manufacturer . "\n";
        }
        echo "  Manufacturer Relation: " . ($product->manufacturerRelation ? $product->manufacturerRelation->name : 'None') . "\n";
        echo "  ---\n";
    }
}

// Check ACZ & ASSOCIATES products
echo "\n\nChecking ACZ & ASSOCIATES products:\n";
$aczManufacturer = Manufacturer::find(1); // ACZ & ASSOCIATES
if ($aczManufacturer) {
    $aczProducts = Product::where('manufacturer_id', 1)->get();
    echo "ACZ & ASSOCIATES has " . $aczProducts->count() . " products:\n";
    foreach ($aczProducts as $product) {
        echo "  - {$product->name} (HCPCS: {$product->hcpcs_code})\n";
    }
    
    // Check if they have a template
    $aczTemplate = DocusealTemplate::where('manufacturer_id', 1)->first();
    if ($aczTemplate) {
        echo "\nACZ & ASSOCIATES template: {$aczTemplate->template_name}\n";
    } else {
        echo "\nACZ & ASSOCIATES has NO DocuSeal template assigned.\n";
    }
}

// Check if we need to create a mapping or use a different template
echo "\n\nSuggestions:\n";
echo "1. If Amnio AMP is from ACZ & ASSOCIATES, they need an IVR template created/imported.\n";
echo "2. If Amnio AMP is from a different manufacturer, check the manufacturer_id in your request.\n";
echo "3. Consider using the ACZ Distribution template if ACZ & ASSOCIATES and ACZ Distribution are related.\n";

// Show all available templates
echo "\n\nAll Available DocuSeal Templates:\n";
$templates = DocusealTemplate::with('manufacturer')->get();
foreach ($templates as $template) {
    $mfrName = $template->manufacturer ? $template->manufacturer->name : 'Unassigned';
    echo "  - {$template->template_name} => {$mfrName} (Manufacturer ID: {$template->manufacturer_id})\n";
}

echo "\nDone\!\n";

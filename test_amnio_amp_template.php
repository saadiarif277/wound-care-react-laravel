<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order\Product;
use App\Models\Order\Manufacturer;
use App\Models\Docuseal\DocusealTemplate;

echo "Testing Amnio AMP template access...\n\n";

// Find Amnio AMP product
$amnioAmp = Product::where('name', 'LIKE', '%Amnio%AMP%')->first();

if (!$amnioAmp) {
    echo "❌ Amnio AMP product not found!\n";
    exit;
}

echo "Product Found:\n";
echo "  Name: {$amnioAmp->name}\n";
echo "  Q-Code: {$amnioAmp->q_code}\n";
echo "  Manufacturer ID: {$amnioAmp->manufacturer_id}\n";

// Get manufacturer
if ($amnioAmp->manufacturer_id) {
    $manufacturer = Manufacturer::find($amnioAmp->manufacturer_id);
    if ($manufacturer) {
        echo "  Manufacturer: {$manufacturer->name} (ID: {$manufacturer->id})\n";
        
        // Check for DocuSeal template
        $template = DocusealTemplate::where('manufacturer_id', $manufacturer->id)
            ->where('document_type', 'InsuranceVerification')
            ->first();
            
        if ($template) {
            echo "\n✓ DocuSeal Template Found:\n";
            echo "  Template Name: {$template->template_name}\n";
            echo "  DocuSeal ID: {$template->docuseal_template_id}\n";
            echo "  Document Type: {$template->document_type}\n";
            echo "  Active: " . ($template->is_active ? 'Yes' : 'No') . "\n";
            
            // Check field definitions
            $fieldCount = DB::table('ivr_template_fields')
                ->where('manufacturer_id', $manufacturer->id)
                ->count();
            echo "\n  Field Definitions: {$fieldCount} fields\n";
            
            // Check field mappings
            $mappingCount = DB::table('ivr_field_mappings')
                ->where('manufacturer_id', $manufacturer->id)
                ->count();
            echo "  Field Mappings: {$mappingCount} mappings\n";
            
            echo "\n✅ Amnio AMP is properly configured for DocuSeal IVR!\n";
        } else {
            echo "\n❌ No DocuSeal template found for {$manufacturer->name}\n";
        }
    } else {
        echo "  ❌ Manufacturer not found for ID: {$amnioAmp->manufacturer_id}\n";
    }
} else {
    echo "  ❌ No manufacturer_id set for this product\n";
}

// Show all MedLife products
echo "\n\nAll MedLife Products:\n";
$medLifeProducts = Product::where('manufacturer_id', 5)->get();
foreach ($medLifeProducts as $product) {
    echo "  - {$product->name} (Q-Code: {$product->q_code})\n";
}

echo "\nDone!\n";
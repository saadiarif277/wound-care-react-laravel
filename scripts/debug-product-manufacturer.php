<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Order\Product;
use App\Models\Order\Manufacturer;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Debugging Product-Manufacturer Relationship\n";
echo "==============================================\n\n";

try {
    // 1. Check Product model structure
    echo "1️⃣ Checking Product Model...\n";
    $product = Product::find(10); // Amnio AMP product
    
    if ($product) {
        echo "✅ Product found: {$product->name}\n";
        echo "   ID: {$product->id}\n";
        echo "   Code: {$product->code}\n";
        
        // Check manufacturer field type and value
        echo "   Manufacturer field value: " . var_export($product->manufacturer, true) . "\n";
        echo "   Manufacturer field type: " . gettype($product->manufacturer) . "\n";
        
        // Check if manufacturer_id exists
        if (isset($product->manufacturer_id)) {
            echo "   Manufacturer ID: {$product->manufacturer_id}\n";
        } else {
            echo "   ❌ No manufacturer_id field found\n";
        }
        
        // Show all product attributes
        echo "   All Product Attributes:\n";
        foreach ($product->getAttributes() as $key => $value) {
            echo "      {$key}: " . var_export($value, true) . "\n";
        }
        
    } else {
        echo "❌ Product not found\n";
    }
    
    echo "\n";
    
    // 2. Check Manufacturer model
    echo "2️⃣ Checking Manufacturer Model...\n";
    $manufacturer = Manufacturer::where('name', 'MEDLIFE SOLUTIONS')->first();
    
    if ($manufacturer) {
        echo "✅ Manufacturer found: {$manufacturer->name}\n";
        echo "   ID: {$manufacturer->id}\n";
        echo "   DocuSeal Template ID: " . ($manufacturer->docuseal_template_id ?? 'Not set') . "\n";
        echo "   DocuSeal Order Form Template ID: " . ($manufacturer->docuseal_order_form_template_id ?? 'Not set') . "\n";
        
        // Show all manufacturer attributes
        echo "   All Manufacturer Attributes:\n";
        foreach ($manufacturer->getAttributes() as $key => $value) {
            echo "      {$key}: " . var_export($value, true) . "\n";
        }
    } else {
        echo "❌ Manufacturer not found\n";
    }
    
    echo "\n";
    
    // 3. Check Product table schema
    echo "3️⃣ Checking Product Table Schema...\n";
    $columns = \Illuminate\Support\Facades\DB::select("DESCRIBE products");
    
    echo "Products table columns:\n";
    foreach ($columns as $column) {
        echo "   {$column->Field}: {$column->Type} (Null: {$column->Null}, Key: {$column->Key})\n";
    }
    
    echo "\n";
    
    // 4. Check if there's a relationship method
    echo "4️⃣ Checking Product Relationship Methods...\n";
    $productModel = new Product();
    $methods = get_class_methods($productModel);
    $relationshipMethods = array_filter($methods, function($method) {
        return in_array($method, ['manufacturer', 'getManufacturer', 'manufacturerRelation']);
    });
    
    if ($relationshipMethods) {
        echo "✅ Found relationship methods: " . implode(', ', $relationshipMethods) . "\n";
    } else {
        echo "❌ No manufacturer relationship methods found\n";
    }
    
    // 5. Try to access manufacturer relationship if it exists
    if ($product && method_exists($product, 'manufacturer')) {
        echo "5️⃣ Testing Manufacturer Relationship...\n";
        try {
            $manufacturerRelation = $product->manufacturer();
            echo "✅ Manufacturer relationship exists\n";
            echo "   Relationship type: " . get_class($manufacturerRelation) . "\n";
            
            // Try to get the actual related manufacturer
            $relatedManufacturer = $product->manufacturer()->first();
            if ($relatedManufacturer) {
                echo "   Related manufacturer: {$relatedManufacturer->name}\n";
                echo "   DocuSeal Template ID: " . ($relatedManufacturer->docuseal_template_id ?? 'Not set') . "\n";
            } else {
                echo "   ❌ No related manufacturer found\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Error accessing manufacturer relationship: " . $e->getMessage() . "\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Debug failed with exception:\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "🎉 Product-Manufacturer Debug Complete!\n";

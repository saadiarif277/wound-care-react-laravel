<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\Order\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Checking Product Table Structure ===\n\n";

// Get column names
$columns = Schema::getColumnListing('msc_products');
echo "Columns in msc_products table:\n";
foreach ($columns as $column) {
    echo "  - $column\n";
}

echo "\n";

// Try to find Amnio products
echo "Looking for Amnio products:\n";
$products = Product::where('name', 'like', '%Amnio%')->get();

if ($products->count() > 0) {
    foreach ($products as $product) {
        echo "  - ID: {$product->id}\n";
        echo "    Name: {$product->name}\n";
        echo "    Manufacturer: {$product->manufacturer}\n";
        echo "    Manufacturer ID: {$product->manufacturer_id}\n";
        
        // Check for any code-like fields
        foreach ($columns as $column) {
            if (stripos($column, 'code') !== false || stripos($column, 'sku') !== false) {
                $value = $product->$column;
                if ($value) {
                    echo "    $column: $value\n";
                }
            }
        }
        echo "\n";
    }
} else {
    echo "  No Amnio products found.\n";
}

// Check BioWound products
echo "\nLooking for BioWound products:\n";
$biowoundProducts = Product::where('manufacturer', 'like', '%BioWound%')
    ->orWhere('manufacturer', 'like', '%Biowound%')
    ->limit(5)
    ->get();

if ($biowoundProducts->count() > 0) {
    foreach ($biowoundProducts as $product) {
        echo "  - {$product->name} (Manufacturer: {$product->manufacturer})\n";
    }
} else {
    echo "  No BioWound products found.\n";
}

echo "\n=== Done ===\n";
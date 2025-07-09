<?php

require_once 'vendor/autoload.php';

use App\Models\Order\Product;
use App\Models\Order\Manufacturer;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Checking product table structure and manufacturer relationship...\n\n";

try {
    // Check if manufacturers exist
    $manufacturers = Manufacturer::all();
    echo "Total manufacturers in database: " . $manufacturers->count() . "\n";

    if ($manufacturers->count() > 0) {
        echo "First few manufacturers:\n";
        foreach ($manufacturers->take(5) as $mfr) {
            echo "- ID: {$mfr->id}, Name: {$mfr->name}\n";
        }
    }

    echo "\n";

    // Check database structure
    $product = Product::first();

    if ($product) {
        echo "Product: {$product->name}\n";
        echo "manufacturer column (raw): " . ($product->getRawOriginal('manufacturer') ?? 'null') . "\n";
        echo "manufacturer_id column: " . ($product->manufacturer_id ?? 'null') . "\n";
        echo "manufacturer relationship type: " . (is_object($product->manufacturer) ? 'object' : gettype($product->manufacturer)) . "\n";

        if (is_object($product->manufacturer)) {
            echo "Manufacturer object: " . get_class($product->manufacturer) . "\n";
            echo "Manufacturer name: " . $product->manufacturer->name . "\n";
        } else {
            echo "Manufacturer value: " . $product->manufacturer . "\n";
        }

        echo "\n";

        // Check if manufacturer_id is set but relationship is not working
        if ($product->manufacturer_id && !is_object($product->manufacturer)) {
            echo "❌ ISSUE: manufacturer_id is set but relationship returns: " . gettype($product->manufacturer) . "\n";

            // Try to load the relationship manually
            $product->load('manufacturer');
            echo "After load('manufacturer'): " . (is_object($product->manufacturer) ? 'object' : gettype($product->manufacturer)) . "\n";

            // Check if the manufacturer exists
            $manufacturer = Manufacturer::find($product->manufacturer_id);
            if ($manufacturer) {
                echo "✅ Manufacturer with ID {$product->manufacturer_id} exists: {$manufacturer->name}\n";
            } else {
                echo "❌ Manufacturer with ID {$product->manufacturer_id} does not exist!\n";
            }
        }

        // Check all products
        echo "\nChecking all products:\n";
        $products = Product::all();
        $withManufacturerId = 0;
        $withManufacturerString = 0;
        $withBoth = 0;

        foreach ($products as $p) {
            if ($p->manufacturer_id && $p->getRawOriginal('manufacturer')) {
                $withBoth++;
            } elseif ($p->manufacturer_id) {
                $withManufacturerId++;
            } elseif ($p->getRawOriginal('manufacturer')) {
                $withManufacturerString++;
            }
        }

        echo "Products with manufacturer_id only: {$withManufacturerId}\n";
        echo "Products with manufacturer string only: {$withManufacturerString}\n";
        echo "Products with both: {$withBoth}\n";

    } else {
        echo "No products found\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

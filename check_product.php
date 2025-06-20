<?php

require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->boot();

use App\Models\Order\Product;

// Get product 10
$product = Product::find(10);

if ($product) {
    echo "Product ID: " . $product->id . "\n";
    echo "Product Name: " . $product->name . "\n";
    echo "Size Options: " . json_encode($product->size_options) . "\n";
    echo "Size Pricing: " . json_encode($product->size_pricing) . "\n";
    echo "Size Unit: " . $product->size_unit . "\n";
    echo "Available Sizes (legacy): " . json_encode($product->available_sizes) . "\n";
} else {
    echo "Product 10 not found\n";
}

// Also check a few other products
echo "\nAll products with size options:\n";
$productsWithSizes = Product::whereNotNull('size_options')->take(5)->get();
foreach ($productsWithSizes as $p) {
    echo "- {$p->name}: " . json_encode($p->size_options) . "\n";
}

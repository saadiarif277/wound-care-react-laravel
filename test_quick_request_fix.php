<?php

require_once 'vendor/autoload.php';

use App\Models\Order\Product;
use App\Models\Order\Manufacturer;
use App\Services\QuickRequestService;
use App\Models\User;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing QuickRequest manufacturer and pricing fixes...\n\n";

try {
    // Test 1: Check if products have manufacturer relationships
    echo "=== Test 1: Product Manufacturer Relationships ===\n";
    $products = Product::with('manufacturer')->limit(3)->get();

    foreach ($products as $product) {
        echo "Product: {$product->name}\n";
        echo "  manufacturer_id: " . ($product->manufacturer_id ?? 'null') . "\n";
        echo "  manufacturer relationship: " . (is_object($product->manufacturer) ? 'object' : gettype($product->manufacturer)) . "\n";

        if (is_object($product->manufacturer)) {
            echo "  manufacturer name: " . $product->manufacturer->name . "\n";
            echo "  docuseal_template_id: " . ($product->manufacturer->docuseal_order_form_template_id ?? 'null') . "\n";
        }

        echo "  price_per_sq_cm: " . ($product->price_per_sq_cm ?? 'null') . "\n";
        echo "  msc_price: " . ($product->msc_price ?? 'null') . "\n";
        echo "\n";
    }

    // Test 2: Test QuickRequestService getActiveProducts method
    echo "=== Test 2: QuickRequestService getActiveProducts ===\n";

    // Create a mock user for testing
    $user = new User();
    $user->id = 1;

    $quickRequestService = new QuickRequestService(
        app(\App\Services\FhirService::class),
        app(\App\Services\DocusealService::class),
        app(\App\Services\PayerService::class)
    );

    // Use reflection to access private method
    $reflection = new ReflectionClass($quickRequestService);
    $method = $reflection->getMethod('getActiveProducts');
    $method->setAccessible(true);

    $activeProducts = $method->invoke($quickRequestService, null);

    echo "Found " . $activeProducts->count() . " active products:\n";

    foreach ($activeProducts->take(3) as $product) {
        echo "- Product: {$product['name']}\n";
        echo "  manufacturer: " . ($product['manufacturer'] ?? 'null') . "\n";
        echo "  manufacturer_id: " . ($product['manufacturer_id'] ?? 'null') . "\n";
        echo "  price_per_sq_cm: " . ($product['price_per_sq_cm'] ?? 'null') . "\n";
        echo "  msc_price: " . ($product['msc_price'] ?? 'null') . "\n";
        echo "  docuseal_template_id: " . ($product['docuseal_template_id'] ?? 'null') . "\n";
        echo "\n";
    }

    // Test 3: Test manufacturer lookup by product ID
    echo "=== Test 3: Manufacturer Lookup by Product ID ===\n";

    if ($products->count() > 0) {
        $testProduct = $products->first();
        $productId = $testProduct->id;

        echo "Testing manufacturer lookup for product ID: {$productId}\n";

        $product = Product::with('manufacturer')->find($productId);

        if ($product && $product->manufacturer_id) {
            echo "✅ Found manufacturer_id: {$product->manufacturer_id}\n";

            if (is_object($product->manufacturer)) {
                echo "✅ Manufacturer name: {$product->manufacturer->name}\n";
            } else {
                echo "❌ Manufacturer relationship not working\n";
            }
        } else {
            echo "❌ No manufacturer_id found\n";
        }
    }

    echo "\n✅ All tests completed!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

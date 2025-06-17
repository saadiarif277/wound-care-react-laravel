<?php

// Quick script to test route issues
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Route;
use App\Models\Order\Order;

echo "Testing route issues...\n\n";

// Check if Order model can be loaded
echo "1. Testing Order model:\n";
try {
    $order = Order::first();
    if ($order) {
        echo "   ✓ Order model loaded successfully\n";
        echo "   First order ID: " . $order->id . "\n";
    } else {
        echo "   ⚠ No orders found in database\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Error loading Order model: " . $e->getMessage() . "\n";
}

// Check routes
echo "\n2. Checking routes:\n";
$routes = [
    'admin.orders.generate-ivr',
    'api.rbac.roles.permissions',
    'rbac.roles.permissions'
];

foreach ($routes as $routeName) {
    try {
        if (Route::has($routeName)) {
            $route = Route::getRoutes()->getByName($routeName);
            echo "   ✓ Route '$routeName' exists\n";
            echo "     URI: " . $route->uri() . "\n";
            echo "     Action: " . $route->getActionName() . "\n";
        } else {
            echo "   ✗ Route '$routeName' not found\n";
        }
    } catch (\Exception $e) {
        echo "   ✗ Error checking route '$routeName': " . $e->getMessage() . "\n";
    }
}

// Check permissions
echo "\n3. Checking permissions:\n";
$permissions = ['manage-orders', 'manage-roles', 'generate-ivr'];
foreach ($permissions as $permission) {
    $exists = \Spatie\Permission\Models\Permission::where('name', $permission)->exists();
    echo "   " . ($exists ? "✓" : "✗") . " Permission '$permission' " . ($exists ? "exists" : "missing") . "\n";
}

echo "\nDone!\n";
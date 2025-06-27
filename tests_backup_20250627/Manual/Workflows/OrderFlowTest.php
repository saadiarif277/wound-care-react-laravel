<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Bootstrap the application
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Order\ProductRequest;
use App\Services\CurrentOrganization;
use Illuminate\Support\Facades\Auth;

// Find admin user
$admin = User::whereHas('roles', function($q) {
    $q->where('slug', 'msc-admin');
})->first();

if (!$admin) {
    die("No admin user found\n");
}

// Authenticate as admin
Auth::login($admin);

echo "Authenticated as: " . $admin->email . "\n";

// Set current organization if the admin has one
if ($admin->current_organization_id) {
    $currentOrg = app(CurrentOrganization::class);
    $currentOrg->setOrganization($admin->currentOrganization);
    echo "Current Organization: " . $admin->currentOrganization->name . "\n";
}

// Query ProductRequests as the admin would see them
$query = ProductRequest::with(['provider', 'facility', 'products'])
    ->whereNotIn('order_status', ['draft']);

echo "\n--- ProductRequests visible to admin ---\n";
$orders = $query->get();
echo "Total orders found: " . $orders->count() . "\n";

foreach ($orders as $order) {
    echo "\nOrder #{$order->id}:\n";
    echo "  Request Number: {$order->request_number}\n";
    echo "  Status: {$order->order_status}\n";
    echo "  Patient ID: {$order->patient_display_id}\n";
    echo "  Provider: " . ($order->provider ? $order->provider->full_name : 'Unknown') . "\n";
    echo "  Facility: " . ($order->facility ? $order->facility->name : 'Unknown') . "\n";
    echo "  Total Value: $" . number_format($order->total_order_value, 2) . "\n";
}

// Now check without scopes
echo "\n--- All ProductRequests (without scopes) ---\n";
$allOrders = ProductRequest::withoutGlobalScopes()->whereNotIn('order_status', ['draft'])->get();
echo "Total orders in database: " . $allOrders->count() . "\n";

foreach ($allOrders as $order) {
    echo "  - {$order->request_number} (Status: {$order->order_status})\n";
}
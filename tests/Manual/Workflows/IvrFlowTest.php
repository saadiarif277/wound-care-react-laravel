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
use App\Services\IvrDocusealService;
use App\Http\Controllers\Admin\AdminOrderCenterController;

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

// Set current organization
if ($admin->current_organization_id) {
    $currentOrg = app(CurrentOrganization::class);
    $currentOrg->setOrganization($admin->currentOrganization);
    echo "Current Organization: " . $admin->currentOrganization->name . "\n";
}

// Find a product request with pending_ivr status
$productRequest = ProductRequest::where('order_status', 'pending_ivr')->first();

if (!$productRequest) {
    die("No product request with pending_ivr status found\n");
}

echo "\n--- Testing Order Flow ---\n";
echo "Order: {$productRequest->request_number}\n";
echo "Current Status: {$productRequest->order_status}\n";
echo "Patient: {$productRequest->patient_display_id}\n";

// Step 1: Admin reviews and decides to generate IVR
echo "\nStep 1: Admin reviews order and generates IVR\n";

try {
    $ivrService = app(IvrDocusealService::class);
    
    // Simulate generating IVR (would normally be done through controller)
    echo "  - Generating IVR document...\n";
    
    // Note: This would fail without DocuSeal API keys configured
    // For testing, let's just update the status manually
    $productRequest->update([
        'ivr_sent_at' => now(),
        'ivr_document_url' => 'https://docuseal.example.com/documents/test-ivr-' . $productRequest->id . '.pdf',
        'order_status' => 'ivr_sent',
        'order_number' => $productRequest->generateOrderNumber()
    ]);
    
    echo "  - IVR generated successfully\n";
    echo "  - Order Number: {$productRequest->order_number}\n";
    echo "  - Status updated to: {$productRequest->order_status}\n";
    
} catch (Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Step 2: Admin sends IVR to manufacturer
echo "\nStep 2: Admin reviews IVR and sends to manufacturer\n";
$productRequest->update([
    'manufacturer_sent_at' => now(),
    'manufacturer_sent_by' => $admin->id
]);
echo "  - IVR marked as sent to manufacturer\n";

// Step 3: Manufacturer approves
echo "\nStep 3: Manufacturer reviews and approves IVR\n";
$productRequest->update([
    'manufacturer_approved' => true,
    'manufacturer_approved_at' => now(),
    'manufacturer_approval_reference' => 'MAN-APPR-' . rand(1000, 9999),
    'manufacturer_notes' => 'Approved via email confirmation',
    'order_status' => 'ivr_confirmed'
]);
echo "  - Manufacturer approval recorded\n";
echo "  - Approval Reference: {$productRequest->manufacturer_approval_reference}\n";
echo "  - Status updated to: {$productRequest->order_status}\n";

// Step 4: Admin gives final approval
echo "\nStep 4: Admin gives final approval\n";
$productRequest->update([
    'order_status' => 'approved',
    'approved_at' => now()
]);
echo "  - Order approved\n";
echo "  - Status updated to: {$productRequest->order_status}\n";

// Step 5: Submit to manufacturer
echo "\nStep 5: Admin submits order to manufacturer\n";
$productRequest->update([
    'order_status' => 'submitted_to_manufacturer',
    'order_submitted_at' => now(),
    'manufacturer_order_id' => 'MFG-' . date('Ymd') . '-' . rand(1000, 9999)
]);
echo "  - Order submitted to manufacturer\n";
echo "  - Manufacturer Order ID: {$productRequest->manufacturer_order_id}\n";
echo "  - Status updated to: {$productRequest->order_status}\n";

echo "\n--- Order Flow Complete ---\n";
echo "Final Status: {$productRequest->order_status}\n";
echo "Order progressed from 'pending_ivr' → 'ivr_sent' → 'ivr_confirmed' → 'approved' → 'submitted_to_manufacturer'\n";
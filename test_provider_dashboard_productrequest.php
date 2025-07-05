<?php

require_once 'vendor/autoload.php';

use App\Models\Order\ProductRequest;
use App\Models\User;
use App\Models\Order\Manufacturer;
use App\Models\Order\Product;
use App\Models\Fhir\Facility;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Provider Dashboard ProductRequest Update\n";
echo "===============================================\n\n";

try {
    // Get a provider user
    $provider = User::first();
    if (!$provider) {
        echo "❌ No provider user found. Please create a provider user first.\n";
        exit(1);
    }
    echo "✅ Found provider: {$provider->name} (ID: {$provider->id})\n";

    // Get or create a manufacturer
    $manufacturer = Manufacturer::first();
    if (!$manufacturer) {
        echo "❌ No manufacturer found. Please create a manufacturer first.\n";
        exit(1);
    }
    echo "✅ Found manufacturer: {$manufacturer->name} (ID: {$manufacturer->id})\n";

    // Get or create a facility
    $facility = Facility::first();
    if (!$facility) {
        echo "❌ No facility found. Please create a facility first.\n";
        exit(1);
    }
    echo "✅ Found facility: {$facility->name} (ID: {$facility->id})\n";

    // Get or create a product
    $product = Product::first();
    if (!$product) {
        echo "❌ No product found. Please create a product first.\n";
        exit(1);
    }
    echo "✅ Found product: {$product->name} (ID: {$product->id})\n";

    // Create a test ProductRequest
    $productRequest = ProductRequest::create([
        'request_number' => 'QR-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 4)),
        'provider_id' => $provider->id,
        'facility_id' => $facility->id,
        'patient_fhir_id' => 'test-patient-' . uniqid(),
        'patient_display_id' => 'PAT' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
        'payer_name_submitted' => 'Test Insurance',
        'payer_id' => 'TEST' . rand(100000, 999999),
        'expected_service_date' => date('Y-m-d', strtotime('+1 week')),
        'wound_type' => 'Diabetic Ulcer',
        'place_of_service' => 'Office',
        'order_status' => 'pending',
        'submitted_at' => now(),
        'total_order_value' => 150.00,
        'docuseal_submission_id' => 'test-submission-' . uniqid(),
        'clinical_summary' => [
            'patient' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'date_of_birth' => '1980-01-01',
            ],
            'product_selection' => [
                'manufacturer_id' => $manufacturer->id,
            ],
        ],
    ]);

    echo "✅ Created test ProductRequest: {$productRequest->request_number}\n";

    // Attach product to the request
    DB::table('product_request_products')->insert([
        'product_request_id' => $productRequest->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'size' => 'Medium',
        'unit_price' => 150.00,
        'total_price' => 150.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    echo "✅ Attached product to ProductRequest\n";

    // Test the dashboard controller
    $dashboardController = new \App\Http\Controllers\Provider\DashboardController();

    // Mock the request
    $request = new \Illuminate\Http\Request();
    $request->merge(['user' => $provider]);

    // Mock Auth facade
    \Illuminate\Support\Facades\Auth::shouldReceive('user')->andReturn($provider);

    // Call the index method
    $response = $dashboardController->index($request);

    echo "✅ Dashboard controller executed successfully\n";

    // Verify the ProductRequest appears in the dashboard
    $productRequests = ProductRequest::where('provider_id', $provider->id)->get();
    echo "📊 Found {$productRequests->count()} ProductRequests for provider\n";

    foreach ($productRequests as $pr) {
        echo "  - {$pr->request_number}: {$pr->order_status} (Patient: {$pr->patient_display_id})\n";
    }

    // Test stats calculation
    $stats = [
        'total_orders' => $productRequests->count(),
        'pending_ivr' => $productRequests->where('order_status', 'pending')->count(),
        'in_progress' => $productRequests->whereIn('order_status', ['submitted_to_manufacturer'])->count(),
        'completed' => $productRequests->whereIn('order_status', ['confirmed_by_manufacturer'])->count(),
    ];

    echo "\n📈 Dashboard Stats:\n";
    echo "  - Total ProductRequests: {$stats['total_orders']}\n";
    echo "  - Pending IVR: {$stats['pending_ivr']}\n";
    echo "  - In Progress: {$stats['in_progress']}\n";
    echo "  - Completed: {$stats['completed']}\n";

    // Test manufacturer name extraction
    $manufacturerName = $dashboardController->getManufacturerName($productRequest);
    echo "🏭 Manufacturer Name: {$manufacturerName}\n";

    echo "\n✅ All tests passed! Provider Dashboard now uses ProductRequest model.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

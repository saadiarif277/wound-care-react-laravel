<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Testing Amnio-Maxx IVR Submission API Endpoint ===\n\n";

try {
    // First, find an Amnio-maxx product
    echo "1. Finding Amnio-maxx product...\n";
    $product = Product::where('q_code', 'Q4239')
        ->orWhere('name', 'Amnio-Maxx')
        ->first();
    
    if (!$product) {
        echo "   ❌ No Amnio-maxx product found in database!\n";
        exit(1);
    }
    
    echo "   ✓ Found product: {$product->name} (Code: {$product->q_code})\n";
    echo "   - Manufacturer ID: {$product->manufacturer_id}\n";
    echo "   - Manufacturer: {$product->manufacturer}\n";
    
    // Check if we have any recent episodes for BioWound
    echo "\n2. Checking for recent BioWound episodes...\n";
    $recentEpisode = PatientManufacturerIVREpisode::where('manufacturer_name', 'like', '%BioWound%')
        ->orderBy('created_at', 'desc')
        ->first();
    
    if ($recentEpisode) {
        echo "   ✓ Found recent episode: {$recentEpisode->id}\n";
        echo "   - Manufacturer: {$recentEpisode->manufacturer_name}\n";
        echo "   - Created: {$recentEpisode->created_at}\n";
        
        // Check if metadata has the necessary data
        $metadata = $recentEpisode->metadata ?? [];
        echo "   - Has patient data: " . (isset($metadata['patient_data']) ? 'YES' : 'NO') . "\n";
        echo "   - Has provider data: " . (isset($metadata['provider_data']) ? 'YES' : 'NO') . "\n";
        echo "   - Has order details: " . (isset($metadata['order_details']) ? 'YES' : 'NO') . "\n";
        
        if (isset($metadata['order_details']['products'])) {
            echo "   - Products in order:\n";
            foreach ($metadata['order_details']['products'] as $prod) {
                echo "     * {$prod['name']} ({$prod['code']})\n";
            }
        }
    } else {
        echo "   ❌ No recent BioWound episodes found\n";
    }
    
    // Create a test episode
    echo "\n3. Creating test episode for Amnio-maxx...\n";
    
    // Find a test user
    $testUser = User::where('email', 'like', '%test%')
        ->orWhere('id', 1)
        ->first();
    
    if (!$testUser) {
        echo "   ❌ No test user found!\n";
        exit(1);
    }
    
    echo "   Using user: {$testUser->name} ({$testUser->email})\n";
    
    // Create episode
    $episode = new PatientManufacturerIVREpisode();
    $episode->id = 'test-amnio-' . uniqid();
    $episode->patient_id = 'test-patient-' . uniqid();
    $episode->manufacturer_id = 3; // BioWound Solutions
    $episode->manufacturer_name = 'BioWound Solutions';
    $episode->created_by = $testUser->id;
    $episode->metadata = [
        'patient_data' => [
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'dob' => '1980-01-01',
            'gender' => 'Male',
            'display_id' => 'TEST-' . uniqid()
        ],
        'provider_data' => [
            'name' => 'Dr. Test Provider',
            'npi' => '1234567890',
            'specialty' => 'Wound Care',
            'email' => 'provider@test.com'
        ],
        'facility_data' => [
            'name' => 'Test Hospital',
            'address' => '123 Test St',
            'city' => 'Test City',
            'state' => 'TX',
            'zip' => '12345'
        ],
        'insurance_data' => [
            'primary_name' => 'Test Insurance',
            'primary_member_id' => 'TEST123'
        ],
        'clinical_data' => [
            'wound_type' => 'DFU',
            'wound_location' => 'Left foot',
            'wound_size' => '2x2x0.5'
        ],
        'order_details' => [
            'products' => [
                [
                    'id' => $product->id,
                    'code' => 'Q4239',
                    'name' => 'Amnio-Maxx',
                    'manufacturer' => 'BioWound Solutions',
                    'quantity' => 1
                ]
            ],
            'expected_service_date' => date('Y-m-d', strtotime('+2 days'))
        ]
    ];
    $episode->save();
    
    echo "   ✓ Episode created: {$episode->id}\n";
    
    // Now test the API endpoint
    echo "\n4. Testing API endpoint...\n";
    
    // Authenticate as the test user
    Auth::login($testUser);
    
    // Simulate the API request
    $request = Request::create('/api/v1/quick-request/create-ivr-submission', 'POST', [
        'episode_id' => $episode->id,
        'manufacturer_name' => 'BioWound Solutions'
    ]);
    
    $controller = app(\App\Http\Controllers\QuickRequestController::class);
    
    try {
        $response = $controller->createIvrSubmission($request);
        $responseData = json_decode($response->getContent(), true);
        
        echo "   Response status: " . $response->getStatusCode() . "\n";
        
        if ($response->getStatusCode() === 200) {
            echo "   ✅ SUCCESS!\n";
            echo "   - Submission ID: " . ($responseData['submission_id'] ?? 'NOT SET') . "\n";
            echo "   - Slug: " . ($responseData['slug'] ?? 'NOT SET') . "\n";
            echo "   - Manufacturer: " . ($responseData['manufacturer'] ?? 'NOT SET') . "\n";
        } else {
            echo "   ❌ FAILED!\n";
            echo "   - Message: " . ($responseData['message'] ?? 'No message') . "\n";
            
            // This should show the exact error we're seeing
            if (strpos($responseData['message'] ?? '', 'No template ID found') !== false) {
                echo "\n   ⚠️  THIS IS THE EXACT ERROR HAPPENING IN PRODUCTION!\n";
                echo "   The manufacturer config is not returning the template ID properly.\n";
            }
        }
        
    } catch (\Exception $e) {
        echo "   ❌ Exception: " . $e->getMessage() . "\n";
    }
    
    // Clean up
    echo "\n5. Cleaning up test data...\n";
    $episode->delete();
    echo "   ✓ Test episode deleted\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
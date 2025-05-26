<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use App\Models\UserRole;
use App\Models\ProductRequest;
use App\Services\ProductRecommendationEngine\MSCProductRecommendationService;

// Test script to verify office manager pricing restrictions

echo "Testing Office Manager Pricing Restrictions\n";
echo "==========================================\n\n";

// Test 1: Check UserRole model methods
echo "1. Testing UserRole model methods:\n";

$officeManagerRole = UserRole::where('name', 'office_manager')->first();
if ($officeManagerRole) {
    echo "   - Office Manager role found\n";
    echo "   - canAccessFinancials(): " . ($officeManagerRole->canAccessFinancials() ? 'true' : 'false') . "\n";
    echo "   - canSeeDiscounts(): " . ($officeManagerRole->canSeeDiscounts() ? 'true' : 'false') . "\n";
} else {
    echo "   - Office Manager role NOT found\n";
}

$providerRole = UserRole::where('name', 'provider')->first();
if ($providerRole) {
    echo "   - Provider role found\n";
    echo "   - canAccessFinancials(): " . ($providerRole->canAccessFinancials() ? 'true' : 'false') . "\n";
    echo "   - canSeeDiscounts(): " . ($providerRole->canSeeDiscounts() ? 'true' : 'false') . "\n";
} else {
    echo "   - Provider role NOT found\n";
}

echo "\n";

// Test 2: Test recommendation service with different roles
echo "2. Testing recommendation service with different user roles:\n";

// Find a test product request
$productRequest = ProductRequest::first();
if (!$productRequest) {
    echo "   - No product requests found. Creating a test one...\n";
    // You would create a test product request here
    echo "   - Please create a product request first to test recommendations\n";
} else {
    echo "   - Found product request: {$productRequest->request_number}\n";

    // Test with office manager role
    echo "   - Testing with office_manager role:\n";
    try {
        $service = app(MSCProductRecommendationService::class);
        $result = $service->getRecommendations($productRequest, [
            'use_ai' => false, // Skip AI for faster testing
            'user_role' => 'office_manager',
            'show_msc_pricing' => false
        ]);

        if ($result['success'] && !empty($result['recommendations'])) {
            $firstRec = $result['recommendations'][0];
            echo "     * First recommendation: {$firstRec['product_name']}\n";
            echo "     * Has MSC price: " . (isset($firstRec['estimated_cost']['msc_price']) ? 'YES' : 'NO') . "\n";
            echo "     * Has National ASP: " . (isset($firstRec['estimated_cost']['national_asp']) ? 'YES' : 'NO') . "\n";
            echo "     * Has savings: " . (isset($firstRec['estimated_cost']['savings']) ? 'YES' : 'NO') . "\n";
        } else {
            echo "     * No recommendations returned\n";
        }
    } catch (Exception $e) {
        echo "     * Error: " . $e->getMessage() . "\n";
    }

    // Test with provider role
    echo "   - Testing with provider role:\n";
    try {
        $service = app(MSCProductRecommendationService::class);
        $result = $service->getRecommendations($productRequest, [
            'use_ai' => false, // Skip AI for faster testing
            'user_role' => 'provider',
            'show_msc_pricing' => true
        ]);

        if ($result['success'] && !empty($result['recommendations'])) {
            $firstRec = $result['recommendations'][0];
            echo "     * First recommendation: {$firstRec['product_name']}\n";
            echo "     * Has MSC price: " . (isset($firstRec['estimated_cost']['msc_price']) ? 'YES' : 'NO') . "\n";
            echo "     * Has National ASP: " . (isset($firstRec['estimated_cost']['national_asp']) ? 'YES' : 'NO') . "\n";
            echo "     * Has savings: " . (isset($firstRec['estimated_cost']['savings']) ? 'YES' : 'NO') . "\n";
        } else {
            echo "     * No recommendations returned\n";
        }
    } catch (Exception $e) {
        echo "     * Error: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// Test 3: Check if User model methods work correctly
echo "3. Testing User model methods:\n";

// Find a test user with office manager role
$officeManager = User::whereHas('userRole', function($q) {
    $q->where('name', 'office_manager');
})->first();

if ($officeManager) {
    echo "   - Found office manager user: {$officeManager->email}\n";
    echo "   - canSeeDiscounts(): " . ($officeManager->canSeeDiscounts() ? 'true' : 'false') . "\n";
    echo "   - canAccessFinancials(): " . ($officeManager->canAccessFinancials() ? 'true' : 'false') . "\n";
} else {
    echo "   - No office manager users found\n";
}

// Find a test user with provider role
$provider = User::whereHas('userRole', function($q) {
    $q->where('name', 'provider');
})->first();

if ($provider) {
    echo "   - Found provider user: {$provider->email}\n";
    echo "   - canSeeDiscounts(): " . ($provider->canSeeDiscounts() ? 'true' : 'false') . "\n";
    echo "   - canAccessFinancials(): " . ($provider->canAccessFinancials() ? 'true' : 'false') . "\n";
} else {
    echo "   - No provider users found\n";
}

echo "\nTest completed!\n";
echo "\nExpected results:\n";
echo "- Office managers should NOT see MSC pricing, discounts, or savings\n";
echo "- Office managers should ONLY see National ASP pricing\n";
echo "- Providers should see all pricing information including MSC prices and savings\n";

<?php

// Simple test script to verify product catalog financial restrictions

echo "Testing Product Catalog Financial Restrictions\n";
echo "=============================================\n\n";

// Test data simulation
$sampleProduct = [
    'id' => 1,
    'name' => 'Test Skin Substitute',
    'price_per_sq_cm' => 100.00,
    'msc_price' => 60.00,
    'commission_rate' => 15.0,
    'available_sizes' => [4, 9, 16, 25]
];

// Simulate different user roles
$roles = [
    'provider' => [
        'can_see_msc_pricing' => true,
        'can_view_financials' => true,
        'can_see_discounts' => true,
        'pricing_access_level' => 'full'
    ],
    'office_manager' => [
        'can_see_msc_pricing' => false,
        'can_view_financials' => false,
        'can_see_discounts' => false,
        'pricing_access_level' => 'national_asp_only'
    ],
    'msc_rep' => [
        'can_see_msc_pricing' => true,
        'can_view_financials' => true,
        'can_see_discounts' => true,
        'pricing_access_level' => 'full'
    ],
    'msc_subrep' => [
        'can_see_msc_pricing' => false,
        'can_view_financials' => false,
        'can_see_discounts' => false,
        'pricing_access_level' => 'limited'
    ]
];

function filterProductData($product, $roleRestrictions) {
    $filteredProduct = $product;

    // Remove MSC pricing if role doesn't allow it
    if (!$roleRestrictions['can_see_msc_pricing']) {
        unset($filteredProduct['msc_price']);
    }

    // Remove commission data if not authorized
    if (!$roleRestrictions['can_view_financials']) {
        unset($filteredProduct['commission_rate']);
    }

    return $filteredProduct;
}

function formatPrice($price) {
    return '$' . number_format($price, 2);
}

function testRoleRestrictions($roleName, $roleRestrictions, $product) {
    echo "Testing Role: " . strtoupper($roleName) . "\n";
    echo str_repeat('-', 30) . "\n";

    $filteredProduct = filterProductData($product, $roleRestrictions);

    echo "Product: {$product['name']}\n";
    echo "National ASP: " . formatPrice($product['price_per_sq_cm']) . "/cm²\n";

    if (isset($filteredProduct['msc_price'])) {
        echo "MSC Price: " . formatPrice($filteredProduct['msc_price']) . "/cm² ✅\n";
        $savings = $product['price_per_sq_cm'] - $filteredProduct['msc_price'];
        echo "Savings: " . formatPrice($savings) . "/cm² (40%) ✅\n";
    } else {
        echo "MSC Price: RESTRICTED ❌\n";
        echo "Savings: RESTRICTED ❌\n";
    }

    if (isset($filteredProduct['commission_rate'])) {
        echo "Commission: {$filteredProduct['commission_rate']}% ✅\n";
    } else {
        echo "Commission: RESTRICTED ❌\n";
    }

    echo "\nSize Pricing Table:\n";
    echo "Size (cm²) | National ASP";
    if ($roleRestrictions['can_see_msc_pricing']) {
        echo " | MSC Price | Savings";
    }
    echo "\n";

    foreach ($product['available_sizes'] as $size) {
        $nationalTotal = $product['price_per_sq_cm'] * $size;
        echo sprintf("%-10s | %s", $size, formatPrice($nationalTotal));

        if ($roleRestrictions['can_see_msc_pricing']) {
            $mscTotal = $product['msc_price'] * $size;
            $savings = $nationalTotal - $mscTotal;
            echo sprintf(" | %s | %s", formatPrice($mscTotal), formatPrice($savings));
        }
        echo "\n";
    }

    echo "\nRole Permissions:\n";
    echo "- Can see MSC pricing: " . ($roleRestrictions['can_see_msc_pricing'] ? 'YES ✅' : 'NO ❌') . "\n";
    echo "- Can view financials: " . ($roleRestrictions['can_view_financials'] ? 'YES ✅' : 'NO ❌') . "\n";
    echo "- Can see discounts: " . ($roleRestrictions['can_see_discounts'] ? 'YES ✅' : 'NO ❌') . "\n";
    echo "- Pricing access level: " . $roleRestrictions['pricing_access_level'] . "\n";

    echo "\n" . str_repeat('=', 50) . "\n\n";
}

// Test each role
foreach ($roles as $roleName => $roleRestrictions) {
    testRoleRestrictions($roleName, $roleRestrictions, $sampleProduct);
}

echo "Summary of Expected Behavior:\n";
echo "============================\n";
echo "✅ PROVIDER: Should see all pricing including MSC price, discounts, and commission\n";
echo "❌ OFFICE MANAGER: Should ONLY see National ASP pricing, NO MSC pricing or commission\n";
echo "✅ MSC REP: Should see all pricing including MSC price, discounts, and commission\n";
echo "❌ MSC SUB-REP: Should have limited access, similar to office manager\n\n";

echo "Key Points:\n";
echo "- Office Managers are completely blocked from financial data\n";
echo "- Only National ASP pricing is visible to office managers\n";
echo "- MSC pricing, discounts, and commission data are hidden\n";
echo "- Product catalog should show appropriate warnings for restricted roles\n";
echo "- All API endpoints should filter data based on user role\n";

?>

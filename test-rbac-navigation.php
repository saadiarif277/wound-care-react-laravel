<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use App\Models\UserRole;

// Initialize Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "RBAC Navigation and Permissions Test\n";
echo "===================================\n\n";

// Test all users and their role assignments
$users = User::with('userRole')->get();

echo "Current User Role Assignments:\n";
echo "------------------------------\n";
foreach ($users as $user) {
    $roleName = $user->userRole ? $user->userRole->name : 'NO ROLE';
    $roleDisplay = $user->userRole ? $user->userRole->display_name : 'No Role Assigned';
    echo "• {$user->email} → {$roleName} ({$roleDisplay})\n";
}

echo "\n";

// Test permission checking for key permissions
$testPermissions = [
    'manage-rbac',
    'manage-access-control',
    'view-users',
    'view-commission',
    'manage-system-config',
    'view-settings'
];

echo "Permission Testing:\n";
echo "-------------------\n";
foreach ($users as $user) {
    if (!$user->userRole) continue;

    echo "\n{$user->email} ({$user->userRole->name}):\n";
    foreach ($testPermissions as $permission) {
        $hasPermission = $user->hasPermission($permission);
        $status = $hasPermission ? '✅ ALLOWED' : '❌ DENIED';
        echo "  {$permission}: {$status}\n";
    }
}

echo "\n";

// Test navigation menu generation
echo "Navigation Menu Generation Test:\n";
echo "-------------------------------\n";

// Simulate the navigation component logic
function getMenuByRole($role) {
    $normalizedRole = $role === 'superadmin' ? 'super_admin' : $role;

    $menus = [
        'provider' => ['Dashboard', 'Product Requests', 'MAC/Eligibility/PA', 'Product Catalog', 'eClinicalWorks'],
        'office_manager' => ['Dashboard', 'Product Requests', 'MAC/Eligibility/PA', 'Product Catalog', 'Provider Management', 'eClinicalWorks'],
        'msc_rep' => ['Dashboard', 'Customer Orders', 'Commissions', 'My Customers'],
        'msc_subrep' => ['Dashboard', 'Customer Orders', 'My Commissions'],
        'msc_admin' => ['Dashboard', 'Request Management', 'Order Management', 'User & Org Management', 'Settings'],
        'super_admin' => ['Dashboard', 'Request Management', 'Order Management', 'Commission Overview', 'User & Org Management', 'System Admin']
    ];

    return $menus[$normalizedRole] ?? [];
}

foreach ($users as $user) {
    if (!$user->userRole) continue;

    $menuItems = getMenuByRole($user->userRole->name);
    echo "\n{$user->email} ({$user->userRole->name}) Navigation:\n";
    foreach ($menuItems as $item) {
        echo "  • {$item}\n";
    }
}

echo "\n";

// Test dashboard component mapping
echo "Dashboard Component Mapping:\n";
echo "----------------------------\n";

$dashboardMapping = [
    'provider' => 'Dashboard/Provider/ProviderDashboard',
    'office_manager' => 'Dashboard/OfficeManager/OfficeManagerDashboard',
    'msc_rep' => 'Dashboard/Sales/MscRepDashboard',
    'msc_subrep' => 'Dashboard/Sales/MscSubrepDashboard',
    'msc_admin' => 'Dashboard/Admin/MscAdminDashboard',
    'super_admin' => 'Dashboard/Admin/SuperAdminDashboard',
    'superadmin' => 'Dashboard/Admin/SuperAdminDashboard'
];

foreach ($users as $user) {
    if (!$user->userRole) continue;

    $component = $dashboardMapping[$user->userRole->name] ?? 'Dashboard/Index';
    echo "• {$user->userRole->name} → {$component}\n";
}

echo "\n";

// Test role restrictions
echo "Role Financial Restrictions:\n";
echo "----------------------------\n";
foreach ($users as $user) {
    if (!$user->userRole) continue;

    $role = $user->userRole;
    echo "\n{$user->email} ({$role->name}):\n";
    echo "  • Can access financials: " . ($role->canAccessFinancials() ? 'YES' : 'NO') . "\n";
    echo "  • Can see discounts: " . ($role->canSeeDiscounts() ? 'YES' : 'NO') . "\n";
    echo "  • Can see MSC pricing: " . ($role->canSeeMscPricing() ? 'YES' : 'NO') . "\n";
    echo "  • Can see order totals: " . ($role->canSeeOrderTotals() ? 'YES' : 'NO') . "\n";
    echo "  • Pricing access level: " . $role->getPricingAccessLevel() . "\n";
    echo "  • Commission access level: " . $role->getCommissionAccessLevel() . "\n";
}

echo "\n✅ RBAC Navigation Test completed!\n";
echo "\nIf you're still seeing issues:\n";
echo "1. Clear your browser cache and cookies\n";
echo "2. Log out and log back in\n";
echo "3. Try an incognito/private browser window\n";
echo "4. Check the browser console for JavaScript errors\n";
echo "5. Verify the user role is correctly set in the database\n";

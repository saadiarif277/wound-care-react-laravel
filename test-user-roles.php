<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing User Role Assignments\n";
echo "=============================\n\n";

// Test each of your created users
$testUsers = [
    'provider@mscwound.com' => 'Provider',
    'office.manager@mscwound.com' => 'Office Manager',
    'subrep@mscwound.com' => 'MSC Sub-Rep',
    'salesrep@mscwound.com' => 'MSC Sales Rep',
    'admin@mscwound.com' => 'MSC Admin',
    'superadmin@mscwound.com' => 'Super Admin',
    'johndoe@example.com' => 'Super Admin (existing)',
];

foreach ($testUsers as $email => $expectedRole) {
    $result = DB::select("
        SELECT u.email, u.first_name, u.last_name, u.owner, ur.name as role_name, ur.display_name
        FROM users u
        LEFT JOIN user_roles ur ON u.user_role_id = ur.id
        WHERE u.email = ?
    ", [$email]);

    if (empty($result)) {
        echo "❌ User {$email} not found!\n";
        continue;
    }

    $user = $result[0];
    $actualRole = $user->display_name;

    if ($actualRole === $expectedRole) {
        echo "✅ {$email}: {$actualRole} (correct)\n";
    } else {
        echo "❌ {$email}: Expected '{$expectedRole}', got '{$actualRole}'\n";
    }
}

echo "\n\nRole Configuration Test\n";
echo "=======================\n\n";

// Test UserRole model methods
use App\Models\UserRole;

$roles = UserRole::all();
foreach ($roles as $role) {
    echo "Role: {$role->display_name} ({$role->name})\n";
    echo "  - Financial Access: " . ($role->canAccessFinancials() ? 'Yes' : 'No') . "\n";
    echo "  - Commission Access: {$role->getCommissionAccessLevel()}\n";
    echo "  - Pricing Access: {$role->getPricingAccessLevel()}\n";
    echo "  - Can Manage Products: " . ($role->canManageProducts() ? 'Yes' : 'No') . "\n";
    echo "\n";
}

echo "Dashboard Component Mapping Test\n";
echo "================================\n\n";

// Test dashboard component mapping
use App\Http\Controllers\DashboardController;

$dashboardController = new DashboardController();
$reflection = new ReflectionClass($dashboardController);
$method = $reflection->getMethod('getDashboardComponent');
$method->setAccessible(true);

$roleNames = ['provider', 'office_manager', 'msc_rep', 'msc_subrep', 'msc_admin', 'super_admin'];
foreach ($roleNames as $roleName) {
    $component = $method->invoke($dashboardController, $roleName);
    echo "Role '{$roleName}' → Component: {$component}\n";
}

echo "\n✅ Test completed!\n";
echo "\nIf you're still seeing provider info when logged in as admin, try:\n";
echo "1. Clear your browser cache and cookies\n";
echo "2. Log out and log back in\n";
echo "3. Try an incognito/private browser window\n";

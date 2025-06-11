<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Bootstrap the application
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Users\Organization\Organization;

$admin = User::whereHas('roles', function($q) {
    $q->where('slug', 'msc-admin');
})->first();

$org = Organization::first();

if ($admin && $org) {
    // Check if relationship exists
    $exists = DB::table('organization_users')
        ->where('organization_id', $org->id)
        ->where('user_id', $admin->id)
        ->exists();
        
    if (!$exists) {
        DB::table('organization_users')->insert([
            'organization_id' => $org->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        echo "Added admin to organization\n";
    }
    
    // Set current organization
    $admin->update(['current_organization_id' => $org->id]);
    
    echo "Admin user updated with organization: {$org->name}\n";
    echo "Admin email: {$admin->email}\n";
} else {
    echo "Admin or Organization not found\n";
}
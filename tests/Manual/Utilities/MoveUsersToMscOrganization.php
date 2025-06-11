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

// Get the admin user and MSC organization
$admin = User::whereHas('roles', function($q) {
    $q->where('slug', 'msc-admin');
})->first();

$mscOrg = Organization::where('name', 'MSC Wound Care')->first();

if ($admin && $mscOrg) {
    // Remove from old organization
    DB::table('organization_users')->where('user_id', $admin->id)->delete();
    
    // Add to MSC Wound Care
    DB::table('organization_users')->insert([
        'organization_id' => $mscOrg->id,
        'user_id' => $admin->id,
        'role' => 'admin',
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    // Update current organization
    $admin->update(['current_organization_id' => $mscOrg->id]);
    
    echo "Admin moved to MSC Wound Care organization\n";
    
    // Also move any sales reps
    $salesReps = User::whereHas('roles', function($q) {
        $q->whereIn('slug', ['msc-rep', 'msc-subrep']);
    })->get();
    
    foreach ($salesReps as $rep) {
        $exists = DB::table('organization_users')
            ->where('organization_id', $mscOrg->id)
            ->where('user_id', $rep->id)
            ->exists();
            
        if (!$exists) {
            DB::table('organization_users')->insert([
                'organization_id' => $mscOrg->id,
                'user_id' => $rep->id,
                'role' => 'member',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $rep->update(['current_organization_id' => $mscOrg->id]);
        }
    }
    
    echo "Sales reps also moved to MSC Wound Care: {$salesReps->count()} users\n";
} else {
    echo "Admin or MSC organization not found\n";
}
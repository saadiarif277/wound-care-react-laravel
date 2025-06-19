<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class FixQuickRequestPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure 'view-facilities' permission exists
        $viewFacilitiesPermission = Permission::firstOrCreate(
            ['slug' => 'view-facilities'],
            [
                'name' => 'View Facilities',
                'description' => 'View facilities',
                'guard_name' => 'web',
            ]
        );

        // Get all roles that have 'create-product-requests' permission
        $rolesWithCreateRequests = Role::whereHas('permissions', function ($query) {
            $query->where('slug', 'create-product-requests');
        })->get();

        // Add 'view-facilities' permission to these roles if they don't have it
        foreach ($rolesWithCreateRequests as $role) {
            if (!$role->permissions()->where('slug', 'view-facilities')->exists()) {
                $role->permissions()->attach($viewFacilitiesPermission->id, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                $this->command->info("Added 'view-facilities' permission to role: {$role->name}");
            }
        }

        // Also ensure that users who need quick request access have proper facility associations
        // This is especially important for testing
        
        // Get all users with roles that have create-product-requests permission
        $usersWithCreateRequests = User::whereHas('roles.permissions', function ($query) {
            $query->where('slug', 'create-product-requests');
        })->get();

        foreach ($usersWithCreateRequests as $user) {
            // If user has no facilities and no organization, assign them to the first organization
            if ($user->facilities()->count() === 0 && !$user->current_organization_id) {
                $firstOrg = DB::table('organizations')->first();
                if ($firstOrg) {
                    $user->current_organization_id = $firstOrg->id;
                    $user->save();
                    
                    $this->command->info("Assigned user {$user->email} to organization: {$firstOrg->name}");
                }
            }
            
            // If user still has no facilities but has an organization, associate them with all facilities in that org
            if ($user->facilities()->count() === 0 && $user->current_organization_id) {
                $facilities = DB::table('facilities')
                    ->where('organization_id', $user->current_organization_id)
                    ->where('active', true)
                    ->get();
                    
                foreach ($facilities as $facility) {
                    // Check if association already exists
                    $exists = DB::table('facility_user')
                        ->where('user_id', $user->id)
                        ->where('facility_id', $facility->id)
                        ->exists();
                        
                    if (!$exists) {
                        DB::table('facility_user')->insert([
                            'user_id' => $user->id,
                            'facility_id' => $facility->id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        
                        $this->command->info("Associated user {$user->email} with facility: {$facility->name}");
                    }
                }
            }
        }

        // Create a test user specifically for quick requests if needed
        $testUser = User::where('email', 'quickrequest@test.com')->first();
        
        if (!$testUser) {
            $testUser = User::create([
                'account_id' => 1,
                'first_name' => 'Quick',
                'last_name' => 'Request',
                'email' => 'quickrequest@test.com',
                'password' => bcrypt('secret'),
                'current_organization_id' => DB::table('organizations')->first()->id ?? null,
            ]);
            
            // Assign provider role (which has create-product-requests permission)
            $providerRole = Role::where('slug', 'provider')->first();
            if ($providerRole) {
                $testUser->roles()->attach($providerRole->id, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            // Associate with all active facilities
            $facilities = DB::table('facilities')->where('active', true)->get();
            foreach ($facilities as $facility) {
                DB::table('facility_user')->insert([
                    'user_id' => $testUser->id,
                    'facility_id' => $facility->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
            $this->command->info("Created test user: quickrequest@test.com (password: secret)");
        }

        $this->command->info('Quick Request permissions have been fixed!');
    }
}
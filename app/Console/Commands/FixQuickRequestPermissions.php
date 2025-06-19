<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class FixQuickRequestPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:quick-request-permissions {--user=} {--all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix permissions for users to access Quick Request feature';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fixing Quick Request permissions...');

        // Ensure 'view-facilities' permission exists
        $viewFacilitiesPermission = Permission::firstOrCreate(
            ['slug' => 'view-facilities'],
            [
                'name' => 'View Facilities',
                'description' => 'View facilities',
                'guard_name' => 'web',
            ]
        );

        // If specific user email is provided
        if ($userEmail = $this->option('user')) {
            $user = User::where('email', $userEmail)->first();
            
            if (!$user) {
                $this->error("User with email {$userEmail} not found.");
                return 1;
            }

            $this->fixUserPermissions($user);
            return 0;
        }

        // If --all flag is provided, fix all users with create-product-requests permission
        if ($this->option('all')) {
            // First, ensure all roles with create-product-requests also have view-facilities
            $rolesWithCreateRequests = Role::whereHas('permissions', function ($query) {
                $query->where('slug', 'create-product-requests');
            })->get();

            foreach ($rolesWithCreateRequests as $role) {
                if (!$role->permissions()->where('slug', 'view-facilities')->exists()) {
                    $role->permissions()->attach($viewFacilitiesPermission->id, [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    $this->info("Added 'view-facilities' permission to role: {$role->name}");
                }
            }

            // Get all users with roles that have create-product-requests permission
            $users = User::whereHas('roles.permissions', function ($query) {
                $query->where('slug', 'create-product-requests');
            })->get();

            $this->info("Found {$users->count()} users with create-product-requests permission.");

            foreach ($users as $user) {
                $this->fixUserPermissions($user);
            }

            return 0;
        }

        $this->info('Use --user=email@example.com to fix a specific user or --all to fix all users.');
        return 0;
    }

    /**
     * Fix permissions and associations for a specific user
     */
    private function fixUserPermissions(User $user)
    {
        $this->info("Processing user: {$user->email}");

        // Check current permissions
        $hasCreatePermission = $user->hasPermission('create-product-requests');
        $hasViewPermission = $user->hasPermission('view-facilities');

        $this->info("  - Has create-product-requests: " . ($hasCreatePermission ? 'Yes' : 'No'));
        $this->info("  - Has view-facilities: " . ($hasViewPermission ? 'Yes' : 'No'));

        // If user has no organization, assign them to the first one
        if (!$user->current_organization_id) {
            $firstOrg = DB::table('organizations')->first();
            if ($firstOrg) {
                $user->current_organization_id = $firstOrg->id;
                $user->save();
                
                $this->info("  - Assigned to organization: {$firstOrg->name}");
            }
        }

        // Check facility associations
        $facilityCount = $user->facilities()->count();
        $this->info("  - Associated facilities: {$facilityCount}");

        // If user has no facilities but has an organization, associate them with facilities
        if ($facilityCount === 0 && $user->current_organization_id) {
            $facilities = DB::table('facilities')
                ->where('organization_id', $user->current_organization_id)
                ->where('active', true)
                ->get();

            if ($facilities->count() > 0) {
                foreach ($facilities as $facility) {
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
                    }
                }
                
                $this->info("  - Associated with {$facilities->count()} facilities from organization");
            }
        }

        $this->info("  - User fixed successfully!");
    }
}
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class FixAdminPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:admin-permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure admin@msc.com has all necessary permissions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fixing admin permissions...');

        // Find the admin user
        $adminUser = User::where('email', 'admin@msc.com')->first();
        
        if (!$adminUser) {
            $this->error('Admin user (admin@msc.com) not found!');
            return 1;
        }

        // Ensure the msc-admin role exists and has manage-orders permission
        $mscAdminRole = Role::where('name', 'msc-admin')->first();
        
        if (!$mscAdminRole) {
            $this->error('msc-admin role not found! Run database seeders first.');
            return 1;
        }

        // Ensure manage-orders permission exists
        $manageOrdersPermission = Permission::where('name', 'manage-orders')->first();
        
        if (!$manageOrdersPermission) {
            $this->info('Creating manage-orders permission...');
            $manageOrdersPermission = Permission::create([
                'name' => 'manage-orders',
                'guard_name' => 'web'
            ]);
        }

        // Give the permission to the msc-admin role
        if (!$mscAdminRole->hasPermissionTo('manage-orders')) {
            $this->info('Granting manage-orders permission to msc-admin role...');
            $mscAdminRole->givePermissionTo('manage-orders');
        }

        // Ensure the user has the msc-admin role
        if (!$adminUser->hasRole('msc-admin')) {
            $this->info('Assigning msc-admin role to admin@msc.com...');
            $adminUser->assignRole('msc-admin');
        }

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Verify
        $this->info("\nVerifying permissions for admin@msc.com:");
        $this->info("Roles: " . $adminUser->getRoleNames()->implode(', '));
        $this->info("Has manage-orders permission: " . ($adminUser->hasPermissionTo('manage-orders') ? 'YES ✓' : 'NO ✗'));
        
        $allPermissions = $adminUser->getAllPermissions()->pluck('name')->sort();
        $this->info("Total permissions: " . $allPermissions->count());
        
        if ($adminUser->hasPermissionTo('manage-orders')) {
            $this->info("\n✓ Admin user has been successfully configured with manage-orders permission!");
        } else {
            $this->error("\n✗ Failed to grant manage-orders permission. Please check the logs.");
        }

        return 0;
    }
}
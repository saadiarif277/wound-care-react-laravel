<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class CheckUserPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:check-permissions {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check permissions for a user by email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found.");
            return 1;
        }

        $this->info("User: {$user->name} ({$user->email})");
        $this->info("ID: {$user->id}");
        
        // Check roles
        $roles = $user->getRoleNames();
        $this->info("\nRoles:");
        foreach ($roles as $role) {
            $this->line("  - {$role}");
        }

        // Check permissions
        $permissions = $user->getAllPermissions()->pluck('name')->sort();
        $this->info("\nPermissions (" . $permissions->count() . " total):");
        foreach ($permissions as $permission) {
            $this->line("  - {$permission}");
        }

        // Check specific permission
        $hasManageOrders = $user->hasPermissionTo('manage-orders');
        $this->info("\nHas 'manage-orders' permission: " . ($hasManageOrders ? 'YES ✓' : 'NO ✗'));

        if (!$hasManageOrders) {
            $this->warn("\nTo grant manage-orders permission, run:");
            $this->warn("php artisan tinker");
            $this->warn("\$user = User::where('email', '{$email}')->first();");
            $this->warn("\$user->givePermissionTo('manage-orders');");
        }

        return 0;
    }
}
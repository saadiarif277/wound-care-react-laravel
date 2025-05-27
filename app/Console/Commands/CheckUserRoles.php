<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Role;

class CheckUserRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:user-roles {--fix : Fix incorrect role assignments}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check user role assignments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('fix')) {
            $this->fixRoleAssignments();
        }

        $this->info('Current user role assignments:');
        $this->line('');

        $users = User::with('roles')->get();

        foreach ($users as $user) {
            $roleInfo = $user->roles->isNotEmpty() ? $user->roles->first()->name : 'No role assigned';
            $this->line($user->email . ' - ' . $roleInfo);
        }

        $this->line('');
        $this->info('Available roles:');

        $roles = Role::all();
        foreach ($roles as $role) {
            $this->line($role->id . ': ' . $role->slug . ' (' . $role->name . ')');
        }
    }

    private function fixRoleAssignments()
    {
        $this->info('Fixing role assignments...');

        // Get the office-manager role
        $officeManagerRole = Role::where('slug', 'office-manager')->first();

        if (!$officeManagerRole) {
            $this->error('Office Manager role not found!');
            return;
        }

        // Fix office.manager@mscwound.com
        $user = User::where('email', 'office.manager@mscwound.com')->first();
        if ($user && !$user->hasRole('office-manager')) {
            $user->roles()->sync([$officeManagerRole->id]);
            $this->info('Fixed office.manager@mscwound.com role assignment');
        }

        // Also fix other users that should have office_manager role
        $otherOfficeManagers = [
            'manager@example.com',
        ];

        foreach ($otherOfficeManagers as $email) {
            $user = User::where('email', $email)->first();
            if ($user && !$user->hasRole('office-manager')) {
                $user->roles()->sync([$officeManagerRole->id]);
                $this->info("Fixed {$email} role assignment");
            }
        }

        $this->info('Role assignments fixed!');
        $this->line('');
    }
}

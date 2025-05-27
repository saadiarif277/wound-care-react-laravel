<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\UserRole;

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

        $users = User::with('userRole')->get();

        foreach ($users as $user) {
            $roleInfo = $user->userRole ? $user->userRole->name : 'No role assigned';
            $this->line($user->email . ' - ' . $roleInfo);
        }

        $this->line('');
        $this->info('Available roles:');

        $roles = UserRole::all();
        foreach ($roles as $role) {
            $this->line($role->id . ': ' . $role->name . ' (' . $role->display_name . ')');
        }
    }

    private function fixRoleAssignments()
    {
        $this->info('Fixing role assignments...');

        // Get the office_manager role
        $officeManagerRole = UserRole::where('name', 'office_manager')->first();

        if (!$officeManagerRole) {
            $this->error('Office Manager role not found!');
            return;
        }

        // Fix office.manager@mscwound.com
        $user = User::where('email', 'office.manager@mscwound.com')->first();
        if ($user) {
            $user->user_role_id = $officeManagerRole->id;
            $user->save();
            $this->info('Fixed office.manager@mscwound.com role assignment');
        }

        // Also fix other users that should have office_manager role
        $otherOfficeManagers = [
            'manager@example.com',
        ];

        foreach ($otherOfficeManagers as $email) {
            $user = User::where('email', $email)->first();
            if ($user && $user->user_role_id != $officeManagerRole->id) {
                $user->user_role_id = $officeManagerRole->id;
                $user->save();
                $this->info("Fixed {$email} role assignment");
            }
        }

        $this->info('Role assignments fixed!');
        $this->line('');
    }
}

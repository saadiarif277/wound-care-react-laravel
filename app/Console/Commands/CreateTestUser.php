<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserRole;
use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateTestUser extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'user:create-test {email} {role} {--first-name=} {--last-name=} {--account-id=1}';

    /**
     * The console command description.
     */
    protected $description = 'Create a test user with a specific role';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $roleName = $this->argument('role');
        $firstName = $this->option('first-name') ?? 'Test';
        $lastName = $this->option('last-name') ?? 'User';
        $accountId = $this->option('account-id');

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists!");
            return 1;
        }

        // Check if role exists
        $role = UserRole::where('name', $roleName)->first();
        if (!$role) {
            $this->error("Role '{$roleName}' does not exist!");
            $this->info('Available roles: ' . UserRole::pluck('name')->implode(', '));
            return 1;
        }

        // Check if account exists
        if (!Account::find($accountId)) {
            $this->error("Account with ID {$accountId} does not exist!");
            return 1;
        }

        try {
            // Create user
            $user = User::create([
                'account_id' => $accountId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password' => Hash::make('password'), // Default password
                'owner' => $roleName === 'superadmin',
                'email_verified_at' => now(),
            ]);

            // Assign role
            $user->userRole()->attach($role->id);

            $this->info("✓ User created successfully!");
            $this->table(['Field', 'Value'], [
                ['ID', $user->id],
                ['Name', $user->first_name . ' ' . $user->last_name],
                ['Email', $user->email],
                ['Role', $role->display_name],
                ['Account ID', $user->account_id],
                ['Password', 'password (default)'],
            ]);

            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to create user: ' . $e->getMessage());
            return 1;
        }
    }
}

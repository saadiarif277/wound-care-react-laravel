<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run()
    {
        // First create a default account
        $accountId = (string) Str::uuid();
        DB::table('accounts')->insert([
            'id' => $accountId,
            'name' => 'Default Account',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create users with their roles
        $users = [
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'account_id' => $accountId,
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@msc.com',
                'password' => Hash::make('secret'),
                'owner' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'account_id' => $accountId,
                'first_name' => 'John',
                'last_name' => 'Smith',
                'email' => 'provider@example.com',
                'password' => Hash::make('secret'),
                'owner' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'account_id' => $accountId,
                'first_name' => 'Jane',
                'last_name' => 'Manager',
                'email' => 'manager@example.com',
                'password' => Hash::make('secret'),
                'owner' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'account_id' => $accountId,
                'first_name' => 'Bob',
                'last_name' => 'Sales',
                'email' => 'rep@msc.com',
                'password' => Hash::make('secret'),
                'owner' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'account_id' => $accountId,
                'first_name' => 'Alice',
                'last_name' => 'SubRep',
                'email' => 'subrep@msc.com',
                'password' => Hash::make('secret'),
                'owner' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Insert users
        foreach ($users as $user) {
            DB::table('users')->insert($user);
        }

        // Assign roles to users using the user_role pivot table
        $roleAssignments = [
            'msc-admin' => 'admin@msc.com',
            'provider' => 'provider@example.com',
            'office-manager' => 'manager@example.com',
            'msc-rep' => 'rep@msc.com',
            'msc-subrep' => 'subrep@msc.com',
        ];

        foreach ($roleAssignments as $roleSlug => $userEmail) {
            $role = DB::table('roles')->where('slug', $roleSlug)->first();
            $user = DB::table('users')->where('email', $userEmail)->first();

            if ($role && $user) {
                DB::table('user_role')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}

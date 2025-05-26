<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // First create a default account
        $accountId = DB::table('accounts')->insertGetId([
            'name' => 'Default Account',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create users with their roles
        $users = [
            [
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

        // Insert users and get their IDs
        $userIds = [];
        foreach ($users as $user) {
            $userIds[] = DB::table('users')->insertGetId($user);
        }

        // Assign roles to users
        $roles = [
            'msc_admin' => 0,
            'provider' => 1,
            'office_manager' => 2,
            'msc_rep' => 3,
            'msc_subrep' => 4,
        ];

        foreach ($roles as $roleName => $userIndex) {
            $roleId = DB::table('user_roles')
                ->where('name', $roleName)
                ->value('id');

            if ($roleId) {
                DB::table('users')
                    ->where('id', $userIds[$userIndex])
                    ->update(['user_role_id' => $roleId]);
            }
        }
    }
}

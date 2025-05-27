<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\UserRole;
use App\Models\User;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create basic user roles
        $roles = [
            [
                'name' => UserRole::PROVIDER,
                'display_name' => 'Provider',
                'description' => 'Healthcare provider with access to patient care tools',
                'is_active' => true,
                'hierarchy_level' => 3,
            ],
            [
                'name' => UserRole::OFFICE_MANAGER,
                'display_name' => 'Office Manager',
                'description' => 'Office manager with administrative access',
                'is_active' => true,
                'hierarchy_level' => 4,
            ],
            [
                'name' => UserRole::MSC_REP,
                'display_name' => 'MSC Sales Rep',
                'description' => 'MSC sales representative with commission tracking',
                'is_active' => true,
                'hierarchy_level' => 2,
            ],
            [
                'name' => UserRole::MSC_SUBREP,
                'display_name' => 'MSC Sub-Rep',
                'description' => 'MSC sub-representative with limited access',
                'is_active' => true,
                'hierarchy_level' => 5,
            ],
            [
                'name' => UserRole::MSC_ADMIN,
                'display_name' => 'MSC Admin',
                'description' => 'MSC administrator with full system access',
                'is_active' => true,
                'hierarchy_level' => 1,
            ],
            [
                'name' => UserRole::SUPER_ADMIN,
                'display_name' => 'Super Admin',
                'description' => 'Super administrator with complete system control',
                'is_active' => true,
                'hierarchy_level' => 0,
            ],
        ];

        foreach ($roles as $roleData) {
            UserRole::updateOrCreate(
                ['name' => $roleData['name']],
                $roleData
            );
        }

        // Assign default provider role to existing users who don't have a role
        $providerRole = UserRole::where('name', UserRole::PROVIDER)->first();
        if ($providerRole) {
            User::whereNull('user_role_id')->update(['user_role_id' => $providerRole->id]);
        }

        $this->command->info('User roles seeded successfully!');
    }
}

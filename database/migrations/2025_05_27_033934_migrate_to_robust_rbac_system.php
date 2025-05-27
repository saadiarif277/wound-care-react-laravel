<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Create role mappings from old user_roles to new roles
        $roleMappings = [
            'provider' => 'provider',
            'office_manager' => 'office-manager',
            'msc_rep' => 'msc-rep',
            'msc_subrep' => 'msc-subrep',
            'msc_admin' => 'msc-admin',
            'super_admin' => 'super-admin',
            'superadmin' => 'super-admin', // Handle legacy inconsistency
        ];

        // Step 2: Migrate existing user role assignments
        foreach ($roleMappings as $oldRoleName => $newRoleSlug) {
            // Get the old role
            $oldRole = DB::table('user_roles')->where('name', $oldRoleName)->first();
            if (!$oldRole) {
                continue;
            }

            // Get the new role
            $newRole = DB::table('roles')->where('slug', $newRoleSlug)->first();
            if (!$newRole) {
                // Create the new role if it doesn't exist
                $newRoleId = DB::table('roles')->insertGetId([
                    'name' => ucwords(str_replace(['-', '_'], ' ', $newRoleSlug)),
                    'slug' => $newRoleSlug,
                    'description' => "Migrated from legacy {$oldRoleName} role",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $newRoleId = $newRole->id;
            }

            // Migrate users from old role to new role
            $usersWithOldRole = DB::table('users')
                ->where('user_role_id', $oldRole->id)
                ->get(['id']);

            foreach ($usersWithOldRole as $user) {
                // Check if assignment already exists to avoid duplicates
                $exists = DB::table('role_user')
                    ->where('user_id', $user->id)
                    ->where('role_id', $newRoleId)
                    ->exists();

                if (!$exists) {
                    DB::table('role_user')->insert([
                        'user_id' => $user->id,
                        'role_id' => $newRoleId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Step 3: Remove the user_role_id foreign key and column
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['user_role_id']);
            $table->dropColumn('user_role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the user_role_id column
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('user_role_id')->nullable()->constrained('user_roles')->onDelete('set null');
        });

        // Note: This doesn't restore the data as that would be complex
        // In a real scenario, you'd want to backup the data before migration
    }
};

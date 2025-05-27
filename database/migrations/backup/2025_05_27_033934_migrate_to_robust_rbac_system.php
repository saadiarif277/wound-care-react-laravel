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
        // Step 1: Create role mappings for new roles
        $roleMappings = [
            'provider' => 'provider',
            'office_manager' => 'office-manager',
            'msc_rep' => 'msc-rep',
            'msc_subrep' => 'msc-subrep',
            'msc_admin' => 'msc-admin',
            'super_admin' => 'super-admin',
            'superadmin' => 'super-admin', // Handle legacy inconsistency
        ];

        // Step 2: Create new roles if they don't exist
        foreach ($roleMappings as $roleName => $roleSlug) {
            // Check if role already exists
            $exists = DB::table('roles')
                ->where('slug', $roleSlug)
                ->exists();

            if (!$exists) {
                // Determine hierarchy level based on role
                $hierarchyLevel = match($roleSlug) {
                    'super-admin' => 100,
                    'msc-admin' => 80,
                    'msc-rep' => 60,
                    'msc-subrep' => 50,
                    'office-manager' => 40,
                    'provider' => 20,
                    default => 0
                };

                DB::table('roles')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'name' => ucwords(str_replace(['-', '_'], ' ', $roleSlug)),
                    'slug' => $roleSlug,
                    'display_name' => ucwords(str_replace(['-', '_'], ' ', $roleSlug)),
                    'description' => "Migrated from legacy {$roleName} role",
                    'is_active' => true,
                    'hierarchy_level' => $hierarchyLevel,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Step 3: Remove the user_role_id column if it exists
        if (Schema::hasColumn('users', 'user_role_id')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'user_role_id')) {
                    $table->dropForeign(['user_role_id']);
                    $table->dropColumn('user_role_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the role_id column
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('role_id')->nullable();
            $table->foreign('role_id')
                  ->references('id')
                  ->on('roles')
                  ->onDelete('set null');
        });
    }
};

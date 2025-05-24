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
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // provider, office_manager, msc_rep, msc_subrep, msc_admin, super_admin
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->json('permissions')->nullable(); // For future RBAC implementation
            $table->boolean('is_active')->default(true);
            $table->integer('hierarchy_level')->default(0); // For role hierarchy (0 = highest)
            $table->timestamps();
        });

        // Seed the 6 MSC Portal roles
        DB::table('user_roles')->insert([
            [
                'name' => 'provider',
                'display_name' => 'Healthcare Provider',
                'description' => 'Healthcare providers/clinicians who request wound care products and services',
                'hierarchy_level' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'office_manager',
                'display_name' => 'Office Manager',
                'description' => 'Facility-attached managers with provider oversight and financial restrictions',
                'hierarchy_level' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'msc_rep',
                'display_name' => 'MSC Sales Representative',
                'description' => 'Primary MSC sales representatives with commission tracking',
                'hierarchy_level' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'msc_subrep',
                'display_name' => 'MSC Sub-Representative',
                'description' => 'Sub-representatives with limited access under primary reps',
                'hierarchy_level' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'msc_admin',
                'display_name' => 'MSC Administrator',
                'description' => 'MSC internal administrators with system management access',
                'hierarchy_level' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'super_admin',
                'display_name' => 'Super Administrator',
                'description' => 'Highest level system access and control',
                'hierarchy_level' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};

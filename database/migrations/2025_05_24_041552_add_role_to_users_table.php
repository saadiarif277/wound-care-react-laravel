<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('user_role_id')->nullable()->constrained('user_roles')->onDelete('set null');
            $table->string('npi_number')->nullable(); // For healthcare providers
            $table->string('dea_number')->nullable(); // For prescribing providers
            $table->string('license_number')->nullable(); // Professional license
            $table->string('license_state')->nullable(); // State of license
            $table->date('license_expiry')->nullable();
            $table->json('credentials')->nullable(); // Additional certifications/credentials
            $table->boolean('is_verified')->default(false); // For credential verification
            $table->timestamp('last_activity')->nullable();

            // Index for performance
            $table->index(['user_role_id', 'is_verified']);
            $table->index('npi_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['user_role_id']);
            $table->dropColumn([
                'user_role_id',
                'npi_number',
                'dea_number',
                'license_number',
                'license_state',
                'license_expiry',
                'credentials',
                'is_verified',
                'last_activity'
            ]);
        });
    }
};

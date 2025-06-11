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
        Schema::table('facilities', function (Blueprint $table) {
            // Add new fields for facility enhancements
            $table->string('tax_id')->nullable()->after('npi');
            $table->string('ptan')->nullable()->after('tax_id');
            $table->string('medicare_admin_contractor')->nullable()->after('ptan');
            $table->enum('default_place_of_service', ['11', '12', '31', '32'])->default('11')->after('medicare_admin_contractor');
            $table->string('group_npi')->nullable()->after('npi');
            $table->string('status')->default('active')->after('active');
            $table->timestamp('npi_verified_at')->nullable()->after('npi');
            
            // Add indexes for performance
            $table->index('tax_id');
            $table->index('ptan');
            $table->index('medicare_admin_contractor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['tax_id']);
            $table->dropIndex(['ptan']);
            $table->dropIndex(['medicare_admin_contractor']);
            
            // Drop columns
            $table->dropColumn([
                'tax_id',
                'ptan',
                'medicare_admin_contractor',
                'default_place_of_service',
                'group_npi',
                'status',
                'npi_verified_at'
            ]);
        });
    }
};
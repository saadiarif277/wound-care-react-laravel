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
        Schema::table('provider_profiles', function (Blueprint $table) {
            // Check and add missing provider fields
            if (!Schema::hasColumn('provider_profiles', 'primary_specialty')) {
                $table->string('primary_specialty')->nullable()->after('specialty');
            }
            if (!Schema::hasColumn('provider_profiles', 'credentials')) {
                $table->string('credentials')->nullable()->after('primary_specialty');
            }
            if (!Schema::hasColumn('provider_profiles', 'dea_number')) {
                $table->string('dea_number')->nullable()->after('credentials');
            }
            if (!Schema::hasColumn('provider_profiles', 'state_license_number')) {
                $table->string('state_license_number')->nullable()->after('dea_number');
            }
            if (!Schema::hasColumn('provider_profiles', 'license_state')) {
                $table->string('license_state')->nullable()->after('state_license_number');
            }
            if (!Schema::hasColumn('provider_profiles', 'medicaid_number')) {
                $table->string('medicaid_number')->nullable()->after('license_state');
            }
            if (!Schema::hasColumn('provider_profiles', 'practice_name')) {
                $table->string('practice_name')->nullable()->after('medicaid_number');
            }
            if (!Schema::hasColumn('provider_profiles', 'phone')) {
                $table->string('phone')->nullable()->after('practice_name');
            }
            if (!Schema::hasColumn('provider_profiles', 'fax')) {
                $table->string('fax')->nullable()->after('phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            $columns = [
                'primary_specialty',
                'credentials', 
                'dea_number',
                'state_license_number',
                'license_state',
                'medicaid_number',
                'practice_name',
                'phone',
                'fax'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('provider_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

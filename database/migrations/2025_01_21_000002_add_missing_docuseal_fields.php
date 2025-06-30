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
        // Add missing provider fields to provider_profiles table
        Schema::table('provider_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('provider_profiles', 'phone')) {
                $table->string('phone', 20)->nullable()->after('specialty');
            }
            if (!Schema::hasColumn('provider_profiles', 'fax')) {
                $table->string('fax', 20)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('provider_profiles', 'medicaid_number')) {
                $table->string('medicaid_number', 50)->nullable()->after('ptan');
            }
        });

        // Add missing facility fields
        Schema::table('facilities', function (Blueprint $table) {
            // Split address into two lines for better DocuSeal compatibility
            if (!Schema::hasColumn('facilities', 'address_line1')) {
                $table->string('address_line1')->nullable()->after('address');
            }
            if (!Schema::hasColumn('facilities', 'address_line2')) {
                $table->string('address_line2')->nullable()->after('address_line1');
            }

            // Add general facility fax (separate from contact_fax)
            if (!Schema::hasColumn('facilities', 'fax')) {
                $table->string('fax', 20)->nullable()->after('phone');
            }

            // Add facility medicaid number
            if (!Schema::hasColumn('facilities', 'medicaid_number')) {
                $table->string('medicaid_number', 50)->nullable()->after('ptan');
            }
        });

        // Optionally migrate existing address data to address_line1
        if (Schema::hasColumn('facilities', 'address_line1')) {
            DB::statement('UPDATE facilities SET address_line1 = address WHERE address_line1 IS NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->dropColumn(['phone', 'fax', 'medicaid_number']);
        });

        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn(['address_line1', 'address_line2', 'fax', 'medicaid_number']);
        });
    }
};
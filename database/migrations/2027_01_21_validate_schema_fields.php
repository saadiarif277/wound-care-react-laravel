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
        // Add missing fields to users table for providers
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'ptan')) {
                $table->string('ptan')->nullable()->after('npi_number');
            }
            if (!Schema::hasColumn('users', 'tax_id')) {
                $table->string('tax_id')->nullable()->after('ptan');
            }
        });

        // Add missing fields to facilities table
        Schema::table('facilities', function (Blueprint $table) {
            if (!Schema::hasColumn('facilities', 'tax_id')) {
                $table->string('tax_id')->nullable()->after('npi');
            }
            if (!Schema::hasColumn('facilities', 'ptan')) {
                $table->string('ptan')->nullable()->after('tax_id');
            }
            if (!Schema::hasColumn('facilities', 'fax')) {
                $table->string('fax')->nullable()->after('phone');
            }
        });

        // Add missing tax_id to organizations if not present
        Schema::table('organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('organizations', 'tax_id')) {
                $table->string('tax_id')->nullable()->after('name');
            }
        });

        // Add relationship fields to episodes table
        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'provider_id')) {
                $table->unsignedBigInteger('provider_id')->nullable()->after('patient_id');
            }
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'facility_id')) {
                $table->unsignedBigInteger('facility_id')->nullable()->after('provider_id');
            }
        });

        // Add provider_id to quick_request_submissions
        Schema::table('quick_request_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('quick_request_submissions', 'provider_id')) {
                $table->unsignedBigInteger('provider_id')->nullable()->after('provider_npi');
            }
            if (!Schema::hasColumn('quick_request_submissions', 'facility_id')) {
                $table->unsignedBigInteger('facility_id')->nullable()->after('facility_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ptan', 'tax_id']);
        });

        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn(['tax_id', 'ptan', 'fax']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            if (Schema::hasColumn('organizations', 'tax_id')) {
                $table->dropColumn('tax_id');
            }
        });

        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            $table->dropColumn(['provider_id', 'facility_id']);
        });

        Schema::table('quick_request_submissions', function (Blueprint $table) {
            $table->dropColumn(['provider_id', 'facility_id']);
        });
    }
};

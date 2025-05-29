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
        Schema::table('medicare_mac_validations', function (Blueprint $table) {
            $table->string('patient_zip_code')->nullable()->after('mac_region')->comment('Patient ZIP code used for MAC jurisdiction determination');
            $table->string('addressing_method')->nullable()->after('patient_zip_code')->comment('Method used for MAC addressing (patient_address, zip_code_specific, state_based, etc.)');

            // Add index on patient_zip_code for performance
            $table->index('patient_zip_code');
            $table->index('addressing_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medicare_mac_validations', function (Blueprint $table) {
            $table->dropIndex(['patient_zip_code']);
            $table->dropIndex(['addressing_method']);
            $table->dropColumn(['patient_zip_code', 'addressing_method']);
        });
    }
};

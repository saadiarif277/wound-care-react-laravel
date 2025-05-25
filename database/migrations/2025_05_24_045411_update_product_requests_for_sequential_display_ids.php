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
        Schema::table('product_requests', function (Blueprint $table) {
            // Add the new patient display ID field
            $table->string('patient_display_id', 7)->nullable()->after('patient_fhir_id'); // "JoSm001" format

            // Add index for efficient patient display ID lookups
            $table->index('patient_display_id');
            $table->index(['facility_id', 'patient_display_id']);
        });

        // Remove the age-based fields that are no longer needed for HIPAA compliance
        Schema::table('product_requests', function (Blueprint $table) {
            $table->dropColumn(['patient_initials', 'patient_dob_month', 'patient_dob_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_requests', function (Blueprint $table) {
            // Re-add the old age-based fields
            $table->string('patient_initials', 4)->nullable();
            $table->tinyInteger('patient_dob_month')->nullable();
            $table->integer('patient_dob_year')->nullable();
        });

        Schema::table('product_requests', function (Blueprint $table) {
            // Remove the new fields and indexes
            $table->dropIndex(['facility_id', 'patient_display_id']);
            $table->dropIndex(['patient_display_id']);
            $table->dropColumn('patient_display_id');
        });
    }
};

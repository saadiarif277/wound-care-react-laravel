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
        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            // Add patient_fhir_id column if it doesn't exist
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'patient_fhir_id')) {
                $table->string('patient_fhir_id')->nullable()->after('patient_id');
                $table->index('patient_fhir_id');
            }

            // Add patient_display_id column if it doesn't exist
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'patient_display_id')) {
                $table->string('patient_display_id', 15)->nullable()->after('patient_fhir_id');
                $table->index('patient_display_id');
            }

            // Add metadata column if it doesn't exist (for storing form data and other metadata)
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'metadata')) {
                $table->json('metadata')->nullable()->after('docuseal_completed_at');
            }

            // Add docuseal_submission_url column if it doesn't exist
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'docuseal_submission_url')) {
                $table->text('docuseal_submission_url')->nullable()->after('docuseal_submission_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            if (Schema::hasColumn('patient_manufacturer_ivr_episodes', 'patient_fhir_id')) {
                $table->dropIndex(['patient_fhir_id']);
                $table->dropColumn('patient_fhir_id');
            }

            if (Schema::hasColumn('patient_manufacturer_ivr_episodes', 'patient_display_id')) {
                $table->dropIndex(['patient_display_id']);
                $table->dropColumn('patient_display_id');
            }

            if (Schema::hasColumn('patient_manufacturer_ivr_episodes', 'metadata')) {
                $table->dropColumn('metadata');
            }

            if (Schema::hasColumn('patient_manufacturer_ivr_episodes', 'docuseal_submission_url')) {
                $table->dropColumn('docuseal_submission_url');
            }
        });
    }
};

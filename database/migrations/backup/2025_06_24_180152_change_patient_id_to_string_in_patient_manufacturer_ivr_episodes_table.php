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
            // Drop index if exists (for patient_id)
            if (Schema::hasColumn('patient_manufacturer_ivr_episodes', 'patient_id')) {
                try {
                    $table->dropIndex('pm_episodes_patient_mfg_idx');
                } catch (\Throwable $e) {}
                // Change column type
                $table->string('patient_id', 64)->nullable()->change();
                // Recreate index
                $table->index(['patient_id', 'manufacturer_id'], 'pm_episodes_patient_mfg_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            // Drop index if exists
            try {
                $table->dropIndex('pm_episodes_patient_mfg_idx');
            } catch (\Throwable $e) {}
            // Change back to uuid
            $table->uuid('patient_id')->nullable()->change();
            // Recreate index
            $table->index(['patient_id', 'manufacturer_id'], 'pm_episodes_patient_mfg_idx');
        });
    }
};

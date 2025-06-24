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
            // Update patient_display_id column to allow longer IDs
            $table->string('patient_display_id', 15)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            // Revert back to original length
            $table->string('patient_display_id', 7)->change();
        });
    }
};

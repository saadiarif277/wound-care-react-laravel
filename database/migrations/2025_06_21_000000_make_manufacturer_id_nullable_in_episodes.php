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
            // Make manufacturer_id nullable to support products without manufacturer
            $table->uuid('manufacturer_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            // Revert manufacturer_id to non-nullable
            $table->uuid('manufacturer_id')->nullable(false)->change();
        });
    }
};
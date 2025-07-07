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
            $table->string('patient_display_id', 15)->default('')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            $table->string('patient_display_id', 15)->nullable()->change();
        });
    }
};

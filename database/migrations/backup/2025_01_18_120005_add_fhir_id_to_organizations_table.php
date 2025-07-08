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
        Schema::table('organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('organizations', 'fhir_id')) {
                $table->string('fhir_id')->nullable()->unique()->after('postal_code'); // Assuming 'postal_code' exists
                $table->comment('Stores the FHIR Organization resource ID for this organization.');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (Schema::hasColumn('organizations', 'fhir_id')) {
                $table->dropColumn('fhir_id');
            }
        });
    }
};

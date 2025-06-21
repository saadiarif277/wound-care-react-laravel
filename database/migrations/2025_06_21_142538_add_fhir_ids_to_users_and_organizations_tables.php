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
        // Add FHIR practitioner ID to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('fhir_practitioner_id')->nullable()->after('dea_number');
            $table->index('fhir_practitioner_id');
        });

        // Add FHIR organization ID to organizations table
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('fhir_organization_id')->nullable()->after('status');
            $table->index('fhir_organization_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['fhir_practitioner_id']);
            $table->dropColumn('fhir_practitioner_id');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex(['fhir_organization_id']);
            $table->dropColumn('fhir_organization_id');
        });
    }
};

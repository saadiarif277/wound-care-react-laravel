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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'practitioner_fhir_id')) {
                $table->string('practitioner_fhir_id')->nullable()->unique()->after('remember_token'); // Assuming 'remember_token' exists or choose appropriate column
                $table->comment('Stores the FHIR Practitioner resource ID for this user.');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'practitioner_fhir_id')) {
                $table->dropColumn('practitioner_fhir_id');
            }
        });
    }
};

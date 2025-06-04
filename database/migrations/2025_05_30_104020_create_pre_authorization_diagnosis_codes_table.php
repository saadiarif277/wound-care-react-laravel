<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This is a PIVOT TABLE that links pre_authorizations to specific ICD-10 codes.
     * It allows a pre-authorization to have multiple diagnosis codes (many-to-many relationship).
     */
    public function up(): void
    {
        Schema::create('pre_authorization_diagnosis_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pre_authorization_id');
            $table->unsignedBigInteger('icd10_code_id');
            $table->enum('type', ['primary', 'secondary', 'other'])->default('secondary');
            $table->integer('sequence')->default(1); // Order of diagnosis codes
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('pre_authorization_id')
                  ->references('id')
                  ->on('pre_authorizations')
                  ->onDelete('cascade');

            $table->foreign('icd10_code_id')
                  ->references('id')
                  ->on('icd10_codes')
                  ->onDelete('cascade');

            // Unique constraint - each ICD-10 code can only be used once per pre-authorization
            $table->unique(['pre_authorization_id', 'icd10_code_id'], 'pre_auth_dx_codes_unique');

            // Indexes for performance
            $table->index('pre_authorization_id', 'pre_auth_dx_pre_auth_idx');
            $table->index('icd10_code_id', 'pre_auth_dx_icd10_idx');
            $table->index(['pre_authorization_id', 'type'], 'pre_auth_dx_type_idx');
            $table->index(['pre_authorization_id', 'sequence'], 'pre_auth_dx_seq_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pre_authorization_diagnosis_codes');
    }
};

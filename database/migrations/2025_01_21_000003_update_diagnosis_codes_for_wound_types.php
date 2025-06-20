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
        // Add wound_type column to diagnosis_codes table if it doesn't exist
        if (Schema::hasTable('diagnosis_codes') && !Schema::hasColumn('diagnosis_codes', 'wound_type')) {
            Schema::table('diagnosis_codes', function (Blueprint $table) {
                $table->string('wound_type')->nullable()->after('specialty')
                    ->comment('Associated wound type: diabetic_foot_ulcer, venous_leg_ulcer, pressure_ulcer, chronic_skin_subs');
                $table->index('wound_type');
            });
        }

        // Create wound_type_diagnosis_codes pivot table
        if (!Schema::hasTable('wound_type_diagnosis_codes')) {
            Schema::create('wound_type_diagnosis_codes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('wound_type_code');
                $table->string('diagnosis_code', 20);
                $table->string('category', 50); // yellow, orange, or null for pressure ulcers
                $table->boolean('is_required')->default(false);
                $table->timestamps();

                $table->foreign('wound_type_code')->references('code')->on('wound_types')->onDelete('cascade');
                $table->foreign('diagnosis_code')->references('code')->on('diagnosis_codes')->onDelete('cascade');
                
                $table->unique(['wound_type_code', 'diagnosis_code'], 'wound_diagnosis_unique');
                $table->index(['wound_type_code', 'category']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wound_type_diagnosis_codes');
        
        if (Schema::hasTable('diagnosis_codes') && Schema::hasColumn('diagnosis_codes', 'wound_type')) {
            Schema::table('diagnosis_codes', function (Blueprint $table) {
                $table->dropColumn('wound_type');
            });
        }
    }
};
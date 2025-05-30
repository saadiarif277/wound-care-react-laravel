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
        Schema::create('icd10_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 10)->unique(); // E.g., 'E11.621'
            $table->text('description'); // E.g., 'Type 2 diabetes mellitus with foot ulcer'
            $table->string('category', 10)->nullable(); // E.g., 'E11' for diabetes
            $table->string('subcategory', 10)->nullable(); // E.g., 'E11.6' for diabetes with skin complications
            $table->boolean('is_billable')->default(true); // Some codes are categories, not billable
            $table->boolean('is_active')->default(true);
            $table->string('version', 10)->default('2024'); // ICD-10 version year
            $table->timestamps();

            // Indexes for performance
            $table->index('code');
            $table->index('category');
            $table->index('subcategory');
            $table->index('is_billable');
            $table->index('is_active');
            $table->index(['category', 'is_active']);
            $table->fullText(['code', 'description']); // For search functionality
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('icd10_codes');
    }
};

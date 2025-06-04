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
        Schema::create('cpt_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 10)->unique(); // E.g., '15271'
            $table->text('description'); // E.g., 'Application of skin substitute graft to trunk, arms, legs'
            $table->string('category', 50)->nullable(); // E.g., 'Surgery', 'Medicine'
            $table->string('subcategory', 100)->nullable(); // E.g., 'Skin Grafts and Flaps'
            $table->decimal('relative_value_units', 8, 4)->nullable(); // RVUs for reimbursement
            $table->boolean('is_active')->default(true);
            $table->string('version', 10)->default('2024'); // CPT version year
            $table->timestamps();

            // Indexes for performance
            $table->index('code');
            $table->index('category');
            $table->index('subcategory');
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
        Schema::dropIfExists('cpt_codes');
    }
};

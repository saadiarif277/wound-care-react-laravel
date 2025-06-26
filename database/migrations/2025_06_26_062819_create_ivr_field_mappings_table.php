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
        Schema::create('ivr_field_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('manufacturer_id')->constrained();
            $table->string('template_name');
            $table->string('template_version')->default('1.0');
            $table->string('fhir_path');
            $table->string('ivr_field_name');
            $table->string('mapping_type')->default('fuzzy'); // exact, fuzzy, semantic, pattern
            $table->decimal('confidence_score', 3, 2)->default(0);
            $table->json('transformation_rules')->nullable();
            $table->json('validation_rules')->nullable();
            $table->string('default_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_learned')->default(false);
            $table->integer('usage_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->timestamps();
            
            $table->index(['manufacturer_id', 'template_name', 'is_active']);
            $table->index(['fhir_path', 'ivr_field_name']);
            $table->index('confidence_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ivr_field_mappings');
    }
};

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
        Schema::create('ivr_mapping_audit', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('mapping_id')->nullable();
            $table->foreignId('manufacturer_id')->constrained();
            $table->string('template_name');
            $table->string('fhir_path');
            $table->string('ivr_field_name');
            $table->string('mapped_value')->nullable();
            $table->string('mapping_strategy'); // exact, fuzzy, semantic, pattern, fallback
            $table->decimal('confidence_score', 3, 2);
            $table->boolean('was_successful')->default(true);
            $table->json('error_details')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('session_id')->nullable();
            $table->timestamps();
            
            $table->index(['manufacturer_id', 'template_name']);
            $table->index(['mapping_strategy', 'was_successful']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ivr_mapping_audit');
    }
};

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
        Schema::create('msc_product_recommendation_rules', function (Blueprint $table) {
            $table->id();

            // Rule identification
            $table->string('name')->index();
            $table->text('description')->nullable();
            $table->integer('priority')->default(0); // Higher number = higher priority
            $table->boolean('is_active')->default(true);

            // Clinical matching criteria
            $table->enum('wound_type', ['DFU', 'VLU', 'PU', 'TW', 'AU', 'OTHER'])->nullable();
            $table->string('wound_stage')->nullable();
            $table->string('wound_depth')->nullable();

            // Complex matching conditions (JSON)
            $table->json('conditions')->nullable(); // Complex matching conditions

            // Recommendation data
            $table->json('recommended_msc_product_qcodes_ranked'); // JSON array with Q-codes, ranks, and reasoning
            $table->json('reasoning_templates')->nullable(); // JSON map for generating human-readable explanations
            $table->string('default_size_suggestion_key')->nullable(); // MATCH_WOUND_AREA, STANDARD_2x2, etc.

            // Exclusion criteria
            $table->json('contraindications')->nullable(); // JSON array of conditions that exclude this rule

            // Evidence and audit
            $table->json('clinical_evidence')->nullable(); // JSON with supporting evidence/studies
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('last_updated_by_user_id')->nullable()->constrained('users')->onDelete('set null');

            // Temporal validity
            $table->date('effective_date')->nullable();
            $table->date('expiration_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['is_active', 'wound_type']);
            $table->index(['effective_date', 'expiration_date']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msc_product_recommendation_rules');
    }
};

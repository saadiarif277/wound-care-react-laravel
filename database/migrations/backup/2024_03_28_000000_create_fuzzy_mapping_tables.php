<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Field Mappings Table
        Schema::create('ivr_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_id')->constrained();
            $table->string('template_id');
            $table->string('source_field');
            $table->string('target_field');
            $table->decimal('confidence', 3, 2);
            $table->enum('match_type', ['exact', 'fuzzy', 'semantic', 'pattern', 'manual', 'fallback']);
            $table->integer('usage_count')->default(0);
            $table->decimal('success_rate', 3, 2)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('created_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['template_id', 'source_field']);
            $table->index('confidence');
            $table->index(['manufacturer_id', 'template_id']);
            $table->index(['usage_count', 'success_rate']);
        });

        // 2. Template Fields Table
        Schema::create('ivr_template_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manufacturer_id')->constrained();
            $table->string('template_id');
            $table->string('field_name');
            $table->string('field_type', 50)->nullable();
            $table->string('field_category', 100)->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_checkbox')->default(false);
            $table->json('validation_rules')->nullable();
            $table->text('default_value')->nullable();
            $table->json('options')->nullable();
            $table->integer('position')->nullable();
            $table->timestamps();
            
            $table->unique(['template_id', 'field_name']);
            $table->index('template_id');
            $table->index('manufacturer_id');
            $table->index('field_category');
        });

        // 3. Mapping Audit Table
        Schema::create('ivr_mapping_audit', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamp('timestamp');
            $table->string('episode_id', 36)->nullable();
            $table->string('template_id');
            $table->foreignId('manufacturer_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            
            // Mapping statistics
            $table->integer('total_fields');
            $table->integer('mapped_fields');
            $table->integer('fallback_fields')->default(0);
            $table->integer('unmapped_fields');
            $table->decimal('avg_confidence', 3, 2)->nullable();
            
            // Performance metrics
            $table->integer('duration_ms');
            $table->boolean('cache_hit')->default(false);
            
            // Validation results
            $table->boolean('validation_passed')->nullable();
            $table->integer('validation_errors')->default(0);
            $table->integer('validation_warnings')->default(0);
            
            // Detailed data
            $table->json('field_details')->nullable();
            $table->json('warnings')->nullable();
            $table->json('errors')->nullable();
            
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('timestamp');
            $table->index('episode_id');
            $table->index('manufacturer_id');
            $table->index('user_id');
            $table->index('avg_confidence');
            $table->index('duration_ms');
        });

        // 4. Diagnosis Code Mappings
        Schema::create('ivr_diagnosis_code_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('icd10_code', 10);
            $table->string('description');
            $table->enum('wound_type', ['venous_leg_ulcer', 'diabetic_foot_ulcer', 'pressure_ulcer', 'chronic_ulcer']);
            $table->string('category')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->json('related_codes')->nullable();
            $table->timestamps();
            
            $table->index('icd10_code');
            $table->index('wound_type');
            $table->index('priority');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ivr_diagnosis_code_mappings');
        Schema::dropIfExists('ivr_mapping_audit');
        Schema::dropIfExists('ivr_template_fields');
        Schema::dropIfExists('ivr_field_mappings');
    }
};

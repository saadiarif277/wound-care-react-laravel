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
        // Drop the unused IVR field mapping tables
        // These tables were part of an old field mapping system that is no longer used
        // Current field mapping uses CSV files and JSON configurations instead
        
        Schema::dropIfExists('ivr_field_mappings');
        Schema::dropIfExists('ivr_template_fields');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate ivr_field_mappings table
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

        // Recreate ivr_template_fields table
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
            $table->index(['manufacturer_id', 'template_id']);
            $table->index('field_category');
        });
    }
};

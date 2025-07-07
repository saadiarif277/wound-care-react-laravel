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
        Schema::create('pdf_field_metadata', function (Blueprint $table) {
            $table->id();
            
            // Template identification
            $table->string('docuseal_template_id')->index();
            $table->foreignId('manufacturer_id')->nullable()->constrained('manufacturers')->cascadeOnDelete();
            $table->string('template_name');
            $table->string('template_version')->nullable();
            
            // PDF file information
            $table->string('pdf_file_path')->nullable();
            $table->string('pdf_file_hash')->nullable(); // To detect changes
            $table->timestamp('pdf_last_modified')->nullable();
            $table->integer('pdf_page_count')->default(1);
            
            // Field information
            $table->string('field_name'); // Exact name from PDF
            $table->string('field_name_normalized')->index(); // Cleaned/normalized version
            $table->enum('field_type', [
                'text', 'number', 'date', 'email', 'phone', 'checkbox', 
                'radio', 'dropdown', 'signature', 'image', 'currency',
                'percentage', 'multiline_text', 'readonly', 'calculated'
            ]);
            $table->string('field_subtype')->nullable(); // More specific type info
            
            // Field properties
            $table->boolean('is_required')->default(false);
            $table->boolean('is_readonly')->default(false);
            $table->boolean('is_calculated')->default(false);
            $table->text('field_validation')->nullable(); // JSON validation rules
            $table->text('field_options')->nullable(); // JSON for dropdown/radio options
            $table->string('default_value')->nullable();
            $table->integer('max_length')->nullable();
            $table->string('input_format')->nullable(); // Date format, phone format, etc.
            
            // Position and layout
            $table->integer('page_number')->default(1);
            $table->decimal('x_coordinate', 10, 4)->nullable();
            $table->decimal('y_coordinate', 10, 4)->nullable();
            $table->decimal('width', 10, 4)->nullable();
            $table->decimal('height', 10, 4)->nullable();
            $table->integer('tab_order')->nullable();
            
            // Field grouping and relationships
            $table->string('field_group')->nullable(); // patient_info, insurance_info, etc.
            $table->string('parent_field')->nullable(); // For nested fields
            $table->json('related_fields')->nullable(); // Fields that depend on this one
            
            // Medical/business context
            $table->string('medical_category')->nullable(); // patient, provider, facility, insurance, etc.
            $table->string('business_purpose')->nullable(); // What this field is used for
            $table->text('field_description')->nullable();
            $table->json('common_values')->nullable(); // Frequently used values
            
            // AI and mapping metadata
            $table->json('ai_suggestions')->nullable(); // AI-generated field mapping suggestions
            $table->decimal('confidence_score', 5, 4)->nullable(); // How confident we are about field detection
            $table->json('mapping_alternatives')->nullable(); // Alternative names this field might be mapped to
            $table->integer('usage_frequency')->default(0); // How often this field is used
            $table->timestamp('last_used_at')->nullable();
            
            // Extraction metadata
            $table->string('extraction_method')->default('pypdf2'); // pypdf2, pdfplumber, pymupdf
            $table->string('extraction_version')->nullable(); // Version of extraction library
            $table->json('extraction_metadata')->nullable(); // Additional extraction info
            $table->boolean('extraction_verified')->default(false); // Human verified
            $table->timestamp('extracted_at');
            
            // Change tracking
            $table->timestamp('field_last_modified')->nullable();
            $table->boolean('field_definition_changed')->default(false);
            $table->json('change_history')->nullable(); // Track changes over time
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['docuseal_template_id', 'field_name']);
            $table->index(['manufacturer_id', 'field_type']);
            $table->index(['field_name_normalized', 'field_type']);
            $table->index(['medical_category', 'field_type']);
            $table->index(['extraction_verified', 'confidence_score']);
            
            // Unique constraint for field per template
            $table->unique(['docuseal_template_id', 'field_name', 'page_number'], 'unique_template_field');
        });
        
        // Create index table for fast field name lookups
        Schema::create('pdf_field_name_index', function (Blueprint $table) {
            $table->id();
            $table->string('field_name_variant')->index(); // All possible variants of field names
            $table->string('canonical_field_name'); // The standardized name
            $table->foreignId('pdf_field_metadata_id')->constrained('pdf_field_metadata')->cascadeOnDelete();
            $table->enum('variant_type', ['exact', 'normalized', 'alias', 'misspelling', 'abbreviation']);
            $table->decimal('similarity_score', 5, 4)->default(1.0);
            $table->integer('usage_count')->default(0);
            $table->timestamps();
            
            $table->index(['field_name_variant', 'variant_type']);
            $table->index(['canonical_field_name', 'similarity_score']);
        });
        
        // Create field mapping validation history
        Schema::create('field_mapping_validation_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdf_field_metadata_id')->constrained('pdf_field_metadata')->cascadeOnDelete();
            $table->string('attempted_mapping'); // What field name was attempted
            $table->boolean('mapping_successful');
            $table->string('error_message')->nullable();
            $table->string('correction_applied')->nullable();
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->string('correction_method')->nullable(); // ai, fuzzy, pattern, manual
            $table->json('context_data')->nullable(); // Additional context about the mapping attempt
            $table->timestamp('attempted_at');
            $table->timestamps();
            
            $table->index(['attempted_mapping', 'mapping_successful'], 'idx_attempted_mapping_success');
            $table->index(['pdf_field_metadata_id', 'attempted_at'], 'idx_field_attempted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_mapping_validation_history');
        Schema::dropIfExists('pdf_field_name_index');
        Schema::dropIfExists('pdf_field_metadata');
    }
};

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
        // Canonical fields table - defines all standard fields
        if (!Schema::hasTable('canonical_fields')) {
            Schema::create('canonical_fields', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // physicianInformation, facilityInformation, etc.
            $table->string('field_name'); // physicianName, physicianNPI, etc.
            $table->string('display_name')->nullable(); // Human-readable name
            $table->string('field_path')->nullable(); // Full path in canonical structure
            $table->string('data_type'); // string, boolean, date, etc.
            $table->boolean('is_required')->default(false);
            $table->boolean('is_phi')->default(false); // PHI flag
            $table->text('description')->nullable();
            $table->json('validation_rules')->nullable(); // JSON array of validation rules
            $table->json('example_values')->nullable(); // Example values
            $table->json('metadata')->nullable(); // Additional metadata
            $table->boolean('hipaa_flag')->default(false); // Indicates if field contains PHI
            $table->timestamps();
            
            $table->index(['category', 'field_name']);
            $table->unique(['category', 'field_name']); // Change unique constraint
            });
        }

        // Template field mappings - maps template fields to canonical fields
        if (!Schema::hasTable('template_field_mappings')) {
            Schema::create('template_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('template_id')->constrained('docuseal_templates')->onDelete('cascade');
            $table->string('field_name'); // Field name in the template
            $table->foreignId('canonical_field_id')->nullable()->constrained('canonical_fields')->onDelete('set null');
            $table->json('transformation_rules')->nullable(); // JSON array of transformation rules
            $table->decimal('confidence_score', 5, 2)->default(0); // 0-100 confidence percentage
            $table->enum('validation_status', ['valid', 'warning', 'error'])->default('valid');
            $table->json('validation_messages')->nullable(); // Array of validation messages
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('version')->default(1);
            $table->timestamps();
            
            $table->unique(['template_id', 'field_name']);
            $table->index(['template_id', 'canonical_field_id']);
            $table->index('validation_status');
            });
        }

        // Mapping audit logs - track all changes to mappings
        if (!Schema::hasTable('mapping_audit_logs')) {
            Schema::create('mapping_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('template_id')->constrained('docuseal_templates')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action'); // created, updated, deleted, bulk_update
            $table->json('changes'); // JSON object with before/after values
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at');
            
            $table->index(['template_id', 'created_at']);
            $table->index('user_id');
            });
        }

        // Mapping presets - saved mapping configurations
        if (!Schema::hasTable('mapping_presets')) {
            Schema::create('mapping_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('manufacturer_id')->nullable();
            $table->string('document_type')->nullable();
            $table->json('preset_mappings'); // Full mapping configuration
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            
            $table->index(['manufacturer_id', 'document_type']);
            $table->index('created_by');
            });
        }

        // Add mapping stats columns to docuseal_templates
        Schema::table('docuseal_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('docuseal_templates', 'total_mapped_fields')) {
                $table->integer('total_mapped_fields')->default(0)->after('last_extracted_at');
            }
            if (!Schema::hasColumn('docuseal_templates', 'mapping_coverage')) {
                $table->decimal('mapping_coverage', 5, 2)->default(0)->after('total_mapped_fields');
            }
            if (!Schema::hasColumn('docuseal_templates', 'required_fields_mapped')) {
                $table->integer('required_fields_mapped')->default(0)->after('mapping_coverage');
            }
            if (!Schema::hasColumn('docuseal_templates', 'validation_errors_count')) {
                $table->integer('validation_errors_count')->default(0)->after('required_fields_mapped');
            }
            if (!Schema::hasColumn('docuseal_templates', 'last_mapping_update')) {
                $table->timestamp('last_mapping_update')->nullable()->after('validation_errors_count');
            }
            if (!Schema::hasColumn('docuseal_templates', 'last_mapped_by')) {
                $table->foreignId('last_mapped_by')->nullable()->after('last_mapping_update')
                    ->constrained('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('docuseal_templates', function (Blueprint $table) {
            $table->dropForeign(['last_mapped_by']);
            $table->dropColumn([
                'total_mapped_fields',
                'mapping_coverage',
                'required_fields_mapped',
                'validation_errors_count',
                'last_mapping_update',
                'last_mapped_by'
            ]);
        });

        Schema::dropIfExists('mapping_presets');
        Schema::dropIfExists('mapping_audit_logs');
        Schema::dropIfExists('template_field_mappings');
        Schema::dropIfExists('canonical_fields');
    }
};
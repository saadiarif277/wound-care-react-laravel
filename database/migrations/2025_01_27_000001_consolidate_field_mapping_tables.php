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
        // Create field_mapping_logs table to track all mapping operations
        if (!Schema::hasTable('field_mapping_logs')) {
            Schema::create('field_mapping_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('episode_id');
            $table->string('manufacturer_name');
            $table->unsignedInteger('manufacturer_id')->nullable();
            $table->string('mapping_type'); // 'docuseal', 'export', 'api', etc.
            $table->decimal('completeness_percentage', 5, 2);
            $table->decimal('required_completeness_percentage', 5, 2);
            $table->integer('fields_mapped');
            $table->integer('fields_total');
            $table->integer('required_fields_mapped');
            $table->integer('required_fields_total');
            $table->json('field_status'); // Detailed field-by-field status
            $table->json('validation_errors')->nullable();
            $table->json('validation_warnings')->nullable();
            $table->decimal('mapping_duration_ms', 10, 2);
            $table->string('source_service')->nullable(); // Track which service performed the mapping
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            
            $table->foreign('episode_id')->references('id')->on('patient_manufacturer_ivr_episodes');
            $table->index(['episode_id', 'manufacturer_name']);
            $table->index('mapping_type');
            $table->index('created_at');
            });
        }

        // Add new columns to patient_manufacturer_ivr_episodes for unified tracking
        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            // Add columns if they don't exist
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'manufacturer_name')) {
                $table->string('manufacturer_name')->nullable()->after('manufacturer_id');
            }
            
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'template_id')) {
                $table->string('template_id')->nullable()->after('manufacturer_name');
            }
            
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'field_mapping_completeness')) {
                $table->decimal('field_mapping_completeness', 5, 2)->nullable()->after('template_id');
            }
            
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'required_fields_completeness')) {
                $table->decimal('required_fields_completeness', 5, 2)->nullable()->after('field_mapping_completeness');
            }
            
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'mapped_fields')) {
                $table->json('mapped_fields')->nullable()->after('required_fields_completeness');
            }
            
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'validation_warnings')) {
                $table->json('validation_warnings')->nullable()->after('mapped_fields');
            }
            
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'docuseal_status')) {
                $table->string('docuseal_status')->default('pending')->after('validation_warnings');
            }
            
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'viewed_at')) {
                $table->timestamp('viewed_at')->nullable();
            }
            
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'started_at')) {
                $table->timestamp('started_at')->nullable();
            }
            
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'sent_at')) {
                $table->timestamp('sent_at')->nullable();
            }
            
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
            
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'expired_at')) {
                $table->timestamp('expired_at')->nullable();
            }
            
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'signed_document_url')) {
                $table->text('signed_document_url')->nullable();
            }
            
        });
        
        // Add indexes separately to check existence
        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexesFound = $sm->listTableIndexes('patient_manufacturer_ivr_episodes');
            
            if (!array_key_exists('pmi_episodes_docuseal_status_idx', $indexesFound)) {
                $table->index('docuseal_status', 'pmi_episodes_docuseal_status_idx');
            }
            
            if (!array_key_exists('pmi_episodes_field_completeness_idx', $indexesFound)) {
                $table->index('field_mapping_completeness', 'pmi_episodes_field_completeness_idx');
            }
        });

        // Create field_mapping_cache table for performance optimization
        if (!Schema::hasTable('field_mapping_cache')) {
            Schema::create('field_mapping_cache', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key')->unique();
            $table->json('cached_data');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index('expires_at');
            });
        }

        // Create field_mapping_analytics table for tracking patterns
        if (!Schema::hasTable('field_mapping_analytics')) {
            Schema::create('field_mapping_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('manufacturer_name');
            $table->string('field_name');
            $table->string('match_type'); // 'exact', 'fuzzy', 'semantic', 'pattern'
            $table->string('source_field')->nullable();
            $table->decimal('match_score', 3, 2)->nullable();
            $table->integer('usage_count')->default(1);
            $table->boolean('successful')->default(true);
            $table->timestamps();
            
            $table->index(['manufacturer_name', 'field_name']);
            $table->index('match_type');
            $table->index('usage_count');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_mapping_analytics');
        Schema::dropIfExists('field_mapping_cache');
        Schema::dropIfExists('field_mapping_logs');
        
        // Remove added columns from patient_manufacturer_ivr_episodes
        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            $columnsToRemove = [
                'manufacturer_name',
                'template_id',
                'field_mapping_completeness',
                'required_fields_completeness',
                'mapped_fields',
                'validation_warnings',
                'docuseal_status',
                'viewed_at',
                'started_at',
                'sent_at',
                'completed_at',
                'expired_at',
                'signed_document_url'
            ];
            
            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('patient_manufacturer_ivr_episodes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create table to track multiple Docuseal submissions per episode
        if (!Schema::hasTable('episode_docuseal_submissions')) {
            Schema::create('episode_docuseal_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('episode_id');
            $table->string('template_type', 50); // 'ivr', 'order_form', 'supplemental', etc.
            $table->string('template_id', 100);
            $table->string('submission_id', 100)->nullable();
            $table->string('status', 50)->default('pending'); // pending, sent, completed, expired
            $table->json('prefill_data')->nullable(); // Store what was sent
            $table->json('submission_data')->nullable(); // Store what was received
            $table->string('signed_document_url')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->foreign('episode_id')->references('id')->on('patient_manufacturer_ivr_episodes');
            $table->index(['episode_id', 'template_type']);
            $table->index('submission_id');
            $table->index('status');
        });
        }
        
        // Add support for multiple template types per manufacturer
        if (Schema::hasTable('docuseal_templates')) {
            Schema::table('docuseal_templates', function (Blueprint $table) {
                if (!Schema::hasColumn('docuseal_templates', 'template_type')) {
                    $table->string('template_type', 50)->default('ivr')->after('docuseal_template_id');
                }
                // is_active already exists in the original migration, skip it
                if (!Schema::hasColumn('docuseal_templates', 'version')) {
                    $table->string('version', 20)->nullable()->after('document_type');
                }
                if (!Schema::hasColumn('docuseal_templates', 'requires_previous')) {
                    $table->string('requires_previous', 50)->nullable()->after('version'); // Must complete this template type first
                }
                
                // Add index using manufacturer_id instead of manufacturer_name
                // Check if index exists (SQLite compatible)
                $indexExists = false;
                if (DB::getDriverName() === 'sqlite') {
                    $indexExists = collect(DB::select("SELECT name FROM sqlite_master WHERE type='index' AND name='idx_manufacturer_template_type'"))->isNotEmpty();
                } else {
                    $indexExists = collect(DB::select("SHOW INDEX FROM docuseal_templates"))->pluck('Key_name')->contains('idx_manufacturer_template_type');
                }
                
                if (!$indexExists) {
                    $table->index(['manufacturer_id', 'template_type', 'is_active'], 'idx_manufacturer_template_type');
                }
            });
        }
        
        // Add order form tracking to episodes
        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'order_form_status')) {
                $table->string('order_form_status', 50)->nullable()->after('ivr_status');
            }
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'order_form_submission_id')) {
                $table->string('order_form_submission_id', 100)->nullable()->after('order_form_status');
            }
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'order_form_completed_at')) {
                $table->timestamp('order_form_completed_at')->nullable()->after('order_form_submission_id');
            }
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'forms_metadata')) {
                $table->json('forms_metadata')->nullable()->comment('Track all forms associated with episode');
            }
        });
        
        // Create configuration table for Docuseal form flow
        if (!Schema::hasTable('docuseal_form_flows')) {
            Schema::create('docuseal_form_flows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('manufacturer_name', 100);
            $table->string('flow_name', 100);
            $table->json('steps'); // Array of template types in order
            $table->json('conditions')->nullable(); // Conditional logic for form selection
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['manufacturer_name', 'is_default', 'is_active']);
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('episode_docuseal_submissions');
        Schema::dropIfExists('docuseal_form_flows');
        
        if (Schema::hasTable('docuseal_templates')) {
            Schema::table('docuseal_templates', function (Blueprint $table) {
                // Drop index if it exists
                $indexes = collect(DB::select("SHOW INDEX FROM docuseal_templates"))->pluck('Key_name');
                if ($indexes->contains('idx_manufacturer_template_type')) {
                    $table->dropIndex('idx_manufacturer_template_type');
                }
                
                // Drop columns if they exist
                $columnsToRemove = [];
                if (Schema::hasColumn('docuseal_templates', 'template_type')) {
                    $columnsToRemove[] = 'template_type';
                }
                if (Schema::hasColumn('docuseal_templates', 'version')) {
                    $columnsToRemove[] = 'version';
                }
                if (Schema::hasColumn('docuseal_templates', 'requires_previous')) {
                    $columnsToRemove[] = 'requires_previous';
                }
                
                if (!empty($columnsToRemove)) {
                    $table->dropColumn($columnsToRemove);
                }
            });
        }
        
        if (Schema::hasTable('patient_manufacturer_ivr_episodes')) {
            Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
                $columnsToRemove = [];
                if (Schema::hasColumn('patient_manufacturer_ivr_episodes', 'order_form_status')) {
                    $columnsToRemove[] = 'order_form_status';
                }
                if (Schema::hasColumn('patient_manufacturer_ivr_episodes', 'order_form_submission_id')) {
                    $columnsToRemove[] = 'order_form_submission_id';
                }
                if (Schema::hasColumn('patient_manufacturer_ivr_episodes', 'order_form_completed_at')) {
                    $columnsToRemove[] = 'order_form_completed_at';
                }
                if (Schema::hasColumn('patient_manufacturer_ivr_episodes', 'forms_metadata')) {
                    $columnsToRemove[] = 'forms_metadata';
                }
                
                if (!empty($columnsToRemove)) {
                    $table->dropColumn($columnsToRemove);
                }
            });
        }
    }
};
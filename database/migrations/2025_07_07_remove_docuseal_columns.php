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
        // Remove DocuSeal columns from patient_manufacturer_ivr_episodes table
        if (Schema::hasTable('patient_manufacturer_ivr_episodes')) {
            Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
                if (Schema::hasColumn('patient_manufacturer_ivr_episodes', 'docuseal_submission_id')) {
                    $table->dropColumn('docuseal_submission_id');
                }
                if (Schema::hasColumn('patient_manufacturer_ivr_episodes', 'docuseal_submission_url')) {
                    $table->dropColumn('docuseal_submission_url');
                }
                if (Schema::hasColumn('patient_manufacturer_ivr_episodes', 'docuseal_status')) {
                    $table->dropColumn('docuseal_status');
                }
                if (Schema::hasColumn('patient_manufacturer_ivr_episodes', 'docuseal_completed_at')) {
                    $table->dropColumn('docuseal_completed_at');
                }
            });
        }

        // Remove DocuSeal columns from product_requests table
        if (Schema::hasTable('product_requests')) {
            Schema::table('product_requests', function (Blueprint $table) {
                if (Schema::hasColumn('product_requests', 'docuseal_submission_id')) {
                    $table->dropColumn('docuseal_submission_id');
                }
                if (Schema::hasColumn('product_requests', 'docuseal_template_id')) {
                    $table->dropColumn('docuseal_template_id');
                }
            });
        }

        // Remove DocuSeal columns from patient_ivr_status table
        if (Schema::hasTable('patient_ivr_status')) {
            Schema::table('patient_ivr_status', function (Blueprint $table) {
                if (Schema::hasColumn('patient_ivr_status', 'latest_docuseal_submission_id')) {
                    $table->dropColumn('latest_docuseal_submission_id');
                }
                if (Schema::hasColumn('patient_ivr_status', 'docuseal_submission_id')) {
                    $table->dropColumn('docuseal_submission_id');
                }
                if (Schema::hasColumn('patient_ivr_status', 'docuseal_status')) {
                    $table->dropColumn('docuseal_status');
                }
                if (Schema::hasColumn('patient_ivr_status', 'docuseal_completed_at')) {
                    $table->dropColumn('docuseal_completed_at');
                }
                if (Schema::hasColumn('patient_ivr_status', 'docuseal_audit_log_url')) {
                    $table->dropColumn('docuseal_audit_log_url');
                }
                if (Schema::hasColumn('patient_ivr_status', 'docuseal_signed_document_url')) {
                    $table->dropColumn('docuseal_signed_document_url');
                }
                if (Schema::hasColumn('patient_ivr_status', 'docuseal_template_id')) {
                    $table->dropColumn('docuseal_template_id');
                }
                if (Schema::hasColumn('patient_ivr_status', 'docuseal_last_synced_at')) {
                    $table->dropColumn('docuseal_last_synced_at');
                }
            });
        }

        // Remove DocuSeal columns from orders table if they exist
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'docuseal_submission_id')) {
                    $table->dropColumn('docuseal_submission_id');
                }
                if (Schema::hasColumn('orders', 'docuseal_status')) {
                    $table->dropColumn('docuseal_status');
                }
                if (Schema::hasColumn('orders', 'docuseal_audit_log_url')) {
                    $table->dropColumn('docuseal_audit_log_url');
                }
                if (Schema::hasColumn('orders', 'docuseal_last_synced_at')) {
                    $table->dropColumn('docuseal_last_synced_at');
                }
            });
        }

        // Remove DocuSeal template ID from pdf_field_metadata if it exists
        if (Schema::hasTable('pdf_field_metadata')) {
            Schema::table('pdf_field_metadata', function (Blueprint $table) {
                if (Schema::hasColumn('pdf_field_metadata', 'docuseal_template_id')) {
                    $table->dropColumn('docuseal_template_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add DocuSeal columns to patient_manufacturer_ivr_episodes table
        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            $table->string('docuseal_submission_id')->nullable();
            $table->text('docuseal_submission_url')->nullable();
            $table->string('docuseal_status')->nullable();
            $table->timestamp('docuseal_completed_at')->nullable();
        });

        // Re-add DocuSeal columns to product_requests table
        Schema::table('product_requests', function (Blueprint $table) {
            $table->string('docuseal_submission_id')->nullable();
            $table->string('docuseal_template_id')->nullable();
        });

        // Re-add DocuSeal columns to patient_ivr_status table
        Schema::table('patient_ivr_status', function (Blueprint $table) {
            $table->string('latest_docuseal_submission_id')->nullable();
            $table->string('docuseal_submission_id')->nullable();
            $table->string('docuseal_status')->nullable();
            $table->timestamp('docuseal_completed_at')->nullable();
            $table->string('docuseal_audit_log_url')->nullable();
            $table->string('docuseal_signed_document_url')->nullable();
            $table->string('docuseal_template_id')->nullable();
            $table->timestamp('docuseal_last_synced_at')->nullable();
        });

        // Re-add DocuSeal columns to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->string('docuseal_submission_id')->nullable();
            $table->string('docuseal_status')->nullable();
            $table->string('docuseal_audit_log_url')->nullable();
            $table->timestamp('docuseal_last_synced_at')->nullable();
        });

        // Re-add DocuSeal template ID to pdf_field_metadata
        Schema::table('pdf_field_metadata', function (Blueprint $table) {
            $table->string('docuseal_template_id')->index();
        });
    }
};
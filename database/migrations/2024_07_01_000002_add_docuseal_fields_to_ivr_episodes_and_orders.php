<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'docuseal_submission_id')) {
                $table->string('docuseal_submission_id')->nullable();
            }
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'docuseal_status')) {
                $table->string('docuseal_status')->nullable();
            }
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'docuseal_audit_log_url')) {
                $table->string('docuseal_audit_log_url')->nullable();
            }
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'docuseal_signed_document_url')) {
                $table->string('docuseal_signed_document_url')->nullable();
            }
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'docuseal_template_id')) {
                $table->string('docuseal_template_id')->nullable();
            }
            if (!Schema::hasColumn('patient_manufacturer_ivr_episodes', 'docuseal_last_synced_at')) {
                $table->timestamp('docuseal_last_synced_at')->nullable();
            }
        });
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'docuseal_submission_id')) {
                $table->string('docuseal_submission_id')->nullable();
            }
            if (!Schema::hasColumn('orders', 'docuseal_status')) {
                $table->string('docuseal_status')->nullable();
            }
            if (!Schema::hasColumn('orders', 'docuseal_audit_log_url')) {
                $table->string('docuseal_audit_log_url')->nullable();
            }
            if (!Schema::hasColumn('orders', 'docuseal_signed_document_url')) {
                $table->string('docuseal_signed_document_url')->nullable();
            }
            if (!Schema::hasColumn('orders', 'docuseal_template_id')) {
                $table->string('docuseal_template_id')->nullable();
            }
            if (!Schema::hasColumn('orders', 'docuseal_last_synced_at')) {
                $table->timestamp('docuseal_last_synced_at')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            $table->dropColumn([
                'docuseal_submission_id',
                'docuseal_status',
                'docuseal_audit_log_url',
                'docuseal_signed_document_url',
                'docuseal_template_id',
                'docuseal_last_synced_at',
            ]);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'docuseal_submission_id',
                'docuseal_status',
                'docuseal_audit_log_url',
                'docuseal_signed_document_url',
                'docuseal_template_id',
                'docuseal_last_synced_at',
            ]);
        });
    }
};

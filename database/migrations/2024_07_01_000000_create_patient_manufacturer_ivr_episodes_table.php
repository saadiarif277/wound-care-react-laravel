<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('patient_manufacturer_ivr_episodes')) {
            Schema::create('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('patient_id');
            $table->uuid('manufacturer_id');
            $table->string('status')->default('ready_for_review'); // ready_for_review, ivr_sent, ivr_verified, sent_to_manufacturer, tracking_added, completed
            $table->string('ivr_status')->nullable(); // pending, verified, expired
            $table->date('verification_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->integer('frequency_days')->default(90);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            // DocuSeal integration fields
            $table->string('docuseal_submission_id')->nullable();
            $table->string('docuseal_status')->nullable();
            $table->timestamp('docuseal_completed_at')->nullable();

            // Foreign keys (commented out until referenced tables exist)
            // $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
            // $table->foreign('manufacturer_id')->references('id')->on('manufacturers')->onDelete('cascade');

            // Indexes for performance (with shorter names)
            $table->index(['status'], 'pm_episodes_status_idx');
            $table->index(['ivr_status'], 'pm_episodes_ivr_status_idx');
            $table->index(['patient_id', 'manufacturer_id'], 'pm_episodes_patient_mfg_idx');
            $table->index(['verification_date'], 'pm_episodes_verification_idx');
            $table->index(['expiration_date'], 'pm_episodes_expiration_idx');
        });
        }
    }

    public function down()
    {
        Schema::dropIfExists('patient_manufacturer_ivr_episodes');
    }
};

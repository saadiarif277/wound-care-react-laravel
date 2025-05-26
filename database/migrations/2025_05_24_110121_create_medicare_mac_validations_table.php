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
        Schema::create('medicare_mac_validations', function (Blueprint $table) {
            $table->id();
            $table->uuid('validation_id')->unique();

            // Related entities
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('patient_fhir_id')->nullable(); // FHIR patient identifier
            $table->foreignId('facility_id')->constrained('facilities')->onDelete('cascade');

            // Medicare MAC Information
            $table->string('mac_contractor')->nullable(); // e.g., 'CGS', 'Novitas', 'WPS', etc.
            $table->string('mac_jurisdiction')->nullable(); // MAC J1, J5, J6, etc.
            $table->string('mac_region')->nullable(); // Geographic region

            // Validation Details
            $table->enum('validation_type', ['vascular_wound_care', 'wound_care_only', 'vascular_only'])->default('wound_care_only');
            $table->enum('validation_status', ['pending', 'validated', 'failed', 'requires_review', 'revalidated'])->default('pending');
            $table->json('validation_results')->nullable(); // Detailed validation results

            // Coverage Validation
            $table->json('coverage_policies')->nullable(); // LCDs, NCDs applied
            $table->boolean('coverage_met')->default(false);
            $table->text('coverage_notes')->nullable();
            $table->json('coverage_requirements')->nullable(); // Required documentation, etc.

            // Procedure/Service Validation
            $table->json('procedures_validated')->nullable(); // List of procedures checked
            $table->json('cpt_codes_validated')->nullable(); // CPT codes validated
            $table->json('hcpcs_codes_validated')->nullable(); // HCPCS codes validated
            $table->json('icd10_codes_validated')->nullable(); // ICD-10 codes validated

            // Documentation Validation
            $table->boolean('documentation_complete')->default(false);
            $table->json('required_documentation')->nullable(); // List of required docs
            $table->json('missing_documentation')->nullable(); // List of missing docs
            $table->json('documentation_status')->nullable(); // Status of each required doc

            // Frequency/Medical Necessity
            $table->boolean('frequency_compliant')->default(false);
            $table->text('frequency_notes')->nullable();
            $table->boolean('medical_necessity_met')->default(false);
            $table->text('medical_necessity_notes')->nullable();

            // Prior Authorization
            $table->boolean('prior_auth_required')->default(false);
            $table->boolean('prior_auth_obtained')->default(false);
            $table->string('prior_auth_number')->nullable();
            $table->date('prior_auth_expiry')->nullable();

            // Billing/Claims Validation
            $table->boolean('billing_compliant')->default(false);
            $table->json('billing_issues')->nullable(); // Any billing compliance issues
            $table->decimal('estimated_reimbursement', 10, 2)->nullable();
            $table->enum('reimbursement_risk', ['low', 'medium', 'high'])->default('medium');

            // Validation Timestamps
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('last_revalidated_at')->nullable();
            $table->timestamp('next_validation_due')->nullable();

            // Validation Metadata
            $table->string('validated_by')->nullable(); // User who performed validation
            $table->string('validation_source')->default('system'); // system, manual, external_api
            $table->json('validation_errors')->nullable(); // Any validation errors encountered
            $table->json('validation_warnings')->nullable(); // Any validation warnings

            // Audit and Tracking
            $table->boolean('daily_monitoring_enabled')->default(false);
            $table->timestamp('last_monitored_at')->nullable();
            $table->integer('validation_count')->default(1);
            $table->json('audit_trail')->nullable(); // Track changes and revalidations

            $table->timestamps();

            // Provider/Specialty Information
            $table->string('provider_specialty')->nullable(); // e.g., 'vascular_surgery', 'interventional_radiology', 'cardiology'
            $table->string('provider_npi')->nullable(); // Provider NPI for validation
            $table->json('specialty_requirements')->nullable(); // Specialty-specific requirements

            // Indexes for performance
            $table->index(['order_id', 'validation_status']);
            $table->index(['facility_id', 'validation_type']);
            $table->index(['mac_contractor', 'mac_jurisdiction']);
            $table->index(['validation_status', 'validated_at']);
            $table->index(['next_validation_due']);
            $table->index(['daily_monitoring_enabled', 'last_monitored_at'], 'mmv_daily_mon_last_mon_idx');
            $table->index(['provider_specialty', 'validation_type'], 'mmv_prov_spec_valtype_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicare_mac_validations');
    }
};

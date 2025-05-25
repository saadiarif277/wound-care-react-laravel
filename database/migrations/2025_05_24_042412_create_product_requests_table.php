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
        Schema::create('product_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('provider_id')->constrained('users')->onDelete('cascade');

            // PHI Handling - Only FHIR reference stored
            $table->string('patient_fhir_id')->index(); // Reference to Azure HDS Patient resource

            // Non-PHI patient identifiers for UI display
            $table->string('patient_initials', 4)->nullable(); // e.g., "JoSm" for John Smith
            $table->tinyInteger('patient_dob_month')->nullable(); // 1-12 for age context
            $table->integer('patient_dob_year')->nullable(); // For age calculation

            // Facility and payer information
            $table->foreignId('facility_id')->nullable()->constrained('facilities')->onDelete('set null');
            $table->string('payer_name_submitted')->nullable();
            $table->string('payer_id')->nullable();

            // Order details
            $table->date('expected_service_date');
            $table->enum('wound_type', ['DFU', 'VLU', 'PU', 'TW', 'AU', 'OTHER']); // Updated wound types

            // Clinical data (stored as FHIR references)
            $table->string('azure_order_checklist_fhir_id')->nullable(); // Reference to clinical data in Azure HDS
            $table->json('clinical_summary')->nullable(); // Non-PHI summary for UI

            // Engine results
            $table->json('mac_validation_results')->nullable();
            $table->enum('mac_validation_status', ['not_checked', 'pending', 'passed', 'warning', 'failed'])->default('not_checked');

            $table->json('eligibility_results')->nullable();
            $table->enum('eligibility_status', ['not_checked', 'pending', 'eligible', 'not_eligible', 'needs_review'])->default('not_checked');

            $table->enum('pre_auth_required_determination', ['pending_determination', 'required', 'not_required', 'unknown'])->default('pending_determination');

            $table->json('clinical_opportunities')->nullable();

            // Order management
            $table->enum('order_status', ['draft', 'submitted', 'processing', 'approved', 'rejected', 'shipped', 'delivered', 'cancelled'])->default('draft');
            $table->integer('step')->default(1); // 1-6 for the workflow steps
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('total_order_value', 10, 2)->default(0);

            // Sales tracking
            $table->foreignId('acquiring_rep_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['provider_id', 'order_status']);
            $table->index(['facility_id', 'expected_service_date']);
            $table->index('request_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_requests');
    }
};

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
            $table->bigIncrements('id');
            $table->string('request_number')->unique();
            $table->unsignedBigInteger('provider_id');
            $table->foreign('provider_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            $table->string('patient_fhir_id'); // Reference to PHI in Azure FHIR
            $table->string('patient_display_id', 7)->nullable(); // Sequential display ID (e.g., "JoSm001")
            $table->unsignedBigInteger('facility_id');
            $table->foreign('facility_id')
                  ->references('id')
                  ->on('facilities')
                  ->onDelete('restrict');
            $table->string('payer_name_submitted');
            $table->string('payer_id')->nullable();
            $table->date('expected_service_date');
            $table->enum('wound_type', ['DFU', 'VLU', 'PU', 'TW', 'AU', 'OTHER']);
            $table->string('azure_order_checklist_fhir_id')->nullable(); // Reference to clinical data in Azure
            $table->json('clinical_summary')->nullable();
            $table->json('mac_validation_results')->nullable();
            $table->string('mac_validation_status')->nullable();
            $table->json('eligibility_results')->nullable();
            $table->string('eligibility_status')->nullable();
            $table->enum('pre_auth_required_determination', ['required', 'not_required', 'pending'])->nullable();
            $table->json('clinical_opportunities')->nullable();
            $table->enum('order_status', ['draft', 'submitted', 'processing', 'approved', 'rejected', 'shipped', 'delivered', 'cancelled'])->default('draft');
            $table->integer('step')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->decimal('total_order_value', 10, 2)->nullable();
            $table->unsignedBigInteger('acquiring_rep_id')->nullable();
            $table->foreign('acquiring_rep_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index('provider_id');
            $table->index('facility_id');
            $table->index('order_status');
            $table->index('patient_display_id');
            $table->index(['facility_id', 'patient_display_id']);
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

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
        Schema::create('fhir_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->index(); // insurance_verification, coverage_update, etc
            $table->string('event_subtype')->index(); // card_scan, ivr_completed, eligibility_check
            $table->foreignId('user_id')->nullable()->constrained();
            $table->json('fhir_resource'); // Complete FHIR AuditEvent resource
            $table->json('entities'); // Quick reference to involved entities
            $table->json('details')->nullable(); // Additional metadata
            $table->timestamp('recorded_at')->index();
            $table->string('azure_fhir_id')->nullable(); // Reference to FHIR server copy
            $table->timestamps();
            
            // Composite indexes for common queries
            $table->index(['event_type', 'event_subtype', 'recorded_at']);
            $table->index(['recorded_at', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fhir_audit_logs');
    }
};

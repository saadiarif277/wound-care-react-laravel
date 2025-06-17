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
        Schema::create('phi_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_id'); // Can be numeric ID or 'system'
            $table->string('action'); // CREATE, READ, UPDATE, DELETE, EXPORT, UNAUTHORIZED_ACCESS_ATTEMPT
            $table->string('resource_type'); // Patient, Order, ClinicalData, etc.
            $table->string('resource_id'); // FHIR ID or other resource identifier
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata'); // Full audit details
            $table->timestamp('created_at');
            
            // Indexes for efficient querying
            $table->index('user_id');
            $table->index('resource_type');
            $table->index('resource_id');
            $table->index('action');
            $table->index('created_at');
            $table->index(['resource_type', 'resource_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phi_audit_logs');
    }
};
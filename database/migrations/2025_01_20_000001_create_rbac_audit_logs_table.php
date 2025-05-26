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
        Schema::create('rbac_audit_logs', function (Blueprint $table) {
            $table->id();

            // Event identification
            $table->string('event_type'); // role_created, role_updated, role_deleted, permission_assigned, etc.
            $table->string('entity_type'); // role, permission, user_role_assignment
            $table->unsignedBigInteger('entity_id')->nullable(); // ID of the affected entity
            $table->string('entity_name')->nullable(); // Name of the affected entity for reference

            // User who performed the action
            $table->unsignedInteger('performed_by')->nullable();
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('set null');
            $table->string('performed_by_name')->nullable(); // Store name for audit trail even if user deleted

            // Target user (for user role assignments)
            $table->unsignedInteger('target_user_id')->nullable();
            $table->foreign('target_user_id')->references('id')->on('users')->onDelete('set null');
            $table->string('target_user_email')->nullable();

            // Change details
            $table->json('old_values')->nullable(); // Previous state
            $table->json('new_values')->nullable(); // New state
            $table->json('changes')->nullable(); // Specific changes made
            $table->text('reason')->nullable(); // Reason for the change

            // Request context
            $table->string('ip_address', 45)->nullable(); // IPv4 or IPv6
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();

            // Risk assessment
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->json('risk_factors')->nullable(); // Factors that contributed to risk level

            // Additional metadata
            $table->json('metadata')->nullable(); // Additional context data
            $table->boolean('requires_review')->default(false); // Flag for manual review
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['event_type', 'created_at']);
            $table->index(['performed_by', 'created_at']);
            $table->index(['target_user_id', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['risk_level', 'requires_review']);
            $table->index('ip_address');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rbac_audit_logs');
    }
};

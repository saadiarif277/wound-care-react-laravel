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
        Schema::create('profile_audit_log', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Entity information
            $table->enum('entity_type', [
                'provider_profile',
                'provider_credential',
                'organization_profile',
                'facility_profile',
                'user_account'
            ])->comment('Type of entity that was modified');
            $table->uuid('entity_id')->comment('ID of the entity that was modified');
            $table->string('entity_display_name')->nullable()->comment('Human-readable name of the entity');

            // User and context information
            $table->unsignedInteger('user_id')->comment('User who performed the action');
            $table->string('user_email')->nullable()->comment('Email of user at time of action');
            $table->string('user_role')->nullable()->comment('Role of user at time of action');

            // Action information
            $table->enum('action_type', [
                'create',
                'update',
                'delete',
                'verify',
                'approve',
                'reject',
                'suspend',
                'restore',
                'export',
                'view_sensitive'
            ])->comment('Type of action performed');
            $table->string('action_description')->nullable()->comment('Human-readable description of the action');

            // Change tracking
            $table->jsonb('field_changes')->nullable()->comment('Before and after values for changed fields');
            $table->jsonb('metadata')->default('{}')->comment('Additional context about the change');
            $table->text('reason')->nullable()->comment('Reason provided for the change');
            $table->text('notes')->nullable()->comment('Additional notes about the change');

            // Request context
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('request_id')->nullable()->comment('Unique request identifier for tracking');
            $table->string('session_id')->nullable()->comment('User session identifier');

            // Compliance and security
            $table->boolean('is_sensitive_data')->default(false)->comment('Whether this change involved sensitive/PHI data');
            $table->enum('compliance_category', [
                'administrative',
                'clinical',
                'financial',
                'security',
                'phi_access',
                'credential_verification'
            ])->default('administrative');
            $table->boolean('requires_approval')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('approved_by')->nullable();

            // Timestamps
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            // Performance indexes
            $table->index('entity_type');
            $table->index('entity_id');
            $table->index('action_type');
            $table->index('user_id');
            $table->index('compliance_category');
            $table->index('is_sensitive_data');
            $table->index(['entity_type', 'entity_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['entity_type', 'action_type']);
            $table->index(['created_at', 'compliance_category']);

            // Composite indexes for common queries
            $table->index(['entity_type', 'entity_id', 'created_at']);
            $table->index(['user_id', 'action_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_audit_log');
    }
};

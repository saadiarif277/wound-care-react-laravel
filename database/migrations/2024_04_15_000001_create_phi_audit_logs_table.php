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
        // PHI audit logs table
        Schema::create('phi_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_email', 255)->nullable();
            $table->string('action', 100);
            $table->string('resource_type', 50);
            $table->string('resource_id', 255);
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('session_id', 255)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('accessed_at');
            $table->timestamps();
            
            // Indexes for performance
            $table->index('user_id', 'idx_phi_audit_user');
            $table->index('resource_type', 'idx_phi_audit_resource_type');
            $table->index(['resource_type', 'resource_id'], 'idx_phi_audit_resource');
            $table->index('action', 'idx_phi_audit_action');
            $table->index('accessed_at', 'idx_phi_audit_accessed_at');
            $table->index(['user_id', 'accessed_at'], 'idx_phi_audit_user_accessed');
            $table->index('ip_address', 'idx_phi_audit_ip');
        });

        // Archive table for HIPAA 6-year retention
        Schema::create('phi_audit_logs_archive', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('user_email', 255)->nullable();
            $table->string('action', 100);
            $table->string('resource_type', 50);
            $table->string('resource_id', 255);
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('session_id', 255)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('accessed_at');
            $table->timestamps();
            $table->timestamp('archived_at')->useCurrent();
            
            // Partitioning by year for better performance
            $table->index('accessed_at', 'idx_phi_audit_archive_accessed_at');
            $table->index('archived_at', 'idx_phi_audit_archive_archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phi_audit_logs_archive');
        Schema::dropIfExists('phi_audit_logs');
    }
};
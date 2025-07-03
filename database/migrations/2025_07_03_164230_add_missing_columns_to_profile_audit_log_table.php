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
        Schema::table('profile_audit_log', function (Blueprint $table) {
            // Add missing columns that the ProfileAuditLog model expects
            $table->text('reason')->nullable()->after('metadata');
            $table->text('notes')->nullable()->after('reason');
            $table->string('ip_address', 45)->nullable()->after('notes');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->string('request_id')->nullable()->after('user_agent');
            $table->string('session_id')->nullable()->after('request_id');
            $table->boolean('is_sensitive_data')->default(false)->after('session_id');
            $table->string('compliance_category')->default('administrative')->after('is_sensitive_data');
            $table->boolean('requires_approval')->default(false)->after('compliance_category');
            $table->timestamp('approved_at')->nullable()->after('requires_approval');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            
            // Add foreign key for approved_by
            $table->foreign('approved_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
                  
            // Add indexes for better performance
            $table->index('compliance_category');
            $table->index('requires_approval');
            $table->index('is_sensitive_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profile_audit_log', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['approved_by']);
            
            // Drop indexes
            $table->dropIndex(['compliance_category']);
            $table->dropIndex(['requires_approval']);
            $table->dropIndex(['is_sensitive_data']);
            
            // Drop columns
            $table->dropColumn([
                'reason',
                'notes',
                'ip_address',
                'user_agent',
                'request_id',
                'session_id',
                'is_sensitive_data',
                'compliance_category',
                'requires_approval',
                'approved_at',
                'approved_by'
            ]);
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_requests', function (Blueprint $table) {
            // IVR Document Management
            $table->boolean('ivr_required')->default(true)->after('pre_auth_denied_at');
            $table->string('ivr_bypass_reason')->nullable()->after('ivr_required');
            $table->timestamp('ivr_bypassed_at')->nullable()->after('ivr_bypass_reason');
            $table->unsignedBigInteger('ivr_bypassed_by')->nullable()->after('ivr_bypassed_at');
            
            // DocuSeal Integration
            $table->string('docuseal_submission_id')->nullable()->after('ivr_bypassed_by');
            $table->string('docuseal_template_id')->nullable()->after('docuseal_submission_id');
            $table->timestamp('ivr_sent_at')->nullable()->after('docuseal_template_id');
            $table->timestamp('ivr_signed_at')->nullable()->after('ivr_sent_at');
            $table->string('ivr_document_url')->nullable()->after('ivr_signed_at');
            
            // Manufacturer Approval
            $table->timestamp('manufacturer_sent_at')->nullable()->after('ivr_document_url');
            $table->unsignedBigInteger('manufacturer_sent_by')->nullable()->after('manufacturer_sent_at');
            $table->boolean('manufacturer_approved')->default(false)->after('manufacturer_sent_by');
            $table->timestamp('manufacturer_approved_at')->nullable()->after('manufacturer_approved');
            $table->string('manufacturer_approval_reference')->nullable()->after('manufacturer_approved_at');
            $table->text('manufacturer_notes')->nullable()->after('manufacturer_approval_reference');
            
            // Order Fulfillment (for when it becomes a fulfilled order)
            $table->string('order_number')->nullable()->unique()->after('manufacturer_notes');
            $table->timestamp('order_submitted_at')->nullable()->after('order_number');
            $table->string('manufacturer_order_id')->nullable()->after('order_submitted_at');
            $table->string('tracking_number')->nullable()->after('manufacturer_order_id');
            $table->timestamp('shipped_at')->nullable()->after('tracking_number');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            
            // Add indexes for performance
            $table->index('ivr_required');
            $table->index('docuseal_submission_id');
            $table->index('manufacturer_approved');
            $table->index('order_number');
            
            // Add foreign keys
            $table->foreign('ivr_bypassed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('manufacturer_sent_by')->references('id')->on('users')->nullOnDelete();
        });
        
        // Update the order_status enum to include new statuses
        DB::statement("ALTER TABLE product_requests MODIFY COLUMN order_status ENUM(
            'draft',
            'submitted',
            'processing',
            'pending_ivr',
            'ivr_sent',
            'ivr_confirmed',
            'approved',
            'sent_back',
            'denied',
            'submitted_to_manufacturer',
            'shipped',
            'delivered',
            'cancelled'
        ) DEFAULT 'draft'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the enum back to original values
        DB::statement("ALTER TABLE product_requests MODIFY COLUMN order_status ENUM(
            'draft',
            'submitted',
            'processing',
            'approved',
            'rejected',
            'shipped',
            'delivered',
            'cancelled'
        ) DEFAULT 'draft'");
        
        Schema::table('product_requests', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['ivr_bypassed_by']);
            $table->dropForeign(['manufacturer_sent_by']);
            
            // Drop indexes
            $table->dropIndex(['ivr_required']);
            $table->dropIndex(['docuseal_submission_id']);
            $table->dropIndex(['manufacturer_approved']);
            $table->dropIndex(['order_number']);
            
            // Drop columns
            $table->dropColumn([
                'ivr_required',
                'ivr_bypass_reason',
                'ivr_bypassed_at',
                'ivr_bypassed_by',
                'docuseal_submission_id',
                'docuseal_template_id',
                'ivr_sent_at',
                'ivr_signed_at',
                'ivr_document_url',
                'manufacturer_sent_at',
                'manufacturer_sent_by',
                'manufacturer_approved',
                'manufacturer_approved_at',
                'manufacturer_approval_reference',
                'manufacturer_notes',
                'order_number',
                'order_submitted_at',
                'manufacturer_order_id',
                'tracking_number',
                'shipped_at',
                'delivered_at'
            ]);
        });
    }
};
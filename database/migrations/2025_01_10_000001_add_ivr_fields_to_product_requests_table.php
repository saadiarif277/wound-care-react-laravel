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
            // Only add columns that don't already exist
            $columns = Schema::getColumnListing('product_requests');

            // IVR Document Management
            if (!in_array('ivr_required', $columns)) {
                $table->boolean('ivr_required')->default(true)->after('pre_auth_denied_at');
            }
            if (!in_array('ivr_bypass_reason', $columns)) {
                $table->string('ivr_bypass_reason')->nullable()->after('ivr_required');
            }
            if (!in_array('ivr_bypassed_at', $columns)) {
                $table->timestamp('ivr_bypassed_at')->nullable()->after('ivr_bypass_reason');
            }
            if (!in_array('ivr_bypassed_by', $columns)) {
                $table->unsignedBigInteger('ivr_bypassed_by')->nullable()->after('ivr_bypassed_at');
            }

            // DocuSeal Integration
            if (!in_array('docuseal_submission_id', $columns)) {
                $table->string('docuseal_submission_id')->nullable()->after('ivr_bypassed_by');
            }
            if (!in_array('docuseal_template_id', $columns)) {
                $table->string('docuseal_template_id')->nullable()->after('docuseal_submission_id');
            }
            if (!in_array('ivr_sent_at', $columns)) {
                $table->timestamp('ivr_sent_at')->nullable()->after('docuseal_template_id');
            }
            if (!in_array('ivr_signed_at', $columns)) {
                $table->timestamp('ivr_signed_at')->nullable()->after('ivr_sent_at');
            }
            if (!in_array('ivr_document_url', $columns)) {
                $table->string('ivr_document_url')->nullable()->after('ivr_signed_at');
            }

            // Manufacturer Approval
            if (!in_array('manufacturer_sent_at', $columns)) {
                $table->timestamp('manufacturer_sent_at')->nullable()->after('ivr_document_url');
            }
            if (!in_array('manufacturer_sent_by', $columns)) {
                $table->unsignedBigInteger('manufacturer_sent_by')->nullable()->after('manufacturer_sent_at');
            }
            if (!in_array('manufacturer_approved', $columns)) {
                $table->boolean('manufacturer_approved')->default(false)->after('manufacturer_sent_by');
            }
            if (!in_array('manufacturer_approved_at', $columns)) {
                $table->timestamp('manufacturer_approved_at')->nullable()->after('manufacturer_approved');
            }
            if (!in_array('manufacturer_approval_reference', $columns)) {
                $table->string('manufacturer_approval_reference')->nullable()->after('manufacturer_approved_at');
            }
            if (!in_array('manufacturer_notes', $columns)) {
                $table->text('manufacturer_notes')->nullable()->after('manufacturer_approval_reference');
            }

            // Order Fulfillment (for when it becomes a fulfilled order)
            if (!in_array('order_number', $columns)) {
                $table->string('order_number')->nullable()->unique()->after('manufacturer_notes');
            }
            if (!in_array('order_submitted_at', $columns)) {
                $table->timestamp('order_submitted_at')->nullable()->after('order_number');
            }
            if (!in_array('manufacturer_order_id', $columns)) {
                $table->string('manufacturer_order_id')->nullable()->after('order_submitted_at');
            }
            if (!in_array('tracking_number', $columns)) {
                $table->string('tracking_number')->nullable()->after('manufacturer_order_id');
            }
            if (!in_array('shipped_at', $columns)) {
                $table->timestamp('shipped_at')->nullable()->after('tracking_number');
            }
            if (!in_array('delivered_at', $columns)) {
                $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            }

            // Add indexes for performance only if they don't exist
            $indexExists = function($table, $column) {
                try {
                    $indexes = DB::select("SHOW INDEX FROM $table WHERE Column_name = ?", [$column]);
                    return !empty($indexes);
                } catch (\Exception $e) {
                    return false;
                }
            };

            if (!$indexExists('product_requests', 'ivr_required')) {
                $table->index('ivr_required');
            }

            if (!$indexExists('product_requests', 'docuseal_submission_id')) {
                $table->index('docuseal_submission_id');
            }

            if (!$indexExists('product_requests', 'manufacturer_approved')) {
                $table->index('manufacturer_approved');
            }

            if (!$indexExists('product_requests', 'order_number')) {
                $table->index('order_number');
            }
        });

                // Add foreign keys separately only if they don't exist
        $foreignKeyExists = function($table, $column) {
            try {
                $constraints = DB::select("
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND COLUMN_NAME = ?
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ", [$table, $column]);
                return !empty($constraints);
            } catch (\Exception $e) {
                return false;
            }
        };

        Schema::table('product_requests', function (Blueprint $table) use ($foreignKeyExists) {
            // Add foreign keys only if they don't exist
            if (Schema::hasColumn('product_requests', 'ivr_bypassed_by') && !$foreignKeyExists('product_requests', 'ivr_bypassed_by')) {
                $table->foreign('ivr_bypassed_by')->references('id')->on('users')->nullOnDelete();
            }

            if (Schema::hasColumn('product_requests', 'manufacturer_sent_by') && !$foreignKeyExists('product_requests', 'manufacturer_sent_by')) {
                $table->foreign('manufacturer_sent_by')->references('id')->on('users')->nullOnDelete();
            }
        });

        // Update the order_status enum to include new statuses
        if (DB::connection()->getDriverName() !== 'sqlite') {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
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
        }

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

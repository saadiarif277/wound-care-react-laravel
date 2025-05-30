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
        Schema::table('product_requests', function (Blueprint $table) {
            // Add pre-authorization status tracking fields
            if (!Schema::hasColumn('product_requests', 'pre_auth_status')) {
                $table->string('pre_auth_status')->nullable()->after('pre_auth_required_determination');
            }
            if (!Schema::hasColumn('product_requests', 'pre_auth_submitted_at')) {
                $table->timestamp('pre_auth_submitted_at')->nullable()->after('pre_auth_status');
            }
            if (!Schema::hasColumn('product_requests', 'pre_auth_approved_at')) {
                $table->timestamp('pre_auth_approved_at')->nullable()->after('pre_auth_submitted_at');
            }
            if (!Schema::hasColumn('product_requests', 'pre_auth_denied_at')) {
                $table->timestamp('pre_auth_denied_at')->nullable()->after('pre_auth_approved_at');
            }

            // Add index for pre_auth_status for faster queries
            $indexName = 'product_requests_pre_auth_status_index';
            if (!Schema::hasIndex('product_requests', $indexName)) {
                $table->index('pre_auth_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_requests', function (Blueprint $table) {
            // Check if index exists before dropping
            $indexName = 'product_requests_pre_auth_status_index';
            if (Schema::hasIndex('product_requests', $indexName)) {
                $table->dropIndex(['pre_auth_status']);
            }

            $columnsToDrop = [];
            if (Schema::hasColumn('product_requests', 'pre_auth_status')) $columnsToDrop[] = 'pre_auth_status';
            if (Schema::hasColumn('product_requests', 'pre_auth_submitted_at')) $columnsToDrop[] = 'pre_auth_submitted_at';
            if (Schema::hasColumn('product_requests', 'pre_auth_approved_at')) $columnsToDrop[] = 'pre_auth_approved_at';
            if (Schema::hasColumn('product_requests', 'pre_auth_denied_at')) $columnsToDrop[] = 'pre_auth_denied_at';

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};

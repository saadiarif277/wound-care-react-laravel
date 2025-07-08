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
            if (!Schema::hasColumn('product_requests', 'notes')) {
                $table->text('notes')->nullable()->after('order_status');
            }
            if (!Schema::hasColumn('product_requests', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('product_requests', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('product_requests', 'carrier')) {
                $table->string('carrier')->nullable()->after('cancellation_reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_requests', function (Blueprint $table) {
            $columns = ['notes', 'rejection_reason', 'cancellation_reason', 'carrier'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('product_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

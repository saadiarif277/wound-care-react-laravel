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
        Schema::table('orders', function (Blueprint $table) {
            // Add JSON columns for structured data
            if (!Schema::hasColumn('orders', 'details')) {
                $table->json('details')->nullable();
            }
            if (!Schema::hasColumn('orders', 'pricing')) {
                $table->json('pricing')->nullable();
            }
            // Add submission tracking
            if (!Schema::hasColumn('orders', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable();
                $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
            }
            // Add delivery tracking
            if (!Schema::hasColumn('orders', 'expected_delivery_date')) {
                $table->date('expected_delivery_date')->nullable();
            }
            if (!Schema::hasColumn('orders', 'tracking_number')) {
                $table->string('tracking_number')->nullable();
            }
            // Add indexes for performance
            if (Schema::hasColumn('orders', 'submitted_at')) {
                $table->index('submitted_at');
            }
            if (Schema::hasColumn('orders', 'expected_delivery_date')) {
                $table->index('expected_delivery_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
            $table->dropColumn([
                'details',
                'pricing',
                'submitted_by',
                'expected_delivery_date',
                'tracking_number'
            ]);
        });
    }
};

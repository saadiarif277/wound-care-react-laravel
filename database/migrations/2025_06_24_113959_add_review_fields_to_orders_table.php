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
            $table->json('details')->nullable()->after('type');
            $table->json('pricing')->nullable()->after('details');
            
            // Add submission tracking
            $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('users');
            
            // Add delivery tracking
            $table->date('expected_delivery_date')->nullable()->after('submitted_to_manufacturer_at');
            $table->string('tracking_number')->nullable()->after('expected_delivery_date');
            
            // Add indexes for performance
            $table->index('submitted_at');
            $table->index('expected_delivery_date');
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
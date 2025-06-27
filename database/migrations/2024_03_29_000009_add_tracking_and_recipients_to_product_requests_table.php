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
            // Check if columns don't exist before adding them
            if (!Schema::hasColumn('product_requests', 'tracking_number')) {
                $table->string('tracking_number')->nullable();
            }
            if (!Schema::hasColumn('product_requests', 'tracking_carrier')) {
                $table->string('tracking_carrier')->nullable();
            }
            if (!Schema::hasColumn('product_requests', 'shipped_at')) {
                $table->timestamp('shipped_at')->nullable();
            }
            if (!Schema::hasColumn('product_requests', 'manufacturer_recipients')) {
                $table->json('manufacturer_recipients')->nullable()
                    ->comment('Email recipients when order was sent to manufacturer');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_requests', function (Blueprint $table) {
            $table->dropColumn([
                'tracking_number',
                'tracking_carrier',
                'shipped_at',
                'manufacturer_recipients',
            ]);
        });
    }
};

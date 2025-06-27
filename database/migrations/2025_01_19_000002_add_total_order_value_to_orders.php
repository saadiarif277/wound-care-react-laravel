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
        // Add total_order_value as a virtual column that mirrors total_amount
        // This maintains backward compatibility with existing code
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'total_order_value')) {
                $table->decimal('total_order_value', 10, 2)
                    ->default(0)
                    ->after('total_amount')
                    ->comment('Alias for total_amount - maintained for compatibility');
            }
        });

        // Copy existing total_amount values to total_order_value
        DB::statement('UPDATE orders SET total_order_value = total_amount');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('total_order_value');
        });
    }
};
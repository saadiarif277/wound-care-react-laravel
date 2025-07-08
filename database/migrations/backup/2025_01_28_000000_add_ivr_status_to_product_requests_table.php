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
            if (!Schema::hasColumn('product_requests', 'ivr_status')) {
                $table->string('ivr_status', 50)->nullable()->after('order_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_requests', function (Blueprint $table) {
            if (Schema::hasColumn('product_requests', 'ivr_status')) {
                $table->dropColumn('ivr_status');
            }
        });
    }
};

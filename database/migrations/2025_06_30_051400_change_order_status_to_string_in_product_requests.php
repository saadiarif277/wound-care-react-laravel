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
        // Change order_status from enum to string
        Schema::table('product_requests', function (Blueprint $table) {
            $table->string('order_status')->default('draft')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Change back to enum (if needed in future)
        Schema::table('product_requests', function (Blueprint $table) {
            $table->enum('order_status', ['draft', 'submitted', 'processing', 'approved', 'rejected', 'shipped', 'delivered', 'cancelled'])->default('draft')->change();
        });
    }
};

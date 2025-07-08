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
        // Update any foreign key references from patient_ivr_statuses to patient_manufacturer_ivr_episodes
        
        // Update orders table if it has ivr_status_id column
        if (Schema::hasColumn('orders', 'ivr_status_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['ivr_status_id']);
                $table->dropColumn('ivr_status_id');
            });
        }
        
        // Update product_requests table if it has ivr_status_id column
        if (Schema::hasColumn('product_requests', 'ivr_status_id')) {
            Schema::table('product_requests', function (Blueprint $table) {
                $table->dropForeign(['ivr_status_id']);
                $table->dropColumn('ivr_status_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a one-way migration
    }
};

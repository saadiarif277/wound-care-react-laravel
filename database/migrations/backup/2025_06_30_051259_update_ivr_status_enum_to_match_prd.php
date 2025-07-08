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
        // Update patient_manufacturer_ivr_episodes table
        Schema::table('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
            // First, update any existing values that need to be mapped
            DB::table('patient_manufacturer_ivr_episodes')
                ->where('ivr_status', 'provider_completed')
                ->update(['ivr_status' => 'pending']);
            
            DB::table('patient_manufacturer_ivr_episodes')
                ->where('ivr_status', 'admin_reviewed')
                ->update(['ivr_status' => 'sent']);
            
            // Note: 'verified' and 'expired' remain the same
            
            // Since we're using string type instead of enum, we just need to ensure
            // the values are updated to match PRD requirements:
            // N/A, Pending, Sent, Verified, Rejected
            
            // Add 'N/A' and 'Rejected' as new valid statuses
            // No schema change needed since it's a string column
        });
        
        // Update any other tables that might have ivr_status
        if (Schema::hasTable('product_requests') && Schema::hasColumn('product_requests', 'ivr_status')) {
            DB::table('product_requests')
                ->where('ivr_status', 'expired')
                ->update(['ivr_status' => 'rejected']);
        }
        
        if (Schema::hasTable('orders')) {
            DB::table('orders')
                ->whereNotNull('ivr_generation_status')
                ->where('ivr_generation_status', 'expired')
                ->update(['ivr_generation_status' => 'rejected']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the mappings
        DB::table('patient_manufacturer_ivr_episodes')
            ->where('ivr_status', 'pending')
            ->where('created_at', '<', '2025-06-30') // Only revert old records
            ->update(['ivr_status' => 'provider_completed']);
        
        DB::table('patient_manufacturer_ivr_episodes')
            ->where('ivr_status', 'sent')
            ->where('created_at', '<', '2025-06-30')
            ->update(['ivr_status' => 'admin_reviewed']);
        
        DB::table('patient_manufacturer_ivr_episodes')
            ->where('ivr_status', 'rejected')
            ->update(['ivr_status' => 'expired']);
            
        if (Schema::hasTable('product_requests') && Schema::hasColumn('product_requests', 'ivr_status')) {
            DB::table('product_requests')
                ->where('ivr_status', 'rejected')
                ->update(['ivr_status' => 'expired']);
        }
        
        if (Schema::hasTable('orders')) {
            DB::table('orders')
                ->where('ivr_generation_status', 'rejected')
                ->update(['ivr_generation_status' => 'expired']);
        }
    }
};

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
        // Update order statuses to match PRD requirements
        // PRD statuses: Pending, Submitted to Manufacturer, Confirmed by Manufacturer, Rejected, Canceled
        
        if (Schema::hasTable('orders')) {
            // Map old statuses to new ones
            DB::table('orders')
                ->where('order_status', 'Pending IVR')
                ->update(['order_status' => 'Pending']);
                
            DB::table('orders')
                ->where('order_status', 'IVR Sent')
                ->update(['order_status' => 'Pending']);
                
            DB::table('orders')
                ->where('order_status', 'IVR Verified')
                ->update(['order_status' => 'Pending']);
                
            DB::table('orders')
                ->where('order_status', 'Approved')
                ->update(['order_status' => 'Submitted to Manufacturer']);
                
            DB::table('orders')
                ->where('order_status', 'Send Back')
                ->update(['order_status' => 'Rejected']);
                
            DB::table('orders')
                ->where('order_status', 'Denied')
                ->update(['order_status' => 'Rejected']);
                
            DB::table('orders')
                ->where('order_status', 'Confirmed & Shipped')
                ->update(['order_status' => 'Confirmed by Manufacturer']);
        }
        
        if (Schema::hasTable('product_requests')) {
            // Map old statuses to new ones for product_requests
            DB::table('product_requests')
                ->whereIn('order_status', ['draft', 'pending_review', 'ivr_pending'])
                ->update(['order_status' => 'pending']);
                
            DB::table('product_requests')
                ->whereIn('order_status', ['approved', 'manufacturer_sent'])
                ->update(['order_status' => 'submitted_to_manufacturer']);
                
            DB::table('product_requests')
                ->whereIn('order_status', ['manufacturer_approved', 'shipped', 'delivered'])
                ->update(['order_status' => 'confirmed_by_manufacturer']);
                
            DB::table('product_requests')
                ->whereIn('order_status', ['rejected', 'send_back'])
                ->update(['order_status' => 'rejected']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a data migration, reversal would be complex
        // and potentially data-losing, so we'll leave it empty
        // The old status values are being consolidated into new ones
    }
};

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
        // Add new columns for IVR and order management
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_status')->after('status')->nullable();
            $table->boolean('action_required')->default(false)->after('order_status');
            $table->string('ivr_generation_status')->nullable()->after('action_required');
            $table->string('ivr_skip_reason')->nullable()->after('ivr_generation_status');
            $table->timestamp('ivr_generated_at')->nullable()->after('ivr_skip_reason');
            $table->timestamp('ivr_sent_at')->nullable()->after('ivr_generated_at');
            $table->timestamp('ivr_confirmed_at')->nullable()->after('ivr_sent_at');
            $table->timestamp('approved_at')->nullable()->after('ivr_confirmed_at');
            $table->timestamp('denied_at')->nullable()->after('approved_at');
            $table->timestamp('sent_back_at')->nullable()->after('denied_at');
            $table->timestamp('submitted_to_manufacturer_at')->nullable()->after('sent_back_at');
            $table->text('denial_reason')->nullable()->after('submitted_to_manufacturer_at');
            $table->text('send_back_notes')->nullable()->after('denial_reason');
            $table->text('approval_notes')->nullable()->after('send_back_notes');
            
            // Add manufacturer relation
            $table->unsignedBigInteger('manufacturer_id')->nullable()->after('facility_id');
            
            // Add provider relation
            $table->unsignedBigInteger('provider_id')->nullable()->after('facility_id');
            
            // Add patient display ID
            $table->string('patient_display_id', 7)->nullable()->after('patient_fhir_id');
            
            // Index for better performance
            $table->index('order_status');
            $table->index('action_required');
            $table->index('patient_display_id');
        });
        
        // Migrate existing status to new order_status field
        DB::statement("UPDATE orders SET order_status = 
            CASE 
                WHEN status = 'pending' THEN 'pending_ivr'
                WHEN status = 'confirmed' THEN 'approved'
                WHEN status = 'shipped' THEN 'submitted_to_manufacturer'
                WHEN status = 'fulfilled' THEN 'submitted_to_manufacturer'
                WHEN status = 'cancelled' THEN 'denied'
                ELSE 'pending_ivr'
            END
        ");
        
        // Set action_required for pending orders
        DB::statement("UPDATE orders SET action_required = true WHERE order_status = 'pending_ivr'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_status',
                'action_required',
                'ivr_generation_status',
                'ivr_skip_reason',
                'ivr_generated_at',
                'ivr_sent_at',
                'ivr_confirmed_at',
                'approved_at',
                'denied_at',
                'sent_back_at',
                'submitted_to_manufacturer_at',
                'denial_reason',
                'send_back_notes',
                'approval_notes',
                'manufacturer_id',
                'provider_id',
                'patient_display_id'
            ]);
        });
    }
};
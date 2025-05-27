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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique();
            $table->string('patient_fhir_id'); // Reference to FHIR Patient resource - NO PHI stored here
            $table->uuid('facility_id');
            $table->uuid('sales_rep_id')->nullable();
            $table->date('date_of_service');
            $table->string('credit_terms')->default('net60');
            $table->enum('status', ['pending', 'confirmed', 'shipped', 'fulfilled', 'cancelled'])->default('pending');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('expected_reimbursement', 10, 2)->default(0);
            $table->date('expected_collection_date')->nullable();
            $table->string('payment_status')->default('pending');
            $table->decimal('msc_commission_structure', 5, 2)->default(40);
            $table->decimal('msc_commission', 10, 2)->default(0);
            $table->json('document_urls')->nullable(); // Non-PHI document URLs in Supabase Storage
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('facility_id')
                  ->references('id')
                  ->on('facilities')
                  ->onDelete('restrict');

            $table->foreign('sales_rep_id')
                  ->references('id')
                  ->on('msc_sales_reps')
                  ->onDelete('set null');

            $table->index('patient_fhir_id');
            $table->index('status');
            $table->index('date_of_service');
            $table->index('payment_status');
            $table->index('order_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

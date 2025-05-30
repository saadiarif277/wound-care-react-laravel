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
        Schema::create('pre_authorizations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_request_id');
            $table->string('authorization_number')->nullable();
            $table->string('payer_name');
            $table->string('patient_id');
            $table->text('clinical_documentation')->nullable();
            $table->enum('urgency', ['routine', 'urgent', 'emergency'])->default('routine');
            $table->enum('status', ['pending', 'submitted', 'approved', 'denied', 'cancelled'])->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->timestamp('last_status_check')->nullable();
            $table->string('payer_transaction_id')->nullable();
            $table->string('payer_confirmation')->nullable();
            $table->json('payer_response')->nullable(); // Keep this as it's the raw API response
            $table->timestamp('estimated_approval_date')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('product_request_id')
                  ->references('id')
                  ->on('product_requests')
                  ->onDelete('cascade');

            $table->foreign('submitted_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            // Indexes for performance
            $table->index('product_request_id');
            $table->index('status');
            $table->index('authorization_number');
            $table->index('payer_transaction_id');
            $table->index('submitted_at');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pre_authorizations');
    }
};

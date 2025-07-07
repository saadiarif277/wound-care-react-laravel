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
        Schema::create('manufacturer_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('order_id'); // Reference to order
            $table->string('manufacturer_id'); // Which manufacturer this is for
            $table->string('manufacturer_name'); // Cache manufacturer name
            $table->string('token', 64)->unique(); // Unique token for email links
            $table->enum('status', ['pending', 'approved', 'denied', 'expired'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('responded_at')->nullable();
            $table->text('response_notes')->nullable(); // Optional notes from manufacturer
            $table->ipAddress('response_ip')->nullable(); // Track who responded
            $table->text('response_user_agent')->nullable(); // Browser info for security
            $table->string('email_message_id')->nullable(); // Azure Communication message ID
            $table->json('email_recipients')->nullable(); // Who received the email
            $table->text('pdf_url')->nullable(); // URL to the IVR PDF
            $table->string('pdf_filename')->nullable(); // Original PDF filename
            $table->json('order_details')->nullable(); // Cache order info for email
            $table->boolean('notification_sent')->default(false); // Admin notification sent
            $table->json('metadata')->nullable(); // Additional tracking data
            $table->timestamps();
            
            // Indexes
            $table->index(['order_id', 'status']);
            $table->index(['manufacturer_id', 'status']);
            $table->index(['token', 'expires_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manufacturer_submissions');
    }
};

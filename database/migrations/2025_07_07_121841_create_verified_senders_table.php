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
        Schema::create('verified_senders', function (Blueprint $table) {
            $table->id();
            $table->string('email_address')->unique();
            $table->string('display_name');
            $table->string('organization')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->enum('verification_method', ['azure_domain', 'smtp_relay', 'on_behalf'])->default('on_behalf');
            $table->text('verification_details')->nullable(); // JSON for storing verification info
            $table->string('azure_domain_verification_code')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('daily_limit')->nullable(); // Email sending limits
            $table->integer('monthly_limit')->nullable();
            $table->json('metadata')->nullable(); // Additional configuration
            $table->timestamps();
            
            // Indexes
            $table->index(['organization', 'is_verified']);
            $table->index(['verification_method', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verified_senders');
    }
};

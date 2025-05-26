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
        Schema::create('ecw_audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('patient_id', 100); // Patient ID from eCW or 'multiple' for searches
            $table->string('action', 50); // Action performed (read, search, etc.)
            $table->unsignedInteger('user_id')->nullable(); // User performing action
            $table->json('metadata')->nullable(); // Additional context (search params, etc.)
            $table->string('ip_address', 45)->nullable(); // IPv4 or IPv6 address
            $table->text('user_agent')->nullable(); // Browser/client info
            $table->timestamp('created_at'); // When action occurred

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            // Indexes for audit queries
            $table->index('patient_id');
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
            $table->index(['patient_id', 'action']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecw_audit_log');
    }
};

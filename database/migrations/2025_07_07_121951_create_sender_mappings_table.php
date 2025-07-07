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
        Schema::create('sender_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('manufacturer_id')->nullable();
            $table->enum('document_type', ['ivr', 'order', 'general', 'notification', 'reminder'])->nullable();
            $table->string('organization')->nullable();
            $table->foreignId('sender_id')->constrained('verified_senders')->onDelete('cascade');
            $table->integer('priority')->default(0); // Higher priority = preferred sender
            $table->boolean('is_active')->default(true);
            $table->json('conditions')->nullable(); // Additional matching conditions
            $table->timestamps();
            
            // Indexes
            $table->index(['manufacturer_id', 'document_type', 'is_active']);
            $table->index(['organization', 'document_type', 'is_active']);
            $table->index(['priority', 'is_active']);
            
            // Unique constraint to prevent duplicate mappings
            $table->unique(['manufacturer_id', 'document_type', 'organization', 'sender_id'], 'unique_sender_mapping');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sender_mappings');
    }
};

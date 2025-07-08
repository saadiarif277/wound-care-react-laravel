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
        Schema::create('order_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('type', 50); // ivr_form, order_form, wound_photo, clinical_notes, etc.
            $table->string('name');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size'); // File size in bytes
            $table->foreignId('uploaded_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable(); // For storing additional document-specific data
            $table->timestamps();
            
            $table->index(['order_id', 'type']);
            $table->index('completed_at');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_documents');
    }
};
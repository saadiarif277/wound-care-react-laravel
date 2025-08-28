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
        // Check if the documents table already exists
        if (!Schema::hasTable('documents')) {
            Schema::create('documents', function (Blueprint $table) {
                $table->id();
                $table->string('filename'); // Stored filename
                $table->string('original_name'); // Original filename
                $table->string('path'); // Storage path
                $table->string('url'); // Public URL
                $table->bigInteger('size'); // File size in bytes
                $table->string('mime_type'); // MIME type
                $table->string('extension'); // File extension
                $table->string('document_type')->default('other'); // Type of document
                $table->morphs('documentable'); // Polymorphic relationship
                $table->foreignId('uploaded_by_user_id')->constrained('users')->onDelete('cascade');
                $table->text('notes')->nullable(); // Additional notes
                $table->json('metadata')->nullable(); // Additional metadata
                $table->timestamps();

                // Indexes
                $table->index(['documentable_type', 'documentable_id']);
                $table->index('document_type');
                $table->index('uploaded_by_user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};

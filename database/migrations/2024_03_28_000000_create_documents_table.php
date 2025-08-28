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
        } else {
            // Table exists, check and add missing columns/indexes
            Schema::table('documents', function (Blueprint $table) {
                // Add missing columns if they don't exist
                if (!Schema::hasColumn('documents', 'filename')) {
                    $table->string('filename')->after('id');
                }
                if (!Schema::hasColumn('documents', 'original_name')) {
                    $table->string('original_name')->after('filename');
                }
                if (!Schema::hasColumn('documents', 'path')) {
                    $table->string('path')->after('original_name');
                }
                if (!Schema::hasColumn('documents', 'url')) {
                    $table->string('url')->after('path');
                }
                if (!Schema::hasColumn('documents', 'size')) {
                    $table->bigInteger('size')->after('url');
                }
                if (!Schema::hasColumn('documents', 'mime_type')) {
                    $table->string('mime_type')->after('size');
                }
                if (!Schema::hasColumn('documents', 'extension')) {
                    $table->string('extension')->after('mime_type');
                }
                if (!Schema::hasColumn('documents', 'document_type')) {
                    $table->string('document_type')->default('other')->after('extension');
                }
                if (!Schema::hasColumn('documents', 'documentable_type')) {
                    $table->string('documentable_type')->after('document_type');
                }
                if (!Schema::hasColumn('documents', 'documentable_id')) {
                    $table->unsignedBigInteger('documentable_id')->after('documentable_type');
                }
                if (!Schema::hasColumn('documents', 'uploaded_by_user_id')) {
                    $table->foreignId('uploaded_by_user_id')->after('documentable_id');
                }
                if (!Schema::hasColumn('documents', 'notes')) {
                    $table->text('notes')->nullable()->after('uploaded_by_user_id');
                }
                if (!Schema::hasColumn('documents', 'metadata')) {
                    $table->json('metadata')->nullable()->after('notes');
                }
            });

            // Add indexes only if they don't exist
            try {
                DB::statement('ALTER TABLE documents ADD INDEX documents_documentable_type_documentable_id_index (documentable_type, documentable_id)');
            } catch (Exception $e) {
                // Index already exists, continue
            }

            try {
                DB::statement('ALTER TABLE documents ADD INDEX documents_document_type_index (document_type)');
            } catch (Exception $e) {
                // Index already exists, continue
            }

            try {
                DB::statement('ALTER TABLE documents ADD INDEX documents_uploaded_by_user_id_index (uploaded_by_user_id)');
            } catch (Exception $e) {
                // Index already exists, continue
            }

            // Add foreign key if it doesn't exist
            try {
                DB::statement('ALTER TABLE documents ADD CONSTRAINT documents_uploaded_by_user_id_foreign FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE CASCADE');
            } catch (Exception $e) {
                // Foreign key already exists, continue
            }
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

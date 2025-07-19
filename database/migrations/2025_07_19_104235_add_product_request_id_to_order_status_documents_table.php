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
        Schema::table('order_status_documents', function (Blueprint $table) {
            $table->foreignId('product_request_id')->constrained('product_requests')->onDelete('cascade');
            $table->string('document_type')->default('order_related_doc'); // 'ivr_doc' or 'order_related_doc'
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_url')->nullable();
            $table->string('mime_type')->nullable();
            $table->integer('file_size')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->string('status_type')->nullable(); // 'ivr' or 'order'
            $table->string('status_value')->nullable(); // The status value when uploaded
            $table->text('notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_status_documents', function (Blueprint $table) {
            $table->dropForeign(['product_request_id']);
            $table->dropForeign(['uploaded_by']);
            $table->dropColumn([
                'product_request_id',
                'document_type',
                'file_name',
                'file_path',
                'file_url',
                'mime_type',
                'file_size',
                'uploaded_by',
                'status_type',
                'status_value',
                'notes'
            ]);
        });
    }
};

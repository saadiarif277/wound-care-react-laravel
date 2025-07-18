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
        Schema::table('product_requests', function (Blueprint $table) {
            // Add fields for storing uploaded IVR files
            $table->string('altered_ivr_file_path')->nullable()->after('ivr_document_url');
            $table->timestamp('altered_ivr_uploaded_at')->nullable()->after('altered_ivr_file_path');
            $table->unsignedBigInteger('altered_ivr_uploaded_by')->nullable()->after('altered_ivr_uploaded_at');

            // Add fields for storing uploaded order form files
            $table->string('altered_order_form_file_path')->nullable()->after('altered_ivr_uploaded_by');
            $table->timestamp('altered_order_form_uploaded_at')->nullable()->after('altered_order_form_file_path');
            $table->unsignedBigInteger('altered_order_form_uploaded_by')->nullable()->after('altered_order_form_uploaded_at');

            // Add foreign key constraints
            $table->foreign('altered_ivr_uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('altered_order_form_uploaded_by')->references('id')->on('users')->nullOnDelete();

            // Add indexes for better performance
            $table->index('altered_ivr_file_path');
            $table->index('altered_order_form_file_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_requests', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['altered_ivr_uploaded_by']);
            $table->dropForeign(['altered_order_form_uploaded_by']);

            // Drop indexes
            $table->dropIndex(['altered_ivr_file_path']);
            $table->dropIndex(['altered_order_form_file_path']);

            // Drop columns
            $table->dropColumn([
                'altered_ivr_file_path',
                'altered_ivr_uploaded_at',
                'altered_ivr_uploaded_by',
                'altered_order_form_file_path',
                'altered_order_form_uploaded_at',
                'altered_order_form_uploaded_by'
            ]);
        });
    }
};

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
        if (!Schema::hasTable('docuseal_submissions')) {
            Schema::create('docuseal_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('order_id');
            $table->string('docuseal_submission_id')->unique();
            $table->string('docuseal_template_id');
            $table->string('document_type');
            $table->string('status')->default('pending');
            $table->string('folder_id'); // Manufacturer folder ID
            $table->string('document_url')->nullable();
            $table->string('signing_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            // Foreign key constraints
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');

            // Indexes
            $table->index('order_id');
            $table->index('status');
            $table->index('document_type');
            $table->index('docuseal_submission_id');
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('docuseal_submissions');
    }
};

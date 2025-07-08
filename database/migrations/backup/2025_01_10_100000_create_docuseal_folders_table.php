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
        if (!Schema::hasTable('docuseal_folders')) {
            Schema::create('docuseal_folders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('manufacturer_id');
            $table->string('docuseal_folder_id')->unique();
            $table->string('folder_name');
            $table->string('delivery_endpoint')->nullable(); // Manufacturer's API endpoint
            $table->text('delivery_credentials_encrypted')->nullable(); // Encrypted credentials
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Foreign key constraints - commented out until manufacturers table exists
            // $table->foreign('manufacturer_id')->references('id')->on('manufacturers')->onDelete('cascade');

            // Indexes
            $table->index('manufacturer_id');
            $table->index('is_active');
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('docuseal_folders');
    }
};

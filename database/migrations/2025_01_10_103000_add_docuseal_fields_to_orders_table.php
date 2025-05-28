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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('docuseal_generation_status')->default('not_started');
            $table->string('docuseal_folder_id')->nullable();
            $table->string('manufacturer_delivery_status')->default('pending');
            $table->timestamp('documents_generated_at')->nullable();
            $table->json('docuseal_metadata')->nullable(); // Store DocuSeal-related metadata
            
            // Add indexes for performance
            $table->index('docuseal_generation_status');
            $table->index('manufacturer_delivery_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['docuseal_generation_status']);
            $table->dropIndex(['manufacturer_delivery_status']);
            $table->dropColumn([
                'docuseal_generation_status',
                'docuseal_folder_id',
                'manufacturer_delivery_status',
                'documents_generated_at',
                'docuseal_metadata'
            ]);
        });
    }
}; 
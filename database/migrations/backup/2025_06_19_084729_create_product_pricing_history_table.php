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
        Schema::create('product_pricing_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('q_code', 10)->index();
            $table->string('product_name');

            // Pricing data
            $table->decimal('national_asp', 10, 2)->nullable();
            $table->decimal('price_per_sq_cm', 10, 2)->nullable();
            $table->decimal('msc_price', 10, 2)->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable();

            // MUE data
            $table->integer('mue')->nullable();

            // Change tracking
            $table->string('change_type')->index(); // 'cms_sync', 'manual_update', 'initial_load'
            $table->string('changed_by_type')->nullable(); // 'system', 'user', 'cms_api'
            $table->unsignedBigInteger('changed_by_id')->nullable(); // user_id if manual
            $table->json('changed_fields')->nullable(); // Array of fields that changed
            $table->json('previous_values')->nullable(); // Previous values for changed fields
            $table->text('change_reason')->nullable(); // Reason for change

            // Metadata
            $table->timestamp('effective_date'); // When this pricing became effective
            $table->timestamp('cms_sync_date')->nullable(); // When CMS data was synced
            $table->string('source')->default('manual'); // 'cms', 'manual', 'import'
            $table->json('metadata')->nullable(); // Additional context data

            $table->timestamps();

            // Foreign key constraints
            $table->foreign('product_id')->references('id')->on('msc_products')->onDelete('cascade');
            $table->foreign('changed_by_id')->references('id')->on('users')->onDelete('set null');

            // Indexes for performance
            $table->index(['product_id', 'effective_date']);
            $table->index(['q_code', 'effective_date']);
            $table->index(['change_type', 'effective_date']);
            $table->index('cms_sync_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_pricing_history');
    }
};

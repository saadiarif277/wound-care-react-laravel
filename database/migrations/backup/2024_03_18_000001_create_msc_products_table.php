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
        Schema::create('msc_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('manufacturer')->nullable();
            $table->uuid('manufacturer_id')->nullable();
            $table->string('category')->nullable();
            $table->uuid('category_id')->nullable();
            $table->decimal('national_asp', 10, 2)->nullable();
            $table->decimal('price_per_sq_cm', 10, 4)->nullable();
            $table->string('q_code', 10)->nullable();
            $table->json('available_sizes')->nullable(); // Store as JSON array
            $table->string('graph_type')->nullable();
            $table->string('image_url')->nullable(); // Supabase Storage URL
            $table->json('document_urls')->nullable(); // Array of Supabase Storage URLs
            $table->boolean('is_active')->default(true);
            $table->decimal('commission_rate', 5, 2)->nullable(); // Default commission rate
            $table->timestamps();
            $table->softDeletes();

            // Add indexes for performance
            $table->index(['manufacturer_id', 'category_id']);
            $table->index('is_active');
            $table->index('sku');
            $table->index('category');
            $table->index('graph_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msc_products');
    }
};

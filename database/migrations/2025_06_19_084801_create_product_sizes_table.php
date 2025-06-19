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
        Schema::create('product_sizes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');

            // Size identification
            $table->string('size_label'); // "2x4cm", "16mm disc", "1.5x1.5cm", etc.
            $table->string('size_type')->default('rectangular'); // 'rectangular', 'circular', 'square', 'custom'

            // Dimensions in standardized units (mm)
            $table->decimal('length_mm', 8, 2)->nullable(); // Length in millimeters
            $table->decimal('width_mm', 8, 2)->nullable(); // Width in millimeters
            $table->decimal('diameter_mm', 8, 2)->nullable(); // Diameter for circular products
            $table->decimal('area_cm2', 8, 2)->nullable(); // Calculated area in cm²

            // Display and ordering
            $table->string('display_label'); // Human-readable label for UI
            $table->integer('sort_order')->default(0); // For consistent ordering
            $table->boolean('is_active')->default(true);

            // Pricing (if size-specific pricing exists)
            $table->decimal('size_specific_price', 10, 2)->nullable();
            $table->decimal('price_per_unit', 10, 2)->nullable();

            // Inventory and availability
            $table->string('sku_suffix')->nullable(); // Size-specific SKU suffix
            $table->boolean('is_available')->default(true);
            $table->text('availability_notes')->nullable();

            $table->timestamps();

            // Foreign key constraints
            $table->foreign('product_id')->references('id')->on('msc_products')->onDelete('cascade');

            // Indexes
            $table->index(['product_id', 'is_active']);
            $table->index(['product_id', 'sort_order']);
            $table->index('size_type');
            $table->index('area_cm2');

            // Unique constraint to prevent duplicate sizes per product
            $table->unique(['product_id', 'size_label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sizes');
    }
};

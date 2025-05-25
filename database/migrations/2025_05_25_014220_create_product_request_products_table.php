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
        Schema::create('product_request_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_request_id')->constrained('product_requests')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('msc_products');
            $table->integer('quantity');
            $table->string('size')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();

            // Composite index for better query performance
            $table->index(['product_request_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_request_products');
    }
};

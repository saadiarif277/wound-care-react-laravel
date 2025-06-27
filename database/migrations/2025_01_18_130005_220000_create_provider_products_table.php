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
        if (!Schema::hasTable('provider_products')) {
            Schema::create('provider_products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('product_id');
                $table->timestamp('onboarded_at')->nullable();
                $table->enum('onboarding_status', ['active', 'pending', 'suspended', 'expired'])->default('active');
                $table->date('expiration_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('product_id')->references('id')->on('msc_products')->onDelete('cascade');

                $table->unique(['user_id', 'product_id']);
                $table->index('onboarding_status');
                $table->index('expiration_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_products');
    }
};

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
        Schema::create('order_action_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_request_id')
                ->constrained('product_requests')
                ->onDelete('cascade');
            $table->string('action', 50); // sent_to_manufacturer, tracking_added, etc.
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // For additional data
            $table->timestamps();
            
            $table->index(['product_request_id', 'created_at']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_action_history');
    }
};
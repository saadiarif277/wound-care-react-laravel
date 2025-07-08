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
        Schema::create('model_predictions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('model_id');
            $table->json('input_data');
            $table->json('prediction');
            $table->decimal('confidence', 5, 4)->default(0.0);
            $table->boolean('actual_outcome')->nullable();
            $table->json('user_feedback')->nullable();
            $table->timestamp('feedback_received_at')->nullable();
            $table->integer('execution_time_ms')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('model_id')->references('id')->on('ml_models')->onDelete('cascade');

            // Indexes
            $table->index(['model_id', 'created_at']);
            $table->index(['model_id', 'actual_outcome']);
            $table->index(['confidence', 'actual_outcome']);
            $table->index(['created_at', 'actual_outcome']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_predictions');
    }
}; 
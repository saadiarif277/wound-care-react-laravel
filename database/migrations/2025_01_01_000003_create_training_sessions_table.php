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
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('model_type')->index();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->integer('training_samples')->nullable();
            $table->integer('feature_count')->nullable();
            $table->decimal('data_quality_score', 5, 4)->nullable();
            $table->json('training_parameters')->nullable();
            $table->enum('status', ['pending', 'training', 'completed', 'failed'])->default('pending');
            $table->decimal('accuracy', 5, 4)->nullable();
            $table->decimal('validation_accuracy', 5, 4)->nullable();
            $table->decimal('loss', 8, 6)->nullable();
            $table->decimal('validation_loss', 8, 6)->nullable();
            $table->integer('epochs_completed')->nullable();
            $table->integer('training_time_seconds')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('model_id')->references('id')->on('ml_models')->onDelete('set null');

            // Indexes
            $table->index(['model_type', 'status']);
            $table->index(['model_type', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['model_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
}; 
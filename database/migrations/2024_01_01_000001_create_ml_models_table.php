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
        Schema::create('ml_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_type')->index();
            $table->string('model_name');
            $table->string('version');
            $table->enum('status', ['training', 'trained', 'active', 'inactive', 'failed'])->default('training');
            $table->decimal('accuracy', 5, 4)->nullable();
            $table->integer('training_samples')->nullable();
            $table->integer('feature_count')->nullable();
            $table->json('model_parameters')->nullable();
            $table->json('model_artifacts')->nullable();
            $table->json('performance_metrics')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('last_prediction_at')->nullable();
            $table->integer('total_predictions')->default(0);
            $table->timestamps();

            // Indexes
            $table->index(['model_type', 'status']);
            $table->index(['model_type', 'created_at']);
            $table->index(['status', 'accuracy']);
            $table->unique(['model_type', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ml_models');
    }
}; 
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
        Schema::create('behavioral_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('user_role')->index();
            $table->unsignedBigInteger('facility_id')->nullable()->index();
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->string('event_type')->index();
            $table->string('event_category')->index();
            $table->timestamp('timestamp')->index();
            $table->string('session_id')->index();
            $table->string('ip_hash');
            $table->string('user_agent_hash');
            $table->string('url_path');
            $table->string('http_method');
            $table->json('event_data')->nullable();
            $table->json('context')->nullable();
            $table->json('browser_info')->nullable();
            $table->json('performance_metrics')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            // Indexes for ML queries
            $table->index(['user_id', 'event_category', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index(['user_role', 'event_category']);
            $table->index(['facility_id', 'event_type']);
            
            // Composite index for common query patterns
            $table->index(['user_id', 'event_type', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('behavioral_events');
    }
}; 
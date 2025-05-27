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
        Schema::create('ecw_user_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->text('access_token'); // Encrypted access token
            $table->text('refresh_token')->nullable(); // Encrypted refresh token
            $table->string('token_type', 20)->default('Bearer');
            $table->text('scope')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            // Index for performance
            $table->index('user_id');
            $table->index(['user_id', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecw_user_tokens');
    }
};

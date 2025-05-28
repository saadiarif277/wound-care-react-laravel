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
        Schema::create('provider_invitations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('email')->index();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('invitation_token', 128)->unique();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('invited_by_user_id');
            $table->unsignedBigInteger('created_user_id')->nullable(); // Set when user accepts invitation
            $table->json('assigned_facilities')->nullable(); // Array of facility IDs
            $table->json('assigned_roles')->nullable(); // Array of role slugs
            $table->enum('status', ['pending', 'sent', 'opened', 'accepted', 'expired', 'cancelled'])->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations')
                  ->onDelete('cascade');

            $table->foreign('invited_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->foreign('created_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            // Additional indexes for performance
            $table->index(['organization_id', 'status']);
            $table->index(['expires_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_invitations');
    }
};

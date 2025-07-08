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
            $table->string('first_name')->nullable(); // Made nullable for org invitations
            $table->string('last_name')->nullable(); // Made nullable for org invitations
            $table->string('invitation_token', 128)->unique();
            $table->unsignedBigInteger('organization_id')->nullable(); // Nullable for new org invitations
            $table->unsignedBigInteger('invited_by_user_id')->nullable(); // Nullable for system invites
            $table->unsignedBigInteger('created_user_id')->nullable(); // Set when user accepts invitation

            // New fields for organization invitations
            $table->enum('invitation_type', ['provider', 'organization'])->default('provider')->index();
            $table->string('organization_name')->nullable(); // For new organization invitations
            $table->json('metadata')->nullable(); // For any additional data needed

            $table->json('assigned_facilities')->nullable(); // Array of facility IDs
            $table->json('assigned_roles')->nullable(); // Array of role slugs
            $table->enum('status', ['pending', 'sent', 'opened', 'accepted', 'expired', 'cancelled'])->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            // Foreign key constraints (all nullable for flexibility)
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
            $table->index(['invitation_type', 'status']);
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

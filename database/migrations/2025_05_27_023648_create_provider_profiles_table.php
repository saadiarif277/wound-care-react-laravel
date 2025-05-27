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
        // Create enum types
        Schema::create('provider_profiles', function (Blueprint $table) {
            $table->unsignedInteger('provider_id')->primary();
            $table->string('azure_provider_fhir_id')->nullable()->comment('Reference to Azure Health Data Services FHIR Patient resource');

            // Profile completion tracking
            $table->timestamp('last_profile_update')->nullable();
            $table->integer('profile_completion_percentage')->default(0);
            $table->enum('verification_status', [
                'pending',
                'documents_required',
                'under_review',
                'verification_in_progress',
                'verified',
                'rejected',
                'suspended'
            ])->default('pending');

            // Preferences (stored as JSON for flexibility)
            $table->jsonb('notification_preferences')->default('{}')->comment('Email, SMS, and system notification preferences');
            $table->jsonb('practice_preferences')->default('{}')->comment('Clinical preferences, templates, workflow settings');
            $table->jsonb('workflow_settings')->default('{}')->comment('Dashboard layout, quick actions, report defaults');

            // Professional information (non-PHI)
            $table->text('professional_bio')->nullable();
            $table->jsonb('specializations')->default('[]')->comment('List of medical specializations');
            $table->jsonb('languages_spoken')->default('[]')->comment('Languages spoken by provider');
            $table->string('professional_photo_path')->nullable()->comment('Path to professional photo');

            // System tracking
            $table->timestamp('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->boolean('two_factor_enabled')->default(false);

            // Audit fields
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('provider_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for performance
            $table->index('verification_status');
            $table->index('last_profile_update');
            $table->index('profile_completion_percentage');
            $table->index(['provider_id', 'verification_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_profiles');
    }
};

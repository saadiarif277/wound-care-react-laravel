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
        Schema::create('provider_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id');

            // Credential information
            $table->enum('credential_type', [
                'medical_license',
                'board_certification',
                'dea_registration',
                'npi_number',
                'hospital_privileges',
                'malpractice_insurance',
                'continuing_education',
                'state_license',
                'specialty_certification'
            ]);
            $table->string('credential_number');
            $table->string('credential_display_name')->nullable()->comment('User-friendly name for the credential');
            $table->string('issuing_authority');
            $table->string('issuing_state')->nullable()->comment('State that issued the credential');

            // Dates and validity
            $table->date('issue_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->date('effective_date')->nullable()->comment('Date credential becomes effective');

            // Verification and status
            $table->enum('verification_status', [
                'pending',
                'in_review',
                'verified',
                'expired',
                'rejected',
                'suspended',
                'revoked'
            ])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->uuid('verified_by')->nullable()->comment('User who verified the credential');
            $table->text('verification_notes')->nullable();

            // Document management
            $table->string('document_path')->nullable()->comment('Secure path to uploaded credential document');
            $table->string('document_type')->nullable()->comment('PDF, JPG, PNG, etc.');
            $table->integer('document_size')->nullable()->comment('File size in bytes');
            $table->string('document_hash')->nullable()->comment('SHA256 hash for integrity verification');

            // Renewal and monitoring
            $table->boolean('auto_renewal_enabled')->default(false);
            $table->jsonb('reminder_sent_dates')->default('[]')->comment('Dates when expiration reminders were sent');
            $table->integer('renewal_period_days')->nullable()->comment('How many days before expiration to send reminders');
            $table->date('next_reminder_date')->nullable();

            // Additional metadata
            $table->jsonb('credential_metadata')->default('{}')->comment('Additional credential-specific information');
            $table->text('notes')->nullable()->comment('Internal notes about the credential');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false)->comment('Primary credential of this type');

            // Audit fields
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('provider_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for performance
            $table->index('provider_id');
            $table->index('credential_type');
            $table->index('verification_status');
            $table->index('expiration_date');
            $table->index('next_reminder_date');
            $table->index(['provider_id', 'credential_type']);
            $table->index(['credential_type', 'verification_status']);
            $table->index(['expiration_date', 'is_active']);

            // Unique constraints
            $table->unique(
                ['provider_id', 'credential_type', 'credential_number'],
                'provider_cred_unique'
            );
            $table->unique(
                ['provider_id', 'credential_type', 'is_primary'],
                'provider_primary_cred_unique'
            )->where('is_primary', true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_credentials');
    }
};

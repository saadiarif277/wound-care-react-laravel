<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Organization onboarding tracking
        Schema::create('organization_onboarding', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('organization_id')->unique();
            $table->enum('status', [
                'initiated',
                'basic_info_complete',
                'billing_setup_complete',
                'facilities_added',
                'providers_invited',
                'training_scheduled',
                'go_live',
                'completed'
            ])->default('initiated');
            $table->json('completed_steps')->default('[]');
            $table->json('pending_items')->default('[]');
            $table->unsignedBigInteger('onboarding_manager_id')->nullable();
            $table->timestamp('initiated_at');
            $table->timestamp('target_go_live_date')->nullable();
            $table->timestamp('actual_go_live_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('onboarding_manager_id')->references('id')->on('users')->onDelete('set null');

            $table->index('status');
        });

        // Onboarding checklists
        if (!Schema::hasTable('onboarding_checklists')) {
            Schema::create('onboarding_checklists', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->morphs('entity'); // Can be used for organizations, facilities, or providers
                $table->string('checklist_type'); // 'organization', 'facility', 'provider'
                $table->json('items')->comment('Array of checklist items with status');
                $table->integer('total_items')->default(0);
                $table->integer('completed_items')->default(0);
                $table->decimal('completion_percentage', 5, 2)->default(0);
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamps();

                $table->index('checklist_type');
            });
        }

        // Onboarding documents
        Schema::create('onboarding_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->morphs('entity'); // Can be organization, facility, or provider
            $table->string('document_type'); // 'w9', 'license', 'insurance', etc.
            $table->string('document_name');
            $table->string('file_path');
            $table->string('file_size');
            $table->string('mime_type');
            $table->enum('status', [
                'uploaded',
                'under_review',
                'approved',
                'rejected',
                'expired'
            ])->default('uploaded');
            $table->unsignedBigInteger('uploaded_by');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->date('expiration_date')->nullable();
            $table->timestamps();

            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');

            $table->index('document_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_documents');
        Schema::dropIfExists('onboarding_checklists');
        Schema::dropIfExists('organization_onboarding');
    }
};

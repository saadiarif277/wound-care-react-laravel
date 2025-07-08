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
        Schema::table('provider_profiles', function (Blueprint $table) {
            // Add missing columns that were not properly added in the previous migration

            // Add verification_status column if it doesn't exist
            if (!Schema::hasColumn('provider_profiles', 'verification_status')) {
                $table->enum('verification_status', [
                    'pending',
                    'documents_required',
                    'under_review',
                    'verification_in_progress',
                    'verified',
                    'rejected',
                    'suspended'
                ])->default('pending')->after('specialty');
            }

            // Add profile_completion_percentage column if it doesn't exist
            if (!Schema::hasColumn('provider_profiles', 'profile_completion_percentage')) {
                $table->integer('profile_completion_percentage')->default(0)->after('verification_status');
            }

            // Add notification_preferences column if it doesn't exist
            if (!Schema::hasColumn('provider_profiles', 'notification_preferences')) {
                $table->json('notification_preferences')->nullable()->after('profile_completion_percentage');
            }

            // Add practice_preferences column if it doesn't exist
            if (!Schema::hasColumn('provider_profiles', 'practice_preferences')) {
                $table->json('practice_preferences')->nullable()->after('notification_preferences');
            }

            // Add workflow_settings column if it doesn't exist
            if (!Schema::hasColumn('provider_profiles', 'workflow_settings')) {
                $table->json('workflow_settings')->nullable()->after('practice_preferences');
            }

            // Add professional_bio column if it doesn't exist
            if (!Schema::hasColumn('provider_profiles', 'professional_bio')) {
                $table->text('professional_bio')->nullable()->after('workflow_settings');
            }

            // Add specializations column if it doesn't exist
            if (!Schema::hasColumn('provider_profiles', 'specializations')) {
                $table->json('specializations')->nullable()->after('professional_bio');
            }

            // Add languages_spoken column if it doesn't exist
            if (!Schema::hasColumn('provider_profiles', 'languages_spoken')) {
                $table->json('languages_spoken')->nullable()->after('specializations');
            }

            // Add last_profile_update column if it doesn't exist
            if (!Schema::hasColumn('provider_profiles', 'last_profile_update')) {
                $table->timestamp('last_profile_update')->nullable()->after('languages_spoken');
            }

            // Add professional_photo_path column if it doesn't exist
            if (!Schema::hasColumn('provider_profiles', 'professional_photo_path')) {
                $table->string('professional_photo_path')->nullable()->after('last_profile_update');
            }

            // Add last_login_at column if it doesn't exist
            if (!Schema::hasColumn('provider_profiles', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('professional_photo_path');
            }

            // Add last_login_ip column if it doesn't exist
            if (!Schema::hasColumn('provider_profiles', 'last_login_ip')) {
                $table->string('last_login_ip')->nullable()->after('last_login_at');
            }

            // Add password_changed_at column if it doesn't exist
            if (!Schema::hasColumn('provider_profiles', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('last_login_ip');
            }

            // Add two_factor_enabled column if it doesn't exist
            if (!Schema::hasColumn('provider_profiles', 'two_factor_enabled')) {
                $table->boolean('two_factor_enabled')->default(false)->after('password_changed_at');
            }

            // Add created_by column if it doesn't exist
            if (!Schema::hasColumn('provider_profiles', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('two_factor_enabled');
            }

            // Add updated_by column if it doesn't exist
            if (!Schema::hasColumn('provider_profiles', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable()->after('created_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            // Drop the added columns
            $columnsToCheck = [
                'verification_status',
                'profile_completion_percentage',
                'notification_preferences',
                'practice_preferences',
                'workflow_settings',
                'professional_bio',
                'specializations',
                'languages_spoken',
                'last_profile_update',
                'professional_photo_path',
                'last_login_at',
                'last_login_ip',
                'password_changed_at',
                'two_factor_enabled',
                'created_by',
                'updated_by'
            ];

            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('provider_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

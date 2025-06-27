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
            $table->enum('verification_status', [
                'pending',
                'documents_required',
                'under_review',
                'verification_in_progress',
                'verified',
                'rejected',
                'suspended'
            ])->default('pending')->after('specialty');
            $table->integer('profile_completion_percentage')->default(0)->after('verification_status');
            $table->text('professional_bio')->nullable()->after('profile_completion_percentage');
            $table->json('specializations')->nullable()->after('professional_bio');
            $table->json('languages_spoken')->nullable()->after('specializations');
            $table->timestamp('last_profile_update')->nullable()->after('languages_spoken');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'verification_status',
                'profile_completion_percentage',
                'professional_bio',
                'specializations',
                'languages_spoken',
                'last_profile_update',
            ]);
        });
    }
};

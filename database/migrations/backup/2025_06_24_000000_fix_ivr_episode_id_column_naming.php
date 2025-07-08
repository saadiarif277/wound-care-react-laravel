<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, check if we have the wrong column name and fix it
        if (Schema::hasColumn('product_requests', 'episode_id') && !Schema::hasColumn('product_requests', 'ivr_episode_id')) {
            // Drop existing foreign key and index for episode_id
            Schema::table('product_requests', function (Blueprint $table) {
                try {
                    $table->dropForeign(['episode_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, continue
                }
                try {
                    $table->dropIndex(['episode_id']);
                } catch (\Exception $e) {
                    // Index might not exist, continue
                }
            });

            // Rename the column
            Schema::table('product_requests', function (Blueprint $table) {
                $table->renameColumn('episode_id', 'ivr_episode_id');
            });

            // Add back the index and foreign key with correct name
            Schema::table('product_requests', function (Blueprint $table) {
                $table->index('ivr_episode_id');
                if (Schema::hasTable('patient_manufacturer_ivr_episodes')) {
                    $table->foreign('ivr_episode_id')->references('id')->on('patient_manufacturer_ivr_episodes')->nullOnDelete();
                }
            });
        }

        // If neither column exists, create ivr_episode_id
        if (!Schema::hasColumn('product_requests', 'ivr_episode_id') && !Schema::hasColumn('product_requests', 'episode_id')) {
            Schema::table('product_requests', function (Blueprint $table) {
                $table->uuid('ivr_episode_id')->nullable()->after('patient_display_id');
                $table->index('ivr_episode_id');
                if (Schema::hasTable('patient_manufacturer_ivr_episodes')) {
                    $table->foreign('ivr_episode_id')->references('id')->on('patient_manufacturer_ivr_episodes')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a fix migration, so we don't reverse it
        // The original migration should handle the rollback
    }
};

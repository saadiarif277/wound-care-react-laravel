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
        Schema::table('product_requests', function (Blueprint $table) {
            // Add ivr_episode_id column if it doesn't exist
            if (!Schema::hasColumn('product_requests', 'ivr_episode_id')) {
                $table->uuid('ivr_episode_id')->nullable()->after('patient_display_id');
                $table->index('ivr_episode_id');
            }
        });

        // Add foreign key constraint if it doesn't exist
        $foreignKeyExists = function($table, $column) {
            try {
                $constraints = DB::select("
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND COLUMN_NAME = ?
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ", [$table, $column]);
                return !empty($constraints);
            } catch (\Exception $e) {
                return false;
            }
        };

        Schema::table('product_requests', function (Blueprint $table) use ($foreignKeyExists) {
            if (Schema::hasColumn('product_requests', 'ivr_episode_id') &&
                Schema::hasTable('patient_manufacturer_ivr_episodes') &&
                !$foreignKeyExists('product_requests', 'ivr_episode_id')) {
                $table->foreign('ivr_episode_id')->references('id')->on('patient_manufacturer_ivr_episodes')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_requests', function (Blueprint $table) {
            // Drop foreign key first
            if (Schema::hasColumn('product_requests', 'ivr_episode_id')) {
                $table->dropForeign(['ivr_episode_id']);
                $table->dropIndex(['ivr_episode_id']);
                $table->dropColumn('ivr_episode_id');
            }
        });
    }
};

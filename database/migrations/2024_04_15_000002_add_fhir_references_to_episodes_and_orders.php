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
        // Add FHIR references to episodes table
        Schema::table('episodes', function (Blueprint $table) {
            if (!Schema::hasColumn('episodes', 'episode_of_care_fhir_id')) {
                $table->string('episode_of_care_fhir_id', 255)->nullable()->after('organization_fhir_id');
                $table->index('episode_of_care_fhir_id', 'idx_episodes_episode_of_care_fhir');
            }
            
            if (!Schema::hasColumn('episodes', 'condition_fhir_id')) {
                $table->string('condition_fhir_id', 255)->nullable()->after('episode_of_care_fhir_id');
                $table->index('condition_fhir_id', 'idx_episodes_condition_fhir');
            }
            
            if (!Schema::hasColumn('episodes', 'encounter_fhir_id')) {
                $table->string('encounter_fhir_id', 255)->nullable()->after('condition_fhir_id');
                $table->index('encounter_fhir_id', 'idx_episodes_encounter_fhir');
            }
            
            if (!Schema::hasColumn('episodes', 'task_fhir_id')) {
                $table->string('task_fhir_id', 255)->nullable()->after('encounter_fhir_id');
                $table->index('task_fhir_id', 'idx_episodes_task_fhir');
            }
            
            if (!Schema::hasColumn('episodes', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable();
            }
            
            if (!Schema::hasColumn('episodes', 'reviewed_by')) {
                $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            }
            
            if (!Schema::hasColumn('episodes', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
            
            if (!Schema::hasColumn('episodes', 'manufacturer_response')) {
                $table->json('manufacturer_response')->nullable();
            }
        });

        // Add FHIR references to orders table
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'fhir_device_request_id')) {
                $table->string('fhir_device_request_id', 255)->nullable()->after('details');
                $table->index('fhir_device_request_id', 'idx_orders_device_request_fhir');
            }
            
            if (!Schema::hasColumn('orders', 'status')) {
                $table->enum('status', [
                    'pending',
                    'approved',
                    'rejected',
                    'processing',
                    'shipped',
                    'delivered',
                    'cancelled'
                ])->default('pending')->after('type');
                $table->index('status', 'idx_orders_status_new');
            }
            
            if (!Schema::hasColumn('orders', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropColumn([
                'episode_of_care_fhir_id',
                'condition_fhir_id',
                'encounter_fhir_id',
                'task_fhir_id',
                'reviewed_at',
                'reviewed_by',
                'completed_at',
                'manufacturer_response'
            ]);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'fhir_device_request_id',
                'status',
                'metadata'
            ]);
        });
    }
};
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
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'docuseal_generation_status')) {
                $table->string('docuseal_generation_status')->default('not_started');
            }
            if (!Schema::hasColumn('orders', 'docuseal_folder_id')) {
                $table->string('docuseal_folder_id')->nullable();
            }
            if (!Schema::hasColumn('orders', 'manufacturer_delivery_status')) {
                $table->string('manufacturer_delivery_status')->default('pending');
            }
            if (!Schema::hasColumn('orders', 'documents_generated_at')) {
                $table->timestamp('documents_generated_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'docuseal_metadata')) {
                $table->json('docuseal_metadata')->nullable(); // Store DocuSeal-related metadata
            }
        });

        // Add indexes separately to avoid conflicts
        $indexExists = function($table, $column) {
            try {
                $indexes = DB::select("SHOW INDEX FROM $table WHERE Column_name = ?", [$column]);
                return !empty($indexes);
            } catch (\Exception $e) {
                return false;
            }
        };

        Schema::table('orders', function (Blueprint $table) use ($indexExists) {
            if (!$indexExists('orders', 'docuseal_generation_status')) {
                $table->index('docuseal_generation_status');
            }
            if (!$indexExists('orders', 'manufacturer_delivery_status')) {
                $table->index('manufacturer_delivery_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['docuseal_generation_status']);
            $table->dropIndex(['manufacturer_delivery_status']);
            $table->dropColumn([
                'docuseal_generation_status',
                'docuseal_folder_id',
                'manufacturer_delivery_status',
                'documents_generated_at',
                'docuseal_metadata'
            ]);
        });
    }
};

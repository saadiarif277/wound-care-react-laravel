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
        // Add indexes for foreign keys and frequently queried columns
        // Only adding indexes for core tables and columns we know exist

        // Product requests table indexes (confirmed columns)
        if (Schema::hasTable('product_requests')) {
            Schema::table('product_requests', function (Blueprint $table) {
                if (!$this->indexExists('product_requests', 'idx_product_requests_created_at')) {
                    $table->index('created_at', 'idx_product_requests_created_at');
                }
                if (!$this->indexExists('product_requests', 'idx_product_requests_facility_id')) {
                    $table->index('facility_id', 'idx_product_requests_facility_id');
                }
                if (!$this->indexExists('product_requests', 'idx_product_requests_patient_fhir_id')) {
                    $table->index('patient_fhir_id', 'idx_product_requests_patient_fhir_id');
                }
            });
        }

        // Only adding essential indexes for confirmed tables and columns
        // Most indexes are commented out to avoid column not found errors
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop only the indexes we actually created
        if (Schema::hasTable('product_requests')) {
            Schema::table('product_requests', function (Blueprint $table) {
                if ($this->indexExists('product_requests', 'idx_product_requests_created_at')) {
                    $table->dropIndex('idx_product_requests_created_at');
                }
                if ($this->indexExists('product_requests', 'idx_product_requests_facility_id')) {
                    $table->dropIndex('idx_product_requests_facility_id');
                }
                if ($this->indexExists('product_requests', 'idx_product_requests_patient_fhir_id')) {
                    $table->dropIndex('idx_product_requests_patient_fhir_id');
                }
            });
        }
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
            return !empty($indexes);
        } catch (\Exception $e) {
            return false;
        }
    }
};

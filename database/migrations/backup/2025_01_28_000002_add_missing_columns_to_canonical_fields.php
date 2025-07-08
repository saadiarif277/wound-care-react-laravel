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
        Schema::table('canonical_fields', function (Blueprint $table) {
            if (!Schema::hasColumn('canonical_fields', 'display_name')) {
                $table->string('display_name')->nullable()->after('field_name');
            }
            if (!Schema::hasColumn('canonical_fields', 'is_phi')) {
                $table->boolean('is_phi')->default(false)->after('is_required');
            }
            if (!Schema::hasColumn('canonical_fields', 'example_values')) {
                $table->json('example_values')->nullable()->after('validation_rules');
            }
            if (!Schema::hasColumn('canonical_fields', 'metadata')) {
                $table->json('metadata')->nullable()->after('example_values');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('canonical_fields', function (Blueprint $table) {
            $columnsToRemove = ['display_name', 'is_phi', 'example_values', 'metadata'];
            
            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('canonical_fields', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
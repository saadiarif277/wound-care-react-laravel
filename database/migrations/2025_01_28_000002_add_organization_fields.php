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
        Schema::table('organizations', function (Blueprint $table) {
            // Add status column - required for organization status tracking
            if (!Schema::hasColumn('organizations', 'status')) {
                $table->enum('status', ['active', 'pending', 'inactive'])
                      ->default('active')
                      ->after('name');
                $table->index('status');
            }

            // Add type column - for organization categorization
            if (!Schema::hasColumn('organizations', 'type')) {
                $table->string('type', 100)
                      ->nullable()
                      ->after('status')
                      ->comment('e.g., Hospital, Clinic Group, Practice');
            }

            // Add tax_id column - for business identification
            if (!Schema::hasColumn('organizations', 'tax_id')) {
                $table->string('tax_id', 50)
                      ->nullable()
                      ->after('type');
                $table->index('tax_id');
            }

            // Add sales_rep_id column - primary sales rep for this organization
            if (!Schema::hasColumn('organizations', 'sales_rep_id')) {
                $table->unsignedBigInteger('sales_rep_id')
                      ->nullable()
                      ->after('tax_id')
                      ->comment('Optional: Primary sales rep for organization coordination (real relationships are at facility/provider level)');
                $table->foreign('sales_rep_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');
                $table->index('sales_rep_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Drop foreign key and index first, then column
            if (Schema::hasColumn('organizations', 'sales_rep_id')) {
                $table->dropForeign(['sales_rep_id']);
                $table->dropIndex(['sales_rep_id']);
                $table->dropColumn('sales_rep_id');
            }

            if (Schema::hasColumn('organizations', 'tax_id')) {
                $table->dropIndex(['tax_id']);
                $table->dropColumn('tax_id');
            }

            if (Schema::hasColumn('organizations', 'type')) {
                $table->dropColumn('type');
            }

            if (Schema::hasColumn('organizations', 'status')) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            }
        });
    }
};

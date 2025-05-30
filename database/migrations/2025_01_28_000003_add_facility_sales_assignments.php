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
        // Add primary sales rep to providers (where real business relationships happen)
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'primary_sales_rep_id')) {
                $table->unsignedBigInteger('primary_sales_rep_id')
                      ->nullable()
                      ->after('remember_token')
                      ->comment('Primary sales rep for this provider - main business relationship');
                $table->foreign('primary_sales_rep_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');
                $table->index('primary_sales_rep_id');
            }
        });

        // Create provider-sales rep assignment table for individual provider relationships (PRIMARY)
        if (!Schema::hasTable('provider_sales_rep_assignments')) {
            Schema::create('provider_sales_rep_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('provider_id'); // User with provider role
                $table->unsignedBigInteger('sales_rep_id');
                $table->unsignedBigInteger('facility_id')->nullable(); // Optional: specific to a facility
                $table->enum('relationship_type', ['primary', 'secondary', 'referral', 'coverage'])->default('primary');
                $table->decimal('commission_split_percentage', 5, 2)->default(100.00);
                $table->boolean('can_create_orders')->default(true);
                $table->date('assigned_from')->default(now());
                $table->date('assigned_until')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                // Foreign keys
                $table->foreign('provider_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');
                $table->foreign('sales_rep_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');
                $table->foreign('facility_id')
                      ->references('id')
                      ->on('facilities')
                      ->onDelete('cascade');

                // Indexes with shorter names
                $table->index(['provider_id', 'is_active'], 'psra_provider_active_idx');
                $table->index(['sales_rep_id', 'is_active'], 'psra_rep_active_idx');
                $table->index(['facility_id', 'is_active'], 'psra_facility_active_idx');
                $table->index(['provider_id', 'relationship_type'], 'psra_provider_rel_idx');

                // Unique constraint: only one primary rep per provider at a time
                $table->unique(['provider_id', 'relationship_type', 'assigned_until'], 'psra_provider_primary_unique');
            });
        }

        // Create facility-sales rep assignment table for coordination/backup (SECONDARY)
        if (!Schema::hasTable('facility_sales_rep_assignments')) {
            Schema::create('facility_sales_rep_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('facility_id');
                $table->unsignedBigInteger('sales_rep_id');
                $table->enum('relationship_type', ['coordinator', 'backup', 'manager'])->default('coordinator');
                $table->decimal('commission_split_percentage', 5, 2)->default(0.00); // Usually 0 since provider has primary
                $table->boolean('can_create_orders')->default(false);
                $table->date('assigned_from')->default(now());
                $table->date('assigned_until')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();

                // Foreign keys
                $table->foreign('facility_id')
                      ->references('id')
                      ->on('facilities')
                      ->onDelete('cascade');
                $table->foreign('sales_rep_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');

                // Indexes with shorter names
                $table->index(['facility_id', 'is_active'], 'fsra_facility_active_idx');
                $table->index(['sales_rep_id', 'is_active'], 'fsra_rep_active_idx');
                $table->index(['facility_id', 'relationship_type'], 'fsra_facility_rel_idx');
            });
        }

        // Add coordinating sales rep to facilities (for coordination only)
        Schema::table('facilities', function (Blueprint $table) {
            if (!Schema::hasColumn('facilities', 'coordinating_sales_rep_id')) {
                $table->unsignedBigInteger('coordinating_sales_rep_id')
                      ->nullable()
                      ->after('active')
                      ->comment('Coordinating sales rep for this facility - secondary to provider relationships');
                $table->foreign('coordinating_sales_rep_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');
                $table->index('coordinating_sales_rep_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop assignment tables first (due to foreign keys)
        Schema::dropIfExists('provider_sales_rep_assignments');
        Schema::dropIfExists('facility_sales_rep_assignments');

        // Remove user primary sales rep column
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'primary_sales_rep_id')) {
                $table->dropForeign(['primary_sales_rep_id']);
                $table->dropIndex(['primary_sales_rep_id']);
                $table->dropColumn('primary_sales_rep_id');
            }
        });

        // Remove facility coordinating sales rep column
        Schema::table('facilities', function (Blueprint $table) {
            if (Schema::hasColumn('facilities', 'coordinating_sales_rep_id')) {
                $table->dropForeign(['coordinating_sales_rep_id']);
                $table->dropIndex(['coordinating_sales_rep_id']);
                $table->dropColumn('coordinating_sales_rep_id');
            }
        });
    }
};

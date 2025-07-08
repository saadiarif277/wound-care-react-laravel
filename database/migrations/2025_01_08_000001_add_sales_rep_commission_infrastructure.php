<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * SALES REP & COMMISSION INFRASTRUCTURE
     * Adds comprehensive sales rep management and commission tracking
     */
    public function up(): void
    {
        // =====================================================
        // SALES REPS TABLE
        // =====================================================
        Schema::create('sales_reps', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('user_id', 36)->unique();
            $table->char('parent_rep_id', 36)->nullable();
            $table->string('territory', 255)->nullable();
            $table->string('region', 100)->nullable();
            $table->decimal('commission_rate_direct', 5, 2)->default(5.00);
            $table->decimal('sub_rep_parent_share_percentage', 5, 2)->default(20.00);
            $table->enum('rep_type', ['direct', 'independent', 'distributor'])->default('direct');
            $table->enum('commission_tier', ['bronze', 'silver', 'gold', 'platinum'])->default('bronze');
            $table->boolean('can_have_sub_reps')->default(false);
            $table->json('performance_metrics')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('hired_date')->nullable();
            $table->date('terminated_date')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('parent_rep_id')->references('id')->on('sales_reps');
            
            $table->index('territory');
            $table->index('region');
            $table->index('is_active');
            $table->index('parent_rep_id');
        });

        // =====================================================
        // PROVIDER SALES ASSIGNMENTS
        // =====================================================
        Schema::create('provider_sales_assignments', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('provider_fhir_id', 255);
            $table->char('sales_rep_id', 36);
            $table->char('facility_id', 36)->nullable();
            $table->enum('relationship_type', ['primary', 'secondary', 'coverage', 'referral'])->default('primary');
            $table->decimal('commission_split_percentage', 5, 2)->default(100.00);
            $table->decimal('override_commission_rate', 5, 2)->nullable();
            $table->boolean('can_create_orders')->default(true);
            $table->date('assigned_from');
            $table->date('assigned_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('sales_rep_id')->references('id')->on('sales_reps');
            $table->foreign('facility_id')->references('id')->on('organizations');
            
            $table->index(['provider_fhir_id', 'is_active']);
            $table->index(['sales_rep_id', 'is_active']);
            $table->index(['facility_id', 'is_active']);
            $table->index('relationship_type');
            $table->unique(['provider_fhir_id', 'relationship_type', 'assigned_until'], 'provider_primary_unique');
        });

        // =====================================================
        // FACILITY SALES ASSIGNMENTS
        // =====================================================
        Schema::create('facility_sales_assignments', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('facility_id', 36);
            $table->char('sales_rep_id', 36);
            $table->enum('relationship_type', ['coordinator', 'backup', 'manager'])->default('coordinator');
            $table->decimal('commission_split_percentage', 5, 2)->default(0.00);
            $table->boolean('can_create_orders')->default(false);
            $table->boolean('can_view_all_providers')->default(true);
            $table->date('assigned_from');
            $table->date('assigned_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('facility_id')->references('id')->on('organizations');
            $table->foreign('sales_rep_id')->references('id')->on('sales_reps');
            
            $table->index(['facility_id', 'is_active']);
            $table->index(['sales_rep_id', 'is_active']);
            $table->index('relationship_type');
        });

        // =====================================================
        // COMMISSION PAYOUTS
        // =====================================================
        Schema::create('commission_payouts', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('sales_rep_id', 36);
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('deductions', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2);
            $table->integer('commission_count');
            $table->enum('status', ['draft', 'calculated', 'approved', 'paid', 'cancelled'])->default('draft');
            $table->string('batch_number', 50)->unique();
            $table->char('approved_by', 36)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_reference', 255)->nullable();
            $table->json('summary_data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('sales_rep_id')->references('id')->on('sales_reps');
            $table->foreign('approved_by')->references('id')->on('users');
            
            $table->index(['sales_rep_id', 'status']);
            $table->index(['period_start', 'period_end']);
            $table->index('status');
            $table->index('batch_number');
        });

        // =====================================================
        // COMMISSION TARGETS
        // =====================================================
        Schema::create('commission_targets', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('sales_rep_id', 36);
            $table->year('target_year');
            $table->unsignedTinyInteger('target_month')->nullable();
            $table->unsignedTinyInteger('target_quarter')->nullable();
            $table->decimal('revenue_target', 12, 2);
            $table->decimal('commission_target', 10, 2);
            $table->integer('order_count_target')->nullable();
            $table->integer('new_provider_target')->nullable();
            $table->json('category_targets')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('sales_rep_id')->references('id')->on('sales_reps');
            
            $table->index(['sales_rep_id', 'target_year']);
            $table->index(['target_year', 'target_month']);
            $table->unique(['sales_rep_id', 'target_year', 'target_month', 'target_quarter'], 'targets_rep_year_month_quarter_unique');
        });

        // =====================================================
        // UPDATE EXISTING TABLES
        // =====================================================
        
        // Add sales rep to orders
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'sales_rep_id')) {
                    $table->char('sales_rep_id', 36)->nullable()->after('ordered_by_user_id');
                    $table->foreign('sales_rep_id')->references('id')->on('sales_reps');
                    $table->index('sales_rep_id');
                }
            });
        }

        // Enhance commission_records
        if (Schema::hasTable('commission_records')) {
            Schema::table('commission_records', function (Blueprint $table) {
                if (!Schema::hasColumn('commission_records', 'sales_rep_id')) {
                    $table->char('sales_rep_id', 36)->nullable()->after('user_id');
                }
                if (!Schema::hasColumn('commission_records', 'parent_rep_id')) {
                    $table->char('parent_rep_id', 36)->nullable()->after('sales_rep_id');
                }
                if (!Schema::hasColumn('commission_records', 'payout_id')) {
                    $table->char('payout_id', 36)->nullable()->after('commission_amount');
                }
                if (!Schema::hasColumn('commission_records', 'split_type')) {
                    $table->enum('split_type', ['direct', 'parent_share', 'referral'])->default('direct')->after('commission_amount');
                }
                if (!Schema::hasColumn('commission_records', 'approved_by')) {
                    $table->char('approved_by', 36)->nullable()->after('status');
                }
                if (!Schema::hasColumn('commission_records', 'paid_at')) {
                    $table->timestamp('paid_at')->nullable()->after('approved_at');
                }
                if (!Schema::hasColumn('commission_records', 'invoice_number')) {
                    $table->string('invoice_number', 50)->nullable()->after('paid_at');
                }
            });

            // Add foreign keys and indexes separately to avoid issues
            Schema::table('commission_records', function (Blueprint $table) {
                if (Schema::hasColumn('commission_records', 'sales_rep_id')) {
                    $table->foreign('sales_rep_id')->references('id')->on('sales_reps');
                    $table->index('sales_rep_id');
                }
                if (Schema::hasColumn('commission_records', 'parent_rep_id')) {
                    $table->foreign('parent_rep_id')->references('id')->on('sales_reps');
                    $table->index('parent_rep_id');
                }
                if (Schema::hasColumn('commission_records', 'payout_id')) {
                    $table->foreign('payout_id')->references('id')->on('commission_payouts');
                    $table->index('payout_id');
                }
                if (Schema::hasColumn('commission_records', 'approved_by')) {
                    $table->foreign('approved_by')->references('id')->on('users');
                }
                if (Schema::hasColumn('commission_records', 'split_type')) {
                    $table->index('split_type');
                }
            });
        }

        // Add sales rep permissions
        $permissions = [
            ['id' => DB::raw('UUID()'), 'name' => 'sales_reps.view', 'description' => 'View sales representatives'],
            ['id' => DB::raw('UUID()'), 'name' => 'sales_reps.create', 'description' => 'Create sales representatives'],
            ['id' => DB::raw('UUID()'), 'name' => 'sales_reps.edit', 'description' => 'Edit sales representatives'],
            ['id' => DB::raw('UUID()'), 'name' => 'sales_reps.delete', 'description' => 'Delete sales representatives'],
            ['id' => DB::raw('UUID()'), 'name' => 'sales_reps.assignments.manage', 'description' => 'Manage sales rep assignments'],
            ['id' => DB::raw('UUID()'), 'name' => 'commissions.view_own', 'description' => 'View own commissions'],
            ['id' => DB::raw('UUID()'), 'name' => 'commissions.view_team', 'description' => 'View team commissions'],
            ['id' => DB::raw('UUID()'), 'name' => 'commissions.view_all', 'description' => 'View all commissions'],
            ['id' => DB::raw('UUID()'), 'name' => 'commissions.approve', 'description' => 'Approve commissions'],
            ['id' => DB::raw('UUID()'), 'name' => 'commission_payouts.view', 'description' => 'View commission payouts'],
            ['id' => DB::raw('UUID()'), 'name' => 'commission_payouts.create', 'description' => 'Create commission payouts'],
            ['id' => DB::raw('UUID()'), 'name' => 'commission_payouts.approve', 'description' => 'Approve commission payouts'],
            ['id' => DB::raw('UUID()'), 'name' => 'commission_payouts.process', 'description' => 'Process commission payments'],
            ['id' => DB::raw('UUID()'), 'name' => 'commission_targets.manage', 'description' => 'Manage commission targets'],
        ];

        foreach ($permissions as &$permission) {
            $permission['created_at'] = now();
        }

        DB::table('permissions')->insert($permissions);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove foreign keys and columns from existing tables
        if (Schema::hasTable('commission_records')) {
            Schema::table('commission_records', function (Blueprint $table) {
                if (Schema::hasColumn('commission_records', 'sales_rep_id')) {
                    $table->dropForeign(['sales_rep_id']);
                }
                if (Schema::hasColumn('commission_records', 'parent_rep_id')) {
                    $table->dropForeign(['parent_rep_id']);
                }
                if (Schema::hasColumn('commission_records', 'payout_id')) {
                    $table->dropForeign(['payout_id']);
                }
                if (Schema::hasColumn('commission_records', 'approved_by')) {
                    $table->dropForeign(['approved_by']);
                }
            });
            
            Schema::table('commission_records', function (Blueprint $table) {
                $columnsToRemove = [];
                foreach (['sales_rep_id', 'parent_rep_id', 'payout_id', 'split_type', 'paid_at', 'invoice_number'] as $column) {
                    if (Schema::hasColumn('commission_records', $column)) {
                        $columnsToRemove[] = $column;
                    }
                }
                if (!empty($columnsToRemove)) {
                    $table->dropColumn($columnsToRemove);
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'sales_rep_id')) {
                    $table->dropForeign(['sales_rep_id']);
                    $table->dropColumn('sales_rep_id');
                }
            });
        }

        // Drop tables
        Schema::dropIfExists('commission_targets');
        Schema::dropIfExists('commission_payouts');
        Schema::dropIfExists('facility_sales_assignments');
        Schema::dropIfExists('provider_sales_assignments');
        Schema::dropIfExists('sales_reps');

        // Remove permissions
        DB::table('permissions')->whereIn('name', [
            'sales_reps.view',
            'sales_reps.create',
            'sales_reps.edit',
            'sales_reps.delete',
            'sales_reps.assignments.manage',
            'commissions.view_own',
            'commissions.view_team',
            'commissions.view_all',
            'commissions.approve',
            'commission_payouts.view',
            'commission_payouts.create',
            'commission_payouts.approve',
            'commission_payouts.process',
            'commission_targets.manage',
        ])->delete();
    }
};
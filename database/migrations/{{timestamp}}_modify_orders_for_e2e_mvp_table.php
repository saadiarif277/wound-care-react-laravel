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
     * @return void
     */
    public function up()
    {
        // Define ENUM type name for PostgreSQL
        $statusEnumName = 'order_status_enum';

        // Create ENUM type for status if PostgreSQL and type doesn't exist
        if (DB::connection()->getDriverName() == 'pgsql') {
            $exists = DB::select("SELECT EXISTS (SELECT 1 FROM pg_type WHERE typname = ?)", [$statusEnumName])[0]->exists;
            if (!$exists) {
                DB::statement("CREATE TYPE {$statusEnumName} AS ENUM ('draft','pending_approval','approved','documents_generating','pending_signatures','completed','cancelled')");
            }
        }

        Schema::table('orders', function (Blueprint $table) use ($statusEnumName) {
            if (!Schema::hasColumn('orders', 'provider_id')) {
                $table->foreignId('provider_id')->nullable()->after('facility_id')->constrained('users')->onDelete('set null');
            }

            $dateOfServiceColumn = Schema::hasColumn('orders', 'date_of_service') ? 'date_of_service' : 'sales_rep_id';
            $expectedServiceDateColumn = 'expected_service_date';

            if (!Schema::hasColumn('orders', $expectedServiceDateColumn)) {
                if (Schema::hasColumn('orders', 'date_of_service')) {
                    $expectedServiceDateColumn = 'date_of_service';
                } else {
                    $table->date($expectedServiceDateColumn)->after('sales_rep_id');
                }
            }

            if (!Schema::hasColumn('orders', 'patient_display_id')) {
                $table->string('patient_display_id', 7)->nullable()->after('patient_fhir_id');
            }
            if (!Schema::hasColumn('orders', 'payer_name_submitted')) {
                $table->string('payer_name_submitted')->nullable()->after($expectedServiceDateColumn);
            }
            if (!Schema::hasColumn('orders', 'payer_id')) {
                $table->string('payer_id')->nullable()->after('payer_name_submitted');
            }

            // Status column handling
            if (DB::connection()->getDriverName() == 'pgsql') {
                if (Schema::hasColumn('orders', 'status')) {
                    DB::statement("ALTER TABLE orders ALTER COLUMN status DROP DEFAULT");
                    DB::statement("ALTER TABLE orders ALTER COLUMN status TYPE {$statusEnumName} USING status::text::{$statusEnumName}");
                    DB::statement("ALTER TABLE orders ALTER COLUMN status SET DEFAULT 'draft'");
                } else {
                    $table->addColumn('enum', 'status', ['type_name' => $statusEnumName])->default('draft');
                }
            } else {
                if (Schema::hasColumn('orders', 'status')) {
                    $table->string('status')->default('draft')->comment('Enum values: draft,pending_approval,approved,documents_generating,pending_signatures,completed,cancelled')->change();
                } else {
                    $table->string('status')->default('draft')->comment('Enum values: draft,pending_approval,approved,documents_generating,pending_signatures,completed,cancelled');
                }
            }

            if (!Schema::hasColumn('orders', 'azure_order_checklist_fhir_id')) {
                $table->string('azure_order_checklist_fhir_id')->nullable()->after('payer_id');
            }
            if (!Schema::hasColumn('orders', 'clinical_summary')) {
                $table->json('clinical_summary')->nullable()->after('azure_order_checklist_fhir_id');
            }
            if (!Schema::hasColumn('orders', 'mac_validation_results')) {
                $table->json('mac_validation_results')->nullable()->after('clinical_summary');
            }
            if (!Schema::hasColumn('orders', 'mac_validation_status')) {
                $table->string('mac_validation_status')->nullable()->after('mac_validation_results');
            }
            if (!Schema::hasColumn('orders', 'eligibility_results')) {
                $table->json('eligibility_results')->nullable()->after('mac_validation_status');
            }
            if (!Schema::hasColumn('orders', 'eligibility_status')) {
                $table->string('eligibility_status')->nullable()->default('not_checked')->after('eligibility_results');
            }
            if (!Schema::hasColumn('orders', 'pre_auth_required_determination')) {
                $table->string('pre_auth_required_determination')->nullable()->default('pending_determination')->after('eligibility_status');
            }
            if (!Schema::hasColumn('orders', 'clinical_opportunities')) {
                $table->json('clinical_opportunities')->nullable()->after('pre_auth_required_determination');
            }

            if (Schema::hasColumn('orders', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->nullable()->default(null)->change();
            }

            if (!Schema::hasColumn('orders', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('orders', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('submitted_at');
            }

            $docusealFolderIdCol = Schema::hasColumn('orders', 'docuseal_folder_id') ? 'docuseal_folder_id' : 'approved_at';
            if (!Schema::hasColumn('orders', 'documents_generated_at')) {
                $table->timestamp('documents_generated_at')->nullable()->after($docusealFolderIdCol);
            }
            if (!Schema::hasColumn('orders', 'docuseal_metadata')) {
                $table->json('docuseal_metadata')->nullable()->after('documents_generated_at');
            }

            // Index Optimizations
            if (!Schema::hasIndex('orders', 'orders_provider_id_status_index')) {
                $table->index(['provider_id', 'status'], 'idx_orders_provider_status');
            }
            if (!Schema::hasIndex('orders', 'orders_facility_id_' . strtolower($expectedServiceDateColumn) . '_index')) {
                $table->index(['facility_id', $expectedServiceDateColumn], 'idx_orders_facility_date_service');
            }
            if (!Schema::hasIndex('orders', 'orders_patient_display_id_index')) {
                $table->index('patient_display_id', 'idx_orders_patient_display');
            }
            if (!Schema::hasIndex('orders', 'orders_provider_id_index')) {
                $table->index('provider_id', 'idx_orders_provider');
            }
            if (!Schema::hasIndex('orders', 'orders_eligibility_status_index')) {
                $table->index('eligibility_status', 'idx_orders_eligibility');
            }
        });

        // Migration Verification Strategy
        $requiredColumns = [
            'provider_id', 'patient_display_id',
            'documents_generated_at', 'docuseal_metadata', 'status'
        ];

        foreach ($requiredColumns as $column) {
            if (!Schema::hasColumn('orders', $column)) {
                throw new \Exception("Critical column {$column} missing after migration");
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $statusEnumName = 'order_status_enum';

        Schema::table('orders', function (Blueprint $table) use ($statusEnumName) {
            $columnsToDrop = [];
            if (Schema::hasColumn('orders', 'patient_display_id')) $columnsToDrop[] = 'patient_display_id';
            if (Schema::hasColumn('orders', 'payer_name_submitted')) $columnsToDrop[] = 'payer_name_submitted';
            if (Schema::hasColumn('orders', 'payer_id')) $columnsToDrop[] = 'payer_id';
            if (Schema::hasColumn('orders', 'azure_order_checklist_fhir_id')) $columnsToDrop[] = 'azure_order_checklist_fhir_id';
            if (Schema::hasColumn('orders', 'clinical_summary')) $columnsToDrop[] = 'clinical_summary';
            if (Schema::hasColumn('orders', 'mac_validation_results')) $columnsToDrop[] = 'mac_validation_results';
            if (Schema::hasColumn('orders', 'mac_validation_status')) $columnsToDrop[] = 'mac_validation_status';
            if (Schema::hasColumn('orders', 'eligibility_results')) $columnsToDrop[] = 'eligibility_results';
            if (Schema::hasColumn('orders', 'eligibility_status')) $columnsToDrop[] = 'eligibility_status';
            if (Schema::hasColumn('orders', 'pre_auth_required_determination')) $columnsToDrop[] = 'pre_auth_required_determination';
            if (Schema::hasColumn('orders', 'clinical_opportunities')) $columnsToDrop[] = 'clinical_opportunities';
            if (Schema::hasColumn('orders', 'submitted_at')) $columnsToDrop[] = 'submitted_at';
            if (Schema::hasColumn('orders', 'approved_at')) $columnsToDrop[] = 'approved_at';
            if (Schema::hasColumn('orders', 'documents_generated_at')) $columnsToDrop[] = 'documents_generated_at';
            if (Schema::hasColumn('orders', 'docuseal_metadata')) $columnsToDrop[] = 'docuseal_metadata';

            if (count($columnsToDrop) > 0) {
                $table->dropColumn($columnsToDrop);
            }

            // Drop indexes
            if (Schema::hasIndex('orders', 'idx_orders_provider_status')) {
                $table->dropIndex('idx_orders_provider_status');
            }
            if (Schema::hasIndex('orders', 'idx_orders_facility_date_service')) {
                $table->dropIndex('idx_orders_facility_date_service');
            }
            if (Schema::hasIndex('orders', 'idx_orders_patient_display')) {
                $table->dropIndex('idx_orders_patient_display');
            }
            if (Schema::hasIndex('orders', 'idx_orders_provider')) {
                $table->dropIndex('idx_orders_provider');
            }
            if (Schema::hasIndex('orders', 'idx_orders_eligibility')) {
                $table->dropIndex('idx_orders_eligibility');
            }

            // Revert status column for PostgreSQL
            if (DB::connection()->getDriverName() == 'pgsql') {
                DB::statement("ALTER TABLE orders ALTER COLUMN status TYPE character varying(255) USING status::text::character varying(255)");
                DB::statement("ALTER TABLE orders ALTER COLUMN status SET DEFAULT 'pending'");
            }
        });
    }
};

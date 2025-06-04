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
        Schema::create('patient_associations', function (Blueprint $table) {
            $table->id();
            $table->string('patient_fhir_id')->index();
            $table->foreignId('provider_id')->constrained('users');
            $table->foreignId('facility_id')->constrained('facilities');
            $table->foreignId('organization_id')->constrained('organizations');
            $table->enum('association_type', ['treatment', 'billing', 'administrative'])->default('treatment');
            $table->boolean('is_primary_provider')->default(false);
            $table->timestamp('established_at')->useCurrent();
            $table->timestamp('terminated_at')->nullable();
            $table->timestamps();

            // Database-agnostic active column
            if ($this->isPostgreSQL()) {
                // PostgreSQL: Use a regular boolean column and handle via triggers
                $table->boolean('active')->default(true);
            } else {
                // MySQL/Other: Use triggers for active column maintenance
                $table->boolean('active')->default(true);

                // Standard unique constraint for MySQL
                $table->unique(['patient_fhir_id', 'provider_id', 'facility_id', 'terminated_at'], 'patient_provider_facility_term_unique');
            }

            // Performance indexes
            $table->index(['patient_fhir_id', 'provider_id', 'active']);
            $table->index(['facility_id', 'active']);
            $table->index(['organization_id', 'association_type']);
            $table->index(['established_at', 'terminated_at']);
        });

        // Create PostgreSQL partial unique index after table creation
        if ($this->isPostgreSQL()) {
            DB::statement('CREATE UNIQUE INDEX patient_provider_facility_active_unique ON patient_associations (patient_fhir_id, provider_id, facility_id) WHERE terminated_at IS NULL');

            // Create PostgreSQL triggers for active column maintenance
            DB::unprepared('
                CREATE OR REPLACE FUNCTION update_patient_association_active()
                RETURNS TRIGGER AS $$
                BEGIN
                    NEW.active = (NEW.terminated_at IS NULL);
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
            ');

            DB::unprepared('
                CREATE TRIGGER patient_associations_active_trigger
                BEFORE INSERT OR UPDATE ON patient_associations
                FOR EACH ROW
                EXECUTE FUNCTION update_patient_association_active();
            ');
        }

        // Create triggers for MySQL active column maintenance
        if (!$this->isPostgreSQL()) {
            $this->createActiveTriggers();
        }

        // Comprehensive backfill strategy
        $this->backfillPatientAssociations();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->isPostgreSQL()) {
            DB::unprepared('DROP TRIGGER IF EXISTS patient_associations_active_trigger ON patient_associations');
            DB::unprepared('DROP FUNCTION IF EXISTS update_patient_association_active()');
        } else {
            DB::unprepared('DROP TRIGGER IF EXISTS patient_associations_active_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS patient_associations_active_update');
        }

        Schema::dropIfExists('patient_associations');
    }

    /**
     * Check if we're running on PostgreSQL
     */
    private function isPostgreSQL(): bool
    {
        return DB::getDriverName() === 'pgsql';
    }

    /**
     * Create MySQL triggers for active column maintenance
     */
    private function createActiveTriggers(): void
    {
        DB::unprepared('
            CREATE TRIGGER patient_associations_active_insert
            BEFORE INSERT ON patient_associations
            FOR EACH ROW
            SET NEW.active = (NEW.terminated_at IS NULL)
        ');

        DB::unprepared('
            CREATE TRIGGER patient_associations_active_update
            BEFORE UPDATE ON patient_associations
            FOR EACH ROW
            SET NEW.active = (NEW.terminated_at IS NULL)
        ');
    }

    /**
     * Comprehensive backfill patient associations from existing data
     */
    private function backfillPatientAssociations(): void
    {
        // Only backfill from product_requests since orders table doesn't have the expected structure
        $this->backfillTreatmentAssociations();

        // Skip administrative associations backfill for now since orders table structure is different
        // This can be implemented later when the orders table has the proper relationships
    }

    /**
     * Pass 1: Backfill treatment associations via product_requests
     */
    private function backfillTreatmentAssociations(): void
    {
        // Check if product_requests table exists
        if (!Schema::hasTable('product_requests')) {
            return;
        }

        if ($this->isPostgreSQL()) {
            // PostgreSQL: Use window functions for sophisticated deduplication
            DB::statement("
                INSERT INTO patient_associations
                    (patient_fhir_id, provider_id, facility_id, organization_id,
                     association_type, is_primary_provider, established_at, created_at, updated_at)
                SELECT DISTINCT
                    pr.patient_fhir_id,
                    pr.provider_id,
                    pr.facility_id,
                    f.organization_id,
                    'treatment' as association_type,
                    ROW_NUMBER() OVER (PARTITION BY pr.patient_fhir_id, pr.facility_id ORDER BY pr.created_at) = 1 as is_primary_provider,
                    MIN(pr.created_at) OVER (PARTITION BY pr.patient_fhir_id, pr.provider_id, pr.facility_id) as established_at,
                    NOW() as created_at,
                    NOW() as updated_at
                FROM product_requests pr
                INNER JOIN facilities f ON pr.facility_id = f.id
                INNER JOIN users u ON pr.provider_id = u.id
                WHERE pr.patient_fhir_id IS NOT NULL
                  AND pr.provider_id IS NOT NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM patient_associations pa
                      WHERE pa.patient_fhir_id = pr.patient_fhir_id
                        AND pa.provider_id = pr.provider_id
                        AND pa.facility_id = pr.facility_id
                  )
                ON CONFLICT (patient_fhir_id, provider_id, facility_id)
                WHERE terminated_at IS NULL DO NOTHING
            ");
        } else {
            // MySQL: Use GROUP BY approach
            DB::statement("
                INSERT IGNORE INTO patient_associations
                    (patient_fhir_id, provider_id, facility_id, organization_id,
                     association_type, is_primary_provider, established_at, created_at, updated_at, active)
                SELECT
                    pr.patient_fhir_id,
                    pr.provider_id,
                    pr.facility_id,
                    f.organization_id,
                    'treatment' as association_type,
                    1 as is_primary_provider, -- Will be corrected in post-processing
                    MIN(pr.created_at) as established_at,
                    NOW() as created_at,
                    NOW() as updated_at,
                    1 as active
                FROM product_requests pr
                INNER JOIN facilities f ON pr.facility_id = f.id
                INNER JOIN users u ON pr.provider_id = u.id
                WHERE pr.patient_fhir_id IS NOT NULL
                  AND pr.provider_id IS NOT NULL
                GROUP BY pr.patient_fhir_id, pr.provider_id, pr.facility_id, f.organization_id
            ");

            // Post-process to set correct primary provider flags
            DB::statement("
                UPDATE patient_associations pa1
                JOIN (
                    SELECT patient_fhir_id, facility_id, MIN(established_at) as earliest_established
                    FROM patient_associations
                    WHERE association_type = 'treatment'
                    GROUP BY patient_fhir_id, facility_id
                ) earliest ON pa1.patient_fhir_id = earliest.patient_fhir_id
                           AND pa1.facility_id = earliest.facility_id
                SET pa1.is_primary_provider = (pa1.established_at = earliest.earliest_established)
                WHERE pa1.association_type = 'treatment'
            ");
        }
    }

    /**
     * Pass 2: Backfill administrative associations via orders→sales_reps
     * Note: Commented out for now due to orders table structure differences
     */
    private function backfillAdministrativeAssociations(): void
    {
        // This method is currently disabled because the orders table structure
        // doesn't match the expected schema. This can be implemented later
        // when the orders table has the proper relationships.
        return;

        /*
        if ($this->isPostgreSQL()) {
            // PostgreSQL: Complex provider mapping with window functions
            DB::statement("
                INSERT INTO patient_associations
                    (patient_fhir_id, provider_id, facility_id, organization_id,
                     association_type, established_at, created_at, updated_at)
                SELECT DISTINCT
                    o.patient_fhir_id,
                    o.sales_rep_id as provider_id,
                    o.facility_id,
                    f.organization_id,
                    'administrative' as association_type,
                    MIN(o.created_at) OVER (PARTITION BY o.patient_fhir_id, o.sales_rep_id, o.facility_id) as established_at,
                    NOW() as created_at,
                    NOW() as updated_at
                FROM orders o
                INNER JOIN facilities f ON o.facility_id = f.id
                INNER JOIN users sr_user ON o.sales_rep_id = sr_user.id
                WHERE o.patient_fhir_id IS NOT NULL
                  AND o.sales_rep_id IS NOT NULL
                  AND NOT EXISTS (
                      SELECT 1 FROM patient_associations pa
                      WHERE pa.patient_fhir_id = o.patient_fhir_id
                        AND pa.provider_id = o.sales_rep_id
                        AND pa.facility_id = o.facility_id
                        AND pa.association_type IN ('treatment', 'administrative')
                  )
                ON CONFLICT (patient_fhir_id, provider_id, facility_id)
                WHERE terminated_at IS NULL DO NOTHING
            ");
        } else {
            // MySQL: Simplified approach
            DB::statement("
                INSERT IGNORE INTO patient_associations
                    (patient_fhir_id, provider_id, facility_id, organization_id,
                     association_type, established_at, created_at, updated_at, active)
                SELECT
                    o.patient_fhir_id,
                    o.sales_rep_id as provider_id,
                    o.facility_id,
                    f.organization_id,
                    'administrative' as association_type,
                    MIN(o.created_at) as established_at,
                    NOW() as created_at,
                    NOW() as updated_at,
                    1 as active
                FROM orders o
                INNER JOIN facilities f ON o.facility_id = f.id
                INNER JOIN users u ON o.sales_rep_id = u.id
                WHERE o.patient_fhir_id IS NOT NULL
                  AND o.sales_rep_id IS NOT NULL
                GROUP BY o.patient_fhir_id, o.sales_rep_id, o.facility_id, f.organization_id
            ");
        }
        */
    }
};

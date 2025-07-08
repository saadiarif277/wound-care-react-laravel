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
        // First, let's check if the table exists
        if (Schema::hasTable('docuseal_templates')) {
            // For MySQL, we need to modify the enum
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE docuseal_templates MODIFY COLUMN document_type ENUM('IVR', 'InsuranceVerification', 'OrderForm', 'OnboardingForm') NOT NULL");
            } 
            // For PostgreSQL
            else if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE docuseal_templates DROP CONSTRAINT IF EXISTS docuseal_templates_document_type_check");
                DB::statement("ALTER TABLE docuseal_templates ADD CONSTRAINT docuseal_templates_document_type_check CHECK (document_type IN ('IVR', 'InsuranceVerification', 'OrderForm', 'OnboardingForm'))");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('docuseal_templates')) {
            // Revert to original enum values
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE docuseal_templates MODIFY COLUMN document_type ENUM('InsuranceVerification', 'OrderForm', 'OnboardingForm') NOT NULL");
            } 
            else if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE docuseal_templates DROP CONSTRAINT IF EXISTS docuseal_templates_document_type_check");
                DB::statement("ALTER TABLE docuseal_templates ADD CONSTRAINT docuseal_templates_document_type_check CHECK (document_type IN ('InsuranceVerification', 'OrderForm', 'OnboardingForm'))");
            }
        }
    }
};
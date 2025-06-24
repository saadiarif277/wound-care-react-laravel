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
        // Episodes table indexes
        Schema::table('episodes', function (Blueprint $table) {
            $table->index('patient_fhir_id', 'idx_episodes_patient_fhir_id');
            $table->index('practitioner_fhir_id', 'idx_episodes_practitioner_fhir_id');
            $table->index('organization_fhir_id', 'idx_episodes_organization_fhir_id');
            $table->index('manufacturer_id', 'idx_episodes_manufacturer_id');
            $table->index('status', 'idx_episodes_status');
            $table->index(['status', 'created_at'], 'idx_episodes_status_created');
            $table->index(['patient_fhir_id', 'manufacturer_id'], 'idx_episodes_patient_manufacturer');
        });

        // Orders table indexes
        Schema::table('orders', function (Blueprint $table) {
            $table->index('episode_id', 'idx_orders_episode_id');
            $table->index('based_on', 'idx_orders_based_on');
            $table->index('type', 'idx_orders_type');
            $table->index('status', 'idx_orders_status');
            $table->index(['episode_id', 'status'], 'idx_orders_episode_status');
            $table->index(['type', 'created_at'], 'idx_orders_type_created');
        });

        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index('email', 'idx_users_email');
            $table->index('organization_id', 'idx_users_organization_id');
            $table->index(['organization_id', 'is_active'], 'idx_users_org_active');
        });

        // Providers table indexes
        Schema::table('providers', function (Blueprint $table) {
            $table->index('npi', 'idx_providers_npi');
            $table->index('facility_id', 'idx_providers_facility_id');
            $table->index('status', 'idx_providers_status');
            $table->index(['npi', 'status'], 'idx_providers_npi_status');
        });

        // Product requests table indexes
        Schema::table('product_requests', function (Blueprint $table) {
            $table->index('provider_id', 'idx_product_requests_provider_id');
            $table->index('patient_id', 'idx_product_requests_patient_id');
            $table->index('status', 'idx_product_requests_status');
            $table->index(['provider_id', 'status'], 'idx_product_requests_provider_status');
            $table->index(['created_at', 'status'], 'idx_product_requests_created_status');
        });

        // Medicare MAC validations table indexes
        Schema::table('medicare_mac_validations', function (Blueprint $table) {
            $table->index('order_id', 'idx_mac_validations_order_id');
            $table->index('mac_contractor', 'idx_mac_validations_contractor');
            $table->index('validation_status', 'idx_mac_validations_status');
            $table->index('monitoring_enabled', 'idx_mac_validations_monitoring');
            $table->index(['mac_contractor', 'validation_status'], 'idx_mac_validations_contractor_status');
            $table->index(['monitoring_enabled', 'next_validation_date'], 'idx_mac_validations_monitoring_date');
        });

        // Audit logs table indexes (for PHI access tracking)
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('user_id', 'idx_audit_logs_user_id');
            $table->index('auditable_type', 'idx_audit_logs_auditable_type');
            $table->index(['auditable_type', 'auditable_id'], 'idx_audit_logs_auditable');
            $table->index('event', 'idx_audit_logs_event');
            $table->index('created_at', 'idx_audit_logs_created_at');
            $table->index(['user_id', 'created_at'], 'idx_audit_logs_user_created');
        });

        // Facilities table indexes
        Schema::table('facilities', function (Blueprint $table) {
            $table->index('organization_id', 'idx_facilities_organization_id');
            $table->index('office_manager_id', 'idx_facilities_office_manager_id');
            $table->index(['organization_id', 'is_active'], 'idx_facilities_org_active');
        });

        // Organizations table indexes
        Schema::table('organizations', function (Blueprint $table) {
            $table->index('status', 'idx_organizations_status');
            $table->index('created_at', 'idx_organizations_created_at');
        });

        // Provider facilities pivot table indexes
        Schema::table('provider_facilities', function (Blueprint $table) {
            $table->index('provider_id', 'idx_provider_facilities_provider_id');
            $table->index('facility_id', 'idx_provider_facilities_facility_id');
            $table->index(['provider_id', 'facility_id'], 'idx_provider_facilities_composite');
        });

        // DocuSeal documents table indexes
        Schema::table('docu_seal_documents', function (Blueprint $table) {
            $table->index('documentable_type', 'idx_docuseal_docs_documentable_type');
            $table->index(['documentable_type', 'documentable_id'], 'idx_docuseal_docs_documentable');
            $table->index('submission_id', 'idx_docuseal_docs_submission_id');
            $table->index('status', 'idx_docuseal_docs_status');
        });

        // Commission records table indexes
        Schema::table('commission_records', function (Blueprint $table) {
            $table->index('sales_rep_id', 'idx_commission_records_sales_rep_id');
            $table->index('order_id', 'idx_commission_records_order_id');
            $table->index('status', 'idx_commission_records_status');
            $table->index(['sales_rep_id', 'status'], 'idx_commission_records_rep_status');
            $table->index(['created_at', 'status'], 'idx_commission_records_created_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop episodes indexes
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropIndex('idx_episodes_patient_fhir_id');
            $table->dropIndex('idx_episodes_practitioner_fhir_id');
            $table->dropIndex('idx_episodes_organization_fhir_id');
            $table->dropIndex('idx_episodes_manufacturer_id');
            $table->dropIndex('idx_episodes_status');
            $table->dropIndex('idx_episodes_status_created');
            $table->dropIndex('idx_episodes_patient_manufacturer');
        });

        // Drop orders indexes
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_episode_id');
            $table->dropIndex('idx_orders_based_on');
            $table->dropIndex('idx_orders_type');
            $table->dropIndex('idx_orders_status');
            $table->dropIndex('idx_orders_episode_status');
            $table->dropIndex('idx_orders_type_created');
        });

        // Drop users indexes
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_email');
            $table->dropIndex('idx_users_organization_id');
            $table->dropIndex('idx_users_org_active');
        });

        // Drop providers indexes
        Schema::table('providers', function (Blueprint $table) {
            $table->dropIndex('idx_providers_npi');
            $table->dropIndex('idx_providers_facility_id');
            $table->dropIndex('idx_providers_status');
            $table->dropIndex('idx_providers_npi_status');
        });

        // Drop product requests indexes
        Schema::table('product_requests', function (Blueprint $table) {
            $table->dropIndex('idx_product_requests_provider_id');
            $table->dropIndex('idx_product_requests_patient_id');
            $table->dropIndex('idx_product_requests_status');
            $table->dropIndex('idx_product_requests_provider_status');
            $table->dropIndex('idx_product_requests_created_status');
        });

        // Drop Medicare MAC validations indexes
        Schema::table('medicare_mac_validations', function (Blueprint $table) {
            $table->dropIndex('idx_mac_validations_order_id');
            $table->dropIndex('idx_mac_validations_contractor');
            $table->dropIndex('idx_mac_validations_status');
            $table->dropIndex('idx_mac_validations_monitoring');
            $table->dropIndex('idx_mac_validations_contractor_status');
            $table->dropIndex('idx_mac_validations_monitoring_date');
        });

        // Drop audit logs indexes
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_logs_user_id');
            $table->dropIndex('idx_audit_logs_auditable_type');
            $table->dropIndex('idx_audit_logs_auditable');
            $table->dropIndex('idx_audit_logs_event');
            $table->dropIndex('idx_audit_logs_created_at');
            $table->dropIndex('idx_audit_logs_user_created');
        });

        // Drop facilities indexes
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropIndex('idx_facilities_organization_id');
            $table->dropIndex('idx_facilities_office_manager_id');
            $table->dropIndex('idx_facilities_org_active');
        });

        // Drop organizations indexes
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropIndex('idx_organizations_status');
            $table->dropIndex('idx_organizations_created_at');
        });

        // Drop provider facilities indexes
        Schema::table('provider_facilities', function (Blueprint $table) {
            $table->dropIndex('idx_provider_facilities_provider_id');
            $table->dropIndex('idx_provider_facilities_facility_id');
            $table->dropIndex('idx_provider_facilities_composite');
        });

        // Drop DocuSeal documents indexes
        Schema::table('docu_seal_documents', function (Blueprint $table) {
            $table->dropIndex('idx_docuseal_docs_documentable_type');
            $table->dropIndex('idx_docuseal_docs_documentable');
            $table->dropIndex('idx_docuseal_docs_submission_id');
            $table->dropIndex('idx_docuseal_docs_status');
        });

        // Drop commission records indexes
        Schema::table('commission_records', function (Blueprint $table) {
            $table->dropIndex('idx_commission_records_sales_rep_id');
            $table->dropIndex('idx_commission_records_order_id');
            $table->dropIndex('idx_commission_records_status');
            $table->dropIndex('idx_commission_records_rep_status');
            $table->dropIndex('idx_commission_records_created_status');
        });
    }
};
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
     * AGNOSTIC MEDICAL DISTRIBUTION PLATFORM SCHEMA
     * Business Logic Database (Non-PHI)
     * FHIR IDs reference Azure Health Data Services
     */
    public function up(): void
    {
        // =====================================================
        // SECTION 1: CORE/BASE TABLES (NO DEPENDENCIES)
        // =====================================================

        // 1.1 Tenants - Multi-tenant foundation
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('name', 255);
                $table->enum('type', ['distributor', 'manufacturer', 'platform']);
                $table->json('settings')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                
                $table->index('type');
                $table->index('is_active');
            });
        }

        // 1.2 Users - Core user accounts
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('email', 255)->unique();
                $table->string('password_hash', 255)->nullable();
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('provider_fhir_id', 255)->nullable(); // FHIR Practitioner
                $table->enum('user_type', ['provider', 'office_manager', 'sales_rep', 'admin', 'manufacturer_rep']);
                $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('email');
                $table->index('user_type');
                $table->index('status');
                $table->index('provider_fhir_id');
            });
        }

        // 1.3 Roles - System roles
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('name', 50)->unique();
                $table->string('description', 255)->nullable();
                $table->boolean('is_system')->default(false);
                $table->timestamp('created_at')->useCurrent();
                
                $table->index('is_system');
            });
        }

        // 1.4 Permissions - System permissions
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('name', 100)->unique();
                $table->string('description', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // =====================================================
        // SECTION 2: RBAC TABLES (DEPENDS ON USERS/ROLES)
        // =====================================================

        // 2.1 User Roles - Scoped RBAC
        if (!Schema::hasTable('user_roles')) {
            Schema::create('user_roles', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('user_id', 36);
                $table->char('role_id', 36);
                $table->enum('scope_type', ['organization', 'facility', 'manufacturer', 'tenant', 'global']);
                $table->char('scope_id', 36)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('deleted_at')->nullable();
                
                $table->foreign('user_id')->references('id')->on('users');
                $table->foreign('role_id')->references('id')->on('roles');
                
                $table->index(['user_id', 'scope_type', 'scope_id']);
                $table->index(['role_id', 'scope_type']);
            });
        }

        // 2.2 Role Permissions
        if (!Schema::hasTable('role_permissions')) {
            Schema::create('role_permissions', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('role_id', 36);
                $table->char('permission_id', 36);
                $table->timestamp('created_at')->useCurrent();
                
                $table->foreign('role_id')->references('id')->on('roles');
                $table->foreign('permission_id')->references('id')->on('permissions');
                
                $table->unique(['role_id', 'permission_id']);
                $table->index('permission_id');
            });
        }

        // =====================================================
        // SECTION 3: ORGANIZATION HIERARCHY
        // =====================================================

        // 3.1 Organizations - Facilities/Distributors/Manufacturers
        if (!Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('tenant_id', 36);
                $table->char('parent_id', 36)->nullable();
                $table->string('organization_fhir_id', 255)->nullable();
                $table->enum('type', ['facility', 'manufacturer', 'provider_practice', 'distributor', 'payer']);
                $table->string('name', 255);
                $table->string('npi', 20)->nullable();
                $table->string('tax_id', 20)->nullable();
                $table->string('business_email', 255)->nullable();
                $table->string('business_phone', 50)->nullable();
                $table->json('settings')->nullable();
                $table->enum('status', ['active', 'inactive', 'onboarding'])->default('onboarding');
                $table->timestamp('activated_at')->nullable();
                $table->timestamps();
                
                $table->foreign('tenant_id')->references('id')->on('tenants');
                $table->foreign('parent_id')->references('id')->on('organizations');
                
                $table->index(['tenant_id', 'type']);
                $table->index('status');
                $table->index('organization_fhir_id');
            });
        }

        // 3.2 User Facility Assignments - Fine-grained per-org permissions
        if (!Schema::hasTable('user_facility_assignments')) {
            Schema::create('user_facility_assignments', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('user_id', 36);
                $table->char('facility_id', 36);
                $table->enum('role', ['office_manager', 'provider', 'coordinator', 'viewer', 'admin', 'rep']);
                $table->boolean('can_order')->default(false);
                $table->boolean('can_view_orders')->default(true);
                $table->boolean('can_view_financial')->default(false);
                $table->boolean('can_manage_verifications')->default(false);
                $table->json('can_order_for_providers')->nullable(); // array of provider_fhir_ids
                $table->boolean('is_primary_facility')->default(false);
                $table->timestamp('assigned_at')->useCurrent();
                
                $table->foreign('user_id')->references('id')->on('users');
                $table->foreign('facility_id')->references('id')->on('organizations');
                
                $table->unique(['user_id', 'facility_id'], 'uniq_user_facility');
                $table->index('facility_id');
                $table->index(['user_id', 'is_primary_facility']);
            });
        }

        // =====================================================
        // SECTION 4: PATIENT & CLINICAL REFERENCES
        // =====================================================

        // 4.1 Patient References - Pointer only, no PHI
        if (!Schema::hasTable('patient_references')) {
            Schema::create('patient_references', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('patient_fhir_id', 255)->unique();
                $table->string('patient_display_id', 10)->nullable(); // For UI, non-PHI
                $table->json('display_metadata')->nullable(); // e.g., {"first_init":"JO", "last_init":"SM", "random":"1234"}
                $table->char('tenant_id', 36);
                $table->timestamp('created_at')->useCurrent();
                
                $table->foreign('tenant_id')->references('id')->on('tenants');
                
                $table->index('patient_display_id');
                $table->index('tenant_id');
            });
        }

        // =====================================================
        // SECTION 5: EPISODES (CORE CLINICAL WORKFLOW)
        // =====================================================

        // 5.1 Episodes - Central clinical context
        if (!Schema::hasTable('episodes')) {
            Schema::create('episodes', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('tenant_id', 36);
                $table->string('episode_number', 50)->unique();
                $table->string('patient_fhir_id', 255);
                $table->string('primary_provider_fhir_id', 255)->nullable();
                $table->char('primary_facility_id', 36);
                $table->enum('type', ['wound_care', 'surgical_case', 'dme_need', 'implant_procedure', 'ongoing_supply']);
                $table->string('sub_type', 100)->nullable();
                $table->enum('status', ['planned', 'active', 'completed', 'cancelled', 'on_hold'])->default('planned');
                $table->json('diagnosis_fhir_refs')->nullable();
                $table->json('procedure_fhir_refs')->nullable();
                $table->integer('estimated_duration_days')->nullable();
                $table->enum('priority', ['routine', 'urgent', 'emergent'])->default('routine');
                $table->date('start_date');
                $table->date('target_date')->nullable();
                $table->date('end_date')->nullable();
                $table->json('tags')->nullable();
                $table->timestamps();
                $table->char('created_by', 36)->nullable();
                
                $table->foreign('tenant_id')->references('id')->on('tenants');
                $table->foreign('primary_facility_id')->references('id')->on('organizations');
                $table->foreign('created_by')->references('id')->on('users');
                
                $table->index(['tenant_id', 'status']);
                $table->index('patient_fhir_id');
                $table->index('primary_provider_fhir_id');
                $table->index(['type', 'status']);
                $table->index('start_date');
            });
        }

        // 5.2 Episode Care Team
        if (!Schema::hasTable('episode_care_team')) {
            Schema::create('episode_care_team', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('episode_id', 36);
                $table->char('user_id', 36)->nullable();
                $table->string('provider_fhir_id', 255)->nullable();
                $table->enum('role', ['primary_surgeon', 'attending_physician', 'care_coordinator', 'office_manager', 'consulting_physician']);
                $table->boolean('can_order')->default(false);
                $table->boolean('can_modify')->default(false);
                $table->boolean('can_view_financial')->default(false);
                $table->date('assigned_date');
                $table->date('removed_date')->nullable();
                
                $table->foreign('episode_id')->references('id')->on('episodes');
                $table->foreign('user_id')->references('id')->on('users');
                
                $table->index(['episode_id', 'role']);
                $table->index('user_id');
                $table->index('provider_fhir_id');
            });
        }

        // =====================================================
        // SECTION 6: PRODUCT CATALOG
        // =====================================================

        // 6.1 Products
        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('tenant_id', 36);
                $table->char('manufacturer_id', 36);
                $table->string('sku', 100);
                $table->string('manufacturer_part_number', 100)->nullable();
                $table->enum('category', ['wound_dressing', 'surgical_implant', 'dme_equipment', 'surgical_instrument', 'pharmaceutical', 'other']);
                $table->string('sub_category', 100)->nullable();
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->string('hcpcs_code', 20)->nullable();
                $table->json('cpt_codes')->nullable();
                $table->boolean('requires_prescription')->default(false);
                $table->boolean('requires_verification')->default(true);
                $table->boolean('requires_sizing')->default(false);
                $table->json('specifications')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                
                $table->foreign('tenant_id')->references('id')->on('tenants');
                $table->foreign('manufacturer_id')->references('id')->on('organizations');
                
                $table->unique(['tenant_id', 'sku'], 'uniq_product_sku');
                $table->index(['manufacturer_id', 'is_active']);
                $table->index('category');
                $table->index('hcpcs_code');
            });
        }

        // =====================================================
        // SECTION 7: REQUESTS & ORDERS
        // =====================================================

        // 7.1 Product Requests
        if (!Schema::hasTable('product_requests')) {
            Schema::create('product_requests', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('episode_id', 36);
                $table->string('request_number', 50)->unique();
                $table->char('requested_by', 36);
                $table->string('requested_for_provider_fhir_id', 255)->nullable();
                $table->enum('request_type', ['initial_assessment', 'replenishment', 'urgent_need', 'planned_procedure']);
                $table->enum('status', ['draft', 'submitted', 'reviewing', 'approved', 'converted_to_order', 'cancelled'])->default('draft');
                $table->text('clinical_need')->nullable();
                $table->enum('urgency', ['routine', 'urgent', 'stat'])->default('routine');
                $table->json('product_categories')->nullable();
                $table->json('specific_products')->nullable();
                $table->date('needed_by_date')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->char('converted_to_order_id', 36)->nullable();
                $table->timestamps();
                
                $table->foreign('episode_id')->references('id')->on('episodes');
                $table->foreign('requested_by')->references('id')->on('users');
                
                $table->index(['episode_id', 'status']);
                $table->index('requested_by');
                $table->index('request_type');
            });
        }

        // 7.2 Orders
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('episode_id', 36);
                $table->char('product_request_id', 36)->nullable();
                $table->string('order_number', 50)->unique();
                $table->enum('order_type', ['standard', 'urgent', 'standing', 'trial'])->default('standard');
                $table->enum('status', [
                    'draft', 'pending_verification', 'verification_in_progress', 
                    'pending_approval', 'approved', 'transmitted_to_manufacturer', 
                    'acknowledged', 'in_fulfillment', 'shipped', 'delivered', 'cancelled'
                ])->default('draft');
                $table->string('ordering_provider_fhir_id', 255)->nullable();
                $table->char('ordered_by_user_id', 36);
                $table->char('facility_id', 36);
                $table->char('manufacturer_id', 36)->nullable();
                $table->date('service_date');
                $table->enum('ship_to_type', ['facility', 'patient_home', 'other'])->default('facility');
                $table->boolean('requires_insurance_verification')->default(true);
                $table->boolean('requires_prior_auth')->default(false);
                $table->enum('verification_status', ['not_required', 'pending', 'in_progress', 'completed', 'failed', 'bypassed'])->default('pending');
                $table->decimal('estimated_total', 10, 2)->nullable();
                $table->decimal('final_total', 10, 2)->nullable();
                $table->decimal('patient_responsibility', 10, 2)->nullable();
                $table->decimal('insurance_coverage', 10, 2)->nullable();
                $table->enum('compliance_check_status', ['pending', 'passed', 'failed_with_override', 'failed'])->default('pending');
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('transmitted_at')->nullable();
                $table->timestamp('shipped_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->text('internal_notes')->nullable();
                $table->text('manufacturer_notes')->nullable();
                $table->timestamps();
                
                $table->foreign('episode_id')->references('id')->on('episodes');
                $table->foreign('product_request_id')->references('id')->on('product_requests');
                $table->foreign('ordered_by_user_id')->references('id')->on('users');
                $table->foreign('facility_id')->references('id')->on('organizations');
                $table->foreign('manufacturer_id')->references('id')->on('organizations');
                
                $table->index(['episode_id', 'status']);
                $table->index(['facility_id', 'status']);
                $table->index(['manufacturer_id', 'status']);
                $table->index('service_date');
                $table->index('verification_status');
            });
        }

        // 7.3 Order Items
        if (!Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('order_id', 36);
                $table->char('product_id', 36);
                $table->integer('quantity');
                $table->string('unit_of_measure', 20)->default('each');
                $table->decimal('unit_price', 10, 2)->nullable();
                $table->decimal('discount_percentage', 5, 2)->default(0);
                $table->decimal('line_total', 10, 2)->storedAs('quantity * unit_price * (1 - discount_percentage/100)');
                $table->text('specific_indication')->nullable();
                $table->timestamp('created_at')->useCurrent();
                
                $table->foreign('order_id')->references('id')->on('orders');
                $table->foreign('product_id')->references('id')->on('products');
                
                $table->index('order_id');
                $table->index('product_id');
            });
        }

        // =====================================================
        // SECTION 8: VERIFICATIONS
        // =====================================================

        // 8.1 Verifications - Unified verification system
        if (!Schema::hasTable('verifications')) {
            Schema::create('verifications', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('episode_id', 36);
                $table->char('order_id', 36)->nullable();
                $table->enum('verification_type', ['insurance_eligibility', 'prior_authorization', 'medical_necessity', 'provider_license']);
                $table->string('verification_subtype', 100)->nullable();
                $table->char('required_by_organization_id', 36);
                $table->char('payer_organization_id', 36)->nullable();
                $table->string('form_template_id', 255)->nullable();
                $table->enum('form_provider', ['docuseal', 'office_ally', 'availity', 'internal', 'manual'])->default('internal');
                $table->enum('status', ['not_started', 'pending', 'in_progress', 'under_review', 'completed', 'expired', 'failed'])->default('not_started');
                $table->json('required_fields')->nullable();
                $table->json('completed_fields')->nullable();
                $table->decimal('completeness_percentage', 5, 2)->default(0);
                $table->enum('determination', ['approved', 'denied', 'partial', 'pending'])->nullable();
                $table->json('coverage_details')->nullable();
                $table->string('external_submission_id', 255)->nullable();
                $table->string('external_status', 100)->nullable();
                $table->date('verified_date')->nullable();
                $table->date('expires_date')->nullable();
                $table->json('submitted_document_ids')->nullable();
                $table->timestamps();
                $table->timestamp('completed_at')->nullable();
                
                $table->foreign('episode_id')->references('id')->on('episodes');
                $table->foreign('order_id')->references('id')->on('orders');
                $table->foreign('required_by_organization_id')->references('id')->on('organizations');
                $table->foreign('payer_organization_id')->references('id')->on('organizations');
                
                $table->index(['episode_id', 'status']);
                $table->index(['order_id', 'status']);
                $table->index('verification_type');
                $table->index('expires_date');
            });
        }

        // =====================================================
        // SECTION 9: COMPLIANCE
        // =====================================================

        // 9.1 Compliance Rules
        if (!Schema::hasTable('compliance_rules')) {
            Schema::create('compliance_rules', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('tenant_id', 36);
                $table->string('rule_name', 255);
                $table->enum('rule_type', ['medicare_lcd', 'medicare_ncd', 'payer_policy', 'state_regulation', 'internal_policy']);
                $table->json('applies_to_categories')->nullable();
                $table->json('applies_to_products')->nullable();
                $table->json('applies_to_states')->nullable();
                $table->json('applies_to_payers')->nullable();
                $table->enum('rule_engine', ['json_logic', 'javascript', 'regex'])->default('json_logic');
                $table->text('rule_definition');
                $table->json('required_documentation')->nullable();
                $table->json('required_fields')->nullable();
                $table->enum('severity', ['error', 'warning', 'info'])->default('error');
                $table->boolean('can_override')->default(false);
                $table->date('effective_date');
                $table->date('expiration_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->useCurrent();
                
                $table->foreign('tenant_id')->references('id')->on('tenants');
                
                $table->index(['tenant_id', 'is_active']);
                $table->index('rule_type');
                $table->index(['effective_date', 'expiration_date']);
            });
        }

        // 9.2 Order Compliance Checks
        if (!Schema::hasTable('order_compliance_checks')) {
            Schema::create('order_compliance_checks', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('order_id', 36);
                $table->enum('check_type', ['pre_submission', 'pre_approval', 'final']);
                $table->boolean('passed');
                $table->json('applied_rules')->nullable();
                $table->json('failures')->nullable();
                $table->json('warnings')->nullable();
                $table->boolean('overridden')->default(false);
                $table->text('override_reason')->nullable();
                $table->char('overridden_by', 36)->nullable();
                $table->timestamp('checked_at')->useCurrent();
                
                $table->foreign('order_id')->references('id')->on('orders');
                $table->foreign('overridden_by')->references('id')->on('users');
                
                $table->index(['order_id', 'check_type']);
                $table->index('passed');
            });
        }

        // =====================================================
        // SECTION 10: DOCUMENTS
        // =====================================================

        // 10.1 Documents - Metadata Only
        if (!Schema::hasTable('documents')) {
            Schema::create('documents', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->enum('entity_type', ['episode', 'order', 'verification', 'product_request']);
                $table->char('entity_id', 36);
                $table->string('document_type', 50);
                $table->string('document_name', 255);
                $table->string('storage_path', 500)->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->bigInteger('file_size_bytes')->nullable();
                $table->boolean('requires_signature')->default(false);
                $table->enum('signature_type', ['patient', 'provider', 'witness', 'notary'])->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->enum('signature_method', ['docuseal', 'manual', 'tablet'])->nullable();
                $table->json('metadata')->nullable();
                $table->char('uploaded_by', 36)->nullable();
                $table->timestamp('uploaded_at')->useCurrent();
                $table->date('retention_until')->nullable();
                
                $table->foreign('uploaded_by')->references('id')->on('users');
                
                $table->index(['entity_type', 'entity_id']);
                $table->index('document_type');
                $table->index('retention_until');
            });
        }

        // =====================================================
        // SECTION 11: COMMISSIONS
        // =====================================================

        // 11.1 Commission Rules
        if (!Schema::hasTable('commission_rules')) {
            Schema::create('commission_rules', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('tenant_id', 36);
                $table->string('rule_name', 255);
                $table->json('applies_to_products')->nullable();
                $table->json('applies_to_categories')->nullable();
                $table->json('applies_to_facilities')->nullable();
                $table->enum('commission_type', ['percentage', 'flat_amount', 'tiered']);
                $table->decimal('base_rate', 10, 4)->nullable();
                $table->json('tier_definitions')->nullable();
                $table->json('split_rules')->nullable();
                $table->date('effective_date');
                $table->date('end_date')->nullable();
                $table->timestamp('created_at')->useCurrent();
                
                $table->foreign('tenant_id')->references('id')->on('tenants');
                
                $table->index(['tenant_id', 'effective_date']);
                $table->index('commission_type');
            });
        }

        // 11.2 Commission Records
        if (!Schema::hasTable('commission_records')) {
            Schema::create('commission_records', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('order_id', 36);
                $table->char('user_id', 36);
                $table->char('rule_id', 36);
                $table->decimal('base_amount', 10, 2);
                $table->decimal('commission_amount', 10, 2);
                $table->enum('status', ['pending', 'approved', 'paid', 'cancelled', 'clawback'])->default('pending');
                $table->string('payment_period', 20)->nullable();
                $table->date('paid_date')->nullable();
                $table->string('payment_reference', 100)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('approved_at')->nullable();
                
                $table->foreign('order_id')->references('id')->on('orders');
                $table->foreign('user_id')->references('id')->on('users');
                $table->foreign('rule_id')->references('id')->on('commission_rules');
                
                $table->index(['user_id', 'status']);
                $table->index(['order_id', 'status']);
                $table->index('payment_period');
            });
        }

        // =====================================================
        // SECTION 12: INTEGRATION & AUDIT
        // =====================================================

        // 12.1 Integration Events
        if (!Schema::hasTable('integration_events')) {
            Schema::create('integration_events', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->string('entity_type', 50)->nullable();
                $table->char('entity_id', 36)->nullable();
                $table->enum('integration_type', ['fhir', 'docuseal', 'availity', 'optum', 'office_ally']);
                $table->string('event_type', 100);
                $table->json('request_data')->nullable();
                $table->json('response_data')->nullable();
                $table->enum('status', ['success', 'failure', 'timeout', 'partial']);
                $table->text('error_message')->nullable();
                $table->integer('duration_ms')->nullable();
                $table->timestamp('created_at')->useCurrent();
                
                $table->index(['entity_type', 'entity_id']);
                $table->index(['integration_type', 'status']);
                $table->index('created_at');
            });
        }

        // 12.2 Audit Logs
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('user_id', 36)->nullable();
                $table->string('acting_as', 255)->nullable();
                $table->string('action', 100);
                $table->string('entity_type', 50)->nullable();
                $table->char('entity_id', 36)->nullable();
                $table->json('changes')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent();
                
                $table->foreign('user_id')->references('id')->on('users');
                
                $table->index(['entity_type', 'entity_id']);
                $table->index('action');
                $table->index('created_at');
                $table->index('user_id');
            });
        }

        // =====================================================
        // SECTION 13: SYSTEM TABLES
        // =====================================================

        // 13.1 Failed Jobs
        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        // 13.2 Password Reset Tokens
        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        // 13.3 Sessions
        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->char('user_id', 36)->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        // 13.4 Cache
        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        // 13.5 Cache Locks
        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }

        // =====================================================
        // SECTION 14: HELPFUL VIEWS
        // =====================================================

        // Office manager order view (NO FINANCIAL DATA)
        DB::statement("DROP VIEW IF EXISTS office_manager_order_view");
        DB::statement("
            CREATE VIEW office_manager_order_view AS
            SELECT 
                o.id,
                o.order_number,
                o.episode_id,
                o.status,
                o.order_type,
                o.ordering_provider_fhir_id,
                o.service_date,
                o.verification_status,
                o.submitted_at,
                o.delivered_at,
                p.patient_display_id,
                e.episode_number,
                e.type as episode_type,
                f.name as facility_name
            FROM orders o
            JOIN episodes e ON o.episode_id = e.id
            JOIN patient_references p ON e.patient_fhir_id = p.patient_fhir_id
            JOIN organizations f ON o.facility_id = f.id
        ");

        // Provider financial view
        DB::statement("DROP VIEW IF EXISTS provider_order_financial_view");
        DB::statement("
            CREATE VIEW provider_order_financial_view AS
            SELECT 
                o.*,
                e.episode_number,
                p.patient_display_id,
                f.name as facility_name,
                m.name as manufacturer_name
            FROM orders o
            JOIN episodes e ON o.episode_id = e.id
            JOIN patient_references p ON e.patient_fhir_id = p.patient_fhir_id
            JOIN organizations f ON o.facility_id = f.id
            LEFT JOIN organizations m ON o.manufacturer_id = m.id
        ");

        // Episode summary with verification status
        DB::statement("DROP VIEW IF EXISTS episode_verification_summary");
        DB::statement("
            CREATE VIEW episode_verification_summary AS
            SELECT 
                e.id as episode_id,
                e.episode_number,
                e.status as episode_status,
                COUNT(DISTINCT v.id) as total_verifications,
                COUNT(DISTINCT CASE WHEN v.status = 'completed' THEN v.id END) as completed_verifications,
                COUNT(DISTINCT CASE WHEN v.status = 'expired' THEN v.id END) as expired_verifications,
                MIN(v.expires_date) as next_expiration_date
            FROM episodes e
            LEFT JOIN verifications v ON v.episode_id = e.id
            GROUP BY e.id, e.episode_number, e.status
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop views first
        DB::statement('DROP VIEW IF EXISTS episode_verification_summary');
        DB::statement('DROP VIEW IF EXISTS provider_order_financial_view');
        DB::statement('DROP VIEW IF EXISTS office_manager_order_view');

        // Drop tables in reverse order of dependencies
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('integration_events');
        Schema::dropIfExists('commission_records');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('order_compliance_checks');
        Schema::dropIfExists('compliance_rules');
        Schema::dropIfExists('verifications');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('product_requests');
        Schema::dropIfExists('products');
        Schema::dropIfExists('episode_care_team');
        Schema::dropIfExists('episodes');
        Schema::dropIfExists('patient_references');
        Schema::dropIfExists('user_facility_assignments');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('tenants');
    }
};
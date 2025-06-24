<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Base tables (no foreign keys)
        Schema::create('accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 50);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('hierarchy_level')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('hierarchy_level');
            $table->index('slug');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('guard_name')->default('web');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('guard_name');
        });

        // 2. Tables with foreign keys to base tables
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_id');
            $table->string('first_name', 25);
            $table->string('last_name', 25);
            $table->string('email', 50)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->boolean('owner')->default(false);
            $table->string('photo', 100)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')
                  ->references('id')
                  ->on('accounts')
                  ->onDelete('cascade');
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_id')->index();
            $table->string('name', 100);
            $table->string('email', 50)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address', 150)->nullable();
            $table->string('city', 50)->nullable();
            $table->string('region', 50)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('postal_code', 25)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')
                  ->references('id')
                  ->on('accounts')
                  ->onDelete('cascade');

            $table->index('email');
            $table->index('country');
        });

        // 3. Tables with foreign keys to users and organizations
        Schema::create('facilities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->string('facility_type');
            $table->string('address');
            $table->string('city');
            $table->string('state');
            $table->string('zip_code');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('npi')->nullable();
            $table->json('business_hours')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations')
                  ->onDelete('cascade');

            $table->index('facility_type');
            $table->index('active');
            $table->index('npi');
        });

        Schema::create('msc_sales_reps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('territory')->nullable();
            $table->decimal('commission_rate_direct', 5, 2)->default(0);
            $table->decimal('sub_rep_parent_share_percentage', 5, 2)->default(50);
            $table->unsignedBigInteger('parent_rep_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_rep_id')
                  ->references('id')
                  ->on('msc_sales_reps')
                  ->onDelete('set null');

            $table->index('parent_rep_id');
            $table->index('is_active');
            $table->index('email');
            $table->index('territory');
        });

        // 4. Pivot tables for roles and permissions
        Schema::create('role_permission', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->timestamps();

            $table->foreign('role_id')
                  ->references('id')
                  ->on('roles')
                  ->onDelete('cascade');

            $table->foreign('permission_id')
                  ->references('id')
                  ->on('permissions')
                  ->onDelete('cascade');

            $table->unique(['role_id', 'permission_id']);
            $table->index(['role_id', 'permission_id']);
        });

        Schema::create('user_role', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->foreign('role_id')
                  ->references('id')
                  ->on('roles')
                  ->onDelete('cascade');

            $table->unique(['user_id', 'role_id']);
            $table->index(['user_id', 'role_id']);
        });

        // Create manufacturers table
        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('is_active');
        });

        // 5. Product and order related tables
        Schema::create('msc_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('sku')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('manufacturer')->nullable();
            $table->unsignedBigInteger('manufacturer_id')->nullable();
            $table->string('category')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->decimal('national_asp', 10, 2)->nullable();
            $table->decimal('price_per_sq_cm', 10, 4)->nullable();
            $table->string('q_code', 10)->nullable();
            $table->json('available_sizes')->nullable();
            $table->string('graph_type')->nullable();
            $table->string('image_url')->nullable();
            $table->json('document_urls')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['manufacturer_id', 'category_id']);
            $table->index('is_active');
            $table->index('sku');
            $table->index('category');
            $table->index('graph_type');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_number')->unique();
            $table->string('patient_fhir_id');
            $table->unsignedBigInteger('facility_id');
            $table->unsignedBigInteger('sales_rep_id')->nullable();
            $table->date('date_of_service');
            $table->string('credit_terms')->default('net60');
            $table->enum('status', ['pending', 'confirmed', 'shipped', 'fulfilled', 'cancelled'])->default('pending');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('expected_reimbursement', 10, 2)->default(0);
            $table->date('expected_collection_date')->nullable();
            $table->string('payment_status')->default('pending');
            $table->decimal('msc_commission_structure', 5, 2)->default(40);
            $table->decimal('msc_commission', 10, 2)->default(0);
            $table->json('document_urls')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('facility_id')
                  ->references('id')
                  ->on('facilities')
                  ->onDelete('restrict');

            $table->foreign('sales_rep_id')
                  ->references('id')
                  ->on('msc_sales_reps')
                  ->onDelete('set null');

            $table->index('patient_fhir_id');
            $table->index('status');
            $table->index('date_of_service');
            $table->index('payment_status');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
            $table->string('graph_size')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('order_id')
                  ->references('id')
                  ->on('orders')
                  ->onDelete('cascade');

            $table->foreign('product_id')
                  ->references('id')
                  ->on('msc_products')
                  ->onDelete('restrict');

            $table->index('quantity');
            $table->index('graph_size');
        });

        // 6. Commission related tables
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->decimal('percentage_rate', 5, 2);
            $table->timestamp('valid_from')->useCurrent();
            $table->timestamp('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['target_type', 'target_id']);
            $table->index('valid_from');
            $table->index('valid_to');
            $table->index('is_active');
            $table->index('percentage_rate');
        });

        // Create commission_payouts before commission_records since it's referenced
        Schema::create('commission_payouts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rep_id');
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->string('status')->default('calculated');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('rep_id')
                  ->references('id')
                  ->on('msc_sales_reps')
                  ->onDelete('cascade');

            $table->foreign('approved_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->index('status');
            $table->index(['period_start', 'period_end']);
            $table->index('payment_reference');
        });

        Schema::create('commission_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('rep_id');
            $table->unsignedBigInteger('parent_rep_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('percentage_rate', 5, 2);
            $table->string('type');
            $table->string('status')->default('pending');
            $table->timestamp('calculation_date')->useCurrent();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('payout_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('order_id')
                  ->references('id')
                  ->on('orders')
                  ->onDelete('restrict');

            $table->foreign('order_item_id')
                  ->references('id')
                  ->on('order_items')
                  ->onDelete('restrict');

            $table->foreign('rep_id')
                  ->references('id')
                  ->on('msc_sales_reps')
                  ->onDelete('restrict');

            $table->foreign('parent_rep_id')
                  ->references('id')
                  ->on('msc_sales_reps')
                  ->onDelete('set null');

            $table->foreign('approved_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->foreign('payout_id')
                  ->references('id')
                  ->on('commission_payouts')
                  ->onDelete('set null');

            // Enhanced performance indexes
            $table->index(['rep_id', 'status']);
            $table->index(['calculation_date', 'status']);
            $table->index('payout_id');
            $table->index('type');
        });

        // 7. Product requests and related tables
        Schema::create('product_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('request_number')->unique();
            $table->unsignedBigInteger('provider_id');
            $table->string('patient_fhir_id');
            $table->string('patient_display_id', 7)->nullable();
            $table->unsignedBigInteger('facility_id');
            $table->string('payer_name_submitted');
            $table->string('payer_id')->nullable();
            $table->date('expected_service_date');
            $table->enum('wound_type', ['DFU', 'VLU', 'PU', 'TW', 'AU', 'OTHER']);
            $table->string('azure_order_checklist_fhir_id')->nullable();
            $table->json('clinical_summary')->nullable();
            $table->json('mac_validation_results')->nullable();
            $table->string('mac_validation_status')->nullable();
            $table->json('eligibility_results')->nullable();
            $table->string('eligibility_status')->nullable();
            $table->enum('pre_auth_required_determination', ['required', 'not_required', 'pending'])->nullable();
            $table->string('pre_auth_status')->nullable();
            $table->timestamp('pre_auth_submitted_at')->nullable();
            $table->timestamp('pre_auth_approved_at')->nullable();
            $table->timestamp('pre_auth_denied_at')->nullable();
            $table->json('clinical_opportunities')->nullable();
            $table->enum('order_status', ['draft', 'submitted', 'processing', 'approved', 'rejected', 'shipped', 'delivered', 'cancelled'])->default('draft');
            $table->integer('step')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->decimal('total_order_value', 10, 2)->nullable();
            $table->unsignedBigInteger('acquiring_rep_id')->nullable();
            $table->string('place_of_service', 10)->nullable();
            $table->boolean('medicare_part_b_authorized')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('provider_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->foreign('facility_id')
                  ->references('id')
                  ->on('facilities')
                  ->onDelete('restrict');

            $table->foreign('acquiring_rep_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->index('provider_id');
            $table->index('facility_id');
            $table->index('order_status');
            $table->index('patient_display_id');
            $table->index(['facility_id', 'patient_display_id']);
            $table->index('wound_type');
            $table->index('expected_service_date');
            $table->index(['order_status', 'step']);
            $table->index('request_number');
            $table->index('place_of_service');
        });

        // 8. Pre-authorizations table
        Schema::create('pre_authorizations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_request_id');
            $table->string('authorization_number')->nullable();
            $table->string('payer_name');
            $table->string('patient_id');
            $table->text('clinical_documentation')->nullable();
            $table->enum('urgency', ['routine', 'urgent', 'emergency'])->default('routine');
            $table->enum('status', ['pending', 'submitted', 'approved', 'denied', 'cancelled'])->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->timestamp('last_status_check')->nullable();
            $table->string('payer_transaction_id')->nullable();
            $table->string('payer_confirmation')->nullable();
            $table->json('payer_response')->nullable();
            $table->timestamp('estimated_approval_date')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_request_id')
                  ->references('id')
                  ->on('product_requests')
                  ->onDelete('cascade');

            $table->foreign('submitted_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->index('product_request_id');
            $table->index('status');
            $table->index('authorization_number');
            $table->index('payer_transaction_id');
            $table->index('submitted_at');
            $table->index('expires_at');
            $table->index('urgency');
        });

        // 9. Payments table
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('order_id');
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['check', 'wire', 'ach', 'credit_card', 'other'])->default('check');
            $table->string('reference_number')->nullable();
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'posted', 'cancelled'])->default('posted');
            $table->unsignedBigInteger('posted_by_user_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('provider_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->foreign('order_id')
                  ->references('id')
                  ->on('orders')
                  ->onDelete('cascade');

            $table->foreign('posted_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');

            $table->index('payment_date');
            $table->index('status');
            $table->index(['provider_id', 'order_id']);
            $table->index('payment_method');
            $table->index('reference_number');
        });

        // 10. Sales rep organizations table
        Schema::create('sales_rep_organizations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('organization_id');
            $table->enum('relationship_type', ['primary', 'secondary', 'referral'])->default('primary');
            $table->decimal('commission_override', 5, 2)->nullable();
            $table->boolean('can_create_orders')->default(false);
            $table->boolean('can_view_all_data')->default(true);
            $table->date('assigned_from')->useCurrent();
            $table->date('assigned_until')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations')
                  ->onDelete('cascade');

            $table->unique(['user_id', 'organization_id'], 'user_organization_unique_idx');
            $table->index(['user_id', 'relationship_type'], 'user_relationship_type_idx');
            $table->index(['user_id', 'assigned_until'], 'user_assigned_until_idx');
            $table->index('relationship_type');
        });

        // 11. Provider sales rep assignments table
        Schema::create('provider_sales_rep_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('sales_rep_id');
            $table->unsignedBigInteger('facility_id')->nullable();
            $table->enum('relationship_type', ['primary', 'secondary', 'referral', 'coverage'])->default('primary');
            $table->decimal('commission_split_percentage', 5, 2)->default(100.00);
            $table->boolean('can_create_orders')->default(true);
            $table->date('assigned_from')->useCurrent();
            $table->date('assigned_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

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

            $table->index(['provider_id', 'is_active'], 'psra_provider_active_idx');
            $table->index(['sales_rep_id', 'is_active'], 'psra_rep_active_idx');
            $table->index(['facility_id', 'is_active'], 'psra_facility_active_idx');
            $table->index(['provider_id', 'relationship_type'], 'psra_provider_rel_idx');
            $table->unique(['provider_id', 'relationship_type', 'assigned_until'], 'psra_provider_primary_unique');
        });

        // 12. Facility sales rep assignments table
        Schema::create('facility_sales_rep_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('facility_id');
            $table->unsignedBigInteger('sales_rep_id');
            $table->enum('relationship_type', ['coordinator', 'backup', 'manager'])->default('coordinator');
            $table->decimal('commission_split_percentage', 5, 2)->default(0.00);
            $table->boolean('can_create_orders')->default(false);
            $table->date('assigned_from')->useCurrent();
            $table->date('assigned_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('facility_id')
                  ->references('id')
                  ->on('facilities')
                  ->onDelete('cascade');

            $table->foreign('sales_rep_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->index(['facility_id', 'is_active']);
            $table->index(['sales_rep_id', 'is_active']);
            $table->index('relationship_type');
            $table->index('assigned_until');
        });

        // 13. Medicare MAC validations table
        Schema::create('medicare_mac_validations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('validation_id', 36)->unique();
            $table->unsignedBigInteger('order_id');
            $table->string('patient_fhir_id')->nullable();
            $table->unsignedBigInteger('facility_id');
            $table->string('mac_contractor')->nullable();
            $table->string('mac_jurisdiction')->nullable();
            $table->string('mac_region')->nullable();
            $table->string('patient_zip_code')->nullable()->comment('Patient ZIP code used for MAC jurisdiction determination');
            $table->string('addressing_method')->nullable()->comment('Method used for MAC addressing (patient_address, zip_code_specific, state_based, etc.)');
            $table->enum('validation_type', ['vascular_wound_care', 'wound_care_only', 'vascular_only'])->default('wound_care_only');
            $table->enum('validation_status', ['pending', 'validated', 'failed', 'requires_review', 'revalidated'])->default('pending');
            $table->json('validation_results')->nullable();
            $table->json('coverage_policies')->nullable();
            $table->boolean('coverage_met')->default(false);
            $table->text('coverage_notes')->nullable();
            $table->json('coverage_requirements')->nullable();
            $table->json('procedures_validated')->nullable();
            $table->json('cpt_codes_validated')->nullable();
            $table->json('hcpcs_codes_validated')->nullable();
            $table->json('icd10_codes_validated')->nullable();
            $table->boolean('documentation_complete')->default(false);
            $table->json('required_documentation')->nullable();
            $table->json('missing_documentation')->nullable();
            $table->json('documentation_status')->nullable();
            $table->boolean('frequency_compliant')->default(false);
            $table->text('frequency_notes')->nullable();
            $table->boolean('medical_necessity_met')->default(false);
            $table->text('medical_necessity_notes')->nullable();
            $table->boolean('prior_auth_required')->default(false);
            $table->boolean('prior_auth_obtained')->default(false);
            $table->string('prior_auth_number')->nullable();
            $table->date('prior_auth_expiry')->nullable();
            $table->boolean('billing_compliant')->default(false);
            $table->json('billing_issues')->nullable();
            $table->decimal('estimated_reimbursement', 10, 2)->nullable();
            $table->enum('reimbursement_risk', ['low', 'medium', 'high'])->default('medium');
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('last_revalidated_at')->nullable();
            $table->timestamp('next_validation_due')->nullable();
            $table->string('validated_by')->nullable();
            $table->string('validation_source')->default('system');
            $table->json('validation_errors')->nullable();
            $table->json('validation_warnings')->nullable();
            $table->boolean('daily_monitoring_enabled')->default(false);
            $table->timestamp('last_monitored_at')->nullable();
            $table->integer('validation_count')->default(1);
            $table->json('audit_trail')->nullable();
            $table->string('provider_specialty')->nullable();
            $table->string('provider_npi')->nullable();
            $table->json('specialty_requirements')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('order_id')
                  ->references('id')
                  ->on('orders')
                  ->onDelete('cascade');

            $table->foreign('facility_id')
                  ->references('id')
                  ->on('facilities')
                  ->onDelete('cascade');

            $table->index(['order_id', 'validation_status']);
            $table->index(['facility_id', 'validation_type']);
            $table->index(['mac_contractor', 'mac_jurisdiction']);
            $table->index(['validation_status', 'validated_at']);
            $table->index('next_validation_due');
            $table->index(['daily_monitoring_enabled', 'last_monitored_at'], 'mmv_daily_mon_last_mon_idx');
            $table->index(['provider_specialty', 'validation_type'], 'mmv_prov_spec_valtype_idx');
            $table->index('patient_zip_code');
            $table->index('addressing_method');
        });

        // 14. ICD-10 and CPT codes tables
        Schema::create('icd10_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 10)->unique();
            $table->text('description');
            $table->string('category', 10)->nullable();
            $table->string('subcategory', 10)->nullable();
            $table->boolean('is_billable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('version', 10)->default('2024');
            $table->timestamps();

            $table->index('code');
            $table->index('category');
            $table->index('subcategory');
            $table->index('is_billable');
            $table->index('is_active');
            $table->index(['category', 'is_active']);
            if (Schema::connection($this->getConnection())->getConnection()->getDriverName() !== 'sqlite') {
                $table->fullText(['code', 'description']);
            }
        });

        Schema::create('cpt_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 10)->unique();
            $table->text('description');
            $table->string('category', 10)->nullable();
            $table->boolean('is_billable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('version', 10)->default('2024');
            $table->timestamps();

            $table->index('code');
            $table->index('category');
            $table->index('is_billable');
            $table->index('is_active');
            $table->index(['category', 'is_active']);
            if (Schema::connection($this->getConnection())->getConnection()->getDriverName() !== 'sqlite') {
                $table->fullText(['code', 'description']);
            }
        });

        // 15. Pre-authorization related tables
        Schema::create('pre_authorization_diagnosis_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pre_authorization_id');
            $table->unsignedBigInteger('icd10_code_id');
            $table->enum('type', ['primary', 'secondary', 'other'])->default('secondary');
            $table->integer('sequence')->default(1);
            $table->timestamps();

            $table->foreign('pre_authorization_id')
                  ->references('id')
                  ->on('pre_authorizations')
                  ->onDelete('cascade');

            $table->foreign('icd10_code_id')
                  ->references('id')
                  ->on('icd10_codes')
                  ->onDelete('cascade');

            $table->unique(['pre_authorization_id', 'icd10_code_id'], 'pre_auth_dx_codes_unique');
            $table->index('pre_authorization_id', 'pre_auth_dx_pre_auth_idx');
            $table->index('icd10_code_id', 'pre_auth_dx_icd10_idx');
            $table->index(['pre_authorization_id', 'type'], 'pre_auth_dx_type_idx');
            $table->index(['pre_authorization_id', 'sequence'], 'pre_auth_dx_seq_idx');
        });

        Schema::create('pre_authorization_procedure_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pre_authorization_id');
            $table->unsignedBigInteger('cpt_code_id');
            $table->integer('quantity')->default(1);
            $table->string('modifier', 10)->nullable();
            $table->integer('sequence')->default(1);
            $table->timestamps();

            $table->foreign('pre_authorization_id')
                  ->references('id')
                  ->on('pre_authorizations')
                  ->onDelete('cascade');

            $table->foreign('cpt_code_id')
                  ->references('id')
                  ->on('cpt_codes')
                  ->onDelete('cascade');

            $table->unique(['pre_authorization_id', 'cpt_code_id', 'modifier'], 'pre_auth_cpt_modifier_unique');
            $table->index('pre_authorization_id', 'pre_auth_cpt_pre_auth_idx');
            $table->index('cpt_code_id', 'pre_auth_cpt_code_idx');
            $table->index(['pre_authorization_id', 'sequence'], 'pre_auth_cpt_seq_idx');
        });

        // 16. Patient associations table
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
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['patient_fhir_id', 'provider_id', 'facility_id', 'terminated_at'], 'patient_provider_facility_term_unique');
            $table->index(['patient_fhir_id', 'provider_id', 'active']);
            $table->index(['facility_id', 'active']);
            $table->index(['organization_id', 'association_type']);
            $table->index(['established_at', 'terminated_at']);
        });

        // 17. Provider credentials table
        Schema::create('provider_credentials', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('provider_id');
            $table->enum('credential_type', [
                'medical_license',
                'board_certification',
                'dea_registration',
                'npi_number',
                'hospital_privileges',
                'malpractice_insurance',
                'continuing_education',
                'state_license',
                'specialty_certification'
            ]);
            $table->string('credential_number');
            $table->string('credential_display_name')->nullable();
            $table->string('issuing_authority');
            $table->string('issuing_state')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->date('effective_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('provider_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->index(['provider_id', 'credential_type']);
            $table->index('expiration_date');
            $table->index('credential_number');
        });

        // 18. MSC product recommendation rules table
        Schema::create('msc_product_recommendation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->text('description')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->enum('wound_type', ['DFU', 'VLU', 'PU', 'TW', 'AU', 'OTHER'])->nullable();
            $table->string('wound_stage')->nullable();
            $table->string('wound_depth')->nullable();
            $table->json('conditions')->nullable();
            $table->json('recommended_msc_product_qcodes_ranked');
            $table->json('reasoning_templates')->nullable();
            $table->string('default_size_suggestion_key')->nullable();
            $table->json('contraindications')->nullable();
            $table->json('clinical_evidence')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('last_updated_by_user_id')->nullable();
            $table->date('effective_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->foreign('last_updated_by_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->index(['is_active', 'wound_type']);
            $table->index(['effective_date', 'expiration_date'], 'msc_prr_eff_exp_idx');
            $table->index('priority');
        });

        // 19. Clinical opportunities and actions
        Schema::create('clinical_opportunities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_request_id');
            $table->string('opportunity_type');
            $table->string('status')->default('open');
            $table->text('description');
            $table->json('clinical_data')->nullable();
            $table->json('recommendations')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_request_id')
                  ->references('id')
                  ->on('product_requests')
                  ->onDelete('cascade');

            $table->foreign('assigned_to')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            // Use shorter custom index names
            $table->index(['product_request_id', 'status'], 'co_req_status_idx');
            $table->index('opportunity_type', 'co_type_idx');
            $table->index('assigned_to', 'co_assigned_idx');
            $table->index('due_date', 'co_due_date_idx');
        });

        Schema::create('clinical_opportunity_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clinical_opportunity_id');
            $table->string('action_type');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('clinical_opportunity_id')
                  ->references('id')
                  ->on('clinical_opportunities')
                  ->onDelete('cascade');

            $table->foreign('performed_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            // Use shorter custom index names
            $table->index(['clinical_opportunity_id', 'created_at'], 'coa_opp_created_idx');
            $table->index('action_type', 'coa_action_type_idx');
            $table->index('performed_by', 'coa_performed_by_idx');
        });

        // 20. Audit and logging tables
        Schema::create('profile_audit_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('entity_type', [
                'provider_profile',
                'provider_credential',
                'organization_profile',
                'facility_profile',
                'user_account'
            ]);
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_display_name')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('user_email')->nullable();
            $table->string('user_role')->nullable();
            $table->enum('action_type', [
                'create',
                'update',
                'delete',
                'verify',
                'approve',
                'reject',
                'suspend',
                'restore',
                'export',
                'view_sensitive'
            ]);
            $table->string('action_description')->nullable();
            $table->json('field_changes')->nullable();
            $table->json('metadata')->nullable(); // FIXED: Removed default value
            $table->timestamps();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->index(['entity_type', 'entity_id']);
            $table->index('action_type');
            $table->index('created_at');
        });

        Schema::create('rbac_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_type');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('entity_name')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->string('performed_by_name')->nullable();
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->string('target_user_email')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changes')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamps();

            $table->foreign('performed_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->foreign('target_user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->index(['event_type', 'entity_type']);
            $table->index('created_at');
        });

        // 15. System tables
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('password_resets', function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    public function down()
    {
        // Drop tables in reverse order to handle foreign key constraints
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('password_resets');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('rbac_audit_logs');
        Schema::dropIfExists('profile_audit_log');
        Schema::dropIfExists('clinical_opportunity_actions');
        Schema::dropIfExists('clinical_opportunities');
        Schema::dropIfExists('msc_product_recommendation_rules');
        Schema::dropIfExists('provider_credentials');
        Schema::dropIfExists('patient_associations');
        Schema::dropIfExists('pre_authorization_procedure_codes');
        Schema::dropIfExists('pre_authorization_diagnosis_codes');
        Schema::dropIfExists('cpt_codes');
        Schema::dropIfExists('icd10_codes');
        Schema::dropIfExists('medicare_mac_validations');
        Schema::dropIfExists('facility_sales_rep_assignments');
        Schema::dropIfExists('provider_sales_rep_assignments');
        Schema::dropIfExists('sales_rep_organizations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('pre_authorizations');
        Schema::dropIfExists('product_requests');
        Schema::dropIfExists('commission_records');
        Schema::dropIfExists('commission_payouts');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('msc_products');
        Schema::dropIfExists('user_role');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('msc_sales_reps');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('users');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('accounts');
    }
};

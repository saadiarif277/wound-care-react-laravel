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
        // ========================================
        // SECTION 1: CORE/BASE TABLES (NO DEPENDENCIES)
        // ========================================

        // Accounts table - top level tenant/account structure
        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 50);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // Categories table - product categories
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index('slug');
                $table->index('is_active');
            });
        }

        // Manufacturers table
        if (!Schema::hasTable('manufacturers')) {
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
                $table->string('docuseal_template_id')->nullable();
                $table->json('field_mappings')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('name');
                $table->index('is_active');
                $table->index('slug');
            });
        }

        // ========================================
        // SECTION 2: USER/AUTH TABLES
        // ========================================

        // Roles table
        if (!Schema::hasTable('roles')) {
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
        }

        // Permissions table
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->string('guard_name')->default('web');
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('guard_name');
                $table->index('slug');
            });
        }

        // Users table
        if (!Schema::hasTable('users')) {
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
                $table->string('role')->nullable();
                $table->unsignedBigInteger('current_organization_id')->nullable();
                $table->string('practitioner_fhir_id')->nullable();
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('account_id')
                      ->references('id')
                      ->on('accounts')
                      ->onDelete('cascade');

                $table->index('email');
                $table->index('current_organization_id');
                $table->index('practitioner_fhir_id');
            });
        }

        // Role-Permission pivot table
        if (!Schema::hasTable('role_permission')) {
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
        }

        // User-Role pivot table
        if (!Schema::hasTable('user_role')) {
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
        }

        // ========================================
        // SECTION 3: ORGANIZATION/FACILITY TABLES
        // ========================================

        // Organizations table
        if (!Schema::hasTable('organizations')) {
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
                $table->string('organization_type')->nullable();
                $table->string('tax_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('billing_address')->nullable();
                $table->string('billing_city')->nullable();
                $table->string('billing_state')->nullable();
                $table->string('billing_zip')->nullable();
                $table->string('billing_contact_name')->nullable();
                $table->string('billing_contact_email')->nullable();
                $table->string('billing_contact_phone')->nullable();
                $table->string('organization_fhir_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('account_id')
                      ->references('id')
                      ->on('accounts')
                      ->onDelete('cascade');

                $table->index('email');
                $table->index('country');
                $table->index('organization_fhir_id');
            });
        }

        // Add foreign key constraint to users table for current_organization_id
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('current_organization_id')
                  ->references('id')
                  ->on('organizations')
                  ->onDelete('set null');
        });

        // Facilities table
        if (!Schema::hasTable('facilities')) {
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
                $table->string('contact_name')->nullable();
                $table->string('contact_phone')->nullable();
                $table->string('contact_email')->nullable();
                $table->string('billing_address')->nullable();
                $table->string('billing_city')->nullable();
                $table->string('billing_state')->nullable();
                $table->string('billing_zip')->nullable();
                $table->boolean('is_billing_same_as_facility')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('organization_id')
                      ->references('id')
                      ->on('organizations')
                      ->onDelete('cascade');

                $table->index('facility_type');
                $table->index('active');
                $table->index('npi');
                $table->index(['organization_id', 'active']);
            });
        }

        // Facility-User pivot table
        if (!Schema::hasTable('facility_user')) {
            Schema::create('facility_user', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('facility_id');
                $table->unsignedBigInteger('user_id');
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->foreign('facility_id')
                      ->references('id')
                      ->on('facilities')
                      ->onDelete('cascade');

                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');

                $table->unique(['facility_id', 'user_id']);
                $table->index(['user_id', 'is_primary']);
            });
        }

        // Organization-Users table
        if (!Schema::hasTable('organization_users')) {
            Schema::create('organization_users', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->unsignedBigInteger('user_id');
                $table->string('role')->default('member');
                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->foreign('organization_id')
                      ->references('id')
                      ->on('organizations')
                      ->onDelete('cascade');

                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');

                $table->unique(['organization_id', 'user_id']);
                $table->index(['user_id', 'role']);
            });
        }

        // ========================================
        // SECTION 4: PRODUCT TABLES
        // ========================================

        // MSC Products table
        if (!Schema::hasTable('msc_products')) {
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
                $table->integer('mue')->nullable();
                $table->decimal('msc_price', 10, 2)->nullable();
                $table->string('code')->nullable();
                $table->json('size_labels')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('manufacturer_id')
                      ->references('id')
                      ->on('manufacturers')
                      ->onDelete('set null');

                $table->foreign('category_id')
                      ->references('id')
                      ->on('categories')
                      ->onDelete('set null');

                $table->index(['manufacturer_id', 'category_id']);
                $table->index('is_active');
                $table->index('sku');
                $table->index('category');
                $table->index('graph_type');
                $table->index('q_code');
            });
        }

        // Product Sizes table
        if (!Schema::hasTable('product_sizes')) {
            Schema::create('product_sizes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->string('size');
                $table->decimal('sq_cm', 10, 2)->nullable();
                $table->decimal('price', 10, 2);
                $table->decimal('cost', 10, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('product_id')
                      ->references('id')
                      ->on('msc_products')
                      ->onDelete('cascade');

                $table->index(['product_id', 'is_active']);
                $table->index('size');
            });
        }

        // Product Pricing History table
        if (!Schema::hasTable('product_pricing_history')) {
            Schema::create('product_pricing_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->string('size')->nullable();
                $table->decimal('old_price', 10, 2)->nullable();
                $table->decimal('new_price', 10, 2);
                $table->string('change_reason')->nullable();
                $table->unsignedBigInteger('changed_by');
                $table->timestamp('effective_date');
                $table->timestamps();

                $table->foreign('product_id')
                      ->references('id')
                      ->on('msc_products')
                      ->onDelete('cascade');

                $table->foreign('changed_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('restrict');

                $table->index(['product_id', 'effective_date']);
                $table->index('effective_date');
            });
        }

        // ========================================
        // SECTION 5: SALES & COMMISSION TABLES
        // ========================================

        // MSC Sales Reps table
        if (!Schema::hasTable('msc_sales_reps')) {
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
        }

        // Commission Rules table
        if (!Schema::hasTable('commission_rules')) {
            Schema::create('commission_rules', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('target_type'); // 'product', 'manufacturer', 'category'
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
        }

        // Sales Rep Organizations table
        if (!Schema::hasTable('sales_rep_organizations')) {
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
        }

        // Provider Sales Rep Assignments table
        if (!Schema::hasTable('provider_sales_rep_assignments')) {
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
        }

        // Facility Sales Rep Assignments table
        if (!Schema::hasTable('facility_sales_rep_assignments')) {
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
        }

        // ========================================
        // SECTION 6: DOCUSEAL/IVR TABLES
        // ========================================

        // DocuSeal Folders table
        if (!Schema::hasTable('docuseal_folders')) {
            Schema::create('docuseal_folders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('folder_name');
                $table->string('docuseal_folder_id')->unique();
                $table->unsignedBigInteger('parent_folder_id')->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('is_active');
            });
        }

        // DocuSeal Templates table
        if (!Schema::hasTable('docuseal_templates')) {
            Schema::create('docuseal_templates', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('template_name');
                $table->string('docuseal_template_id')->unique();
                $table->unsignedBigInteger('manufacturer_id')->nullable();
                $table->enum('document_type', ['InsuranceVerification', 'OrderForm', 'OnboardingForm', 'IVR']);
                $table->boolean('is_default')->default(false);
                $table->json('field_mappings');
                $table->boolean('is_active')->default(true);
                $table->json('discovered_fields')->nullable();
                $table->timestamp('field_discovery_completed_at')->nullable();
                $table->json('ai_analysis')->nullable();
                $table->timestamp('ai_analysis_completed_at')->nullable();
                $table->timestamps();

                $table->foreign('manufacturer_id')
                      ->references('id')
                      ->on('manufacturers')
                      ->onDelete('set null');

                $table->index(['document_type', 'is_active']);
                $table->index(['manufacturer_id', 'is_active']);
            });
        }

        // DocuSeal Submissions table
        if (!Schema::hasTable('docuseal_submissions')) {
            Schema::create('docuseal_submissions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('docuseal_submission_id')->unique();
                $table->uuid('docuseal_template_id');
                $table->morphs('submittable'); // Can be used for any model
                $table->enum('status', ['pending', 'sent', 'viewed', 'signed', 'completed', 'expired', 'declined']);
                $table->json('signers')->nullable();
                $table->json('field_values')->nullable();
                $table->string('signed_document_url')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('viewed_at')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('expired_at')->nullable();
                $table->timestamps();

                $table->foreign('docuseal_template_id')
                      ->references('id')
                      ->on('docuseal_templates')
                      ->onDelete('cascade');

                $table->index('status');
                $table->index('docuseal_submission_id');
            });
        }

        // Patient Manufacturer IVR Episodes table
        if (!Schema::hasTable('patient_manufacturer_ivr_episodes')) {
            Schema::create('patient_manufacturer_ivr_episodes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('patient_id'); // Changed from uuid to string to support both FHIR IDs and display IDs
                $table->string('patient_fhir_id')->nullable();
                $table->string('patient_display_id', 10)->nullable();
                $table->unsignedBigInteger('manufacturer_id')->nullable();
                $table->string('manufacturer_name')->nullable();
                $table->string('template_id')->nullable();
                $table->string('status')->default('ready_for_review');
                $table->string('ivr_status')->nullable();
                $table->date('verification_date')->nullable();
                $table->date('expiration_date')->nullable();
                $table->integer('frequency_days')->default(90);
                $table->uuid('created_by')->nullable();
                $table->string('docuseal_submission_id')->nullable();
                $table->string('docuseal_status')->default('pending');
                $table->timestamp('docuseal_completed_at')->nullable();
                $table->decimal('field_mapping_completeness', 5, 2)->nullable();
                $table->decimal('required_fields_completeness', 5, 2)->nullable();
                $table->json('mapped_fields')->nullable();
                $table->json('validation_warnings')->nullable();
                $table->timestamp('viewed_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('expired_at')->nullable();
                $table->text('signed_document_url')->nullable();
                $table->timestamps();

                $table->foreign('manufacturer_id')
                      ->references('id')
                      ->on('manufacturers')
                      ->onDelete('set null');

                $table->index(['status'], 'pm_episodes_status_idx');
                $table->index(['ivr_status'], 'pm_episodes_ivr_status_idx');
                $table->index(['patient_id', 'manufacturer_id'], 'pm_episodes_patient_mfg_idx');
                $table->index(['verification_date'], 'pm_episodes_verification_idx');
                $table->index(['expiration_date'], 'pm_episodes_expiration_idx');
                $table->index('docuseal_status', 'pmi_episodes_docuseal_status_idx');
                $table->index('field_mapping_completeness', 'pmi_episodes_field_completeness_idx');
                $table->index('patient_fhir_id');
                $table->index('patient_display_id');
            });
        }

        // Quick Request Submissions table
        if (!Schema::hasTable('quick_request_submissions')) {
            Schema::create('quick_request_submissions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('provider_id');
                $table->unsignedBigInteger('facility_id');
                $table->integer('step')->default(1);
                $table->json('form_data');
                $table->enum('status', ['in_progress', 'completed', 'abandoned'])->default('in_progress');
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->foreign('provider_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');

                $table->foreign('facility_id')
                      ->references('id')
                      ->on('facilities')
                      ->onDelete('cascade');

                $table->index(['provider_id', 'status']);
                $table->index(['facility_id', 'status']);
                $table->index('created_at');
            });
        }

        // ========================================
        // SECTION 7: ORDER/REQUEST TABLES
        // ========================================

        // Orders table
        if (!Schema::hasTable('orders')) {
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
                $table->uuid('ivr_episode_id')->nullable();
                $table->string('docuseal_template_id')->nullable();
                $table->string('docuseal_submission_id')->nullable();
                $table->string('docuseal_document_id')->nullable();
                $table->string('docuseal_status')->nullable();
                $table->timestamp('docuseal_sent_at')->nullable();
                $table->timestamp('docuseal_viewed_at')->nullable();
                $table->timestamp('docuseal_completed_at')->nullable();
                $table->decimal('paid_amount', 10, 2)->default(0);
                $table->timestamp('last_payment_date')->nullable();
                $table->decimal('total_order_value', 10, 2)->nullable();
                $table->unsignedBigInteger('parent_order_id')->nullable();
                $table->string('order_type')->default('standard');
                $table->boolean('requires_review')->default(false);
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();
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

                $table->foreign('ivr_episode_id')
                      ->references('id')
                      ->on('patient_manufacturer_ivr_episodes')
                      ->onDelete('set null');

                $table->foreign('parent_order_id')
                      ->references('id')
                      ->on('orders')
                      ->onDelete('set null');

                $table->foreign('reviewed_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');

                $table->index('patient_fhir_id');
                $table->index('status');
                $table->index('date_of_service');
                $table->index('payment_status');
                $table->index('order_type');
                $table->index('requires_review');
            });
        }

        // Order Items table
        if (!Schema::hasTable('order_items')) {
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
                $table->index(['order_id', 'product_id']);
            });
        }

        // Product Requests table
        if (!Schema::hasTable('product_requests')) {
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
                $table->string('wound_type'); // Changed from enum to string
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
                $table->string('order_status')->default('draft'); // Changed from enum to string
                $table->integer('step')->default(1);
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->decimal('total_order_value', 10, 2)->nullable();
                $table->unsignedBigInteger('acquiring_rep_id')->nullable();
                $table->string('place_of_service', 10)->nullable();
                $table->boolean('medicare_part_b_authorized')->default(false);
                $table->timestamp('delivered_at')->nullable();
                $table->string('tracking_number')->nullable();
                $table->json('notification_recipients')->nullable();
                $table->uuid('ivr_episode_id')->nullable();
                $table->boolean('ivr_required')->default(true);
                $table->text('ivr_bypass_reason')->nullable();
                $table->timestamp('ivr_sent_at')->nullable();
                $table->timestamp('ivr_signed_at')->nullable();
                $table->string('ivr_document_url')->nullable();
                $table->string('docuseal_submission_id')->nullable();
                $table->string('docuseal_template_id')->nullable();
                $table->string('manufacturer_approval_status')->nullable();
                $table->timestamp('manufacturer_approved_at')->nullable();
                $table->string('manufacturer_order_id')->nullable();
                $table->boolean('clinical_attestation_complete')->default(false);
                $table->json('clinical_attestation_data')->nullable();
                $table->string('ivr_status')->nullable();
                $table->string('insurance_primary_name')->nullable();
                $table->string('insurance_primary_member_id')->nullable();
                $table->string('insurance_secondary_name')->nullable();
                $table->string('insurance_secondary_member_id')->nullable();
                $table->date('shipped_date')->nullable();
                $table->date('delivered_date')->nullable();
                $table->string('shipping_carrier')->nullable();
                $table->json('shipping_details')->nullable();
                $table->string('delivery_confirmation_number')->nullable();
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

                $table->foreign('ivr_episode_id')
                      ->references('id')
                      ->on('patient_manufacturer_ivr_episodes')
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
                $table->index('ivr_status');
            });
        }

        // Product Request Products pivot table
        if (!Schema::hasTable('product_request_products')) {
            Schema::create('product_request_products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_request_id');
                $table->unsignedBigInteger('product_id');
                $table->integer('quantity')->default(1);
                $table->string('size')->nullable();
                $table->decimal('unit_price', 10, 2)->nullable();
                $table->decimal('total_price', 10, 2)->nullable();
                $table->timestamps();

                $table->foreign('product_request_id')
                      ->references('id')
                      ->on('product_requests')
                      ->onDelete('cascade');

                $table->foreign('product_id')
                      ->references('id')
                      ->on('msc_products')
                      ->onDelete('cascade');

                $table->unique(['product_request_id', 'product_id', 'size']);
                $table->index('product_request_id');
                $table->index('product_id');
            });
        }

        // Order Notes table
        if (!Schema::hasTable('order_notes')) {
            Schema::create('order_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('user_id');
                $table->text('note');
                $table->enum('visibility', ['internal', 'provider', 'all'])->default('internal');
                $table->boolean('is_system_generated')->default(false);
                $table->timestamps();

                $table->foreign('order_id')
                      ->references('id')
                      ->on('orders')
                      ->onDelete('cascade');

                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');

                $table->index(['order_id', 'visibility']);
                $table->index('created_at');
            });
        }

        // Order Documents table
        if (!Schema::hasTable('order_documents')) {
            Schema::create('order_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->string('document_type');
                $table->string('file_name');
                $table->string('file_path');
                $table->string('mime_type');
                $table->integer('file_size');
                $table->unsignedBigInteger('uploaded_by');
                $table->timestamps();

                $table->foreign('order_id')
                      ->references('id')
                      ->on('orders')
                      ->onDelete('cascade');

                $table->foreign('uploaded_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');

                $table->index(['order_id', 'document_type']);
            });
        }

        // Order Status History table
        if (!Schema::hasTable('order_status_history')) {
            Schema::create('order_status_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->string('previous_status')->nullable();
                $table->string('new_status');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->foreign('order_id')
                      ->references('id')
                      ->on('orders')
                      ->onDelete('cascade');

                $table->foreign('changed_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');

                $table->index(['order_id', 'created_at']);
                $table->index('new_status');
            });
        }

        // Order Audit Logs table
        if (!Schema::hasTable('order_audit_logs')) {
            Schema::create('order_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('product_request_id')->nullable();
                $table->string('action');
                $table->json('changes')->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_email')->nullable();
                $table->string('user_role')->nullable();
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->timestamps();

                $table->foreign('order_id')
                      ->references('id')
                      ->on('orders')
                      ->onDelete('cascade');

                $table->foreign('product_request_id')
                      ->references('id')
                      ->on('product_requests')
                      ->onDelete('cascade');

                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');

                $table->index(['order_id', 'created_at']);
                $table->index(['product_request_id', 'created_at']);
                $table->index('action');
                $table->index('user_id');
            });
        }

        // ========================================
        // SECTION 8: CLINICAL/VALIDATION TABLES
        // ========================================

        // Pre-authorizations table
        if (!Schema::hasTable('pre_authorizations')) {
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
        }

        // ICD-10 Codes table
        if (!Schema::hasTable('icd10_codes')) {
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
                
                // Add fulltext index for non-SQLite databases
                if (Schema::connection($this->getConnection())->getConnection()->getDriverName() !== 'sqlite') {
                    $table->fullText(['code', 'description']);
                }
            });
        }

        // CPT Codes table
        if (!Schema::hasTable('cpt_codes')) {
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
                
                // Add fulltext index for non-SQLite databases
                if (Schema::connection($this->getConnection())->getConnection()->getDriverName() !== 'sqlite') {
                    $table->fullText(['code', 'description']);
                }
            });
        }

        // Pre-authorization Diagnosis Codes table
        if (!Schema::hasTable('pre_authorization_diagnosis_codes')) {
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
        }

        // Pre-authorization Procedure Codes table
        if (!Schema::hasTable('pre_authorization_procedure_codes')) {
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
        }

        // Medicare MAC Validations table
        if (!Schema::hasTable('medicare_mac_validations')) {
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
                $table->string('addressing_method')->nullable()->comment('Method used for MAC addressing');
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
        }

        // Clinical Opportunities table
        if (!Schema::hasTable('clinical_opportunities')) {
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

                $table->index(['product_request_id', 'status'], 'co_req_status_idx');
                $table->index('opportunity_type', 'co_type_idx');
                $table->index('assigned_to', 'co_assigned_idx');
                $table->index('due_date', 'co_due_date_idx');
            });
        }

        // Clinical Opportunity Actions table
        if (!Schema::hasTable('clinical_opportunity_actions')) {
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

                $table->index(['clinical_opportunity_id', 'created_at'], 'coa_opp_created_idx');
                $table->index('action_type', 'coa_action_type_idx');
                $table->index('performed_by', 'coa_performed_by_idx');
            });
        }

        // MSC Product Recommendation Rules table
        if (!Schema::hasTable('msc_product_recommendation_rules')) {
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
        }

        // Insurance Product Rules table
        if (!Schema::hasTable('insurance_product_rules')) {
            Schema::create('insurance_product_rules', function (Blueprint $table) {
                $table->id();
                $table->string('payer_name');
                $table->string('payer_id')->nullable();
                $table->unsignedBigInteger('product_id');
                $table->string('coverage_type'); // 'covered', 'not_covered', 'prior_auth_required'
                $table->json('coverage_conditions')->nullable();
                $table->json('required_documentation')->nullable();
                $table->decimal('reimbursement_rate', 5, 2)->nullable();
                $table->decimal('max_reimbursement_amount', 10, 2)->nullable();
                $table->integer('frequency_limit')->nullable();
                $table->string('frequency_period')->nullable(); // 'day', 'week', 'month', 'year'
                $table->boolean('is_active')->default(true);
                $table->date('effective_date')->nullable();
                $table->date('end_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('product_id')
                      ->references('id')
                      ->on('msc_products')
                      ->onDelete('cascade');

                $table->index(['payer_name', 'product_id']);
                $table->index(['payer_id', 'is_active']);
                $table->index(['effective_date', 'end_date']);
            });
        }

        // ========================================
        // SECTION 9: PROVIDER/PATIENT TABLES
        // ========================================

        // Provider Profiles table
        if (!Schema::hasTable('provider_profiles')) {
            Schema::create('provider_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('npi')->nullable();
                $table->string('license_number')->nullable();
                $table->string('license_state')->nullable();
                $table->string('specialty')->nullable();
                $table->string('sub_specialty')->nullable();
                $table->json('certifications')->nullable();
                $table->boolean('is_verified')->default(false);
                $table->timestamp('verified_at')->nullable();
                $table->string('profile_photo_url')->nullable();
                $table->text('bio')->nullable();
                $table->json('languages_spoken')->nullable();
                $table->integer('years_of_experience')->nullable();
                $table->json('education')->nullable();
                $table->json('hospital_affiliations')->nullable();
                $table->boolean('accepts_new_patients')->default(true);
                $table->timestamps();

                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');

                $table->index('npi');
                $table->index('specialty');
                $table->index('is_verified');
            });
        }

        // Provider Credentials table
        if (!Schema::hasTable('provider_credentials')) {
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
        }

        // Provider Products table
        if (!Schema::hasTable('provider_products')) {
            Schema::create('provider_products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('provider_id');
                $table->unsignedBigInteger('product_id');
                $table->boolean('is_preferred')->default(false);
                $table->integer('usage_count')->default(0);
                $table->timestamp('last_used_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('provider_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');

                $table->foreign('product_id')
                      ->references('id')
                      ->on('msc_products')
                      ->onDelete('cascade');

                $table->unique(['provider_id', 'product_id']);
                $table->index(['provider_id', 'is_preferred']);
                $table->index('usage_count');
            });
        }

        // Provider Invitations table
        if (!Schema::hasTable('provider_invitations')) {
            Schema::create('provider_invitations', function (Blueprint $table) {
                $table->id();
                $table->string('email');
                $table->unsignedBigInteger('organization_id');
                $table->unsignedBigInteger('facility_id')->nullable();
                $table->unsignedBigInteger('invited_by');
                $table->string('token')->unique();
                $table->string('role')->default('provider');
                $table->enum('status', ['pending', 'accepted', 'expired', 'cancelled'])->default('pending');
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('expires_at');
                $table->timestamps();

                $table->foreign('organization_id')
                      ->references('id')
                      ->on('organizations')
                      ->onDelete('cascade');

                $table->foreign('facility_id')
                      ->references('id')
                      ->on('facilities')
                      ->onDelete('cascade');

                $table->foreign('invited_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');

                $table->index(['email', 'status']);
                $table->index('token');
                $table->index('expires_at');
            });
        }

        // Patient Associations table
        if (!Schema::hasTable('patient_associations')) {
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
        }

        // Patient IVR Status table
        if (!Schema::hasTable('patient_ivr_status')) {
            Schema::create('patient_ivr_status', function (Blueprint $table) {
                $table->id();
                $table->string('patient_fhir_id');
                $table->unsignedBigInteger('product_request_id');
                $table->string('ivr_status')->default('pending');
                $table->timestamp('ivr_sent_at')->nullable();
                $table->timestamp('ivr_completed_at')->nullable();
                $table->timestamp('ivr_expires_at')->nullable();
                $table->string('docuseal_submission_id')->nullable();
                $table->json('ivr_metadata')->nullable();
                $table->timestamps();

                $table->foreign('product_request_id')
                      ->references('id')
                      ->on('product_requests')
                      ->onDelete('cascade');

                $table->index(['patient_fhir_id', 'ivr_status']);
                $table->index('product_request_id');
                $table->index('ivr_expires_at');
            });
        }

        // ========================================
        // SECTION 10: FIELD MAPPING TABLES
        // ========================================

        // IVR Field Mappings table
        if (!Schema::hasTable('ivr_field_mappings')) {
            Schema::create('ivr_field_mappings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('manufacturer_id')->constrained();
                $table->string('template_id');
                $table->string('source_field');
                $table->string('target_field');
                $table->decimal('confidence', 3, 2);
                $table->enum('match_type', ['exact', 'fuzzy', 'semantic', 'pattern', 'manual', 'fallback']);
                $table->integer('usage_count')->default(0);
                $table->decimal('success_rate', 3, 2)->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->string('created_by')->nullable();
                $table->string('approved_by')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                
                $table->index(['template_id', 'source_field']);
                $table->index('confidence');
                $table->index(['manufacturer_id', 'template_id']);
                $table->index(['usage_count', 'success_rate']);
            });
        }

        // IVR Template Fields table
        if (!Schema::hasTable('ivr_template_fields')) {
            Schema::create('ivr_template_fields', function (Blueprint $table) {
                $table->id();
                $table->foreignId('manufacturer_id')->constrained();
                $table->string('template_id');
                $table->string('field_name');
                $table->string('field_type', 50)->nullable();
                $table->string('field_category', 100)->nullable();
                $table->boolean('is_required')->default(false);
                $table->boolean('is_checkbox')->default(false);
                $table->json('validation_rules')->nullable();
                $table->text('default_value')->nullable();
                $table->json('options')->nullable();
                $table->integer('position')->nullable();
                $table->timestamps();
                
                $table->unique(['template_id', 'field_name']);
                $table->index('template_id');
                $table->index('manufacturer_id');
                $table->index('field_category');
            });
        }

        // IVR Mapping Audit table
        if (!Schema::hasTable('ivr_mapping_audit')) {
            Schema::create('ivr_mapping_audit', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->timestamp('timestamp');
                $table->string('episode_id', 36)->nullable();
                $table->string('template_id');
                $table->foreignId('manufacturer_id')->constrained();
                $table->foreignId('user_id')->nullable()->constrained();
                
                // Mapping statistics
                $table->integer('total_fields');
                $table->integer('mapped_fields');
                $table->integer('fallback_fields')->default(0);
                $table->integer('unmapped_fields');
                $table->decimal('avg_confidence', 3, 2)->nullable();
                
                // Performance metrics
                $table->integer('duration_ms');
                $table->boolean('cache_hit')->default(false);
                
                // Validation results
                $table->boolean('validation_passed')->nullable();
                $table->integer('validation_errors')->default(0);
                $table->integer('validation_warnings')->default(0);
                
                // Detailed data
                $table->json('field_details')->nullable();
                $table->json('warnings')->nullable();
                $table->json('errors')->nullable();
                
                $table->timestamp('created_at')->useCurrent();
                
                $table->index('timestamp');
                $table->index('episode_id');
                $table->index('manufacturer_id');
                $table->index('user_id');
                $table->index('avg_confidence');
                $table->index('duration_ms');
            });
        }

        // IVR Diagnosis Code Mappings table
        if (!Schema::hasTable('ivr_diagnosis_code_mappings')) {
            Schema::create('ivr_diagnosis_code_mappings', function (Blueprint $table) {
                $table->id();
                $table->string('icd10_code', 10);
                $table->string('description');
                $table->enum('wound_type', ['venous_leg_ulcer', 'diabetic_foot_ulcer', 'pressure_ulcer', 'chronic_ulcer']);
                $table->string('category')->nullable();
                $table->integer('priority')->default(0);
                $table->boolean('is_primary')->default(false);
                $table->json('related_codes')->nullable();
                $table->timestamps();
                
                $table->index('icd10_code');
                $table->index('wound_type');
                $table->index('priority');
            });
        }

        // Canonical Fields table
        if (!Schema::hasTable('canonical_fields')) {
            Schema::create('canonical_fields', function (Blueprint $table) {
                $table->id();
                $table->string('field_name')->unique();
                $table->string('display_name');
                $table->string('data_type');
                $table->string('field_category');
                $table->text('description')->nullable();
                $table->json('validation_rules')->nullable();
                $table->json('common_aliases')->nullable();
                $table->string('fhir_path')->nullable();
                $table->boolean('is_required')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('field_category');
                $table->index('is_active');
            });
        }

        // Template Field Mappings table
        if (!Schema::hasTable('template_field_mappings')) {
            Schema::create('template_field_mappings', function (Blueprint $table) {
                $table->id();
                $table->string('template_id');
                $table->unsignedBigInteger('manufacturer_id');
                $table->string('template_field_name');
                $table->unsignedBigInteger('canonical_field_id')->nullable();
                $table->string('mapping_type')->default('direct');
                $table->json('transformation_rules')->nullable();
                $table->decimal('confidence_score', 3, 2)->default(1.00);
                $table->boolean('is_verified')->default(false);
                $table->unsignedBigInteger('verified_by')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->boolean('is_composite')->default(false);
                $table->json('composite_fields')->nullable();
                $table->timestamps();

                $table->foreign('manufacturer_id')
                      ->references('id')
                      ->on('manufacturers')
                      ->onDelete('cascade');

                $table->foreign('canonical_field_id')
                      ->references('id')
                      ->on('canonical_fields')
                      ->onDelete('set null');

                $table->foreign('verified_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');

                $table->unique(['template_id', 'template_field_name']);
                $table->index(['manufacturer_id', 'template_id']);
                $table->index('is_verified');
                $table->index('confidence_score');
            });
        }

        // Field Mapping Logs table
        if (!Schema::hasTable('field_mapping_logs')) {
            Schema::create('field_mapping_logs', function (Blueprint $table) {
                $table->id();
                $table->uuid('episode_id');
                $table->string('manufacturer_name');
                $table->unsignedInteger('manufacturer_id')->nullable();
                $table->string('mapping_type');
                $table->decimal('completeness_percentage', 5, 2);
                $table->decimal('required_completeness_percentage', 5, 2);
                $table->integer('fields_mapped');
                $table->integer('fields_total');
                $table->integer('required_fields_mapped');
                $table->integer('required_fields_total');
                $table->json('field_status');
                $table->json('validation_errors')->nullable();
                $table->json('validation_warnings')->nullable();
                $table->decimal('mapping_duration_ms', 10, 2);
                $table->string('source_service')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('episode_id')
                      ->references('id')
                      ->on('patient_manufacturer_ivr_episodes');

                $table->index(['episode_id', 'manufacturer_name']);
                $table->index('mapping_type');
                $table->index('created_at');
            });
        }

        // Field Mapping Cache table
        if (!Schema::hasTable('field_mapping_cache')) {
            Schema::create('field_mapping_cache', function (Blueprint $table) {
                $table->id();
                $table->string('cache_key')->unique();
                $table->json('cached_data');
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index('expires_at');
            });
        }

        // Field Mapping Analytics table
        if (!Schema::hasTable('field_mapping_analytics')) {
            Schema::create('field_mapping_analytics', function (Blueprint $table) {
                $table->id();
                $table->string('manufacturer_name');
                $table->string('field_name');
                $table->string('match_type');
                $table->string('source_field')->nullable();
                $table->decimal('match_score', 3, 2)->nullable();
                $table->integer('usage_count')->default(1);
                $table->boolean('successful')->default(true);
                $table->timestamps();

                $table->index(['manufacturer_name', 'field_name']);
                $table->index('match_type');
                $table->index('usage_count');
            });
        }

        // ========================================
        // SECTION 11: PAYMENT & COMMISSION TABLES
        // ========================================

        // Payments table
        if (!Schema::hasTable('payments')) {
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
                $table->string('paid_to')->nullable();
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
        }

        // Commission Payouts table
        if (!Schema::hasTable('commission_payouts')) {
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
        }

        // Commission Records table
        if (!Schema::hasTable('commission_records')) {
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

                $table->index(['rep_id', 'status']);
                $table->index(['calculation_date', 'status']);
                $table->index('payout_id');
                $table->index('type');
            });
        }

        // ========================================
        // SECTION 12: ONBOARDING TABLES
        // ========================================

        // Organization Onboarding table
        if (!Schema::hasTable('organization_onboarding')) {
            Schema::create('organization_onboarding', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('organization_id')->unique();
                $table->enum('status', [
                    'initiated',
                    'basic_info_complete',
                    'billing_setup_complete',
                    'facilities_added',
                    'providers_invited',
                    'training_scheduled',
                    'go_live',
                    'completed'
                ])->default('initiated');
                $table->json('completed_steps')->nullable();
                $table->json('pending_items')->nullable();
                $table->unsignedBigInteger('onboarding_manager_id')->nullable();
                $table->timestamp('initiated_at');
                $table->timestamp('target_go_live_date')->nullable();
                $table->timestamp('actual_go_live_date')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->foreign('organization_id')
                      ->references('id')
                      ->on('organizations')
                      ->onDelete('cascade');

                $table->foreign('onboarding_manager_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');

                $table->index('status');
            });
        }

        // Onboarding Checklists table
        if (!Schema::hasTable('onboarding_checklists')) {
            Schema::create('onboarding_checklists', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->morphs('entity');
                $table->string('checklist_type');
                $table->json('items')->comment('Array of checklist items with status');
                $table->integer('total_items')->default(0);
                $table->integer('completed_items')->default(0);
                $table->decimal('completion_percentage', 5, 2)->default(0);
                $table->timestamp('last_activity_at')->nullable();
                $table->timestamps();

                $table->index('checklist_type');
            });
        }

        // Onboarding Documents table
        if (!Schema::hasTable('onboarding_documents')) {
            Schema::create('onboarding_documents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->morphs('entity');
                $table->string('document_type');
                $table->string('document_name');
                $table->string('file_path');
                $table->string('file_size');
                $table->string('mime_type');
                $table->enum('status', [
                    'uploaded',
                    'under_review',
                    'approved',
                    'rejected',
                    'expired'
                ])->default('uploaded');
                $table->unsignedBigInteger('uploaded_by');
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->date('expiration_date')->nullable();
                $table->timestamps();

                $table->foreign('uploaded_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');

                $table->foreign('reviewed_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');

                $table->index('document_type');
                $table->index('status');
            });
        }

        // ========================================
        // SECTION 13: AUDIT & LOGGING TABLES
        // ========================================

        // Profile Audit Log table
        if (!Schema::hasTable('profile_audit_log')) {
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
                $table->json('metadata')->nullable();
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->timestamps();

                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('cascade');

                $table->index(['entity_type', 'entity_id']);
                $table->index('action_type');
                $table->index('created_at');
            });
        }

        // RBAC Audit Logs table
        if (!Schema::hasTable('rbac_audit_logs')) {
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
        }

        // FHIR Audit Logs table
        if (!Schema::hasTable('fhir_audit_logs')) {
            Schema::create('fhir_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('operation');
                $table->string('resource_type');
                $table->string('resource_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_email')->nullable();
                $table->string('patient_fhir_id')->nullable();
                $table->boolean('success')->default(true);
                $table->integer('status_code')->nullable();
                $table->text('error_message')->nullable();
                $table->json('request_data')->nullable();
                $table->json('response_data')->nullable();
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->decimal('duration_ms', 10, 2)->nullable();
                $table->timestamps();

                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');

                $table->index(['operation', 'resource_type']);
                $table->index('patient_fhir_id');
                $table->index('success');
                $table->index('created_at');
            });
        }

        // PHI Audit Logs table
        if (!Schema::hasTable('phi_audit_logs')) {
            Schema::create('phi_audit_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('action');
                $table->string('resource_type');
                $table->string('resource_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_email')->nullable();
                $table->string('patient_identifier')->nullable();
                $table->json('details')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent')->nullable();
                $table->string('session_id')->nullable();
                $table->json('context')->nullable();
                $table->timestamp('accessed_at');
                $table->timestamps();

                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');

                $table->index(['action', 'resource_type']);
                $table->index('patient_identifier');
                $table->index('user_id');
                $table->index('accessed_at');
                $table->index('session_id');
            });
        }

        // Activity Logs table
        if (!Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->string('log_name')->nullable();
                $table->text('description');
                $table->morphs('subject');
                $table->morphs('causer');
                $table->json('properties')->nullable();
                $table->timestamps();

                $table->index('log_name');
            });
        }

        // Manufacturer Contacts table
        if (!Schema::hasTable('manufacturer_contacts')) {
            Schema::create('manufacturer_contacts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('manufacturer_id');
                $table->string('contact_type');
                $table->string('name');
                $table->string('title')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('extension')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('manufacturer_id')
                      ->references('id')
                      ->on('manufacturers')
                      ->onDelete('cascade');

                $table->index(['manufacturer_id', 'contact_type']);
                $table->index('is_primary');
            });
        }

        // ========================================
        // SECTION 14: SYSTEM TABLES
        // ========================================

        // Failed Jobs table
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

        // Password Resets table
        if (!Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $table) {
                $table->string('email')->index();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        // Personal Access Tokens table
        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        // Sessions table
        if (!Schema::hasTable('sessions')) {
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
        }

        // Cache table
        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        // Cache Locks table
        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }

        // Order Action History table
        if (!Schema::hasTable('order_action_history')) {
            Schema::create('order_action_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->string('action');
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->unsignedBigInteger('performed_by')->nullable();
                $table->timestamps();

                $table->foreign('order_id')
                      ->references('id')
                      ->on('orders')
                      ->onDelete('cascade');

                $table->foreign('performed_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');

                $table->index(['order_id', 'created_at']);
                $table->index('action');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order to handle foreign key constraints
        
        // System tables
        Schema::dropIfExists('order_action_history');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('password_resets');
        Schema::dropIfExists('failed_jobs');
        
        // Audit tables
        Schema::dropIfExists('manufacturer_contacts');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('phi_audit_logs');
        Schema::dropIfExists('fhir_audit_logs');
        Schema::dropIfExists('rbac_audit_logs');
        Schema::dropIfExists('profile_audit_log');
        
        // Onboarding tables
        Schema::dropIfExists('onboarding_documents');
        Schema::dropIfExists('onboarding_checklists');
        Schema::dropIfExists('organization_onboarding');
        
        // Commission tables
        Schema::dropIfExists('commission_records');
        Schema::dropIfExists('commission_payouts');
        Schema::dropIfExists('payments');
        
        // Field mapping tables
        Schema::dropIfExists('field_mapping_analytics');
        Schema::dropIfExists('field_mapping_cache');
        Schema::dropIfExists('field_mapping_logs');
        Schema::dropIfExists('template_field_mappings');
        Schema::dropIfExists('canonical_fields');
        Schema::dropIfExists('ivr_diagnosis_code_mappings');
        Schema::dropIfExists('ivr_mapping_audit');
        Schema::dropIfExists('ivr_template_fields');
        Schema::dropIfExists('ivr_field_mappings');
        
        // Patient/Provider tables
        Schema::dropIfExists('patient_ivr_status');
        Schema::dropIfExists('patient_associations');
        Schema::dropIfExists('provider_invitations');
        Schema::dropIfExists('provider_products');
        Schema::dropIfExists('provider_credentials');
        Schema::dropIfExists('provider_profiles');
        
        // Clinical tables
        Schema::dropIfExists('insurance_product_rules');
        Schema::dropIfExists('msc_product_recommendation_rules');
        Schema::dropIfExists('clinical_opportunity_actions');
        Schema::dropIfExists('clinical_opportunities');
        Schema::dropIfExists('medicare_mac_validations');
        Schema::dropIfExists('pre_authorization_procedure_codes');
        Schema::dropIfExists('pre_authorization_diagnosis_codes');
        Schema::dropIfExists('cpt_codes');
        Schema::dropIfExists('icd10_codes');
        Schema::dropIfExists('pre_authorizations');
        
        // Order tables
        Schema::dropIfExists('order_audit_logs');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_documents');
        Schema::dropIfExists('order_notes');
        Schema::dropIfExists('product_request_products');
        Schema::dropIfExists('product_requests');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        
        // DocuSeal/IVR tables
        Schema::dropIfExists('quick_request_submissions');
        Schema::dropIfExists('patient_manufacturer_ivr_episodes');
        Schema::dropIfExists('docuseal_submissions');
        Schema::dropIfExists('docuseal_templates');
        Schema::dropIfExists('docuseal_folders');
        
        // Sales tables
        Schema::dropIfExists('facility_sales_rep_assignments');
        Schema::dropIfExists('provider_sales_rep_assignments');
        Schema::dropIfExists('sales_rep_organizations');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('msc_sales_reps');
        
        // Product tables
        Schema::dropIfExists('product_pricing_history');
        Schema::dropIfExists('product_sizes');
        Schema::dropIfExists('msc_products');
        
        // Organization tables
        Schema::dropIfExists('organization_users');
        Schema::dropIfExists('facility_user');
        Schema::dropIfExists('facilities');
        
        // User tables - remove foreign key constraint first
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_organization_id']);
        });
        
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('user_role');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('users');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        
        // Core tables
        Schema::dropIfExists('manufacturers');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('accounts');
    }
};
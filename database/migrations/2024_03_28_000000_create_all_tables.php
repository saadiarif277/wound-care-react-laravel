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
            $table->timestamp('valid_from');
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
            $table->timestamp('calculation_date');
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
        });

        Schema::create('commission_payouts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rep_id');
            $table->timestamp('period_start')->useCurrent();
            $table->timestamp('period_end')->useCurrent();
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
        });

        // 8. Audit and logging tables
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
            $table->json('metadata')->default('{}');
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

        // 9. System tables
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
        Schema::dropIfExists('product_requests');
        Schema::dropIfExists('commission_payouts');
        Schema::dropIfExists('commission_records');
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

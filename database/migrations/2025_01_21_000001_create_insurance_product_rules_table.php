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
        // Table for insurance product rules
        if (!Schema::hasTable('insurance_product_rules')) {
            Schema::create('insurance_product_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('insurance_type'); // ppo, commercial, medicare, medicaid
            $table->string('state_code', 2)->nullable(); // For Medicaid state-specific rules
            $table->decimal('wound_size_min', 10, 2)->nullable(); // Min wound size in sq cm
            $table->decimal('wound_size_max', 10, 2)->nullable(); // Max wound size in sq cm
            $table->json('allowed_product_codes'); // Array of Q-codes
            $table->text('coverage_message'); // Display message for users
            $table->boolean('requires_consultation')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['insurance_type', 'state_code']);
            $table->index('is_active');
        });
        }

        // Table for diagnosis codes
        if (!Schema::hasTable('diagnosis_codes')) {
            Schema::create('diagnosis_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->text('description');
            $table->string('category', 50); // yellow, orange, etc.
            $table->string('specialty')->nullable(); // diabetic, pressure, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category');
            $table->index('is_active');
        });
        }

        // Table for wound types
        if (!Schema::hasTable('wound_types')) {
            Schema::create('wound_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
        });
        }

        // Add MUE limit to products table
        $productsTable = Schema::hasTable('msc_products') ? 'msc_products' : 'products';
        
        if (Schema::hasTable($productsTable) && !Schema::hasColumn($productsTable, 'mue_limit')) {
            Schema::table($productsTable, function (Blueprint $table) {
                $table->integer('mue_limit')->nullable()->after('price_per_sq_cm')
                    ->comment('Maximum Units of Eligibility limit in sq cm');
            });
        }

        // Table for MSC contact information
        if (!Schema::hasTable('msc_contacts')) {
            Schema::create('msc_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('department'); // admin, support, etc.
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('purpose'); // consultation, general, etc.
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['department', 'is_active']);
        });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_product_rules');
        Schema::dropIfExists('diagnosis_codes');
        Schema::dropIfExists('wound_types');
        Schema::dropIfExists('msc_contacts');

        // Remove MUE limit from whichever products table has it
        foreach (['msc_products', 'products'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'mue_limit')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('mue_limit');
                });
                break;
            }
        }
    }
};
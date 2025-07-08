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
                // Add provider attribution system to users table
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'acquired_by_rep_id')) {
                $table->unsignedBigInteger('acquired_by_rep_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('users', 'acquired_by_subrep_id')) {
                $table->unsignedBigInteger('acquired_by_subrep_id')->nullable()->after('acquired_by_rep_id');
            }
            if (!Schema::hasColumn('users', 'acquisition_date')) {
                $table->timestamp('acquisition_date')->nullable()->after('acquired_by_subrep_id');
            }
        });

        // Add enhanced fields to commission_records table
        Schema::table('commission_records', function (Blueprint $table) {
            if (!Schema::hasColumn('commission_records', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('amount');
            }
            if (!Schema::hasColumn('commission_records', 'first_application_date')) {
                $table->date('first_application_date')->nullable()->after('invoice_number');
            }
            if (!Schema::hasColumn('commission_records', 'tissue_ids')) {
                $table->json('tissue_ids')->nullable()->after('first_application_date');
            }
            if (!Schema::hasColumn('commission_records', 'payment_delay_days')) {
                $table->integer('payment_delay_days')->default(0)->after('tissue_ids');
            }
            if (!Schema::hasColumn('commission_records', 'payment_date')) {
                $table->timestamp('payment_date')->nullable()->after('payment_delay_days');
            }
            if (!Schema::hasColumn('commission_records', 'friendly_patient_id')) {
                $table->string('friendly_patient_id', 10)->nullable()->after('payment_date');
            }
        });

        // Add friendly patient ID tracking to product_requests
        Schema::table('product_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('product_requests', 'friendly_patient_id')) {
                $table->string('friendly_patient_id', 10)->nullable()->after('id');
                $table->index('friendly_patient_id');
            }
        });

                // Add payment tracking to orders (check if columns don't exist first)
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status')->default('pending')->after('status');
            }
            if (!Schema::hasColumn('orders', 'payment_date')) {
                $table->timestamp('payment_date')->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('orders', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('payment_date');
            }
        });

        // Create provider_sales_rep_attribution table for better tracking
        if (!Schema::hasTable('provider_sales_rep_attribution')) {
            Schema::create('provider_sales_rep_attribution', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('provider_id');
                $table->unsignedBigInteger('sales_rep_id');
                $table->unsignedBigInteger('sub_rep_id')->nullable();
                $table->timestamp('attribution_date');
                $table->string('attribution_type')->default('direct'); // direct, referral, transfer
                $table->decimal('commission_split_percentage', 5, 2)->default(50.00);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('provider_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('sales_rep_id')->references('id')->on('msc_sales_reps')->onDelete('cascade');
                $table->foreign('sub_rep_id')->references('id')->on('msc_sales_reps')->onDelete('set null');

                $table->unique(['provider_id', 'sales_rep_id']);
                $table->index(['sales_rep_id', 'attribution_date'], 'prov_rep_attr_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_sales_rep_attribution');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_status', 'payment_date']);
            $table->dropColumn(['payment_status', 'payment_date', 'payment_reference']);
        });

        Schema::table('product_requests', function (Blueprint $table) {
            $table->dropIndex(['friendly_patient_id']);
            $table->dropColumn('friendly_patient_id');
        });

        Schema::table('commission_records', function (Blueprint $table) {
            $table->dropIndex(['rep_id', 'status', 'calculation_date']);
            $table->dropIndex(['friendly_patient_id']);
            $table->dropIndex(['payment_delay_days']);
            $table->dropIndex(['invoice_number']);
            $table->dropColumn([
                'invoice_number',
                'first_application_date',
                'tissue_ids',
                'payment_delay_days',
                'payment_date',
                'friendly_patient_id'
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['acquired_by_rep_id', 'acquisition_date']);
            $table->dropForeign(['acquired_by_subrep_id']);
            $table->dropForeign(['acquired_by_rep_id']);
            $table->dropColumn(['acquired_by_rep_id', 'acquired_by_subrep_id', 'acquisition_date']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('quick_request_submissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Patient Info
            $table->string('patient_first_name');
            $table->string('patient_last_name');
            $table->date('patient_dob');
            $table->string('patient_gender')->nullable();
            $table->string('patient_phone')->nullable();
            $table->string('patient_email')->nullable();
            $table->string('patient_address')->nullable();
            $table->string('patient_city')->nullable();
            $table->string('patient_state')->nullable();
            $table->string('patient_zip')->nullable();
            // Insurance Info
            $table->string('primary_insurance_name');
            $table->string('primary_plan_type')->nullable();
            $table->string('primary_member_id')->nullable();
            $table->boolean('has_secondary_insurance')->default(false);
            $table->string('secondary_insurance_name')->nullable();
            $table->string('secondary_plan_type')->nullable();
            $table->string('secondary_member_id')->nullable();
            $table->boolean('insurance_card_uploaded')->default(false);
            // Provider/Facility Info
            $table->string('provider_name');
            $table->string('provider_npi')->nullable();
            $table->string('facility_name')->nullable();
            $table->string('facility_address')->nullable();
            $table->string('organization_name')->nullable();
            // Clinical Info
            $table->string('wound_type')->nullable();
            $table->string('wound_location')->nullable();
            $table->string('wound_size_length')->nullable();
            $table->string('wound_size_width')->nullable();
            $table->string('wound_size_depth')->nullable();
            $table->json('diagnosis_codes')->nullable();
            $table->json('icd10_codes')->nullable();
            $table->string('procedure_info')->nullable();
            $table->integer('prior_applications')->nullable();
            $table->integer('anticipated_applications')->nullable();
            $table->string('clinical_facility_info')->nullable();
            // Product Info
            $table->string('product_name')->nullable();
            $table->json('product_sizes')->nullable();
            $table->integer('product_quantity')->nullable();
            $table->decimal('asp_price', 10, 2)->nullable();
            $table->decimal('discounted_price', 10, 2)->nullable();
            $table->json('coverage_warnings')->nullable();
            // IVR & Order Form Status
            $table->string('ivr_status')->nullable();
            $table->timestamp('ivr_submission_date')->nullable();
            $table->string('ivr_document_link')->nullable();
            $table->string('order_form_status')->nullable();
            $table->timestamp('order_form_submission_date')->nullable();
            $table->string('order_form_document_link')->nullable();
            // Order Meta
            $table->string('order_number')->nullable();
            $table->string('order_status')->nullable();
            $table->string('created_by')->nullable();
            $table->decimal('total_bill', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('quick_request_submissions');
    }
};

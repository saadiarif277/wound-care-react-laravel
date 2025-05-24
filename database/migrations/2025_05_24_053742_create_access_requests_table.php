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
        Schema::create('access_requests', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->enum('requested_role', [
                'provider',
                'office_manager',
                'msc_rep',
                'msc_subrep',
                'msc_admin'
            ]);

            // Provider-specific fields
            $table->string('npi_number')->nullable();
            $table->string('medical_license')->nullable();
            $table->string('license_state')->nullable();
            $table->string('specialization')->nullable();
            $table->string('facility_name')->nullable();
            $table->string('facility_address')->nullable();

            // Office Manager fields
            $table->string('manager_name')->nullable();
            $table->string('manager_email')->nullable();

            // MSC Rep fields
            $table->string('territory')->nullable();
            $table->string('manager_contact')->nullable();
            $table->string('experience_years')->nullable();

            // MSC SubRep fields
            $table->string('main_rep_name')->nullable();
            $table->string('main_rep_email')->nullable();

            // MSC Admin fields
            $table->string('department')->nullable();
            $table->string('supervisor_name')->nullable();
            $table->string('supervisor_email')->nullable();

            // Request management
            $table->enum('status', ['pending', 'approved', 'denied'])->default('pending');
            $table->text('request_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['status', 'requested_role']);
            $table->index('email');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_requests');
    }
};

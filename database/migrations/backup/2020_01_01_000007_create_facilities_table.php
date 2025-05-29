<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facilities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('organization_id');
            $table->string('name');
            $table->string('facility_type'); // clinic, hospital, etc.
            $table->string('address');
            $table->string('city');
            $table->string('state');
            $table->string('zip_code');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('npi')->nullable(); // National Provider Identifier
            $table->json('business_hours')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Add foreign key constraint
            $table->foreign('organization_id')
                  ->references('id')
                  ->on('organizations')
                  ->onDelete('cascade');

            // Add indexes for performance
            $table->index('facility_type');
            $table->index('active');
            $table->index('npi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};

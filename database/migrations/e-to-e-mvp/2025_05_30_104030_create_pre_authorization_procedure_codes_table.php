<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This is a PIVOT TABLE that links pre_authorizations to specific CPT codes.
     * It allows a pre-authorization to have multiple procedure codes (many-to-many relationship).
     */
    public function up(): void
    {
        Schema::create('pre_authorization_procedure_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pre_authorization_id');
            $table->unsignedBigInteger('cpt_code_id');
            $table->integer('quantity')->default(1); // How many times this procedure is requested
            $table->string('modifier', 10)->nullable(); // CPT modifier (e.g., 'LT', 'RT')
            $table->integer('sequence')->default(1); // Order of procedure codes
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('pre_authorization_id')
                  ->references('id')
                  ->on('pre_authorizations')
                  ->onDelete('cascade');

            $table->foreign('cpt_code_id')
                  ->references('id')
                  ->on('cpt_codes')
                  ->onDelete('cascade');

            // Unique constraint - each CPT code + modifier can only be used once per pre-authorization
            $table->unique(['pre_authorization_id', 'cpt_code_id', 'modifier'], 'pre_auth_cpt_modifier_unique');

            // Indexes for performance
            $table->index('pre_authorization_id');
            $table->index('cpt_code_id');
            $table->index(['pre_authorization_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pre_authorization_procedure_codes');
    }
};

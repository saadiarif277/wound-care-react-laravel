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
        Schema::create('facility_user', function (Blueprint $table) {
            $table->id();
            $table->uuid('facility_id');
            $table->uuid('user_id');
            $table->string('relationship_type')->default('attached'); // attached, managed, supervised
            $table->boolean('is_primary')->default(false); // Primary facility for the user
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Add foreign key constraints
            $table->foreign('facility_id')
                  ->references('id')
                  ->on('facilities')
                  ->onDelete('cascade');
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            // Prevent duplicate relationships
            $table->unique(['facility_id', 'user_id', 'relationship_type'], 'facility_user_relationship_unique');

            // Add indexes for performance
            $table->index(['facility_id', 'is_active']);
            $table->index(['user_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facility_user');
    }
};

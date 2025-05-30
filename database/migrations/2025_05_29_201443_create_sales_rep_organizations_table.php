<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sales_rep_organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // User who is a sales rep
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->enum('relationship_type', ['primary', 'secondary', 'referral'])->default('primary');
            $table->decimal('commission_override', 5, 2)->nullable();
            $table->boolean('can_create_orders')->default(false);
            $table->boolean('can_view_all_data')->default(true); // Per organization context
            $table->date('assigned_from')->nullable();
            $table->date('assigned_until')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'organization_id'], 'user_organization_unique_idx');
            $table->index(['user_id', 'relationship_type'], 'user_relationship_type_idx');
            $table->index(['user_id', 'assigned_until'], 'user_assigned_until_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sales_rep_organizations');
    }
};

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
        Schema::create('msc_sales_reps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('territory')->nullable();
            $table->decimal('commission_rate_direct', 5, 2)->default(0); // Base commission rate
            $table->decimal('sub_rep_parent_share_percentage', 5, 2)->default(50); // Parent share when sub-rep makes sale
            $table->unsignedBigInteger('parent_rep_id')->nullable(); // For hierarchical structure
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_rep_id')->references('id')->on('msc_sales_reps')->onDelete('set null');
            $table->index('parent_rep_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('msc_sales_reps');
    }
};

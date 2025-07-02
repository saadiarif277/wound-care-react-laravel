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
        Schema::table('product_requests', function (Blueprint $table) {
            // Change wound_type from enum to string to support more wound type values
            $table->string('wound_type')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_requests', function (Blueprint $table) {
            // Revert back to enum if needed
            $table->enum('wound_type', ['DFU', 'VLU', 'PU', 'TW', 'AU', 'OTHER'])->change();
        });
    }
};

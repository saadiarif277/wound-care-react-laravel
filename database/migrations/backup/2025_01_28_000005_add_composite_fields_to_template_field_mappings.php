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
        Schema::table('template_field_mappings', function (Blueprint $table) {
            if (!Schema::hasColumn('template_field_mappings', 'is_composite')) {
                $table->boolean('is_composite')->default(false)->after('is_active');
            }
            
            if (!Schema::hasColumn('template_field_mappings', 'composite_fields')) {
                $table->json('composite_fields')->nullable()->after('is_composite');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('template_field_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('template_field_mappings', 'is_composite')) {
                $table->dropColumn('is_composite');
            }
            
            if (Schema::hasColumn('template_field_mappings', 'composite_fields')) {
                $table->dropColumn('composite_fields');
            }
        });
    }
};
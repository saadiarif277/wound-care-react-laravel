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
        Schema::table('msc_products', function (Blueprint $table) {
            $table->unsignedInteger('mue')->nullable()->after('national_asp')
                ->comment('CMS Medically Unlikely Edit - maximum units allowed per date of service');
            $table->timestamp('cms_last_updated')->nullable()->after('mue')
                ->comment('Last time CMS pricing was synced');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('msc_products', function (Blueprint $table) {
            $table->dropColumn(['mue', 'cms_last_updated']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('facility_user', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('role');
        });
    }

    public function down()
    {
        Schema::table('facility_user', function (Blueprint $table) {
            $table->dropColumn('is_primary');
        });
    }
};
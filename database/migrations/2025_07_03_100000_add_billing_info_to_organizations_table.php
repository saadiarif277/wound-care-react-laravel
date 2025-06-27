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
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('billing_address')->nullable()->after('postal_code');
            $table->string('billing_city')->nullable()->after('billing_address');
            $table->string('billing_state')->nullable()->after('billing_city');
            $table->string('billing_zip')->nullable()->after('billing_state');
            $table->string('ap_contact_name')->nullable()->after('billing_zip');
            $table->string('ap_contact_phone')->nullable()->after('ap_contact_name');
            $table->string('ap_contact_email')->nullable()->after('ap_contact_phone');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'billing_address',
                'billing_city',
                'billing_state',
                'billing_zip',
                'ap_contact_name',
                'ap_contact_phone',
                'ap_contact_email',
            ]);
        });
    }
};

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
            $table->string('order_form_submission_id')->nullable()->after('docuseal_submission_id');
            $table->timestamp('order_form_submitted_at')->nullable()->after('order_form_submission_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_requests', function (Blueprint $table) {
            $table->dropColumn(['order_form_submission_id', 'order_form_submitted_at']);
        });
    }
};

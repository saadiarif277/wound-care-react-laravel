<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('commission_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('rep_id');
            $table->unsignedBigInteger('parent_rep_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('percentage_rate', 5, 2);
            $table->string('type'); // direct-rep, sub-rep-share, parent-rep-share
            $table->string('status')->default('pending'); // pending, approved, included_in_payout, paid
            $table->timestamp('calculation_date');
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('payout_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('order_id')->references('id')->on('orders');
            $table->foreign('order_item_id')->references('id')->on('order_items');
            $table->foreign('rep_id')->references('id')->on('msc_sales_reps');
            $table->foreign('parent_rep_id')->references('id')->on('msc_sales_reps');
            $table->foreign('approved_by')->references('id')->on('users');
            $table->foreign('payout_id')->references('id')->on('commission_payouts');

            $table->index('status');
            $table->index('calculation_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('commission_records');
    }
};

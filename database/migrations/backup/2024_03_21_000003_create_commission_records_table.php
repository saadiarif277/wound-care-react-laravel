<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('commission_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_id');
            $table->uuid('order_item_id');
            $table->uuid('rep_id');
            $table->uuid('parent_rep_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('percentage_rate', 5, 2);
            $table->string('type'); // direct-rep, sub-rep-share, parent-rep-share
            $table->string('status')->default('pending'); // pending, approved, included_in_payout, paid
            $table->timestamp('calculation_date');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('payout_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('order_id')
                  ->references('id')
                  ->on('orders')
                  ->onDelete('restrict');

            $table->foreign('order_item_id')
                  ->references('id')
                  ->on('order_items')
                  ->onDelete('restrict');

            $table->foreign('rep_id')
                  ->references('id')
                  ->on('msc_sales_reps')
                  ->onDelete('restrict');

            $table->foreign('parent_rep_id')
                  ->references('id')
                  ->on('msc_sales_reps')
                  ->onDelete('set null');

            $table->foreign('approved_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->foreign('payout_id')
                  ->references('id')
                  ->on('commission_payouts')
                  ->onDelete('set null');

            $table->index('status');
            $table->index('calculation_date');
            $table->index('type');
            $table->index(['rep_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('commission_records');
    }
};

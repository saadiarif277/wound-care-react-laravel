<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('commission_payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rep_id');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->decimal('total_amount', 10, 2);
            $table->string('status')->default('calculated'); // calculated, approved, processed
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('rep_id')->references('id')->on('msc_sales_reps');
            $table->foreign('approved_by')->references('id')->on('users');

            $table->index('status');
            $table->index(['period_start', 'period_end']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('commission_payouts');
    }
};

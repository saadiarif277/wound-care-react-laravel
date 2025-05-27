<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('commission_payouts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rep_id');
            $table->timestamp('period_start')->useCurrent();
            $table->timestamp('period_end')->useCurrent();
            $table->decimal('total_amount', 10, 2);
            $table->string('status')->default('calculated'); // calculated, approved, processed
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('rep_id')
                  ->references('id')
                  ->on('msc_sales_reps')
                  ->onDelete('cascade');

            $table->foreign('approved_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->index('status');
            $table->index(['period_start', 'period_end']);
            $table->index('payment_reference');
        });
    }

    public function down()
    {
        Schema::dropIfExists('commission_payouts');
    }
};

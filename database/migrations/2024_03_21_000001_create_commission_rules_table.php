<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->string('target_type'); // product, manufacturer, category
            $table->unsignedBigInteger('target_id');
            $table->decimal('percentage_rate', 5, 2);
            $table->timestamp('valid_from');
            $table->timestamp('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['target_type', 'target_id']);
            $table->index('valid_from');
            $table->index('valid_to');
        });
    }

    public function down()
    {
        Schema::dropIfExists('commission_rules');
    }
};

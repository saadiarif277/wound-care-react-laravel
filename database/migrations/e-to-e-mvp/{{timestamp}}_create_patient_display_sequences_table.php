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
        Schema::create('patient_display_sequences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('facility_id')->constrained('facilities')->onDelete('cascade');
            $table->string('initials_base', 4);
            $table->integer('next_sequence')->default(1);
            $table->timestamps();

            $table->unique(['facility_id', 'initials_base']);
            $table->index(['facility_id', 'initials_base'], 'facility_initials_base_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('patient_display_sequences');
    }
};

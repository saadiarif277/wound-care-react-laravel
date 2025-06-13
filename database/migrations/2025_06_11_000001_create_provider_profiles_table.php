<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('provider_profiles')) {
            return;
        }

        Schema::create('provider_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('provider_id')->primary();
            $table->string('npi')->nullable();
            $table->string('tax_id')->nullable();
            $table->string('ptan')->nullable();
            $table->string('specialty')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('provider_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_profiles');
    }
};

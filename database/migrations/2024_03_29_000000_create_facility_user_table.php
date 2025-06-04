<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('facility_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('relationship_type')->default('provider'); // Type of relationship (provider, manager, etc.)
            $table->string('role')->nullable(); // Provider's role at the facility
            $table->timestamps();

            // Ensure a user can only be associated with a facility once
            $table->unique(['facility_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('facility_user');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('patient_display_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('facility_id');
            $table->foreign('facility_id')
                  ->references('id')
                  ->on('facilities')
                  ->onDelete('cascade');
            $table->string('initials_base', 4); // "JoSm"
            $table->integer('next_sequence')->default(1);
            $table->timestamps();

            // Unique constraint for facility + initials combination
            $table->unique(['facility_id', 'initials_base']);

            // Index for efficient lookups
            $table->index(['facility_id', 'initials_base']);
        });

        // Create the atomic sequence increment function
        DB::unprepared('
            CREATE OR REPLACE FUNCTION increment_patient_sequence(
                p_facility_id CHAR(36),
                p_initials_base VARCHAR(4)
            ) RETURNS INTEGER
            BEGIN
                DECLARE current_seq INTEGER;
                INSERT INTO patient_display_sequences (id, facility_id, initials_base, next_sequence, created_at, updated_at)
                VALUES (UUID(), p_facility_id, p_initials_base, 2, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    next_sequence = next_sequence + 1,
                    updated_at = NOW();
                SELECT next_sequence - 1 INTO current_seq FROM patient_display_sequences WHERE facility_id = p_facility_id AND initials_base = p_initials_base;
                RETURN current_seq;
            END;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the function first
        DB::unprepared('DROP FUNCTION IF EXISTS increment_patient_sequence(CHAR(36), VARCHAR(4));');

        Schema::dropIfExists('patient_display_sequences');
    }
};

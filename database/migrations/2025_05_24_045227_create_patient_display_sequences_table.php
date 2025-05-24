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
            $table->foreignId('facility_id')->constrained('facilities')->onDelete('cascade');
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
                p_facility_id INTEGER,
                p_initials_base VARCHAR(4)
            ) RETURNS INTEGER AS $$
            DECLARE
                current_seq INTEGER;
            BEGIN
                -- Insert or update sequence record atomically
                INSERT INTO patient_display_sequences (id, facility_id, initials_base, next_sequence, created_at, updated_at)
                VALUES (gen_random_uuid(), p_facility_id, p_initials_base, 2, now(), now())
                ON CONFLICT (facility_id, initials_base)
                DO UPDATE SET
                    next_sequence = patient_display_sequences.next_sequence + 1,
                    updated_at = now()
                RETURNING next_sequence - 1 INTO current_seq;

                RETURN current_seq;
            END;
            $$ LANGUAGE plpgsql;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the function first
        DB::unprepared('DROP FUNCTION IF EXISTS increment_patient_sequence(INTEGER, VARCHAR(4));');

        Schema::dropIfExists('patient_display_sequences');
    }
};

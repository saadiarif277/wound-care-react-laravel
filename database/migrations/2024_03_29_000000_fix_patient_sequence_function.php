<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the old function first
        DB::unprepared('DROP FUNCTION IF EXISTS increment_patient_sequence;');

        // Create a simple function that just increments a number
        DB::unprepared('
            CREATE FUNCTION increment_patient_sequence(
                p_facility_id INT,
                p_initials_base VARCHAR(4)
            ) RETURNS INT
            DETERMINISTIC
            BEGIN
                DECLARE v_sequence INT;

                -- Get or create sequence
                INSERT INTO patient_display_sequences
                    (facility_id, initials_base, next_sequence, created_at, updated_at)
                VALUES
                    (p_facility_id, p_initials_base, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    next_sequence = next_sequence + 1,
                    updated_at = NOW();

                -- Get the current sequence
                SELECT next_sequence INTO v_sequence
                FROM patient_display_sequences
                WHERE facility_id = p_facility_id
                AND initials_base = p_initials_base;

                RETURN v_sequence;
            END;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS increment_patient_sequence;');
    }
};

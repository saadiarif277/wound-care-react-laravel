<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DiagnosisCodesFromCsvSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First ensure wound types exist
        $this->ensureWoundTypesExist();
        
        // Clear existing diagnosis codes and relationships
        DB::table('wound_type_diagnosis_codes')->delete();
        DB::table('diagnosis_codes')->delete();

        // Import Diabetic Foot Ulcer codes
        $this->importDiabeticFootUlcerCodes();
        
        // Import Venous Leg Ulcer codes
        $this->importVenousLegUlcerCodes();
        
        // Import Pressure Ulcer codes
        $this->importPressureUlcerCodes();
        
        // Import Chronic Skin Subs codes
        $this->importChronicSkinSubsCodes();
    }

    private function importDiabeticFootUlcerCodes(): void
    {
        $csvPath = base_path('docs/data/Diabetic Foot Ulcer-Skin Subs Diagnosis Codes.csv');
        if (!file_exists($csvPath)) {
            $this->command->warn("CSV file not found: $csvPath");
            return;
        }

        $file = fopen($csvPath, 'r');
        fgetcsv($file); // Skip header row
        fgetcsv($file); // Skip instruction row
        fgetcsv($file); // Skip column headers

        $diagnosisCodes = [];
        $relationships = [];

        while (($row = fgetcsv($file)) !== false) {
            // Yellow codes (Diabetes Mellitus) - columns 0 and 1
            if (!empty($row[0]) && strlen($row[0]) > 3) {
                $code = trim($row[0]);
                $description = trim($row[1] ?? '');
                
                if (preg_match('/^[A-Z]\d{2}/', $code)) {
                    $diagnosisCodes[$code] = [
                        'id' => Str::uuid(),
                        'code' => $code,
                        'description' => $description,
                        'category' => 'yellow',
                        'specialty' => 'diabetic',
                        'wound_type' => 'diabetic_foot_ulcer',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $relationships[] = [
                        'id' => Str::uuid(),
                        'wound_type_code' => 'diabetic_foot_ulcer',
                        'diagnosis_code' => $code,
                        'category' => 'yellow',
                        'is_required' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Orange codes (Chronic Ulcer) - columns 3 and 4
            if (!empty($row[3]) && strlen($row[3]) > 3) {
                $code = trim($row[3]);
                $description = trim($row[4] ?? '');
                
                if (preg_match('/^[A-Z]\d{2}/', $code)) {
                    $diagnosisCodes[$code] = [
                        'id' => Str::uuid(),
                        'code' => $code,
                        'description' => $description,
                        'category' => 'orange',
                        'specialty' => 'chronic_ulcer',
                        'wound_type' => 'diabetic_foot_ulcer',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $relationships[] = [
                        'id' => Str::uuid(),
                        'wound_type_code' => 'diabetic_foot_ulcer',
                        'diagnosis_code' => $code,
                        'category' => 'orange',
                        'is_required' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        fclose($file);

        // Insert unique diagnosis codes
        foreach (array_chunk($diagnosisCodes, 100) as $chunk) {
            DB::table('diagnosis_codes')->insertOrIgnore(array_values($chunk));
        }

        // Insert relationships
        foreach (array_chunk($relationships, 100) as $chunk) {
            DB::table('wound_type_diagnosis_codes')->insert($chunk);
        }

        $this->command->info('Imported ' . count($diagnosisCodes) . ' Diabetic Foot Ulcer diagnosis codes');
    }

    private function importVenousLegUlcerCodes(): void
    {
        $csvPath = base_path('docs/data/Venous Leg Ulcer-Skin Subs Diagnosis Codes.csv');
        if (!file_exists($csvPath)) {
            $this->command->warn("CSV file not found: $csvPath");
            return;
        }

        $file = fopen($csvPath, 'r');
        fgetcsv($file); // Skip header row
        fgetcsv($file); // Skip instruction row

        $diagnosisCodes = [];
        $relationships = [];

        while (($row = fgetcsv($file)) !== false) {
            // Yellow codes (Venous insufficiency) - columns 0 and 1
            if (!empty($row[0]) && strlen($row[0]) > 3) {
                $code = trim($row[0]);
                $description = trim($row[1] ?? '');
                
                if (preg_match('/^[A-Z]\d{2}/', $code)) {
                    if (!isset($diagnosisCodes[$code])) {
                        $diagnosisCodes[$code] = [
                            'id' => Str::uuid(),
                            'code' => $code,
                            'description' => $description,
                            'category' => 'yellow',
                            'specialty' => 'venous',
                            'wound_type' => 'venous_leg_ulcer',
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    $relationships[] = [
                        'id' => Str::uuid(),
                        'wound_type_code' => 'venous_leg_ulcer',
                        'diagnosis_code' => $code,
                        'category' => 'yellow',
                        'is_required' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Orange codes (Chronic Ulcer) - columns 4 and 5
            if (!empty($row[4]) && strlen($row[4]) > 3) {
                $code = trim($row[4]);
                $description = trim($row[5] ?? '');
                
                if (preg_match('/^[A-Z]\d{2}/', $code)) {
                    if (!isset($diagnosisCodes[$code])) {
                        $diagnosisCodes[$code] = [
                            'id' => Str::uuid(),
                            'code' => $code,
                            'description' => $description,
                            'category' => 'orange',
                            'specialty' => 'chronic_ulcer',
                            'wound_type' => 'venous_leg_ulcer',
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    $relationships[] = [
                        'id' => Str::uuid(),
                        'wound_type_code' => 'venous_leg_ulcer',
                        'diagnosis_code' => $code,
                        'category' => 'orange',
                        'is_required' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        fclose($file);

        // Insert unique diagnosis codes
        foreach (array_chunk($diagnosisCodes, 100) as $chunk) {
            DB::table('diagnosis_codes')->insertOrIgnore(array_values($chunk));
        }

        // Insert relationships
        foreach (array_chunk($relationships, 100) as $chunk) {
            DB::table('wound_type_diagnosis_codes')->insert($chunk);
        }

        $this->command->info('Imported ' . count($diagnosisCodes) . ' Venous Leg Ulcer diagnosis codes');
    }

    private function importPressureUlcerCodes(): void
    {
        $csvPath = base_path('docs/data/Pressure Ulcer-Skin Subs Diagnosis Codes.csv');
        if (!file_exists($csvPath)) {
            $this->command->warn("CSV file not found: $csvPath");
            return;
        }

        $file = fopen($csvPath, 'r');
        fgetcsv($file); // Skip header row

        $diagnosisCodes = [];
        $relationships = [];

        while (($row = fgetcsv($file)) !== false) {
            // Pressure ulcer codes - columns 0 and 1
            if (!empty($row[0]) && strlen($row[0]) > 3) {
                $code = trim($row[0]);
                $description = trim($row[1] ?? '');
                
                if (preg_match('/^L89\./', $code)) {
                    $diagnosisCodes[$code] = [
                        'id' => Str::uuid(),
                        'code' => $code,
                        'description' => $description,
                        'category' => null, // No color category for pressure ulcers
                        'specialty' => 'pressure',
                        'wound_type' => 'pressure_ulcer',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $relationships[] = [
                        'id' => Str::uuid(),
                        'wound_type_code' => 'pressure_ulcer',
                        'diagnosis_code' => $code,
                        'category' => null,
                        'is_required' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        fclose($file);

        // Insert unique diagnosis codes
        foreach (array_chunk($diagnosisCodes, 100) as $chunk) {
            DB::table('diagnosis_codes')->insertOrIgnore(array_values($chunk));
        }

        // Insert relationships
        foreach (array_chunk($relationships, 100) as $chunk) {
            DB::table('wound_type_diagnosis_codes')->insert($chunk);
        }

        $this->command->info('Imported ' . count($diagnosisCodes) . ' Pressure Ulcer diagnosis codes');
    }

    private function importChronicSkinSubsCodes(): void
    {
        $csvPath = base_path('docs/data/Chronic-Skin Subs Diagnosis Codes.csv');
        if (!file_exists($csvPath)) {
            $this->command->warn("CSV file not found: $csvPath");
            return;
        }

        $file = fopen($csvPath, 'r');
        fgetcsv($file); // Skip header row
        fgetcsv($file); // Skip sub-header

        $diagnosisCodes = [];
        $relationships = [];

        while (($row = fgetcsv($file)) !== false) {
            // Chronic ulcer codes - columns 0 and 1
            if (!empty($row[0]) && strlen($row[0]) > 3) {
                $code = trim($row[0]);
                $description = trim($row[1] ?? '');
                
                if (preg_match('/^L9[78]\./', $code)) {
                    $diagnosisCodes[$code] = [
                        'id' => Str::uuid(),
                        'code' => $code,
                        'description' => $description,
                        'category' => 'orange', // All chronic ulcers are orange
                        'specialty' => 'chronic',
                        'wound_type' => 'chronic_ulcer',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    // These codes can be used for multiple wound types
                    $woundTypes = ['surgical_wound', 'traumatic_wound', 'arterial_ulcer', 'other'];
                    foreach ($woundTypes as $woundType) {
                        $relationships[] = [
                            'id' => Str::uuid(),
                            'wound_type_code' => $woundType,
                            'diagnosis_code' => $code,
                            'category' => 'orange',
                            'is_required' => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }
        }

        fclose($file);

        // Insert unique diagnosis codes
        foreach (array_chunk($diagnosisCodes, 100) as $chunk) {
            DB::table('diagnosis_codes')->insertOrIgnore(array_values($chunk));
        }

        // Insert relationships
        foreach (array_chunk($relationships, 100) as $chunk) {
            DB::table('wound_type_diagnosis_codes')->insert($chunk);
        }

        $this->command->info('Imported ' . count($diagnosisCodes) . ' Chronic Skin Subs diagnosis codes');
    }

    private function ensureWoundTypesExist(): void
    {
        // Check if wound types exist
        $requiredWoundTypes = [
            'diabetic_foot_ulcer' => 'Diabetic Foot Ulcer',
            'venous_leg_ulcer' => 'Venous Leg Ulcer',
            'pressure_ulcer' => 'Pressure Ulcer',
            'surgical_wound' => 'Surgical Wound',
            'traumatic_wound' => 'Traumatic Wound',
            'arterial_ulcer' => 'Arterial Ulcer',
            'chronic_ulcer' => 'Chronic Ulcer',
            'other' => 'Other'
        ];

        foreach ($requiredWoundTypes as $code => $displayName) {
            $exists = DB::table('wound_types')->where('code', $code)->exists();
            if (!$exists) {
                DB::table('wound_types')->insert([
                    'id' => Str::uuid(),
                    'code' => $code,
                    'display_name' => $displayName,
                    'description' => null,
                    'sort_order' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->command->info("Created wound type: $displayName");
            }
        }
    }
}
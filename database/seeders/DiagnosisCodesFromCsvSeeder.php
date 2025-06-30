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
<<<<<<< HEAD
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Drop dependent table first, then main table
        DB::statement('DROP TABLE IF EXISTS `wound_type_diagnosis_codes`');
        DB::statement('DROP TABLE IF EXISTS `diagnosis_codes`');
        DB::statement(<<<SQL
=======
        // Handle the table operations outside of a transaction
        $this->recreateTables();
        
        // Then do the data insertion in a transaction
        DB::transaction(function () {
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

            // Import generic L97 chronic ulcer codes first
            $this->importGenericChronicUlcerCodes();

            // Import Chronic Skin Subs codes
            $this->importChronicSkinSubsCodes();
        });
    }
    
    private function recreateTables(): void
    {
        // Use unprepared statements to avoid transaction issues
        DB::unprepared('SET FOREIGN_KEY_CHECKS=0');
        
        try {
            // Drop dependent table first, then main table
            DB::unprepared('DROP TABLE IF EXISTS `wound_type_diagnosis_codes`');
            DB::unprepared('DROP TABLE IF EXISTS `diagnosis_codes`');
            
            DB::unprepared(<<<SQL
>>>>>>> origin/provider-side
CREATE TABLE `diagnosis_codes` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `specialty` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wound_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Associated wound type: diabetic_foot_ulcer, venous_leg_ulcer, pressure_ulcer, chronic_skin_subs',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `diagnosis_codes_code_unique` (`code`),
  KEY `diagnosis_codes_category_index` (`category`),
  KEY `diagnosis_codes_is_active_index` (`is_active`),
  KEY `diagnosis_codes_wound_type_index` (`wound_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

<<<<<<< HEAD
        // Recreate wound_type_diagnosis_codes table
        DB::statement(<<<SQL
=======
            // Recreate wound_type_diagnosis_codes table
            DB::unprepared(<<<SQL
>>>>>>> origin/provider-side
CREATE TABLE `wound_type_diagnosis_codes` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wound_type_code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `diagnosis_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wound_type_diagnosis_codes_unique` (`wound_type_code`,`diagnosis_code`),
  KEY `wound_type_diagnosis_codes_wound_type_code_foreign` (`wound_type_code`),
  KEY `wound_type_diagnosis_codes_diagnosis_code_foreign` (`diagnosis_code`),
  CONSTRAINT `wound_type_diagnosis_codes_diagnosis_code_foreign` FOREIGN KEY (`diagnosis_code`) REFERENCES `diagnosis_codes` (`code`) ON DELETE CASCADE,
  CONSTRAINT `wound_type_diagnosis_codes_wound_type_code_foreign` FOREIGN KEY (`wound_type_code`) REFERENCES `wound_types` (`code`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
<<<<<<< HEAD

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

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

        // Import generic L97 chronic ulcer codes first
        $this->importGenericChronicUlcerCodes();

        // Import Chronic Skin Subs codes
        $this->importChronicSkinSubsCodes();
=======
        } finally {
            // Always re-enable foreign key checks
            DB::unprepared('SET FOREIGN_KEY_CHECKS=1');
        }
>>>>>>> origin/provider-side
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
<<<<<<< HEAD


=======
>>>>>>> origin/provider-side
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

        // Associate generic L97 codes
        $l97Codes = DB::table('diagnosis_codes')->where('specialty', 'chronic_ulcer_generic')->pluck('code');
        $dfuRelationships = [];
        foreach ($l97Codes as $l97Code) {
            $dfuRelationships[] = [
                'id' => Str::uuid(),
                'wound_type_code' => 'diabetic_foot_ulcer',
                'diagnosis_code' => $l97Code,
                'category' => 'orange',
                'is_required' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if (!empty($dfuRelationships)) {
            DB::table('wound_type_diagnosis_codes')->insert($dfuRelationships);
        }
    }

    private function importVenousLegUlcerCodes(): void
    {
        $csvPath = base_path('docs/data/VLU-primary-codes.csv');
        if (!file_exists($csvPath)) {
            $this->command->warn("CSV file not found: $csvPath");
            return;
        }

        $file = fopen($csvPath, 'r');
        fgetcsv($file); // Skip header row

        $diagnosisCodes = [];
        $relationships = [];

        while (($row = fgetcsv($file)) !== false) {
            if (!empty($row[0])) {
                $code = trim($row[0]);
                $description = trim($row[1] ?? '');

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

        fclose($file);

        // Insert unique diagnosis codes
        if (!empty($diagnosisCodes)) {
            DB::table('diagnosis_codes')->insertOrIgnore(array_values($diagnosisCodes));
        }

        // Insert relationships
        if (!empty($relationships)) {
            DB::table('wound_type_diagnosis_codes')->insert($relationships);
        }

        $this->command->info('Imported ' . count($diagnosisCodes) . ' Venous Leg Ulcer primary diagnosis codes');

        // Associate generic L97 codes
        $l97Codes = DB::table('diagnosis_codes')->where('specialty', 'chronic_ulcer_generic')->pluck('code');
        $vluRelationships = [];
        foreach ($l97Codes as $l97Code) {
            $vluRelationships[] = [
                'id' => Str::uuid(),
                'wound_type_code' => 'venous_leg_ulcer',
                'diagnosis_code' => $l97Code,
                'category' => 'orange',
                'is_required' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if (!empty($vluRelationships)) {
            DB::table('wound_type_diagnosis_codes')->insert($vluRelationships);
        }
    }

    private function importPressureUlcerCodes(): void
    {
        $csvPath = base_path('docs/data/L89-pressure-ulcer-codes.csv');
        if (!file_exists($csvPath)) {
            $this->command->warn("CSV file not found: $csvPath");
            return;
        }

        $file = fopen($csvPath, 'r');
        fgetcsv($file); // Skip header row

        $diagnosisCodes = [];
        $relationships = [];

        while (($row = fgetcsv($file)) !== false) {
            if (!empty($row[0])) {
                $code = trim($row[0]);
                $description = trim($row[1] ?? '');

                $diagnosisCodes[$code] = [
                    'id' => Str::uuid(),
                    'code' => $code,
                    'description' => $description,
                    'category' => 'orange', // Pressure ulcers are orange category
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
                    'category' => 'orange',
                    'is_required' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        fclose($file);

        // Insert unique diagnosis codes
        if (!empty($diagnosisCodes)) {
            foreach (array_chunk(array_values($diagnosisCodes), 100) as $chunk) {
                DB::table('diagnosis_codes')->insertOrIgnore($chunk);
            }
        }

        // Insert relationships
        if (!empty($relationships)) {
            foreach (array_chunk($relationships, 100) as $chunk) {
                DB::table('wound_type_diagnosis_codes')->insert($chunk);
            }
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

    private function importGenericChronicUlcerCodes(): void
    {
        $csvPath = base_path('docs/data/L97-chronic-ulcer-codes.csv');
        if (!file_exists($csvPath)) {
            $this->command->warn("CSV file not found: $csvPath");
            return;
        }

        $file = fopen($csvPath, 'r');
        fgetcsv($file); // Skip header row

        $diagnosisCodes = [];

        while (($row = fgetcsv($file)) !== false) {
            if (!empty($row[0]) && strlen($row[0]) > 3) {
                $code = trim($row[0]);
                $description = trim($row[1] ?? '');

                if (preg_match('/^[A-Z]\d{2}/', $code)) {
                    $diagnosisCodes[$code] = [
                        'id' => Str::uuid(),
                        'code' => $code,
                        'description' => $description,
                        'category' => 'orange', // Use 'orange' for consistency
                        'specialty' => 'chronic_ulcer_generic',
                        'wound_type' => null, // Not specific to one wound type
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        fclose($file);

        if (!empty($diagnosisCodes)) {
            DB::table('diagnosis_codes')->insert(array_values($diagnosisCodes));
            $this->command->info('Imported ' . count($diagnosisCodes) . ' generic chronic ulcer codes.');
        }
<<<<<<< HEAD


=======
>>>>>>> origin/provider-side
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
<<<<<<< HEAD
}
=======
}
>>>>>>> origin/provider-side

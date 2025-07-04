<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DiagnosisCodesFromCsvSeeder extends Seeder
{
    public function run()
    {
        // Clear existing data
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('diagnosis_codes')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        $codes = [];
        
        // 1. Parse the first markdown file (arterial, surgical, traumatic codes)
        $markdownCodes1 = $this->parseFirstMarkdownFile();
        $codes = array_merge($codes, $markdownCodes1);
        
        // 2. Parse the second markdown file (venous, diabetic, pressure, chronic codes)
        $markdownCodes2 = $this->parseSecondMarkdownFile();
        $codes = array_merge($codes, $markdownCodes2);
        
        // Remove duplicates based on code
        $uniqueCodes = [];
        foreach ($codes as $code) {
            $uniqueCodes[$code['code']] = $code;
        }
        
        // Post-process to ensure ALL L97/L98 codes are categorized as orange
        foreach ($uniqueCodes as $codeKey => $codeData) {
            if (preg_match('/^L(97|98)/', $codeData['code'])) {
                $uniqueCodes[$codeKey]['category'] = 'orange';
                // Keep wound type as is for specific wound types, or set to general for others
                if (!in_array($codeData['wound_type'], ['Diabetic foot ulcer', 'Venous leg ulcer', 'Arterial wounds'])) {
                    $uniqueCodes[$codeKey]['wound_type'] = 'Other/general wounds';
                }
            }
        }
        
        // Insert all codes
        foreach (array_chunk(array_values($uniqueCodes), 100) as $chunk) {
            DB::table('diagnosis_codes')->insert($chunk);
        }
        
        Log::info('DiagnosisCodesFromCsvSeeder completed', [
            'first_markdown_codes' => count($markdownCodes1),
            'second_markdown_codes' => count($markdownCodes2),
            'total_unique_codes' => count($uniqueCodes)
        ]);
        
        echo "Imported " . count($uniqueCodes) . " unique diagnosis codes\n";
        echo "First markdown codes: " . count($markdownCodes1) . "\n";
        echo "Second markdown codes: " . count($markdownCodes2) . "\n";
    }
    
    private function parseFirstMarkdownFile()
    {
        $markdownFile = base_path('docs/data-and-reference/icd-10-diagnosis-codes-for-traumatic-surgicial-articular-wounds.md');
        $codes = [];
        
        if (!file_exists($markdownFile)) {
            echo "First markdown file not found: {$markdownFile}\n";
            return $codes;
        }
        
        $content = file_get_contents($markdownFile);
        
        // Extract JavaScript arrays using regex
        $pattern = '/\{ code: "([^"]+)", description: "([^"]+)" \}/';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $code = trim($match[1]);
            $description = trim($match[2]);
            
            // Determine category based on code prefix
            $category = $this->determineMarkdownCategory($code);
            $woundType = $this->determineMarkdownWoundType($code);
            
            $codes[] = [
                'id' => Str::uuid(),
                'code' => $code,
                'description' => $description,
                'category' => $category,
                'wound_type' => $woundType,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
        
        return $codes;
    }
    
    private function parseSecondMarkdownFile()
    {
        $markdownFile = base_path('docs/data-and-reference/neat-list-of-csv-icd-10-codes.md');
        $codes = [];
        
        if (!file_exists($markdownFile)) {
            echo "Second markdown file not found: {$markdownFile}\n";
            return $codes;
        }
        
        $lines = file($markdownFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $currentGroup = null;
        
        foreach ($lines as $line) {
            // Check for group headers
            if (stripos($line, 'Group 1') !== false && stripos($line, 'Venous Ulcers') !== false) {
                $currentGroup = 'venous_ulcer';
                continue;
            } elseif (stripos($line, 'Group 2') !== false && stripos($line, 'Venous') !== false) {
                $currentGroup = 'venous_ulcer';
                continue;
            } elseif (stripos($line, 'Group 3') !== false && stripos($line, 'Diabetic') !== false) {
                $currentGroup = 'diabetic_foot_ulcer';
                continue;
            } elseif (stripos($line, 'Group 4') !== false && stripos($line, 'Pressure Ulcers') !== false) {
                $currentGroup = 'pressure_ulcer';
                continue;
            } elseif (stripos($line, 'Chronic Ulcer') !== false && $currentGroup === null) {
                $currentGroup = 'chronic_ulcer';
                continue;
            }
            
            // Parse lines that start with " - " (code lines)
            if (strpos($line, ' - ') === 0 && $currentGroup) {
                $parsedCodes = $this->parseMarkdownLine($line, $currentGroup);
                $codes = array_merge($codes, $parsedCodes);
            }
        }
        
        return $codes;
    }
    
    private function parseMarkdownLine($line, $group)
    {
        $codes = [];
        
        // Remove the leading " - "
        $line = substr($line, 3);
        
        // Split by pipe
        $parts = array_map('trim', explode('|', $line));
        
        if ($group === 'pressure_ulcer' || $group === 'chronic_ulcer') {
            // Single code format: CODE | Description
            if (count($parts) >= 2 && $this->isValidDiagnosisCode($parts[0])) {
                $code = $parts[0];
                $category = '';
                $woundType = '';
                
                if ($group === 'pressure_ulcer') {
                    // L89 codes are pressure ulcers (single coding)
                    $category = 'pressure_ulcer';
                    $woundType = 'Other/general wounds';
                } elseif ($group === 'chronic_ulcer') {
                    // L97/L98 codes are chronic ulcers (orange - wound location)
                    if (preg_match('/^L(97|98)/', $code)) {
                        $category = 'orange';
                        $woundType = 'Other/general wounds';
                    } else {
                        $category = 'chronic_ulcer_generic';
                        $woundType = 'Other/general wounds';
                    }
                }
                
                $codes[] = [
                    'id' => Str::uuid(),
                    'code' => $code,
                    'description' => $parts[1],
                    'category' => $category,
                    'wound_type' => $woundType,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        } else {
            // Dual coding format for venous and diabetic ulcers
            // Format: CODE1 | Description1 | CODE2 | Description2
            
            // For venous ulcers: I codes are orange (primary)
            // For diabetic ulcers: E codes are yellow (primary), L codes are orange (secondary)
            
            if (count($parts) >= 2) {
                $code1 = $parts[0];
                $desc1 = $parts[1];
                
                if ($this->isValidDiagnosisCode($code1)) {
                    $category = '';
                    $woundType = '';
                    
                    if ($group === 'venous_ulcer') {
                        // I83/I87 codes (venous) are YELLOW (underlying condition)
                        $category = preg_match('/^I(83|87)/', $code1) ? 'yellow' : 'orange';
                        $woundType = 'Venous leg ulcer';
                    } elseif ($group === 'diabetic_foot_ulcer') {
                        // E codes (diabetes) are YELLOW (underlying condition)
                        $category = preg_match('/^E(08|09|10|11|13)/', $code1) ? 'yellow' : 'orange';
                        $woundType = 'Diabetic foot ulcer';
                    }
                    
                    $codes[] = [
                        'id' => Str::uuid(),
                        'code' => $code1,
                        'description' => $desc1,
                        'category' => $category,
                        'wound_type' => $woundType,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
            
            if (count($parts) >= 4) {
                $code2 = $parts[2];
                $desc2 = $parts[3];
                
                if ($this->isValidDiagnosisCode($code2)) {
                    $category = '';
                    $woundType = '';
                    
                    if ($group === 'venous_ulcer') {
                        // L97/L98 codes are ORANGE (wound location), I codes are YELLOW (underlying condition)
                        if (preg_match('/^L(97|98)/', $code2)) {
                            $category = 'orange';
                        } elseif (preg_match('/^I(83|87)/', $code2)) {
                            $category = 'yellow';
                        } else {
                            $category = 'orange'; // default for chronic ulcer codes
                        }
                        $woundType = 'Venous leg ulcer';
                    } elseif ($group === 'diabetic_foot_ulcer') {
                        // L97/L98 codes are ORANGE (wound location), E codes are YELLOW (underlying condition)
                        if (preg_match('/^L(97|98)/', $code2)) {
                            $category = 'orange';
                        } elseif (preg_match('/^E(08|09|10|11|13)/', $code2)) {
                            $category = 'yellow';
                        } else {
                            $category = 'orange'; // default for chronic ulcer codes
                        }
                        $woundType = 'Diabetic foot ulcer';
                    }
                    
                    $codes[] = [
                        'id' => Str::uuid(),
                        'code' => $code2,
                        'description' => $desc2,
                        'category' => $category,
                        'wound_type' => $woundType,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
            }
        }
        
        return $codes;
    }
    
    private function determineMarkdownCategory($code)
    {
        // Arterial ulcer codes
        if (str_starts_with($code, 'I70.')) {
            return 'arterial_primary';
        }
        
        // L97/L98 codes are always ORANGE (wound location/chronic ulcer codes)
        if (str_starts_with($code, 'L97.') || str_starts_with($code, 'L98.')) {
            return 'orange';
        }
        
        // Surgical wound codes
        if (str_starts_with($code, 'T81.')) {
            return 'surgical_wound';
        }
        
        // Traumatic wound codes
        if (preg_match('/^S[0-9]{2}\./', $code)) {
            return 'traumatic_wound';
        }
        
        // Post-procedural codes
        if (str_starts_with($code, 'Z48.')) {
            return 'post_procedural';
        }
        
        // Infection codes
        if (str_starts_with($code, 'L03.') || str_starts_with($code, 'B95.') || str_starts_with($code, 'B96.')) {
            return 'infection';
        }
        
        // Amputation codes
        if (preg_match('/^S[0-9]{2}8\./', $code) || str_starts_with($code, 'T87.')) {
            return 'amputation';
        }
        
        // Other chronic ulcers
        if (str_starts_with($code, 'L98.4')) {
            return 'chronic_ulcer';
        }
        
        return 'other';
    }
    
    private function determineMarkdownWoundType($code)
    {
        // Arterial ulcer codes
        if (str_starts_with($code, 'I70.') || str_starts_with($code, 'L97.') || str_starts_with($code, 'L98.49')) {
            return 'Arterial wounds';
        }
        
        // Surgical wound codes
        if (str_starts_with($code, 'T81.')) {
            return 'Surgical wounds';
        }
        
        // Traumatic wound codes
        if (preg_match('/^S[0-9]{2}\./', $code)) {
            return 'Traumatic wounds';
        }
        
        // All other codes including chronic ulcers, infections, etc.
        return 'Other/general wounds';
    }
    
    private function isValidDiagnosisCode($code)
    {
        // Basic validation for ICD-10 format
        return preg_match('/^[A-Z][0-9]{2}\.?[0-9A-Z]*$/', $code);
    }
}

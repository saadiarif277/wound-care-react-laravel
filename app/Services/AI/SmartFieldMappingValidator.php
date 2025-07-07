<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SmartFieldMappingValidator
{
    private array $commonFieldMappings = [];
    private array $invalidFieldHistory = [];
    
    public function __construct()
    {
        $this->loadCommonFieldMappings();
        $this->loadInvalidFieldHistory();
    }
    
    /**
     * Validate and auto-correct field mappings
     */
    public function validateAndCorrectFieldMappings(array $fieldMappings, string $manufacturerName = null): array
    {
        $validTemplateFields = $this->getValidTemplateFields($manufacturerName);
        $correctedMappings = [];
        $corrections = [];
        
        foreach ($fieldMappings as $internalField => $docusealField) {
            $validation = $this->validateField($docusealField, $validTemplateFields);
            
            if ($validation['isValid']) {
                $correctedMappings[$internalField] = $docusealField;
            } else {
                $suggestion = $this->suggestCorrection($docusealField, $validTemplateFields);
                
                if ($suggestion) {
                    $correctedMappings[$internalField] = $suggestion['field'];
                    $corrections[] = [
                        'original' => $docusealField,
                        'corrected' => $suggestion['field'],
                        'confidence' => $suggestion['confidence'],
                        'reason' => $suggestion['reason']
                    ];
                    
                    Log::info("Auto-corrected field mapping", [
                        'manufacturer' => $manufacturerName,
                        'internal_field' => $internalField,
                        'original' => $docusealField,
                        'corrected' => $suggestion['field'],
                        'confidence' => $suggestion['confidence']
                    ]);
                } else {
                    // Skip invalid fields that can't be corrected
                    Log::warning("Cannot auto-correct invalid field mapping", [
                        'manufacturer' => $manufacturerName,
                        'internal_field' => $internalField,
                        'invalid_field' => $docusealField
                    ]);
                }
            }
        }
        
        return [
            'mappings' => $correctedMappings,
            'corrections' => $corrections,
            'removed_invalid' => array_diff_key($fieldMappings, $correctedMappings)
        ];
    }
    
    /**
     * Get valid fields from DocuSeal templates
     */
    private function getValidTemplateFields(string $manufacturerName = null): array
    {
        $cacheKey = "valid_docuseal_fields" . ($manufacturerName ? "_$manufacturerName" : "");
        
        return Cache::remember($cacheKey, 3600, function () use ($manufacturerName) {
            $validFields = [];
            
            // Get templates for specific manufacturer or all templates
            $templates = DocusealTemplate::when($manufacturerName, function ($query, $name) {
                return $query->whereHas('manufacturer', function ($q) use ($name) {
                    $q->where('name', 'like', "%$name%");
                });
            })->get();
            
            foreach ($templates as $template) {
                try {
                    // Get actual fields from DocuSeal API
                    $templateFields = $this->docusealService->getTemplateFields($template->docuseal_template_id);
                    
                    foreach ($templateFields as $field) {
                        $validFields[] = $field['name'];
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to get template fields", [
                        'template_id' => $template->docuseal_template_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Add common field patterns
            $validFields = array_merge($validFields, $this->getCommonFieldPatterns());
            
            return array_unique($validFields);
        });
    }
    
    /**
     * Validate a single field
     */
    private function validateField(string $fieldName, array $validFields): array
    {
        // Exact match
        if (in_array($fieldName, $validFields)) {
            return ['isValid' => true, 'reason' => 'exact_match'];
        }
        
        // Case insensitive match
        $lowercaseField = strtolower($fieldName);
        foreach ($validFields as $validField) {
            if (strtolower($validField) === $lowercaseField) {
                return ['isValid' => true, 'reason' => 'case_insensitive_match'];
            }
        }
        
        // Check against known invalid patterns
        if ($this->isKnownInvalidField($fieldName)) {
            return ['isValid' => false, 'reason' => 'known_invalid_pattern'];
        }
        
        return ['isValid' => false, 'reason' => 'not_found'];
    }
    
    /**
     * Suggest correction for invalid field
     */
    private function suggestCorrection(string $invalidField, array $validFields): ?array
    {
        $suggestions = [];
        
        // 1. Check common corrections mapping
        if (isset($this->commonFieldMappings[$invalidField])) {
            return [
                'field' => $this->commonFieldMappings[$invalidField],
                'confidence' => 0.95,
                'reason' => 'common_correction'
            ];
        }
        
        // 2. Fuzzy matching with similarity
        foreach ($validFields as $validField) {
            $similarity = $this->calculateSimilarity($invalidField, $validField);
            
            if ($similarity > 0.7) {
                $suggestions[] = [
                    'field' => $validField,
                    'confidence' => $similarity,
                    'reason' => 'fuzzy_match'
                ];
            }
        }
        
        // 3. Semantic matching (if AI service available)
        $semanticSuggestions = $this->getSemanticSuggestions($invalidField, $validFields);
        $suggestions = array_merge($suggestions, $semanticSuggestions);
        
        // 4. Pattern-based suggestions
        $patternSuggestions = $this->getPatternBasedSuggestions($invalidField, $validFields);
        $suggestions = array_merge($suggestions, $patternSuggestions);
        
        // Sort by confidence and return best match
        if (!empty($suggestions)) {
            usort($suggestions, function($a, $b) {
                return $b['confidence'] <=> $a['confidence'];
            });
            
            return $suggestions[0];
        }
        
        return null;
    }
    
    /**
     * Calculate string similarity
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        // Combine multiple similarity algorithms
        $levenshtein = 1 - (levenshtein(strtolower($str1), strtolower($str2)) / max(strlen($str1), strlen($str2)));
        $jaro = $this->jaroWinkler(strtolower($str1), strtolower($str2));
        $metaphone = metaphone($str1) === metaphone($str2) ? 1 : 0;
        
        // Weighted average
        return ($levenshtein * 0.4) + ($jaro * 0.4) + ($metaphone * 0.2);
    }
    
    /**
     * Jaro-Winkler similarity
     */
    private function jaroWinkler(string $s1, string $s2): float
    {
        $len1 = strlen($s1);
        $len2 = strlen($s2);
        
        if ($len1 == 0) return $len2 == 0 ? 1.0 : 0.0;
        if ($len2 == 0) return 0.0;
        
        $matchDistance = (int) floor(max($len1, $len2) / 2) - 1;
        $s1Matches = array_fill(0, $len1, false);
        $s2Matches = array_fill(0, $len2, false);
        
        $matches = 0;
        $transpositions = 0;
        
        // Identify matches
        for ($i = 0; $i < $len1; $i++) {
            $start = max(0, $i - $matchDistance);
            $end = min($i + $matchDistance + 1, $len2);
            
            for ($j = $start; $j < $end; $j++) {
                if ($s2Matches[$j] || $s1[$i] != $s2[$j]) continue;
                $s1Matches[$i] = true;
                $s2Matches[$j] = true;
                $matches++;
                break;
            }
        }
        
        if ($matches == 0) return 0.0;
        
        // Count transpositions
        $k = 0;
        for ($i = 0; $i < $len1; $i++) {
            if (!$s1Matches[$i]) continue;
            while (!$s2Matches[$k]) $k++;
            if ($s1[$i] != $s2[$k]) $transpositions++;
            $k++;
        }
        
        $jaro = ($matches / $len1 + $matches / $len2 + ($matches - $transpositions / 2) / $matches) / 3.0;
        
        // Winkler prefix scaling
        $prefix = 0;
        for ($i = 0; $i < min($len1, $len2, 4); $i++) {
            if ($s1[$i] == $s2[$i]) $prefix++;
            else break;
        }
        
        return $jaro + (0.1 * $prefix * (1.0 - $jaro));
    }
    
    /**
     * Get semantic suggestions using AI
     */
    private function getSemanticSuggestions(string $invalidField, array $validFields): array
    {
        try {
            $response = Http::timeout(5)->post('http://localhost:8080/semantic-field-match', [
                'invalid_field' => $invalidField,
                'valid_fields' => array_slice($validFields, 0, 50), // Limit for API
                'context' => 'docuseal_field_mapping'
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['suggestions'] ?? [];
            }
        } catch (\Exception $e) {
            Log::debug("Semantic suggestion failed", ['error' => $e->getMessage()]);
        }
        
        return [];
    }
    
    /**
     * Get pattern-based suggestions
     */
    private function getPatternBasedSuggestions(string $invalidField, array $validFields): array
    {
        $suggestions = [];
        $patterns = [
            'Name' => ['Patient Name', 'Provider Name', 'Facility Name', 'Patient Full Name'],
            'Email' => ['Patient Email', 'Provider Email', 'Contact Email'],
            'Phone' => ['Patient Phone', 'Provider Phone', 'Contact Phone', 'Phone Number'],
            'Date' => ['Date of Birth', 'Service Date', 'Signature Date'],
            'Address' => ['Patient Address', 'Provider Address', 'Facility Address'],
        ];
        
        foreach ($patterns as $pattern => $alternatives) {
            if (stripos($invalidField, $pattern) !== false) {
                foreach ($alternatives as $alternative) {
                    if (in_array($alternative, $validFields)) {
                        $suggestions[] = [
                            'field' => $alternative,
                            'confidence' => 0.8,
                            'reason' => 'pattern_match'
                        ];
                    }
                }
            }
        }
        
        return $suggestions;
    }
    
    /**
     * Check if field is known to be invalid
     */
    private function isKnownInvalidField(string $fieldName): bool
    {
        $knownInvalidPatterns = [
            'Name' => true,  // Too generic
            'Email' => true, // Too generic
            'Phone' => true, // Too generic
            'Date' => true,  // Too generic
            'Address' => true, // Too generic
        ];
        
        return isset($knownInvalidPatterns[$fieldName]) || 
               in_array($fieldName, $this->invalidFieldHistory);
    }
    
    /**
     * Get common field patterns
     */
    private function getCommonFieldPatterns(): array
    {
        return [
            'Patient Name',
            'Patient Full Name',
            'Patient First Name',
            'Patient Last Name',
            'Patient Phone',
            'Patient Email',
            'Patient Address',
            'Patient DOB',
            'Date of Birth',
            'Provider Name',
            'Provider Phone',
            'Provider Email',
            'Provider NPI',
            'Facility Name',
            'Facility Address',
            'Facility Phone',
            'Insurance Name',
            'Policy Number',
            'Member ID',
            'Signature Date',
            'Service Date',
        ];
    }
    
    /**
     * Load common field mappings
     */
    private function loadCommonFieldMappings(): void
    {
        $this->commonFieldMappings = [
            'Name' => 'Patient Name',
            'Email' => 'Patient Email',
            'Phone' => 'Patient Phone',
            'Date' => 'Date of Birth',
            'Address' => 'Patient Address',
            'DOB' => 'Date of Birth',
            'NPI' => 'Provider NPI',
            'Insurance' => 'Insurance Name',
            'Policy' => 'Policy Number',
            'Member' => 'Member ID',
        ];
    }
    
    /**
     * Load invalid field history
     */
    private function loadInvalidFieldHistory(): void
    {
        $this->invalidFieldHistory = Cache::get('invalid_field_history', []);
    }
    
    /**
     * Record invalid field for future reference
     */
    public function recordInvalidField(string $fieldName, string $context = null): void
    {
        $this->invalidFieldHistory[] = $fieldName;
        $this->invalidFieldHistory = array_unique($this->invalidFieldHistory);
        
        Cache::put('invalid_field_history', $this->invalidFieldHistory, 86400 * 30); // 30 days
        
        Log::info("Recorded invalid field", [
            'field' => $fieldName,
            'context' => $context
        ]);
    }
    
    /**
     * Get validation report
     */
    public function getValidationReport(): array
    {
        return [
            'common_mappings_count' => count($this->commonFieldMappings),
            'invalid_history_count' => count($this->invalidFieldHistory),
            'cache_status' => Cache::has('valid_docuseal_fields') ? 'active' : 'empty',
            'last_validation' => Cache::get('last_field_validation', null),
        ];
    }
    
    /**
     * Prevalidate manufacturer config
     */
    public function prevalidateManufacturerConfig(array $config): array
    {
        $errors = [];
        $warnings = [];
        
        if (isset($config['docuseal_field_names'])) {
            $result = $this->validateAndCorrectFieldMappings(
                $config['docuseal_field_names'],
                $config['name'] ?? null
            );
            
            if (!empty($result['corrections'])) {
                $warnings[] = "Auto-corrected field mappings: " . count($result['corrections']);
            }
            
            if (!empty($result['removed_invalid'])) {
                $errors[] = "Removed invalid fields: " . implode(', ', array_keys($result['removed_invalid']));
            }
        }
        
        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'corrected_config' => $config
        ];
    }
} 
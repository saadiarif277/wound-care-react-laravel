<?php

namespace App\Services\FieldMapping;

use Illuminate\Support\Facades\Cache;

class FieldMatcher
{
    private array $config;
    private array $semanticMappings;

    public function __construct()
    {
        $this->config = config('field-mapping.fuzzy_matching', [
            'confidence_threshold' => 0.7,
            'exact_match_boost' => 1.5,
            'semantic_match_boost' => 1.2,
            'fuzzy_match_boost' => 1.0,
            'pattern_match_boost' => 1.1,
            'cache_ttl' => 3600,
        ]);

        $this->initializeSemanticMappings();
    }

    /**
     * Initialize semantic mappings for common field variations
     */
    private function initializeSemanticMappings(): void
    {
        $this->semanticMappings = config('field-mapping.field_aliases', [
            'patient_first_name' => ['first_name', 'fname', 'patient_fname', 'firstName'],
            'patient_last_name' => ['last_name', 'lname', 'patient_lname', 'lastName'],
            'patient_dob' => ['date_of_birth', 'dob', 'birth_date', 'birthDate'],
            'patient_phone' => ['phone', 'phone_number', 'telephone', 'contact_phone'],
            'patient_email' => ['email', 'email_address', 'contact_email'],
            'primary_insurance_name' => ['insurance_name', 'payer_name', 'insurance_company'],
            'primary_member_id' => ['member_id', 'subscriber_id', 'insurance_id'],
            'provider_npi' => ['npi', 'provider_number', 'npi_number'],
        ]);
    }

    /**
     * Find the best matching field from available fields
     */
    public function findBestMatch(string $targetField, array $availableFields, array $context = []): ?array
    {
        $cacheKey = "field_match:{$targetField}:" . md5(json_encode($availableFields));
        
        return Cache::remember($cacheKey, $this->config['cache_ttl'], function() use ($targetField, $availableFields, $context) {
            $matches = [];

            foreach ($availableFields as $field) {
                $score = $this->calculateMatchScore($targetField, $field, $context);
                
                if ($score >= $this->config['confidence_threshold']) {
                    $matches[] = [
                        'field' => $field,
                        'score' => $score,
                        'match_type' => $this->determineMatchType($targetField, $field),
                    ];
                }
            }

            if (empty($matches)) {
                return null;
            }

            // Sort by score descending
            usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

            return $matches[0];
        });
    }

    /**
     * Calculate match score between target and candidate field
     */
    private function calculateMatchScore(string $target, string $candidate, array $context): float
    {
        $scores = [];

        // Exact match
        if ($this->isExactMatch($target, $candidate)) {
            $scores[] = 1.0 * $this->config['exact_match_boost'];
        }

        // Semantic match
        $semanticScore = $this->getSemanticScore($target, $candidate);
        if ($semanticScore > 0) {
            $scores[] = $semanticScore * $this->config['semantic_match_boost'];
        }

        // Fuzzy string match
        $fuzzyScore = $this->getFuzzyScore($target, $candidate);
        if ($fuzzyScore > 0) {
            $scores[] = $fuzzyScore * $this->config['fuzzy_match_boost'];
        }

        // Pattern match
        $patternScore = $this->getPatternScore($target, $candidate);
        if ($patternScore > 0) {
            $scores[] = $patternScore * $this->config['pattern_match_boost'];
        }

        // Context boost
        if (!empty($context[$candidate])) {
            $contextScore = $this->getContextScore($target, $candidate, $context[$candidate]);
            if ($contextScore > 0) {
                $scores[] = $contextScore;
            }
        }

        return empty($scores) ? 0.0 : max($scores);
    }

    /**
     * Check for exact match (case-insensitive)
     */
    private function isExactMatch(string $target, string $candidate): bool
    {
        return strtolower($target) === strtolower($candidate);
    }

    /**
     * Calculate semantic similarity score
     */
    private function getSemanticScore(string $target, string $candidate): float
    {
        // Check if target has known aliases
        foreach ($this->semanticMappings as $canonical => $aliases) {
            if (strtolower($target) === strtolower($canonical)) {
                if (in_array(strtolower($candidate), array_map('strtolower', $aliases))) {
                    return 0.95;
                }
            }
            
            // Check reverse mapping
            if (in_array(strtolower($target), array_map('strtolower', $aliases))) {
                if (strtolower($candidate) === strtolower($canonical)) {
                    return 0.95;
                }
                if (in_array(strtolower($candidate), array_map('strtolower', $aliases))) {
                    return 0.90;
                }
            }
        }

        return 0.0;
    }

    /**
     * Calculate fuzzy string match score using multiple algorithms
     */
    private function getFuzzyScore(string $target, string $candidate): float
    {
        $target = strtolower($target);
        $candidate = strtolower($candidate);

        // Jaro-Winkler distance
        $jaroWinkler = $this->jaroWinkler($target, $candidate);
        
        // Levenshtein similarity
        $levenshtein = 1 - (levenshtein($target, $candidate) / max(strlen($target), strlen($candidate)));
        
        // Contains check
        $contains = 0;
        if (str_contains($candidate, $target) || str_contains($target, $candidate)) {
            $contains = 0.8;
        }

        return max($jaroWinkler, $levenshtein, $contains);
    }

    /**
     * Calculate Jaro-Winkler distance
     */
    private function jaroWinkler(string $s1, string $s2, float $p = 0.1): float
    {
        $jaro = $this->jaro($s1, $s2);
        
        if ($jaro < 0.7) {
            return $jaro;
        }

        // Find common prefix up to 4 characters
        $prefix = 0;
        for ($i = 0; $i < min(strlen($s1), strlen($s2), 4); $i++) {
            if ($s1[$i] === $s2[$i]) {
                $prefix++;
            } else {
                break;
            }
        }

        return $jaro + ($prefix * $p * (1 - $jaro));
    }

    /**
     * Calculate Jaro distance
     */
    private function jaro(string $s1, string $s2): float
    {
        if ($s1 === $s2) {
            return 1.0;
        }

        $len1 = strlen($s1);
        $len2 = strlen($s2);

        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }

        $matchWindow = floor(max($len1, $len2) / 2) - 1;
        $matchWindow = max(0, $matchWindow);

        $s1Matches = array_fill(0, $len1, false);
        $s2Matches = array_fill(0, $len2, false);

        $matches = 0;
        $transpositions = 0;

        // Find matches
        for ($i = 0; $i < $len1; $i++) {
            $start = max(0, $i - $matchWindow);
            $end = min($i + $matchWindow + 1, $len2);

            for ($j = $start; $j < $end; $j++) {
                if ($s2Matches[$j] || $s1[$i] !== $s2[$j]) {
                    continue;
                }
                $s1Matches[$i] = true;
                $s2Matches[$j] = true;
                $matches++;
                break;
            }
        }

        if ($matches === 0) {
            return 0.0;
        }

        // Find transpositions
        $k = 0;
        for ($i = 0; $i < $len1; $i++) {
            if (!$s1Matches[$i]) {
                continue;
            }
            while (!$s2Matches[$k]) {
                $k++;
            }
            if ($s1[$i] !== $s2[$k]) {
                $transpositions++;
            }
            $k++;
        }

        return ($matches / $len1 + $matches / $len2 + ($matches - $transpositions / 2) / $matches) / 3;
    }

    /**
     * Calculate pattern-based matching score
     */
    private function getPatternScore(string $target, string $candidate): float
    {
        $patterns = [
            // Common field naming patterns
            '/^(.+)_id$/' => '/^(.+)_id$/',
            '/^(.+)_name$/' => '/^(.+)_name$/',
            '/^(.+)_date$/' => '/^(.+)_date$/',
            '/^(.+)_code$/' => '/^(.+)_code$/',
            '/^patient_(.+)$/' => '/^patient_(.+)$/',
            '/^provider_(.+)$/' => '/^provider_(.+)$/',
            '/^insurance_(.+)$/' => '/^insurance_(.+)$/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $target, $targetMatches) && 
                preg_match($pattern, $candidate, $candidateMatches)) {
                if ($targetMatches[1] === $candidateMatches[1]) {
                    return 0.9;
                }
                // Partial match on pattern
                return 0.7;
            }
        }

        return 0.0;
    }

    /**
     * Calculate context-based score
     */
    private function getContextScore(string $target, string $candidate, $value): float
    {
        // If the value looks like what we expect, boost the score
        $expectations = [
            'phone' => '/^\d{10}$/',
            'npi' => '/^\d{10}$/',
            'zip' => '/^\d{5}(-\d{4})?$/',
            'email' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
            'date' => '/^\d{4}-\d{2}-\d{2}$/',
        ];

        foreach ($expectations as $type => $pattern) {
            if (str_contains($target, $type) && preg_match($pattern, $value)) {
                return 0.2; // Context boost
            }
        }

        return 0.0;
    }

    /**
     * Determine the type of match
     */
    private function determineMatchType(string $target, string $candidate): string
    {
        if ($this->isExactMatch($target, $candidate)) {
            return 'exact';
        }

        if ($this->getSemanticScore($target, $candidate) > 0) {
            return 'semantic';
        }

        if ($this->getPatternScore($target, $candidate) > 0) {
            return 'pattern';
        }

        return 'fuzzy';
    }

    /**
     * Clear matching cache
     */
    public function clearCache(): void
    {
        Cache::flush(); // In production, use tags for more selective clearing
    }
}
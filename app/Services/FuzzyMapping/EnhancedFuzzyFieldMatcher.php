<?php

namespace App\Services\FuzzyMapping;

use App\Models\IVRFieldMapping;
use App\Models\IVRMappingAudit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EnhancedFuzzyFieldMatcher
{
    protected array $semanticMappings = [
        // Patient mappings
        'patient.name.given' => ['patient_first_name', 'first_name', 'fname', 'given_name', 'patient_fname'],
        'patient.name.family' => ['patient_last_name', 'last_name', 'lname', 'surname', 'family_name', 'patient_lname'],
        'patient.birthDate' => ['dob', 'date_of_birth', 'birth_date', 'patient_dob', 'patient_birth_date'],
        
        // Provider mappings
        'practitioner.name' => ['provider_name', 'physician_name', 'doctor_name', 'practitioner_name', 'provider'],
        'practitioner.identifier.npi' => ['npi', 'provider_npi', 'physician_npi', 'npi_number'],
        
        // Wound mappings
        'condition.code.text' => ['wound_type', 'wound_description', 'condition_type', 'diagnosis'],
        'condition.bodySite.text' => ['wound_location', 'body_site', 'anatomical_location', 'location'],
        
        // Insurance mappings
        'coverage.payor.display' => ['insurance_company', 'payor_name', 'insurance_carrier', 'carrier_name'],
        'coverage.identifier.value' => ['insurance_id', 'member_id', 'policy_number', 'insurance_number'],
    ];

    protected array $patternMappings = [
        '/patient.*first.*name/i' => 'patient.name.given',
        '/patient.*last.*name/i' => 'patient.name.family',
        '/provider.*npi/i' => 'practitioner.identifier.npi',
        '/wound.*type/i' => 'condition.code.text',
        '/wound.*location/i' => 'condition.bodySite.text',
    ];

    protected float $fuzzyThreshold = 0.7;
    protected float $semanticBoost = 1.2;
    protected float $patternBoost = 1.1;

    public function findBestMatch(
        string $ivrFieldName,
        array $availableFhirData,
        int $manufacturerId,
        string $templateName
    ): ?array {
        $cacheKey = "field_match:{$manufacturerId}:{$templateName}:{$ivrFieldName}";
        
        // Check cache first
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        // Check database for existing high-confidence mapping
        $existingMapping = IVRFieldMapping::forManufacturerTemplate($manufacturerId, $templateName)
            ->where('source_field', $ivrFieldName)
            ->highConfidence()
            ->first();

        if ($existingMapping) {
            $result = $this->applyMapping($existingMapping, $availableFhirData);
            if ($result) {
                $existingMapping->incrementUsage(true);
                $this->auditMapping($existingMapping, $result, 'database', 1.0, true);
                Cache::put($cacheKey, $result, now()->addHours(1));
                return $result;
            }
        }

        // Try multiple matching strategies
        $matches = [];

        // 1. Exact match
        if ($exactMatch = $this->findExactMatch($ivrFieldName, $availableFhirData)) {
            $matches[] = array_merge($exactMatch, ['strategy' => 'exact', 'boost' => 1.5]);
        }

        // 2. Semantic match
        if ($semanticMatch = $this->findSemanticMatch($ivrFieldName, $availableFhirData)) {
            $matches[] = array_merge($semanticMatch, ['strategy' => 'semantic', 'boost' => $this->semanticBoost]);
        }

        // 3. Fuzzy string match
        $fuzzyMatches = $this->findFuzzyMatches($ivrFieldName, $availableFhirData);
        foreach ($fuzzyMatches as $match) {
            $matches[] = array_merge($match, ['strategy' => 'fuzzy', 'boost' => 1.0]);
        }

        // 4. Pattern match
        if ($patternMatch = $this->findPatternMatch($ivrFieldName, $availableFhirData)) {
            $matches[] = array_merge($patternMatch, ['strategy' => 'pattern', 'boost' => $this->patternBoost]);
        }

        // Select best match
        $bestMatch = $this->selectBestMatch($matches);

        if ($bestMatch) {
            // Save successful mapping for future use
            $this->saveMapping($manufacturerId, $templateName, $ivrFieldName, $bestMatch);
            Cache::put($cacheKey, $bestMatch, now()->addHours(1));
        }

        return $bestMatch;
    }

    protected function findExactMatch(string $ivrFieldName, array $availableFhirData): ?array
    {
        $normalizedIvr = $this->normalizeFieldName($ivrFieldName);

        foreach ($availableFhirData as $fhirPath => $value) {
            if ($this->normalizeFieldName($fhirPath) === $normalizedIvr) {
                return [
                    'target_field' => $fhirPath,
                    'value' => $value,
                    'confidence' => 1.0,
                ];
            }
        }

        return null;
    }

    protected function findSemanticMatch(string $ivrFieldName, array $availableFhirData): ?array
    {
        $normalizedIvr = $this->normalizeFieldName($ivrFieldName);

        foreach ($this->semanticMappings as $fhirPath => $variations) {
            foreach ($variations as $variation) {
                if ($this->normalizeFieldName($variation) === $normalizedIvr) {
                    if (isset($availableFhirData[$fhirPath])) {
                        return [
                            'target_field' => $fhirPath,
                            'value' => $availableFhirData[$fhirPath],
                            'confidence' => 0.95,
                        ];
                    }
                }
            }
        }

        return null;
    }

    protected function findFuzzyMatches(string $ivrFieldName, array $availableFhirData): array
    {
        $matches = [];
        $normalizedIvr = $this->normalizeFieldName($ivrFieldName);

        foreach ($availableFhirData as $fhirPath => $value) {
            $normalizedFhir = $this->normalizeFieldName($fhirPath);
            $similarity = $this->calculateSimilarity($normalizedIvr, $normalizedFhir);

            if ($similarity >= $this->fuzzyThreshold) {
                $matches[] = [
                    'target_field' => $fhirPath,
                    'value' => $value,
                    'confidence' => $similarity,
                ];
            }
        }

        return $matches;
    }

    protected function findPatternMatch(string $ivrFieldName, array $availableFhirData): ?array
    {
        foreach ($this->patternMappings as $pattern => $fhirPath) {
            if (preg_match($pattern, $ivrFieldName)) {
                if (isset($availableFhirData[$fhirPath])) {
                    return [
                        'target_field' => $fhirPath,
                        'value' => $availableFhirData[$fhirPath],
                        'confidence' => 0.85,
                    ];
                }
            }
        }

        return null;
    }

    protected function normalizeFieldName(string $fieldName): string
    {
        // Remove special characters and normalize
        $normalized = strtolower($fieldName);
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized);
        $normalized = trim($normalized, '_');
        
        return $normalized;
    }

    protected function calculateSimilarity(string $str1, string $str2): float
    {
        // Use multiple similarity metrics
        $levenshtein = 1 - (levenshtein($str1, $str2) / max(strlen($str1), strlen($str2)));
        $jaroWinkler = $this->jaroWinkler($str1, $str2);
        $tokenSimilarity = $this->tokenSimilarity($str1, $str2);

        // Weighted average
        return ($levenshtein * 0.4 + $jaroWinkler * 0.4 + $tokenSimilarity * 0.2);
    }

    protected function jaroWinkler(string $str1, string $str2): float
    {
        // Simplified Jaro-Winkler implementation
        $jaro = $this->jaro($str1, $str2);
        $prefix = 0;
        $max = min(strlen($str1), strlen($str2), 4);

        for ($i = 0; $i < $max; $i++) {
            if ($str1[$i] == $str2[$i]) {
                $prefix++;
            } else {
                break;
            }
        }

        return $jaro + ($prefix * 0.1 * (1.0 - $jaro));
    }

    protected function jaro(string $str1, string $str2): float
    {
        $len1 = strlen($str1);
        $len2 = strlen($str2);

        if ($len1 == 0 && $len2 == 0) return 1.0;
        if ($len1 == 0 || $len2 == 0) return 0.0;

        $matchDistance = (int) (max($len1, $len2) / 2) - 1;
        $matches = 0;
        $transpositions = 0;

        $s1Matches = array_fill(0, $len1, false);
        $s2Matches = array_fill(0, $len2, false);

        // Find matches
        for ($i = 0; $i < $len1; $i++) {
            $start = max(0, $i - $matchDistance);
            $end = min($i + $matchDistance + 1, $len2);

            for ($j = $start; $j < $end; $j++) {
                if ($s2Matches[$j] || $str1[$i] != $str2[$j]) continue;
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
            if ($str1[$i] != $str2[$k]) $transpositions++;
            $k++;
        }

        return ($matches / $len1 + $matches / $len2 + ($matches - $transpositions / 2) / $matches) / 3;
    }

    protected function tokenSimilarity(string $str1, string $str2): float
    {
        $tokens1 = array_filter(explode('_', $str1));
        $tokens2 = array_filter(explode('_', $str2));

        if (empty($tokens1) || empty($tokens2)) return 0.0;

        $intersection = count(array_intersect($tokens1, $tokens2));
        $union = count(array_unique(array_merge($tokens1, $tokens2)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    protected function selectBestMatch(array $matches): ?array
    {
        if (empty($matches)) return null;

        // Sort by confidence * boost
        usort($matches, function ($a, $b) {
            $scoreA = $a['confidence'] * $a['boost'];
            $scoreB = $b['confidence'] * $b['boost'];
            return $scoreB <=> $scoreA;
        });

        $best = $matches[0];
        $finalScore = $best['confidence'] * $best['boost'];

        // Only return if score is above threshold
        if ($finalScore >= $this->fuzzyThreshold) {
            return [
                'target_field' => $best['target_field'],
                'value' => $best['value'],
                'confidence' => min($finalScore, 1.0),
                'strategy' => $best['strategy'],
            ];
        }

        return null;
    }

    protected function applyMapping(IVRFieldMapping $mapping, array $availableFhirData): ?array
    {
        if (!isset($availableFhirData[$mapping->target_field])) {
            return null;
        }

        $value = $availableFhirData[$mapping->target_field];

        // Apply transformation rules if any
        if ($mapping->transformation_rules) {
            $value = $this->applyTransformations($value, $mapping->transformation_rules);
        }

        return [
            'target_field' => $mapping->target_field,
            'value' => $value,
            'confidence' => $mapping->confidence,
            'strategy' => $mapping->match_type,
        ];
    }

    protected function applyTransformations($value, array $rules): mixed
    {
        foreach ($rules as $rule) {
            switch ($rule['type'] ?? '') {
                case 'uppercase':
                    $value = strtoupper($value);
                    break;
                case 'lowercase':
                    $value = strtolower($value);
                    break;
                case 'trim':
                    $value = trim($value);
                    break;
                case 'date_format':
                    if ($date = \DateTime::createFromFormat($rule['from'] ?? 'Y-m-d', $value)) {
                        $value = $date->format($rule['to'] ?? 'Y-m-d');
                    }
                    break;
                case 'replace':
                    $value = str_replace($rule['search'] ?? '', $rule['replace'] ?? '', $value);
                    break;
            }
        }

        return $value;
    }

    protected function saveMapping(
        int $manufacturerId,
        string $templateName,
        string $ivrFieldName,
        array $match
    ): void {
        try {
            IVRFieldMapping::create([
                'manufacturer_id' => $manufacturerId,
                'template_id' => $templateName,
                'target_field' => $match['target_field'],
                'source_field' => $ivrFieldName,
                'match_type' => $match['strategy'],
                'confidence' => $match['confidence'],
                'is_learned' => true,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to save learned mapping', [
                'error' => $e->getMessage(),
                'manufacturer_id' => $manufacturerId,
                'template_id' => $templateName,
                'source_field' => $ivrFieldName,
            ]);
        }
    }

    protected function auditMapping(
        ?IVRFieldMapping $mapping,
        array $result,
        string $strategy,
        float $confidence,
        bool $wasSuccessful,
        ?array $error = null
    ): void {
        try {
            IVRMappingAudit::create([
                'mapping_id' => $mapping?->id,
                'manufacturer_id' => $mapping?->manufacturer_id ?? 0,
                'template_id' => $mapping?->template_id ?? '',
                'target_field' => $result['target_field'] ?? '',
                'source_field' => $mapping?->source_field ?? '',
                'mapped_value' => $result['value'] ?? null,
                'mapping_strategy' => $strategy,
                'confidence' => $confidence,
                'was_successful' => $wasSuccessful,
                'error_details' => $error,
                'user_id' => auth()->id(),
                'session_id' => session()->getId(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to audit mapping', ['error' => $e->getMessage()]);
        }
    }
}
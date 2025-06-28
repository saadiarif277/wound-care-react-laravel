<?php

namespace App\Services;

use App\Models\CanonicalField;
use App\Models\TemplateFieldMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FieldMappingSuggestionService
{
    /**
     * Common field name patterns and their canonical mappings
     */
    private array $fieldPatterns = [
        // Physician patterns
        '/physician.*name|doctor.*name|provider.*name|treating.*physician/i' => 'physicianName',
        '/physician.*npi|doctor.*npi|provider.*npi|npi.*physician/i' => 'physicianNPI',
        '/physician.*specialty|doctor.*specialty|specialty/i' => 'physicianSpecialty',
        '/tax.*id|ein|federal.*tax/i' => 'taxId',
        '/ptan|medicare.*number/i' => 'ptanNumber',
        '/medicaid.*number|medicaid.*id/i' => 'medicaidNumber',
        
        // Facility patterns
        '/facility.*name|clinic.*name|hospital.*name|practice.*name/i' => 'facilityName',
        '/facility.*address|clinic.*address|practice.*address/i' => 'facilityAddress',
        '/facility.*city|clinic.*city/i' => 'facilityCity',
        '/facility.*state|clinic.*state/i' => 'facilityState',
        '/facility.*zip|clinic.*zip|facility.*postal/i' => 'facilityZip',
        '/facility.*npi|clinic.*npi|practice.*npi/i' => 'facilityNPI',
        '/facility.*type|place.*service|pos/i' => 'facilityType',
        
        // Patient patterns
        '/patient.*name|member.*name|beneficiary.*name/i' => 'patientName',
        '/patient.*dob|date.*birth|birth.*date|patient.*birthday/i' => 'patientDOB',
        '/patient.*address|member.*address/i' => 'patientAddress',
        '/patient.*city/i' => 'patientCity',
        '/patient.*state/i' => 'patientState',
        '/patient.*zip|patient.*postal/i' => 'patientZip',
        '/patient.*phone|member.*phone/i' => 'patientPhone',
        '/patient.*gender|patient.*sex|gender|sex/i' => 'patientGender',
        
        // Insurance patterns
        '/insurance.*name|payer.*name|carrier.*name/i' => 'insuranceCompanyName',
        '/policy.*number|member.*id|subscriber.*id/i' => 'policyNumber',
        '/group.*number|group.*id/i' => 'groupNumber',
        '/insurance.*phone|payer.*phone/i' => 'insurancePhone',
        
        // Service patterns
        '/place.*service|service.*location|pos/i' => 'placeOfService',
        '/hospice|in.*hospice/i' => 'inHospice',
        '/skilled.*nursing|snf|nursing.*facility/i' => 'inSkilledNursingFacility',
        '/days.*snf|snf.*days/i' => 'daysInSNF',
        '/global.*period|under.*global/i' => 'underGlobalPeriod',
        
        // Wound patterns
        '/wound.*location|location.*wound|wound.*site/i' => 'woundLocation',
        '/wound.*type|type.*wound|wound.*etiology/i' => 'woundType',
        '/wound.*size|size.*wound|wound.*dimensions/i' => 'woundSize',
        '/wound.*duration|duration.*wound|wound.*age/i' => 'woundDuration',
        '/icd.*10|diagnosis.*code|icd.*code/i' => 'icd10Codes',
        '/cpt.*code|procedure.*code/i' => 'cptCodes',
        
        // Product patterns
        '/product.*name|product.*requested|item.*requested/i' => 'productRequested',
        '/hcpcs.*code|hcpcs/i' => 'hcpcsCode',
        '/procedure.*date|service.*date|treatment.*date/i' => 'procedureDate',
        '/size.*requested|product.*size/i' => 'sizeRequested',
        
        // Authorization patterns
        '/prior.*auth|authorization.*permission|pa.*permission/i' => 'priorAuthPermission',
        '/request.*type|submission.*type/i' => 'requestType',
        '/insurance.*card|card.*attached/i' => 'insuranceCardCopyAttached',
        
        // Contact patterns
        '/contact.*name|representative.*name/i' => 'contactName',
        '/contact.*phone|representative.*phone/i' => 'contactPhone',
        '/contact.*fax|fax.*number/i' => 'contactFax',
        '/contact.*email|email.*address/i' => 'contactEmail',
    ];

    /**
     * Suggest mapping for a field
     */
    public function suggestMapping(string $fieldName, Collection $canonicalFields): array
    {
        $suggestions = [];

        // 1. Try pattern matching first
        $patternSuggestion = $this->suggestByPattern($fieldName);
        if ($patternSuggestion) {
            $canonicalField = $canonicalFields->firstWhere('field_name', $patternSuggestion);
            if ($canonicalField) {
                $suggestions[] = [
                    'canonical_field_id' => $canonicalField->id,
                    'canonical_field' => $canonicalField,
                    'confidence' => 95,
                    'method' => 'pattern',
                ];
            }
        }

        // 2. Try similarity matching
        foreach ($canonicalFields as $canonicalField) {
            $similarity = $this->calculateSimilarity($fieldName, $canonicalField->field_name);
            
            if ($similarity >= 50) { // Only include if at least 50% similar
                $suggestions[] = [
                    'canonical_field_id' => $canonicalField->id,
                    'canonical_field' => $canonicalField,
                    'confidence' => $similarity,
                    'method' => 'similarity',
                ];
            }
        }

        // 3. Try historical mapping
        $historicalSuggestions = $this->getHistoricalMappings($fieldName);
        foreach ($historicalSuggestions as $historicalMapping) {
            $canonicalField = $canonicalFields->firstWhere('id', $historicalMapping['canonical_field_id']);
            if ($canonicalField) {
                $suggestions[] = [
                    'canonical_field_id' => $canonicalField->id,
                    'canonical_field' => $canonicalField,
                    'confidence' => $historicalMapping['confidence'],
                    'method' => 'historical',
                ];
            }
        }

        // Remove duplicates and sort by confidence
        $suggestions = collect($suggestions)
            ->unique('canonical_field_id')
            ->sortByDesc('confidence')
            ->values()
            ->take(5) // Return top 5 suggestions
            ->toArray();

        return $suggestions;
    }

    /**
     * Suggest field by pattern matching
     */
    private function suggestByPattern(string $fieldName): ?string
    {
        foreach ($this->fieldPatterns as $pattern => $canonicalFieldName) {
            if (preg_match($pattern, $fieldName)) {
                return $canonicalFieldName;
            }
        }

        return null;
    }

    /**
     * Calculate similarity between two field names
     */
    public function calculateSimilarity(string $field1, string $field2): float
    {
        // Normalize field names
        $field1 = $this->normalizeFieldName($field1);
        $field2 = $this->normalizeFieldName($field2);

        // Calculate different similarity metrics
        $levenshteinSimilarity = $this->levenshteinSimilarity($field1, $field2);
        $tokenSimilarity = $this->tokenSimilarity($field1, $field2);
        $prefixSimilarity = $this->prefixSimilarity($field1, $field2);

        // Weight the different metrics
        $weightedScore = (
            $levenshteinSimilarity * 0.4 +
            $tokenSimilarity * 0.4 +
            $prefixSimilarity * 0.2
        );

        return round($weightedScore, 2);
    }

    /**
     * Normalize field name for comparison
     */
    private function normalizeFieldName(string $fieldName): string
    {
        // Convert to lowercase
        $normalized = strtolower($fieldName);
        
        // Replace common separators with spaces
        $normalized = str_replace(['_', '-', '.'], ' ', $normalized);
        
        // Remove common prefixes/suffixes
        $normalized = preg_replace('/^(txt|lbl|chk|btn|fld)/', '', $normalized);
        
        // Remove numbers at the end
        $normalized = preg_replace('/\d+$/', '', $normalized);
        
        // Remove extra spaces
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized));
        
        return $normalized;
    }

    /**
     * Calculate Levenshtein-based similarity percentage
     */
    private function levenshteinSimilarity(string $str1, string $str2): float
    {
        $maxLength = max(strlen($str1), strlen($str2));
        
        if ($maxLength === 0) {
            return 100;
        }
        
        $distance = levenshtein($str1, $str2);
        return (1 - $distance / $maxLength) * 100;
    }

    /**
     * Calculate token-based similarity
     */
    private function tokenSimilarity(string $str1, string $str2): float
    {
        $tokens1 = array_filter(explode(' ', $str1));
        $tokens2 = array_filter(explode(' ', $str2));
        
        if (empty($tokens1) || empty($tokens2)) {
            return 0;
        }
        
        $commonTokens = array_intersect($tokens1, $tokens2);
        $totalTokens = count(array_unique(array_merge($tokens1, $tokens2)));
        
        return (count($commonTokens) / $totalTokens) * 100;
    }

    /**
     * Calculate prefix similarity
     */
    private function prefixSimilarity(string $str1, string $str2): float
    {
        $minLength = min(strlen($str1), strlen($str2));
        $commonPrefix = 0;
        
        for ($i = 0; $i < $minLength; $i++) {
            if ($str1[$i] === $str2[$i]) {
                $commonPrefix++;
            } else {
                break;
            }
        }
        
        $maxLength = max(strlen($str1), strlen($str2));
        return ($commonPrefix / $maxLength) * 100;
    }

    /**
     * Get historical mappings for a field name
     */
    public function getHistoricalMappings(string $fieldName): array
    {
        // Find similar field names that have been mapped before
        $similarMappings = TemplateFieldMapping::where('field_name', 'LIKE', '%' . $fieldName . '%')
            ->whereNotNull('canonical_field_id')
            ->where('confidence_score', '>=', 70)
            ->select('canonical_field_id', 'confidence_score')
            ->groupBy('canonical_field_id', 'confidence_score')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(3)
            ->get();

        $results = [];
        foreach ($similarMappings as $mapping) {
            $results[] = [
                'canonical_field_id' => $mapping->canonical_field_id,
                'confidence' => min($mapping->confidence_score * 0.8, 90), // Reduce confidence for historical
            ];
        }

        return $results;
    }

    /**
     * Analyze field pattern to detect type
     */
    public function analyzeFieldPattern(string $fieldName): array
    {
        $analysis = [
            'likely_type' => 'unknown',
            'contains_phi' => false,
            'data_format' => 'text',
            'validation_hints' => [],
        ];

        $lowerFieldName = strtolower($fieldName);

        // Check for PHI indicators
        $phiIndicators = ['ssn', 'social', 'dob', 'birth', 'patient', 'member'];
        foreach ($phiIndicators as $indicator) {
            if (str_contains($lowerFieldName, $indicator)) {
                $analysis['contains_phi'] = true;
                break;
            }
        }

        // Detect likely data type
        if (preg_match('/date|dob|birth/i', $fieldName)) {
            $analysis['likely_type'] = 'date';
            $analysis['data_format'] = 'date';
            $analysis['validation_hints'][] = 'date_format';
        } elseif (preg_match('/phone|fax|tel/i', $fieldName)) {
            $analysis['likely_type'] = 'phone';
            $analysis['data_format'] = 'phone';
            $analysis['validation_hints'][] = 'phone_format';
        } elseif (preg_match('/email|e-mail/i', $fieldName)) {
            $analysis['likely_type'] = 'email';
            $analysis['data_format'] = 'email';
            $analysis['validation_hints'][] = 'email_format';
        } elseif (preg_match('/npi/i', $fieldName)) {
            $analysis['likely_type'] = 'npi';
            $analysis['data_format'] = 'numeric';
            $analysis['validation_hints'][] = '10_digits';
        } elseif (preg_match('/zip|postal/i', $fieldName)) {
            $analysis['likely_type'] = 'zip';
            $analysis['data_format'] = 'zip';
            $analysis['validation_hints'][] = 'zip_format';
        } elseif (preg_match('/yes|no|check|confirm/i', $fieldName)) {
            $analysis['likely_type'] = 'boolean';
            $analysis['data_format'] = 'boolean';
        }

        return $analysis;
    }

    /**
     * Rank suggestions based on multiple factors
     */
    public function rankSuggestions(array $suggestions): array
    {
        return collect($suggestions)
            ->map(function ($suggestion) {
                // Boost score based on method
                $methodBoost = match($suggestion['method']) {
                    'pattern' => 1.2,
                    'historical' => 1.1,
                    'similarity' => 1.0,
                    default => 1.0,
                };

                // Boost score if it's a required field
                $requiredBoost = $suggestion['canonical_field']->is_required ? 1.1 : 1.0;

                // Calculate final score
                $suggestion['final_score'] = $suggestion['confidence'] * $methodBoost * $requiredBoost;

                return $suggestion;
            })
            ->sortByDesc('final_score')
            ->values()
            ->toArray();
    }

    /**
     * Get mapping confidence explanation
     */
    public function getConfidenceExplanation(float $confidence): string
    {
        if ($confidence >= 90) {
            return 'Excellent match - very high confidence';
        } elseif ($confidence >= 80) {
            return 'Good match - high confidence';
        } elseif ($confidence >= 70) {
            return 'Fair match - moderate confidence';
        } elseif ($confidence >= 60) {
            return 'Possible match - low confidence';
        } else {
            return 'Weak match - very low confidence';
        }
    }
}
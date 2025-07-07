<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Medical Terminology Validation Service
 * 
 * Validates medical terms using UMLS API and local healthcare dictionaries
 * Based on 2025 healthcare OCR best practices
 */
class MedicalTerminologyService
{
    private ?string $umlsApiKey;
    private string $umlsBaseUrl = 'https://uts-ws.nlm.nih.gov/rest';
    private ?string $healthVocabApiUrl;
    
    // Local medical terminology dictionaries
    private array $terminologyDictionaries = [
        'wound_care' => [
            'wound_types' => [
                'pressure_ulcer', 'diabetic_foot_ulcer', 'venous_stasis_ulcer',
                'arterial_ulcer', 'surgical_wound', 'traumatic_wound',
                'dehiscence', 'abscess', 'cellulitis', 'osteomyelitis'
            ],
            'anatomical_locations' => [
                'sacrum', 'coccyx', 'heel', 'ankle', 'malleolus', 'toe',
                'forefoot', 'midfoot', 'hindfoot', 'plantar', 'dorsal',
                'lateral', 'medial', 'proximal', 'distal'
            ],
            'wound_characteristics' => [
                'necrotic', 'slough', 'eschar', 'granulation', 'epithelialization',
                'macerated', 'undermining', 'tunneling', 'sinus_tract',
                'erythema', 'induration', 'fluctuance', 'purulent', 'serous'
            ],
            'measurements' => [
                'length', 'width', 'depth', 'area', 'volume', 'circumference',
                'cm', 'mm', 'centimeter', 'millimeter', 'inch', 'in'
            ]
        ],
        'insurance' => [
            'member_terms' => [
                'member_id', 'subscriber_id', 'policy_number', 'group_number',
                'plan_number', 'benefit_plan', 'coverage_type', 'effective_date',
                'termination_date', 'dependent', 'subscriber', 'beneficiary'
            ],
            'plan_types' => [
                'hmo', 'ppo', 'epo', 'pos', 'medicare', 'medicaid', 'tricare',
                'commercial', 'employer_sponsored', 'individual', 'cobra'
            ],
            'financial_terms' => [
                'copay', 'coinsurance', 'deductible', 'out_of_pocket_max',
                'premium', 'allowable_amount', 'covered_amount', 'benefit_amount'
            ]
        ],
        'clinical' => [
            'vital_signs' => [
                'blood_pressure', 'heart_rate', 'respiratory_rate', 'temperature',
                'oxygen_saturation', 'pulse', 'bp', 'hr', 'rr', 'temp', 'o2_sat'
            ],
            'common_conditions' => [
                'diabetes', 'hypertension', 'obesity', 'peripheral_vascular_disease',
                'chronic_kidney_disease', 'heart_failure', 'copd', 'cancer',
                'stroke', 'myocardial_infarction', 'sepsis', 'pneumonia'
            ],
            'medications' => [
                'insulin', 'metformin', 'aspirin', 'warfarin', 'lisinopril',
                'atorvastatin', 'omeprazole', 'levothyroxine', 'amlodipine'
            ]
        ]
    ];

    public function __construct()
    {
        $this->umlsApiKey = config('services.umls.api_key');
        $this->healthVocabApiUrl = config('services.health_vocab.api_url');
    }

    /**
     * Validate medical terminology with confidence scoring
     */
    public function validateMedicalTerms(array $terms, string $context = 'general'): array
    {
        $validationResults = [];
        
        foreach ($terms as $term) {
            $validationResults[] = $this->validateSingleTerm($term, $context);
        }
        
        return [
            'overall_confidence' => $this->calculateOverallConfidence($validationResults),
            'validation_results' => $validationResults,
            'context' => $context,
            'total_terms' => count($terms),
            'valid_terms' => count(array_filter($validationResults, fn($r) => $r['is_valid'])),
            'processing_method' => $this->umlsApiKey ? 'umls_enhanced' : 'local_dictionary'
        ];
    }

    /**
     * Validate a single medical term
     */
    private function validateSingleTerm(string $term, string $context): array
    {
        $normalizedTerm = $this->normalizeTerm($term);
        
        // Step 1: Local dictionary validation
        $localValidation = $this->validateWithLocalDictionary($normalizedTerm, $context);
        
        // Step 2: External API validation (Health Vocab API + UMLS fallback)
        $externalValidation = null;
        if (!$localValidation['is_valid']) {
            $externalValidation = $this->validateWithExternalAPIs($normalizedTerm);
        }
        
        // Step 3: Combine results
        $isValid = $localValidation['is_valid'] || ($externalValidation['is_valid'] ?? false);
        $confidence = $this->calculateTermConfidence($localValidation, $externalValidation);
        
        return [
            'original_term' => $term,
            'normalized_term' => $normalizedTerm,
            'is_valid' => $isValid,
            'confidence' => $confidence,
            'matched_category' => $localValidation['category'] ?? $externalValidation['category'] ?? null,
            'suggestions' => $this->generateSuggestions($normalizedTerm, $context),
            'local_validation' => $localValidation,
            'external_validation' => $externalValidation
        ];
    }

    /**
     * Validate term using local dictionaries
     */
    private function validateWithLocalDictionary(string $term, string $context): array
    {
        $relevantDictionaries = $this->getRelevantDictionaries($context);
        
        foreach ($relevantDictionaries as $dictionaryName => $categories) {
            foreach ($categories as $categoryName => $terms) {
                if ($this->isTermInCategory($term, $terms)) {
                    return [
                        'is_valid' => true,
                        'confidence' => 0.95,
                        'category' => $categoryName,
                        'dictionary' => $dictionaryName,
                        'match_type' => 'exact'
                    ];
                }
                
                // Check for partial matches
                $partialMatch = $this->findPartialMatch($term, $terms);
                if ($partialMatch) {
                    return [
                        'is_valid' => true,
                        'confidence' => 0.80,
                        'category' => $categoryName,
                        'dictionary' => $dictionaryName,
                        'match_type' => 'partial',
                        'matched_term' => $partialMatch
                    ];
                }
            }
        }
        
        return [
            'is_valid' => false,
            'confidence' => 0.0,
            'category' => null,
            'dictionary' => null,
            'match_type' => 'none'
        ];
    }

    /**
     * Validate term using UMLS API
     */
    private function validateWithUMLS(string $term): array
    {
        $cacheKey = "umls_validation_" . md5($term);
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        try {
            $response = Http::timeout(10)->get($this->umlsBaseUrl . '/search/current', [
                'string' => $term,
                'apiKey' => $this->umlsApiKey,
                'pageSize' => 5
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $results = $data['result']['results'] ?? [];
                
                if (!empty($results)) {
                    $bestMatch = $results[0];
                    
                    $result = [
                        'is_valid' => true,
                        'confidence' => 0.90,
                        'category' => 'umls_concept',
                        'umls_cui' => $bestMatch['ui'] ?? null,
                        'preferred_name' => $bestMatch['name'] ?? null,
                        'semantic_types' => $bestMatch['semantic'] ?? [],
                        'match_type' => 'umls_api'
                    ];
                    
                    Cache::put($cacheKey, $result, now()->addHours(24));
                    return $result;
                }
            }
        } catch (Exception $e) {
            Log::warning('UMLS API validation failed', [
                'term' => $term,
                'error' => $e->getMessage()
            ]);
        }
        
        $result = [
            'is_valid' => false,
            'confidence' => 0.0,
            'category' => null,
            'match_type' => 'umls_not_found'
        ];
        
        Cache::put($cacheKey, $result, now()->addMinutes(30));
        return $result;
    }

    /**
     * Validate term using multiple APIs (UMLS + Health Vocabulary API)
     */
    private function validateWithExternalAPIs(string $term): array
    {
        // Try Health Vocabulary API first (no auth needed)
        if ($this->healthVocabApiUrl) {
            $healthVocabResult = $this->validateWithHealthVocabAPI($term);
            if ($healthVocabResult['is_valid']) {
                return $healthVocabResult;
            }
        }
        
        // Fallback to UMLS if available
        if ($this->umlsApiKey) {
            return $this->validateWithUMLS($term);
        }
        
        return [
            'is_valid' => false,
            'confidence' => 0.0,
            'category' => null,
            'match_type' => 'no_external_api'
        ];
    }

    /**
     * Validate term using Health Vocabulary REST API
     */
    private function validateWithHealthVocabAPI(string $term): array
    {
        $cacheKey = "health_vocab_validation_" . md5($term);
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        try {
            $response = Http::timeout(10)->get($this->healthVocabApiUrl . '/concepts', [
                'term' => $term,
                'partial' => 1
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                if (!empty($data) && is_array($data)) {
                    $bestMatch = $data[0];
                    
                    $result = [
                        'is_valid' => true,
                        'confidence' => 0.88,
                        'category' => 'health_vocab_concept',
                        'cui' => $bestMatch['cui'] ?? null,
                        'preferred_name' => $bestMatch['str'] ?? null,
                        'source_vocabularies' => $bestMatch['sabs'] ?? [],
                        'match_type' => 'health_vocab_api'
                    ];
                    
                    Cache::put($cacheKey, $result, now()->addHours(24));
                    return $result;
                }
            }
        } catch (Exception $e) {
            Log::warning('Health Vocabulary API validation failed', [
                'term' => $term,
                'error' => $e->getMessage()
            ]);
        }
        
        $result = [
            'is_valid' => false,
            'confidence' => 0.0,
            'category' => null,
            'match_type' => 'health_vocab_not_found'
        ];
        
        Cache::put($cacheKey, $result, now()->addMinutes(30));
        return $result;
    }

    /**
     * Normalize medical term for comparison
     */
    private function normalizeTerm(string $term): string
    {
        $normalized = strtolower(trim($term));
        $normalized = preg_replace('/[^\w\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', '_', $normalized);
        
        return $normalized;
    }

    /**
     * Get relevant dictionaries based on context
     */
    private function getRelevantDictionaries(string $context): array
    {
        switch ($context) {
            case 'wound_care':
                return ['wound_care' => $this->terminologyDictionaries['wound_care']];
            
            case 'insurance_card':
                return ['insurance' => $this->terminologyDictionaries['insurance']];
            
            case 'clinical_note':
                return [
                    'clinical' => $this->terminologyDictionaries['clinical'],
                    'wound_care' => $this->terminologyDictionaries['wound_care']
                ];
            
            default:
                return $this->terminologyDictionaries;
        }
    }

    /**
     * Check if term exists in category
     */
    private function isTermInCategory(string $term, array $categoryTerms): bool
    {
        return in_array($term, $categoryTerms) || 
               in_array(str_replace('_', ' ', $term), $categoryTerms);
    }

    /**
     * Find partial match in terms
     */
    private function findPartialMatch(string $term, array $categoryTerms): ?string
    {
        foreach ($categoryTerms as $categoryTerm) {
            if (strpos($categoryTerm, $term) !== false || 
                strpos($term, $categoryTerm) !== false) {
                return $categoryTerm;
            }
        }
        
        return null;
    }

    /**
     * Calculate confidence for a single term
     */
    private function calculateTermConfidence(array $localValidation, ?array $externalValidation): float
    {
        if ($localValidation['is_valid']) {
            return $localValidation['confidence'];
        }
        
        if ($externalValidation && $externalValidation['is_valid']) {
            return $externalValidation['confidence'];
        }
        
        return 0.0;
    }

    /**
     * Calculate overall confidence across all terms
     */
    private function calculateOverallConfidence(array $validationResults): float
    {
        if (empty($validationResults)) {
            return 0.0;
        }
        
        $totalConfidence = array_sum(array_column($validationResults, 'confidence'));
        return $totalConfidence / count($validationResults);
    }

    /**
     * Generate suggestions for invalid terms
     */
    private function generateSuggestions(string $term, string $context): array
    {
        $suggestions = [];
        $relevantDictionaries = $this->getRelevantDictionaries($context);
        
        foreach ($relevantDictionaries as $categories) {
            foreach ($categories as $categoryTerms) {
                foreach ($categoryTerms as $categoryTerm) {
                    $similarity = $this->calculateSimilarity($term, $categoryTerm);
                    if ($similarity > 0.6) {
                        $suggestions[] = [
                            'term' => $categoryTerm,
                            'similarity' => $similarity
                        ];
                    }
                }
            }
        }
        
        // Sort by similarity and return top 3
        usort($suggestions, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
        return array_slice($suggestions, 0, 3);
    }

    /**
     * Calculate similarity between two terms
     */
    private function calculateSimilarity(string $term1, string $term2): float
    {
        return (1 - levenshtein($term1, $term2) / max(strlen($term1), strlen($term2)));
    }

    /**
     * Get medical terminology statistics
     */
    public function getTerminologyStats(): array
    {
        $stats = [
            'total_dictionaries' => count($this->terminologyDictionaries),
            'total_categories' => 0,
            'total_terms' => 0,
            'dictionaries' => []
        ];
        
        foreach ($this->terminologyDictionaries as $dictionaryName => $categories) {
            $categoryCount = count($categories);
            $termCount = array_sum(array_map('count', $categories));
            
            $stats['total_categories'] += $categoryCount;
            $stats['total_terms'] += $termCount;
            
            $stats['dictionaries'][$dictionaryName] = [
                'categories' => $categoryCount,
                'terms' => $termCount
            ];
        }
        
        return $stats;
    }

    /**
     * Test UMLS API connection
     */
    public function testUMLSConnection(): array
    {
        if (!$this->umlsApiKey) {
            return [
                'connected' => false,
                'error' => 'UMLS API key not configured'
            ];
        }
        
        try {
            $response = Http::timeout(5)->get($this->umlsBaseUrl . '/search/current', [
                'string' => 'diabetes',
                'apiKey' => $this->umlsApiKey,
                'pageSize' => 1
            ]);
            
            return [
                'connected' => $response->successful(),
                'status_code' => $response->status(),
                'response_time' => $response->handlerStats()['total_time'] ?? 0,
                'test_term' => 'diabetes'
            ];
            
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test Health Vocabulary API connection
     */
    public function testHealthVocabConnection(): array
    {
        if (!$this->healthVocabApiUrl) {
            return [
                'connected' => false,
                'error' => 'Health Vocabulary API URL not configured'
            ];
        }
        
        try {
            $response = Http::timeout(5)->get($this->healthVocabApiUrl . '/concepts', [
                'term' => 'diabetes'
            ]);
            
            return [
                'connected' => $response->successful(),
                'status_code' => $response->status(),
                'response_time' => $response->handlerStats()['total_time'] ?? 0,
                'test_term' => 'diabetes',
                'api_type' => 'health_vocabulary_rest_api'
            ];
            
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
} 
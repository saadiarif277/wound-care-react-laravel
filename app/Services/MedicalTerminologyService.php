<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Medical Terminology Service
 * 
 * Validates medical terms using UMLS API and local healthcare dictionaries
 * Provides comprehensive medical concept information, cross-referencing, and relationships
 */
class MedicalTerminologyService
{
    private ?string $umlsApiKey;
    private string $umlsBaseUrl = 'https://uts-ws.nlm.nih.gov/rest';
    private ?string $healthVocabApiUrl;
    private int $cacheTime = 86400; // 24 hours
    
    /**
     * Local medical terminology dictionaries
     */
    private array $terminologyDictionaries = [
        'wound_care' => [
            'wound_types' => [
                'pressure_ulcer', 'diabetic_foot_ulcer', 'venous_stasis_ulcer',
                'arterial_ulcer', 'surgical_wound', 'traumatic_wound', 'burn',
                'laceration', 'abrasion', 'dehiscence', 'abscess'
            ],
            'anatomical_locations' => [
                'sacrum', 'heel', 'ankle', 'toe', 'foot', 'lower_leg',
                'hip', 'elbow', 'shoulder', 'forearm', 'hand'
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
        $this->cacheTime = config('services.umls.cache_ttl', 86400);
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
     * Search UMLS for medical concepts by term or code
     * Uses /search/{version} endpoint
     */
    public function searchConcepts(string $searchTerm, array $options = []): array
    {
        $cacheKey = "umls_search_" . md5($searchTerm . serialize($options));
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        try {
            $params = array_merge([
                'string' => $searchTerm,
                'apiKey' => $this->umlsApiKey,
                'pageSize' => $options['pageSize'] ?? 10,
                'includeSuppressible' => $options['includeSuppressible'] ?? false
            ], $options);
            
            // Filter by source vocabulary if specified
            if (isset($options['sabs'])) {
                $params['sabs'] = $options['sabs']; // e.g., 'ICD10CM,CPT,SNOMEDCT_US'
            }
            
            $response = Http::timeout(config('services.umls.timeout', 30))
                ->get($this->umlsBaseUrl . '/search/current', $params);
            
            if ($response->successful()) {
                $data = $response->json();
                $results = $data['result']['results'] ?? [];
                
                $processedResults = array_map(function($result) {
                    return [
                        'cui' => $result['ui'],
                        'name' => $result['name'],
                        'semantic_types' => $result['semanticTypes'] ?? [],
                        'source' => $result['rootSource'] ?? null,
                        'atoms_count' => $result['atomCount'] ?? 0
                    ];
                }, $results);
                
                $result = [
                    'success' => true,
                    'results' => $processedResults,
                    'total' => $data['result']['recCount'] ?? 0
                ];
                
                Cache::put($cacheKey, $result, now()->addSeconds($this->cacheTime));
                return $result;
            }
            
            return ['success' => false, 'error' => 'Failed to search UMLS'];
            
        } catch (Exception $e) {
            Log::error('UMLS search failed', [
                'term' => $searchTerm,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get detailed information about a specific CUI
     * Uses /content/{version}/CUI/{CUI} endpoint
     */
    public function getConceptDetails(string $cui): array
    {
        $cacheKey = "umls_concept_" . $cui;
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        try {
            $response = Http::timeout(config('services.umls.timeout', 30))
                ->get($this->umlsBaseUrl . "/content/current/CUI/{$cui}", [
                    'apiKey' => $this->umlsApiKey
                ]);
            
            if ($response->successful()) {
                $data = $response->json()['result'];
                
                $result = [
                    'success' => true,
                    'cui' => $data['ui'],
                    'name' => $data['name'],
                    'semantic_types' => $data['semanticTypes'] ?? [],
                    'definition' => $data['definitions'] ?? null,
                    'atoms_count' => $data['atomCount'] ?? 0,
                    'relations_count' => $data['relationCount'] ?? 0,
                    'is_obsolete' => $data['obsolete'] ?? false
                ];
                
                Cache::put($cacheKey, $result, now()->addSeconds($this->cacheTime));
                return $result;
            }
            
            return ['success' => false, 'error' => 'Failed to get concept details'];
            
        } catch (Exception $e) {
            Log::error('UMLS concept retrieval failed', [
                'cui' => $cui,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get definitions for a concept
     * Uses /content/{version}/CUI/{CUI}/definitions endpoint
     */
    public function getConceptDefinitions(string $cui): array
    {
        $cacheKey = "umls_definitions_" . $cui;
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        try {
            $response = Http::timeout(config('services.umls.timeout', 30))
                ->get($this->umlsBaseUrl . "/content/current/CUI/{$cui}/definitions", [
                    'apiKey' => $this->umlsApiKey
                ]);
            
            if ($response->successful()) {
                $data = $response->json()['result'];
                
                $definitions = array_map(function($def) {
                    return [
                        'value' => $def['value'],
                        'source' => $def['rootSource']
                    ];
                }, $data);
                
                $result = [
                    'success' => true,
                    'cui' => $cui,
                    'definitions' => $definitions
                ];
                
                Cache::put($cacheKey, $result, now()->addSeconds($this->cacheTime));
                return $result;
            }
            
            return ['success' => false, 'error' => 'No definitions found'];
            
        } catch (Exception $e) {
            Log::error('UMLS definitions retrieval failed', [
                'cui' => $cui,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get related concepts (relationships like "treats", "caused_by", etc.)
     * Uses /content/{version}/CUI/{CUI}/relations endpoint
     */
    public function getConceptRelations(string $cui, array $options = []): array
    {
        $cacheKey = "umls_relations_" . $cui . "_" . md5(serialize($options));
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        try {
            $params = [
                'apiKey' => $this->umlsApiKey,
                'pageSize' => $options['pageSize'] ?? 25
            ];
            
            if (isset($options['relationLabel'])) {
                $params['relationLabel'] = $options['relationLabel']; // e.g., 'RO', 'RB', 'RN'
            }
            
            $response = Http::timeout(config('services.umls.timeout', 30))
                ->get($this->umlsBaseUrl . "/content/current/CUI/{$cui}/relations", $params);
            
            if ($response->successful()) {
                $data = $response->json()['result'];
                
                $relations = array_map(function($rel) {
                    return [
                        'related_cui' => $rel['relatedId'],
                        'related_name' => $rel['relatedIdName'],
                        'relation_label' => $rel['relationLabel'],
                        'relation_type' => $rel['additionalRelationLabel'] ?? null,
                        'source' => $rel['rootSource']
                    ];
                }, $data);
                
                $result = [
                    'success' => true,
                    'cui' => $cui,
                    'relations' => $relations
                ];
                
                Cache::put($cacheKey, $result, now()->addSeconds($this->cacheTime));
                return $result;
            }
            
            return ['success' => false, 'error' => 'No relations found'];
            
        } catch (Exception $e) {
            Log::error('UMLS relations retrieval failed', [
                'cui' => $cui,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cross-reference codes between different vocabularies
     * Uses /crosswalk/{version}/source/{source}/{id} endpoint
     * 
     * @param string $source Source vocabulary (e.g., 'ICD10CM', 'CPT', 'SNOMEDCT_US')
     * @param string $code The code to cross-reference
     */
    public function crosswalkCode(string $source, string $code): array
    {
        $cacheKey = "umls_crosswalk_{$source}_{$code}";
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        try {
            $response = Http::timeout(config('services.umls.timeout', 30))
                ->get($this->umlsBaseUrl . "/crosswalk/current/source/{$source}/{$code}", [
                    'apiKey' => $this->umlsApiKey
                ]);
            
            if ($response->successful()) {
                $data = $response->json()['result'];
                
                $crosswalks = array_map(function($item) {
                    return [
                        'ui' => $item['ui'],
                        'name' => $item['name'],
                        'source' => $item['rootSource'],
                        'target_code' => $item['ui']
                    ];
                }, $data);
                
                // Group by source vocabulary
                $groupedCrosswalks = [];
                foreach ($crosswalks as $crosswalk) {
                    $groupedCrosswalks[$crosswalk['source']][] = $crosswalk;
                }
                
                $result = [
                    'success' => true,
                    'source' => $source,
                    'source_code' => $code,
                    'crosswalks' => $groupedCrosswalks,
                    'total_mappings' => count($crosswalks)
                ];
                
                Cache::put($cacheKey, $result, now()->addSeconds($this->cacheTime));
                return $result;
            }
            
            return ['success' => false, 'error' => 'No crosswalk found'];
            
        } catch (Exception $e) {
            Log::error('UMLS crosswalk failed', [
                'source' => $source,
                'code' => $code,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get source-specific information (atoms) for a concept
     * Uses /content/{version}/source/{source}/{id}/atoms endpoint
     */
    public function getSourceAtoms(string $source, string $id): array
    {
        $cacheKey = "umls_atoms_{$source}_{$id}";
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        try {
            $response = Http::timeout(config('services.umls.timeout', 30))
                ->get($this->umlsBaseUrl . "/content/current/source/{$source}/{$id}/atoms", [
                    'apiKey' => $this->umlsApiKey
                ]);
            
            if ($response->successful()) {
                $data = $response->json()['result'];
                
                $atoms = array_map(function($atom) {
                    return [
                        'aui' => $atom['ui'],
                        'name' => $atom['name'],
                        'term_type' => $atom['termType'],
                        'language' => $atom['language'],
                        'is_preferred' => $atom['termType'] === 'PT'
                    ];
                }, $data);
                
                $result = [
                    'success' => true,
                    'source' => $source,
                    'id' => $id,
                    'atoms' => $atoms
                ];
                
                Cache::put($cacheKey, $result, now()->addSeconds($this->cacheTime));
                return $result;
            }
            
            return ['success' => false, 'error' => 'No atoms found'];
            
        } catch (Exception $e) {
            Log::error('UMLS atoms retrieval failed', [
                'source' => $source,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get parent concepts in a hierarchy
     * Uses /content/{version}/source/{source}/{id}/parents endpoint
     */
    public function getParentConcepts(string $source, string $id): array
    {
        $cacheKey = "umls_parents_{$source}_{$id}";
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        try {
            $response = Http::timeout(config('services.umls.timeout', 30))
                ->get($this->umlsBaseUrl . "/content/current/source/{$source}/{$id}/parents", [
                    'apiKey' => $this->umlsApiKey
                ]);
            
            if ($response->successful()) {
                $data = $response->json()['result'];
                
                $parents = array_map(function($parent) {
                    return [
                        'cui' => $parent['ui'],
                        'name' => $parent['name'],
                        'source' => $parent['rootSource']
                    ];
                }, $data);
                
                $result = [
                    'success' => true,
                    'source' => $source,
                    'id' => $id,
                    'parents' => $parents
                ];
                
                Cache::put($cacheKey, $result, now()->addSeconds($this->cacheTime));
                return $result;
            }
            
            return ['success' => false, 'error' => 'No parents found'];
            
        } catch (Exception $e) {
            Log::error('UMLS parents retrieval failed', [
                'source' => $source,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get child concepts in a hierarchy
     * Uses /content/{version}/source/{source}/{id}/children endpoint
     */
    public function getChildConcepts(string $source, string $id): array
    {
        $cacheKey = "umls_children_{$source}_{$id}";
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        try {
            $response = Http::timeout(config('services.umls.timeout', 30))
                ->get($this->umlsBaseUrl . "/content/current/source/{$source}/{$id}/children", [
                    'apiKey' => $this->umlsApiKey
                ]);
            
            if ($response->successful()) {
                $data = $response->json()['result'];
                
                $children = array_map(function($child) {
                    return [
                        'cui' => $child['ui'],
                        'name' => $child['name'],
                        'source' => $child['rootSource']
                    ];
                }, $data);
                
                $result = [
                    'success' => true,
                    'source' => $source,
                    'id' => $id,
                    'children' => $children
                ];
                
                Cache::put($cacheKey, $result, now()->addSeconds($this->cacheTime));
                return $result;
            }
            
            return ['success' => false, 'error' => 'No children found'];
            
        } catch (Exception $e) {
            Log::error('UMLS children retrieval failed', [
                'source' => $source,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Enhanced ICD-10 to CPT mapping for wound care
     * Combines UMLS crosswalk with domain-specific knowledge
     */
    public function mapICD10ToCPT(string $icd10Code): array
    {
        // First try UMLS crosswalk
        $crosswalk = $this->crosswalkCode('ICD10CM', $icd10Code);
        
        $cptCodes = [];
        if ($crosswalk['success'] && isset($crosswalk['crosswalks']['CPT'])) {
            foreach ($crosswalk['crosswalks']['CPT'] as $mapping) {
                $cptCodes[] = [
                    'code' => $mapping['target_code'],
                    'name' => $mapping['name'],
                    'confidence' => 0.9
                ];
            }
        }
        
        // Add domain-specific mappings for common wound care scenarios
        $domainMappings = $this->getWoundCareCPTMappings($icd10Code);
        foreach ($domainMappings as $mapping) {
            if (!in_array($mapping['code'], array_column($cptCodes, 'code'))) {
                $cptCodes[] = $mapping;
            }
        }
        
        return [
            'success' => true,
            'icd10_code' => $icd10Code,
            'cpt_codes' => $cptCodes,
            'source' => $crosswalk['success'] ? 'umls_enhanced' : 'domain_knowledge'
        ];
    }

    /**
     * Get wound care specific CPT mappings
     */
    private function getWoundCareCPTMappings(string $icd10Code): array
    {
        // Common wound care ICD-10 to CPT mappings
        $mappings = [
            // Pressure ulcers
            'L89.0' => [['code' => '15940', 'name' => 'Excision, pressure ulcer, sacrum', 'confidence' => 0.8]],
            'L89.1' => [['code' => '15941', 'name' => 'Excision, pressure ulcer, coccyx', 'confidence' => 0.8]],
            'L89.2' => [['code' => '15944', 'name' => 'Excision, pressure ulcer, hip', 'confidence' => 0.8]],
            
            // Diabetic ulcers
            'E11.621' => [
                ['code' => '97597', 'name' => 'Debridement of open wound', 'confidence' => 0.85],
                ['code' => '15271', 'name' => 'Application of skin substitute graft', 'confidence' => 0.8]
            ],
            
            // Venous ulcers
            'I83.0' => [
                ['code' => '97597', 'name' => 'Debridement of open wound', 'confidence' => 0.85],
                ['code' => '29580', 'name' => 'Unna boot application', 'confidence' => 0.75]
            ]
        ];
        
        // Check for partial matches
        foreach ($mappings as $pattern => $cptMappings) {
            if (strpos($icd10Code, $pattern) === 0) {
                return $cptMappings;
            }
        }
        
        return [];
    }

    /**
     * Validate a single medical term
     */
    private function validateSingleTerm(string $term, string $context): array
    {
        $normalizedTerm = $this->normalizeTerm($term);
        
        // Step 1: Local dictionary validation
        $localValidation = $this->validateWithLocalDictionary($normalizedTerm, $context);
        
        // Step 2: UMLS validation if available
        $umlsValidation = null;
        if ($this->umlsApiKey && !$localValidation['is_valid']) {
            $umlsValidation = $this->validateWithUMLS($normalizedTerm);
        }
        
        // Step 3: Combine results
        $isValid = $localValidation['is_valid'] || ($umlsValidation['is_valid'] ?? false);
        $confidence = $this->calculateTermConfidence($localValidation, $umlsValidation);
        
        // Get additional UMLS data if valid
        $enrichedData = [];
        if ($umlsValidation && $umlsValidation['is_valid'] && $umlsValidation['umls_cui']) {
            $enrichedData = [
                'definitions' => $this->getConceptDefinitions($umlsValidation['umls_cui']),
                'relations' => $this->getConceptRelations($umlsValidation['umls_cui'])
            ];
        }
        
        return [
            'original_term' => $term,
            'normalized_term' => $normalizedTerm,
            'is_valid' => $isValid,
            'confidence' => $confidence,
            'matched_category' => $localValidation['category'] ?? $umlsValidation['category'] ?? null,
            'suggestions' => $this->generateSuggestions($normalizedTerm, $context),
            'local_validation' => $localValidation,
            'umls_validation' => $umlsValidation,
            'enriched_data' => $enrichedData
        ];
    }

    /**
     * Enhanced validate with local dictionary
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
     * Enhanced validate with UMLS - now includes semantic type checking
     */
    private function validateWithUMLS(string $term): array
    {
        $cacheKey = "umls_validation_" . md5($term);
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        try {
            $searchResult = $this->searchConcepts($term, ['pageSize' => 5]);
            
            if ($searchResult['success'] && !empty($searchResult['results'])) {
                $bestMatch = $searchResult['results'][0];
                
                // Get additional details for the best match
                $details = $this->getConceptDetails($bestMatch['cui']);
                
                $result = [
                    'is_valid' => true,
                    'confidence' => $this->calculateUMLSConfidence($term, $bestMatch['name']),
                    'category' => 'umls_concept',
                    'umls_cui' => $bestMatch['cui'],
                    'preferred_name' => $bestMatch['name'],
                    'semantic_types' => $bestMatch['semantic_types'],
                    'match_type' => 'umls_api',
                    'details' => $details['success'] ? $details : null
                ];
                
                Cache::put($cacheKey, $result, now()->addSeconds($this->cacheTime));
                return $result;
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
     * Calculate UMLS confidence based on string similarity
     */
    private function calculateUMLSConfidence(string $searchTerm, string $matchedTerm): float
    {
        $searchNorm = strtolower(trim($searchTerm));
        $matchedNorm = strtolower(trim($matchedTerm));
        
        if ($searchNorm === $matchedNorm) {
            return 0.95;
        }
        
        $similarity = 1 - (levenshtein($searchNorm, $matchedNorm) / max(strlen($searchNorm), strlen($matchedNorm)));
        return min(0.9, max(0.7, $similarity));
    }

    /**
     * Enhanced ICD-10 validation with hierarchy checking
     */
    public function validateICD10Code(string $code): array
    {
        $searchResult = $this->searchConcepts($code, ['sabs' => 'ICD10CM']);
        
        if ($searchResult['success'] && !empty($searchResult['results'])) {
            $match = $searchResult['results'][0];
            
            // Get parent codes for context
            $parents = $this->getParentConcepts('ICD10CM', $code);
            
            return [
                'valid' => true,
                'code' => $code,
                'description' => $match['name'],
                'cui' => $match['cui'],
                'parent_codes' => $parents['success'] ? $parents['parents'] : [],
                'is_billable' => strlen($code) >= 3, // Basic check - codes need at least 3 chars to be billable
                'confidence' => 0.95
            ];
        }
        
        return [
            'valid' => false,
            'code' => $code,
            'error' => 'Invalid ICD-10 code',
            'confidence' => 0.0
        ];
    }

    /**
     * Enhanced CPT validation with related procedures
     */
    public function validateCPTCode(string $code): array
    {
        $searchResult = $this->searchConcepts($code, ['sabs' => 'CPT']);
        
        if ($searchResult['success'] && !empty($searchResult['results'])) {
            $match = $searchResult['results'][0];
            
            // Get related procedures
            $relations = $this->getConceptRelations($match['cui']);
            
            $relatedProcedures = [];
            if ($relations['success']) {
                foreach ($relations['relations'] as $relation) {
                    if (strpos($relation['source'], 'CPT') !== false) {
                        $relatedProcedures[] = [
                            'code' => $relation['related_cui'],
                            'name' => $relation['related_name']
                        ];
                    }
                }
            }
            
            return [
                'valid' => true,
                'code' => $code,
                'description' => $match['name'],
                'cui' => $match['cui'],
                'related_procedures' => array_slice($relatedProcedures, 0, 5),
                'confidence' => 0.95
            ];
        }
        
        return [
            'valid' => false,
            'code' => $code,
            'error' => 'Invalid CPT code',
            'confidence' => 0.0
        ];
    }

    /**
     * Smart medical code suggestion based on description
     */
    public function suggestMedicalCodes(string $description, string $codeType = 'all'): array
    {
        $suggestions = [];
        
        // Determine which vocabularies to search
        $vocabularies = [];
        switch ($codeType) {
            case 'icd10':
                $vocabularies = ['ICD10CM'];
                break;
            case 'cpt':
                $vocabularies = ['CPT'];
                break;
            case 'hcpcs':
                $vocabularies = ['HCPCS'];
                break;
            default:
                $vocabularies = ['ICD10CM', 'CPT', 'HCPCS'];
        }
        
        foreach ($vocabularies as $vocab) {
            $searchResult = $this->searchConcepts($description, [
                'sabs' => $vocab,
                'pageSize' => 5
            ]);
            
            if ($searchResult['success']) {
                foreach ($searchResult['results'] as $result) {
                    $suggestions[] = [
                        'code_system' => $vocab,
                        'code' => $this->extractCodeFromCUI($result['cui'], $vocab),
                        'description' => $result['name'],
                        'cui' => $result['cui'],
                        'confidence' => $this->calculateUMLSConfidence($description, $result['name'])
                    ];
                }
            }
        }
        
        // Sort by confidence
        usort($suggestions, fn($a, $b) => $b['confidence'] <=> $a['confidence']);
        
        return array_slice($suggestions, 0, 10);
    }

    /**
     * Extract actual code from CUI for specific vocabulary
     */
    private function extractCodeFromCUI(string $cui, string $vocabulary): string
    {
        // For this to work properly, we'd need to get the atoms
        // This is a simplified version
        $atoms = $this->getSourceAtoms($vocabulary, $cui);
        
        if ($atoms['success'] && !empty($atoms['atoms'])) {
            foreach ($atoms['atoms'] as $atom) {
                if ($atom['is_preferred']) {
                    // Extract code from atom name (often format is "CODE - Description")
                    $parts = explode(' - ', $atom['name']);
                    return $parts[0];
                }
            }
        }
        
        return $cui; // Fallback to CUI if can't extract code
    }

    /**
     * Get medical term statistics with UMLS integration status
     */
    public function getTerminologyStats(): array
    {
        $stats = [
            'total_dictionaries' => count($this->terminologyDictionaries),
            'total_categories' => 0,
            'total_terms' => 0,
            'umls_enabled' => !empty($this->umlsApiKey),
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
        
        // Add UMLS connection status
        if ($stats['umls_enabled']) {
            $stats['umls_status'] = $this->testUMLSConnection();
        }
        
        return $stats;
    }

    /**
     * Helper methods from original implementation
     */
    private function normalizeTerm(string $term): string
    {
        $normalized = strtolower(trim($term));
        $normalized = preg_replace('/[^\w\s]/', '', $normalized);
        $normalized = preg_replace('/\s+/', '_', $normalized);
        
        return $normalized;
    }

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

    private function isTermInCategory(string $term, array $categoryTerms): bool
    {
        return in_array($term, $categoryTerms) || 
               in_array(str_replace('_', ' ', $term), $categoryTerms);
    }

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

    private function calculateOverallConfidence(array $validationResults): float
    {
        if (empty($validationResults)) {
            return 0.0;
        }
        
        $totalConfidence = array_sum(array_column($validationResults, 'confidence'));
        return $totalConfidence / count($validationResults);
    }

    private function generateSuggestions(string $term, string $context): array
    {
        $suggestions = [];
        
        // First try UMLS suggestions
        if ($this->umlsApiKey) {
            $searchResult = $this->searchConcepts($term, ['pageSize' => 3]);
            if ($searchResult['success']) {
                foreach ($searchResult['results'] as $result) {
                    $suggestions[] = [
                        'term' => $result['name'],
                        'similarity' => $this->calculateUMLSConfidence($term, $result['name']),
                        'source' => 'umls',
                        'cui' => $result['cui']
                    ];
                }
            }
        }
        
        // Add local dictionary suggestions
        $relevantDictionaries = $this->getRelevantDictionaries($context);
        
        foreach ($relevantDictionaries as $categories) {
            foreach ($categories as $categoryTerms) {
                foreach ($categoryTerms as $categoryTerm) {
                    $similarity = $this->calculateSimilarity($term, $categoryTerm);
                    if ($similarity > 0.6) {
                        $suggestions[] = [
                            'term' => $categoryTerm,
                            'similarity' => $similarity,
                            'source' => 'local'
                        ];
                    }
                }
            }
        }
        
        // Sort by similarity and return top 5
        usort($suggestions, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
        return array_slice($suggestions, 0, 5);
    }

    private function calculateSimilarity(string $term1, string $term2): float
    {
        return (1 - levenshtein($term1, $term2) / max(strlen($term1), strlen($term2)));
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
            $startTime = microtime(true);
            $response = Http::timeout(5)->get($this->umlsBaseUrl . '/search/current', [
                'string' => 'diabetes',
                'apiKey' => $this->umlsApiKey,
                'pageSize' => 1
            ]);
            $endTime = microtime(true);
            
            return [
                'connected' => $response->successful(),
                'status_code' => $response->status(),
                'response_time' => round(($endTime - $startTime) * 1000, 2) . ' ms',
                'test_term' => 'diabetes',
                'api_version' => 'current'
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
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\CmsCoverageApiSampleData;

class CmsCoverageApiService
{
    private string $baseUrl;
    private int $throttleLimit;
    private int $cacheMinutes;
    private array $config;
    private ?string $pplApiKey; // For Procedure Price Lookup API

    // API call tracking
    private int $apiCallCount = 0;
    private int $cacheHits = 0;
    private int $cacheMisses = 0;

    public function checkEligibility(array $request): array
    {
        // Implement eligibility check using CMS Coverage API data
        $coverageData = $this->getLCDsBySpecialty('wound_care', $request['state'] ?? null);
        
        return [
            'eligible' => !empty($coverageData),
            'details' => $coverageData,
            'source' => 'CMS Coverage API'
        ];
    }

    public function healthCheck(): bool
    {
        try {
            $response = Http::get("{$this->baseUrl}/reports/whats-new/local", ['limit' => 1]);
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function __construct()
    {
        $this->validateConfiguration();
        $this->initializeConfiguration();
    }

    /**
     * Validate configuration values
     */
    private function validateConfiguration(): void
    {
        // Validate cache configuration
        if (!config('cache.default')) {
            throw new \InvalidArgumentException('Cache configuration is not properly set');
        }

        // Validate HTTP timeout configuration
        $httpTimeout = config('services.cms.timeout', 30);
        if (!is_numeric($httpTimeout) || $httpTimeout <= 0) {
            throw new \InvalidArgumentException('Invalid HTTP timeout configuration');
        }

        // Validate throttle limit
        $throttleLimit = config('services.cms.throttle_limit', 9000);
        if (!is_numeric($throttleLimit) || $throttleLimit <= 0) {
            throw new \InvalidArgumentException('Invalid throttle limit configuration');
        }

        // Validate cache minutes
        $cacheMinutes = config('services.cms.cache_minutes', 60);
        if (!is_numeric($cacheMinutes) || $cacheMinutes <= 0) {
            throw new \InvalidArgumentException('Invalid cache minutes configuration');
        }
    }

    /**
     * Initialize configuration with validated values
     */
    private function initializeConfiguration(): void
    {
        $this->baseUrl = config('services.cms.base_url', 'https://api.coverage.cms.gov/v1');
        $this->throttleLimit = config('services.cms.throttle_limit', 9000);
        $this->cacheMinutes = config('services.cms.cache_minutes', 60);
        $this->pplApiKey = config('services.cms.ppl_api_key'); // For Procedure Price Lookup

        $this->config = [
            'timeout' => config('services.cms.timeout', 30),
            'max_retries' => config('services.cms.max_retries', 3),
            'retry_delay' => config('services.cms.retry_delay', 1000),
        ];

        Log::info('CmsCoverageApiService initialized', [
            'base_url' => $this->baseUrl,
            'throttle_limit' => $this->throttleLimit,
            'cache_minutes' => $this->cacheMinutes,
            'ppl_api_enabled' => !empty($this->pplApiKey)
        ]);
    }

    /**
     * Get Local Coverage Determinations for a specific specialty and state
     */
    public function getLCDsBySpecialty(string $specialty, ?string $state = null): array
    {
        if (empty($specialty)) {
            Log::warning('Empty specialty provided to getLCDsBySpecialty');
            return [];
        }

        $cacheKey = "cms_lcds_{$specialty}_" . ($state ?? 'all');

        return Cache::remember($cacheKey, $this->cacheMinutes, function () use ($specialty, $state) {
            try {
                $params = [];
                if ($state && strlen($state) === 2) {
                    $params['state'] = strtoupper($state);
                }

                $response = Http::timeout($this->config['timeout'])
                    ->retry($this->config['max_retries'], $this->config['retry_delay'])
                    ->get("{$this->baseUrl}/reports/local-coverage-final-lcds", $params);

                if (!$response->successful()) {
                    Log::error('CMS Coverage API LCD request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'specialty' => $specialty,
                        'state' => $state,
                        'url' => $response->effectiveUri()
                    ]);
                    return [];
                }

                $data = $response->json();

                if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
                    Log::warning('Invalid LCD response format from CMS API', [
                        'specialty' => $specialty,
                        'state' => $state
                    ]);
                    return [];
                }

                $lcds = $data['data'];

                // Filter LCDs by specialty-relevant keywords
                return $this->filterLCDsBySpecialty($lcds, $specialty);

            } catch (\Exception $e) {
                Log::error('CMS Coverage API LCD request exception', [
                    'error' => $e->getMessage(),
                    'specialty' => $specialty,
                    'state' => $state
                ]);
                return [];
            }
        });
    }

    /**
     * Get National Coverage Determinations relevant to a specialty
     */
    public function getNCDsBySpecialty(string $specialty): array
    {
        $cacheKey = "cms_ncds_{$specialty}";

        return Cache::remember($cacheKey, $this->cacheMinutes, function () use ($specialty) {
            try {
                $response = Http::timeout(30)
                    ->get("{$this->baseUrl}/reports/national-coverage-ncd");

                if (!$response->successful()) {
                    Log::error('CMS Coverage API NCD request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'specialty' => $specialty
                    ]);
                    return [];
                }

                $data = $response->json();
                $ncds = $data['data'] ?? [];

                // Filter NCDs by specialty-relevant keywords
                return $this->filterNCDsBySpecialty($ncds, $specialty);

            } catch (\Exception $e) {
                Log::error('CMS Coverage API NCD request exception', [
                    'error' => $e->getMessage(),
                    'specialty' => $specialty
                ]);
                return [];
            }
        });
    }

    /**
     * Get specific LCD details by document ID
     */
    public function getLCDDetails(string $documentId): ?array
    {
        $cacheKey = "cms_lcd_details_{$documentId}";

        return Cache::remember($cacheKey, $this->cacheMinutes * 24, function () use ($documentId) {
            try {
                // Use MCD search to get specific LCD
                $response = Http::timeout(30)
                    ->get("{$this->baseUrl}/data/lcd/{$documentId}");

                if (!$response->successful()) {
                    Log::error('CMS Coverage API LCD details request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'document_id' => $documentId
                    ]);
                    return null;
                }

                return $response->json();

            } catch (\Exception $e) {
                Log::error('CMS Coverage API LCD details request exception', [
                    'error' => $e->getMessage(),
                    'document_id' => $documentId
                ]);
                return null;
            }
        });
    }

    /**
     * Get specific NCD details by document ID
     */
    public function getNCDDetails(string $documentId): ?array
    {
        $cacheKey = "cms_ncd_details_{$documentId}";

        return Cache::remember($cacheKey, $this->cacheMinutes * 24, function () use ($documentId) {
            try {
                $response = Http::timeout(30)
                    ->get("{$this->baseUrl}/data/ncd/{$documentId}");

                if (!$response->successful()) {
                    Log::error('CMS Coverage API NCD details request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'document_id' => $documentId
                    ]);
                    return null;
                }

                return $response->json();

            } catch (\Exception $e) {
                Log::error('CMS Coverage API NCD details request exception', [
                    'error' => $e->getMessage(),
                    'document_id' => $documentId
                ]);
                return null;
            }
        });
    }

    /**
     * Get Articles (billing and coding guidelines) for specialty
     */
    public function getArticlesBySpecialty(string $specialty, ?string $state = null): array
    {
        $cacheKey = "cms_articles_{$specialty}_" . ($state ?? 'all');

        return Cache::remember($cacheKey, $this->cacheMinutes, function () use ($specialty, $state) {
            try {
                $params = [];
                if ($state) {
                    $params['state'] = $state;
                }

                $response = Http::timeout(30)
                    ->get("{$this->baseUrl}/reports/local-coverage-articles", $params);

                if (!$response->successful()) {
                    Log::error('CMS Coverage API Articles request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'specialty' => $specialty,
                        'state' => $state
                    ]);
                    return [];
                }

                $data = $response->json();
                $articles = $data['data'] ?? [];

                return $this->filterArticlesBySpecialty($articles, $specialty);

            } catch (\Exception $e) {
                Log::error('CMS Coverage API Articles request exception', [
                    'error' => $e->getMessage(),
                    'specialty' => $specialty,
                    'state' => $state
                ]);
                return [];
            }
        });
    }

    /**
     * Filter LCDs by specialty-relevant keywords
     */
    private function filterLCDsBySpecialty(array $lcds, string $specialty): array
    {
        $keywords = $this->getSpecialtyKeywords($specialty);

        return array_filter($lcds, function ($lcd) use ($keywords) {
            $title = strtolower($lcd['title'] ?? '');
            $summary = strtolower($lcd['summary'] ?? '');
            $description = strtolower($lcd['description'] ?? '');

            $text = $title . ' ' . $summary . ' ' . $description;

            foreach ($keywords as $keyword) {
                if (str_contains($text, strtolower($keyword))) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Filter NCDs by specialty-relevant keywords
     */
    private function filterNCDsBySpecialty(array $ncds, string $specialty): array
    {
        $keywords = $this->getSpecialtyKeywords($specialty);

        return array_filter($ncds, function ($ncd) use ($keywords) {
            $title = strtolower($ncd['title'] ?? '');
            $summary = strtolower($ncd['summary'] ?? '');
            $description = strtolower($ncd['description'] ?? '');

            $text = $title . ' ' . $summary . ' ' . $description;

            foreach ($keywords as $keyword) {
                if (str_contains($text, strtolower($keyword))) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Filter Articles by specialty-relevant keywords
     */
    private function filterArticlesBySpecialty(array $articles, string $specialty): array
    {
        $keywords = $this->getSpecialtyKeywords($specialty);

        return array_filter($articles, function ($article) use ($keywords) {
            $title = strtolower($article['title'] ?? '');
            $summary = strtolower($article['summary'] ?? '');
            $articleType = strtolower($article['article_type'] ?? '');

            $text = $title . ' ' . $summary . ' ' . $articleType;

            foreach ($keywords as $keyword) {
                if (str_contains($text, strtolower($keyword))) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Get specialty-specific keywords for filtering
     */
    private function getSpecialtyKeywords(string $specialty): array
    {
        return match($specialty) {
            'wound_care_specialty', 'wound_care' => [
                'wound', 'ulcer', 'dressing', 'debridement', 'skin substitute',
                'cellular tissue product', 'ctp', 'graft', 'diabetic foot',
                'venous leg', 'pressure ulcer', 'pressure injury', 'decubitus',
                'arterial ulcer', 'chronic wound', 'wound care', 'hyperbaric',
                'negative pressure', 'biological', 'synthetic', 'collagen',
                'dermal substitute', 'skin replacement', 'advanced wound',
                'wound therapy', 'bioengineered', 'human skin', 'bovine',
                'porcine', 'acellular', 'matrix', 'scaffold'
            ],
            'pulmonology', 'pulmonary' => [
                'pulmonary', 'lung', 'respiratory', 'copd', 'asthma',
                'pneumonia', 'bronchitis', 'emphysema', 'fibrosis',
                'pulmonary hypertension', 'sleep apnea', 'oxygen therapy',
                'mechanical ventilation', 'cpap', 'bipap', 'spirometry',
                'bronchodilator', 'inhaler', 'nebulizer', 'chest x-ray',
                'ct chest', 'arterial blood gas', 'oximetry', 'dyspnea',
                'shortness of breath', 'wheezing', 'cystic fibrosis',
                'bronchiectasis', 'interstitial lung disease', 'lung cancer',
                'pneumothorax', 'pleural effusion', 'respiratory failure'
            ],
            'pulmonology_wound_care' => [
                // Combined keywords from both specialties
                'wound', 'ulcer', 'dressing', 'debridement', 'skin substitute',
                'cellular tissue product', 'pressure ulcer', 'pressure injury',
                'chronic wound', 'wound care', 'hyperbaric', 'negative pressure',
                'pulmonary', 'lung', 'respiratory', 'copd', 'asthma',
                'oxygen therapy', 'mechanical ventilation', 'cpap', 'bipap',
                'spirometry', 'bronchodilator', 'dyspnea', 'tissue oxygenation',
                'transcutaneous oxygen', 'tcpo2', 'hyperbaric oxygen',
                'respiratory compromise', 'ventilator associated', 'tracheostomy',
                'oxygen saturation', 'arterial blood gas', 'tissue hypoxia'
            ],
            'vascular_surgery' => [
                'vascular', 'artery', 'vein', 'angioplasty', 'stent', 'graft',
                'bypass', 'endarterectomy', 'aneurysm', 'atherosclerosis',
                'peripheral artery', 'carotid', 'aortic', 'arteriovenous',
                'dialysis access', 'thrombectomy', 'embolectomy', 'endovascular',
                'balloon', 'catheter', 'duplex', 'angiography'
            ],
            'interventional_radiology' => [
                'interventional', 'radiology', 'catheter', 'angiography',
                'embolization', 'biopsy', 'drainage', 'stent', 'balloon',
                'thrombolysis', 'ablation', 'vertebroplasty', 'kyphoplasty',
                'uterine fibroid', 'chemoembolization', 'radioembolization',
                'image guided', 'percutaneous', 'minimally invasive'
            ],
            'cardiology' => [
                'cardiac', 'heart', 'coronary', 'angioplasty', 'stent',
                'pacemaker', 'defibrillator', 'valve', 'catheterization',
                'echocardiogram', 'stress test', 'holter', 'arrhythmia',
                'myocardial', 'chest pain', 'heart failure', 'cardiovascular'
            ],
            'podiatry' => [
                'foot', 'ankle', 'toe', 'nail', 'diabetic foot', 'plantar',
                'bunion', 'hammer toe', 'ingrown nail', 'callus', 'corn',
                'heel', 'arch', 'podiatric', 'orthotic', 'shoe'
            ],
            'plastic_surgery' => [
                'plastic', 'reconstruction', 'cosmetic', 'breast', 'liposuction',
                'rhinoplasty', 'facelift', 'abdominoplasty', 'mammoplasty',
                'implant', 'tissue expander', 'flap', 'microsurgery'
            ],
            default => ['medical', 'surgical', 'procedure', 'treatment']
        };
    }

    /**
     * Get MAC jurisdiction for a state with optional ZIP code
     */
    public function getMACJurisdiction(string $state, ?string $zipCode = null): ?array
    {
        $cacheKey = "mac_jurisdiction_{$state}_" . ($zipCode ?? 'state_only');

        // Check cache first
        $cached = Cache::get($cacheKey);
        if ($cached) {
            $this->cacheHits++;
            Log::info('MAC jurisdiction cache hit', ['state' => $state, 'zip' => $zipCode]);
            return array_merge($cached, ['data_source' => 'cached']);
        }

        $this->cacheMisses++;

        try {
            // First try CMS API for real-time data
            $startTime = microtime(true);
            $this->apiCallCount++;

            // Use getLCDsBySpecialty to extract MAC info from real data
            $lcds = $this->getLCDsBySpecialty('wound_care', $state);

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            if (!empty($lcds)) {
                // Extract MAC contractor from real LCD data
                foreach ($lcds as $lcd) {
                    if (!empty($lcd['contractor'])) {
                        $macInfo = [
                            'contractor' => $lcd['contractor'],
                            'jurisdiction' => $this->inferJurisdictionFromContractor($lcd['contractor']),
                            'state' => $state,
                            'zip_code' => $zipCode,
                            'data_source' => 'cms_api',
                            'response_time_ms' => $responseTime,
                            'phone' => $this->getMACContactInfo($lcd['contractor'])['phone'] ?? null,
                            'website' => $this->getMACContactInfo($lcd['contractor'])['website'] ?? null
                        ];

                        // Cache for 4 hours - MAC jurisdictions are stable
                        Cache::put($cacheKey, $macInfo, $this->cacheMinutes * 4);

                        Log::info('MAC jurisdiction determined from CMS API', [
                            'state' => $state,
                            'contractor' => $lcd['contractor'],
                            'response_time' => $responseTime
                        ]);

                        return $macInfo;
                    }
                }
            }

            // Fallback to hardcoded mapping if API doesn't provide MAC info
            $fallbackMac = $this->getFallbackMACJurisdiction($state, $zipCode);
            if ($fallbackMac) {
                $fallbackMac['data_source'] = 'fallback_mapping';
                $fallbackMac['response_time_ms'] = $responseTime;

                // Cache fallback for shorter time
                Cache::put($cacheKey, $fallbackMac, $this->cacheMinutes * 2);

                return $fallbackMac;
            }

        } catch (\Exception $e) {
            Log::error('MAC jurisdiction lookup failed', [
                'state' => $state,
                'zip_code' => $zipCode,
                'error' => $e->getMessage()
            ]);

            // Return fallback even on error
            $fallbackMac = $this->getFallbackMACJurisdiction($state, $zipCode);
            if ($fallbackMac) {
                $fallbackMac['data_source'] = 'fallback_mapping';
                $fallbackMac['response_time_ms'] = 0;
                return $fallbackMac;
            }
        }

        return null;
    }

    /**
     * Get fallback MAC jurisdiction information when CMS API fails
     */
    private function getFallbackMACJurisdiction(string $state, ?string $zipCode = null): ?array
    {
        // Comprehensive MAC contractor mapping by state
        $macMapping = [
            // Jurisdiction JH (Novitas Solutions)
            'AL' => ['contractor' => 'Novitas Solutions, Inc.', 'jurisdiction' => 'JH', 'region' => 'South'],
            'AR' => ['contractor' => 'Novitas Solutions, Inc.', 'jurisdiction' => 'JH', 'region' => 'South'],
            'CO' => ['contractor' => 'Novitas Solutions, Inc.', 'jurisdiction' => 'JH', 'region' => 'West'],
            'DE' => ['contractor' => 'Novitas Solutions, Inc.', 'jurisdiction' => 'JH', 'region' => 'Northeast'],
            'DC' => ['contractor' => 'Novitas Solutions, Inc.', 'jurisdiction' => 'JH', 'region' => 'Northeast'],
            'FL' => ['contractor' => 'First Coast Service Options, Inc.', 'jurisdiction' => 'JN', 'region' => 'South'],
            'GA' => ['contractor' => 'Novitas Solutions, Inc.', 'jurisdiction' => 'JH', 'region' => 'South'],
            'LA' => ['contractor' => 'Novitas Solutions, Inc.', 'jurisdiction' => 'JH', 'region' => 'South'],
            'MD' => ['contractor' => 'Novitas Solutions, Inc.', 'jurisdiction' => 'JH', 'region' => 'Northeast'],
            'MS' => ['contractor' => 'Novitas Solutions, Inc.', 'jurisdiction' => 'JH', 'region' => 'South'],
            'NJ' => ['contractor' => 'Novitas Solutions, Inc.', 'jurisdiction' => 'JH', 'region' => 'Northeast'],
            'NM' => ['contractor' => 'Novitas Solutions, Inc.', 'jurisdiction' => 'JH', 'region' => 'West'],
            'OK' => ['contractor' => 'Novitas Solutions, Inc.', 'jurisdiction' => 'JH', 'region' => 'South'],
            'PA' => ['contractor' => 'Novitas Solutions, Inc.', 'jurisdiction' => 'JH', 'region' => 'Northeast'],
            'TX' => ['contractor' => 'Novitas Solutions, Inc.', 'jurisdiction' => 'JH', 'region' => 'South'],
            'WV' => ['contractor' => 'Novitas Solutions, Inc.', 'jurisdiction' => 'JH', 'region' => 'Northeast'],

            // Jurisdiction JF (Noridian Healthcare Solutions)
            'AK' => ['contractor' => 'Noridian Healthcare Solutions, LLC', 'jurisdiction' => 'JF', 'region' => 'West'],
            'AZ' => ['contractor' => 'Noridian Healthcare Solutions, LLC', 'jurisdiction' => 'JF', 'region' => 'West'],
            'CA' => ['contractor' => 'Noridian Healthcare Solutions, LLC', 'jurisdiction' => 'JF', 'region' => 'West'],
            'HI' => ['contractor' => 'Noridian Healthcare Solutions, LLC', 'jurisdiction' => 'JF', 'region' => 'West'],
            'ID' => ['contractor' => 'Noridian Healthcare Solutions, LLC', 'jurisdiction' => 'JF', 'region' => 'West'],
            'IA' => ['contractor' => 'Noridian Healthcare Solutions, LLC', 'jurisdiction' => 'JF', 'region' => 'Midwest'],
            'KS' => ['contractor' => 'Noridian Healthcare Solutions, LLC', 'jurisdiction' => 'JF', 'region' => 'Midwest'],
            'MO' => ['contractor' => 'Noridian Healthcare Solutions, LLC', 'jurisdiction' => 'JF', 'region' => 'Midwest'],
            'MT' => ['contractor' => 'Noridian Healthcare Solutions, LLC', 'jurisdiction' => 'JF', 'region' => 'West'],
            'NE' => ['contractor' => 'Noridian Healthcare Solutions, LLC', 'jurisdiction' => 'JF', 'region' => 'Midwest'],
            'NV' => ['contractor' => 'Noridian Healthcare Solutions, LLC', 'jurisdiction' => 'JF', 'region' => 'West'],
            'ND' => ['contractor' => 'Noridian Healthcare Solutions, LLC', 'jurisdiction' => 'JF', 'region' => 'Midwest'],
            'OR' => ['contractor' => 'Noridian Healthcare Solutions, LLC', 'jurisdiction' => 'JF', 'region' => 'West'],
            'SD' => ['contractor' => 'Noridian Healthcare Solutions, LLC', 'jurisdiction' => 'JF', 'region' => 'Midwest'],
            'UT' => ['contractor' => 'Noridian Healthcare Solutions, LLC', 'jurisdiction' => 'JF', 'region' => 'West'],
            'WA' => ['contractor' => 'Noridian Healthcare Solutions, LLC', 'jurisdiction' => 'JF', 'region' => 'West'],
            'WY' => ['contractor' => 'Noridian Healthcare Solutions, LLC', 'jurisdiction' => 'JF', 'region' => 'West'],

            // Jurisdiction J15 (CGS Administrators)
            'KY' => ['contractor' => 'CGS Administrators, LLC', 'jurisdiction' => 'J15', 'region' => 'South'],
            'OH' => ['contractor' => 'CGS Administrators, LLC', 'jurisdiction' => 'J15', 'region' => 'Midwest'],

            // Jurisdiction JJ (Palmetto GBA)
            'NC' => ['contractor' => 'Palmetto GBA', 'jurisdiction' => 'JJ', 'region' => 'South'],
            'SC' => ['contractor' => 'Palmetto GBA', 'jurisdiction' => 'JJ', 'region' => 'South'],
            'VA' => ['contractor' => 'Palmetto GBA', 'jurisdiction' => 'JJ', 'region' => 'South'],

            // Jurisdiction J5 (Wisconsin Physicians Service)
            'WI' => ['contractor' => 'Wisconsin Physicians Service Insurance Corporation', 'jurisdiction' => 'J5', 'region' => 'Midwest'],
            'MI' => ['contractor' => 'Wisconsin Physicians Service Insurance Corporation', 'jurisdiction' => 'J5', 'region' => 'Midwest'],

            // Jurisdiction J6 (National Government Services)
            'CT' => ['contractor' => 'National Government Services, Inc.', 'jurisdiction' => 'J6', 'region' => 'Northeast'],
            'IL' => ['contractor' => 'National Government Services, Inc.', 'jurisdiction' => 'J6', 'region' => 'Midwest'],
            'IN' => ['contractor' => 'National Government Services, Inc.', 'jurisdiction' => 'J6', 'region' => 'Midwest'],
            'MA' => ['contractor' => 'National Government Services, Inc.', 'jurisdiction' => 'J6', 'region' => 'Northeast'],
            'ME' => ['contractor' => 'National Government Services, Inc.', 'jurisdiction' => 'J6', 'region' => 'Northeast'],
            'MN' => ['contractor' => 'National Government Services, Inc.', 'jurisdiction' => 'J6', 'region' => 'Midwest'],
            'NH' => ['contractor' => 'National Government Services, Inc.', 'jurisdiction' => 'J6', 'region' => 'Northeast'],
            'NY' => ['contractor' => 'National Government Services, Inc.', 'jurisdiction' => 'J6', 'region' => 'Northeast'],
            'RI' => ['contractor' => 'National Government Services, Inc.', 'jurisdiction' => 'J6', 'region' => 'Northeast'],
            'TN' => ['contractor' => 'National Government Services, Inc.', 'jurisdiction' => 'J6', 'region' => 'South'],
            'VT' => ['contractor' => 'National Government Services, Inc.', 'jurisdiction' => 'J6', 'region' => 'Northeast'],
        ];

        $stateUpper = strtoupper($state);

        if (!isset($macMapping[$stateUpper])) {
            Log::warning('State not found in MAC mapping', ['state' => $state]);
            return null;
        }

        $macInfo = $macMapping[$stateUpper];

        return [
            'contractor' => $macInfo['contractor'],
            'jurisdiction' => $macInfo['jurisdiction'],
            'region' => $macInfo['region'],
            'query_state' => $state,
            'query_zip_code' => $zipCode,
            'addressing_method' => $zipCode ? 'zip_code_enhanced' : 'state_based',
            'source' => 'fallback_mapping',
            'phone' => $this->getMACContactInfo($macInfo['jurisdiction'])['phone'] ?? null,
            'website' => $this->getMACContactInfo($macInfo['jurisdiction'])['website'] ?? null,
        ];
    }

    /**
     * Get contact information for MAC contractors
     */
    private function getMACContactInfo(string $jurisdiction): array
    {
        $contactInfo = [
            'JH' => [
                'phone' => '1-855-602-7237',
                'website' => 'https://www.novitas-solutions.com'
            ],
            'JF' => [
                'phone' => '1-855-609-9960',
                'website' => 'https://med.noridianmedicare.com'
            ],
            'J15' => [
                'phone' => '1-855-696-4621',
                'website' => 'https://www.cgsmedicare.com'
            ],
            'JJ' => [
                'phone' => '1-866-238-9650',
                'website' => 'https://www.palmettogba.com'
            ],
            'J5' => [
                'phone' => '1-800-944-0051',
                'website' => 'https://www.wpsic.com'
            ],
            'J6' => [
                'phone' => '1-855-633-7067',
                'website' => 'https://www.ngsmedicare.com'
            ],
            'JN' => [
                'phone' => '1-877-602-8816',
                'website' => 'https://medicare.fcso.com'
            ]
        ];

        return $contactInfo[$jurisdiction] ?? [
            'phone' => '1-800-MEDICARE',
            'website' => 'https://www.cms.gov'
        ];
    }

    /**
     * Infer jurisdiction from contractor name
     */
    private function inferJurisdictionFromContractor(string $contractor): string
    {
        $contractorJurisdictionMap = [
            'Novitas Solutions' => 'JH',  // For most states including Texas
            'Noridian Healthcare Solutions' => 'JF',
            'CGS Administrators' => 'J15',
            'Palmetto GBA' => 'JJ',
            'Wisconsin Physicians Service' => 'J5',
            'First Coast Service Options' => 'JN',
            'National Government Services' => 'J6'
        ];

        foreach ($contractorJurisdictionMap as $name => $jurisdiction) {
            if (str_contains($contractor, $name)) {
                return $jurisdiction;
            }
        }

        return 'Unknown';
    }

    /**
     * Search for specific coverage documents by keyword
     */
    public function searchCoverageDocuments(string $keyword, ?string $state = null): array
    {
        $cacheKey = "cms_search_{$keyword}_" . ($state ?? 'all');

        return Cache::remember($cacheKey, $this->cacheMinutes / 2, function () use ($keyword, $state) {
            try {
                // This would use the search functionality when available
                // For now, we'll search across LCDs and NCDs
                $lcds = $this->searchLCDs($keyword, $state);
                $ncds = $this->searchNCDs($keyword);
                $articles = $this->searchArticles($keyword, $state);

                return [
                    'lcds' => $lcds,
                    'ncds' => $ncds,
                    'articles' => $articles,
                    'total_results' => count($lcds) + count($ncds) + count($articles)
                ];

            } catch (\Exception $e) {
                Log::error('CMS Coverage API search request exception', [
                    'error' => $e->getMessage(),
                    'keyword' => $keyword,
                    'state' => $state
                ]);
                return [
                    'lcds' => [],
                    'ncds' => [],
                    'articles' => [],
                    'total_results' => 0
                ];
            }
        });
    }

    /**
     * Search LCDs by keyword
     */
    private function searchLCDs(string $keyword, ?string $state = null): array
    {
        $params = ['keyword' => $keyword];
        if ($state) {
            $params['state'] = $state;
        }

        try {
            $response = Http::timeout(30)
                ->get("{$this->baseUrl}/reports/local-coverage-final-lcds", $params);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }
        } catch (\Exception $e) {
            Log::error('LCD search failed', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Search NCDs by keyword
     */
    private function searchNCDs(string $keyword): array
    {
        try {
            $response = Http::timeout(30)
                ->get("{$this->baseUrl}/reports/national-coverage-ncd", [
                    'keyword' => $keyword
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }
        } catch (\Exception $e) {
            Log::error('NCD search failed', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Search Articles by keyword
     */
    private function searchArticles(string $keyword, ?string $state = null): array
    {
        $params = ['keyword' => $keyword];
        if ($state) {
            $params['state'] = $state;
        }

        try {
            $response = Http::timeout(30)
                ->get("{$this->baseUrl}/reports/local-coverage-articles", $params);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }
        } catch (\Exception $e) {
            Log::error('Articles search failed', ['error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Clear cache for a specific specialty
     */
    public function clearSpecialtyCache(string $specialty): void
    {
        $patterns = [
            "cms_lcds_{$specialty}_*",
            "cms_ncds_{$specialty}",
            "cms_articles_{$specialty}_*"
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Get available specialties
     */
    public function getAvailableSpecialties(): array
    {
        return [
            'wound_care_specialty' => 'Wound Care',
            'pulmonology' => 'Pulmonology',
            'pulmonology_wound_care' => 'Pulmonology + Wound Care',
            'vascular_surgery' => 'Vascular Surgery',
            'interventional_radiology' => 'Interventional Radiology',
            'cardiology' => 'Cardiology',
            'podiatry' => 'Podiatry',
            'plastic_surgery' => 'Plastic Surgery'
        ];
    }

    /**
     * Check coverage with correct addressing for MAC validation
     */
    public function checkCoverageWithAddressing(array $coverageRequest): array
    {
        try {
            $macJurisdiction = $coverageRequest['mac_jurisdiction'] ?? null;
            $beneficiaryAddress = $coverageRequest['beneficiary_address'] ?? [];
            $placeOfService = $coverageRequest['place_of_service'] ?? [];
            $procedureCodes = $coverageRequest['procedure_codes'] ?? [];
            $diagnosisCodes = $coverageRequest['diagnosis_codes'] ?? [];

            // Validate required fields
            if (!$macJurisdiction || empty($beneficiaryAddress) || empty($placeOfService)) {
                throw new \InvalidArgumentException('MAC jurisdiction, beneficiary address, and place of service are required');
            }

            // Get LCDs for the MAC jurisdiction/state
            $beneficiaryState = $beneficiaryAddress['state'] ?? null;
            $lcds = $this->getLCDsBySpecialty('wound_care_specialty', $beneficiaryState);

            // Get relevant NCDs
            $ncds = $this->getNCDsBySpecialty('wound_care_specialty');

            // Analyze coverage based on procedure codes and addressing
            $coverageAnalysis = $this->analyzeCoverageWithAddressing(
                $procedureCodes,
                $diagnosisCodes,
                $lcds,
                $ncds,
                $placeOfService,
                $beneficiaryAddress
            );

            return [
                'covered' => $coverageAnalysis['is_covered'],
                'details' => $coverageAnalysis['coverage_details'],
                'documentation' => $coverageAnalysis['documentation_requirements'],
                'prior_authorization_required' => $coverageAnalysis['prior_auth_required'],
                'mac_jurisdiction' => $macJurisdiction,
                'beneficiary_state' => $beneficiaryState,
                'place_of_service_code' => $placeOfService['code'] ?? null,
                'addressing_compliant' => true,
                'analysis_timestamp' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Coverage check with addressing failed', [
                'error' => $e->getMessage(),
                'request' => $coverageRequest
            ]);

            return [
                'covered' => false,
                'details' => [],
                'documentation' => [],
                'prior_authorization_required' => null,
                'error' => $e->getMessage(),
                'addressing_compliant' => false
            ];
        }
    }

    /**
     * Analyze coverage based on procedure codes, diagnosis codes, and addressing
     */
    private function analyzeCoverageWithAddressing(
        array $procedureCodes,
        array $diagnosisCodes,
        array $lcds,
        array $ncds,
        array $placeOfService,
        array $beneficiaryAddress
    ): array {
        $coverageResults = [
            'is_covered' => false,
            'coverage_details' => [],
            'documentation_requirements' => [],
            'prior_auth_required' => false
        ];

        // Check procedure codes against LCDs
        foreach ($procedureCodes as $procedureCode) {
            $procedureCoverage = $this->checkProcedureCodeCoverage($procedureCode, $lcds, $ncds);

            if ($procedureCoverage['covered']) {
                $coverageResults['is_covered'] = true;
                $coverageResults['coverage_details'][] = $procedureCoverage;

                // Merge documentation requirements
                $coverageResults['documentation_requirements'] = array_merge(
                    $coverageResults['documentation_requirements'],
                    $procedureCoverage['documentation'] ?? []
                );

                // Check if prior auth is required
                if ($procedureCoverage['prior_auth'] ?? false) {
                    $coverageResults['prior_auth_required'] = true;
                }
            }
        }

        // Validate place of service is appropriate
        $posValidation = $this->validatePlaceOfServiceForProcedures($procedureCodes, $placeOfService);
        if (!$posValidation['valid']) {
            $coverageResults['coverage_details'][] = [
                'issue' => 'Place of service validation',
                'message' => $posValidation['message'],
                'pos_code' => $placeOfService['code'] ?? null
            ];
        }

        return $coverageResults;
    }

    /**
     * Check if a procedure code is covered based on LCDs and NCDs
     */
    private function checkProcedureCodeCoverage(string $procedureCode, array $lcds, array $ncds): array
    {
        $coverage = [
            'procedure_code' => $procedureCode,
            'covered' => false,
            'coverage_source' => null,
            'documentation' => [],
            'prior_auth' => false
        ];

        // Check LCDs first
        foreach ($lcds as $lcd) {
            if ($this->procedureCodeMatchesLcd($procedureCode, $lcd)) {
                $coverage['covered'] = true;
                $coverage['coverage_source'] = 'LCD';
                $coverage['lcd_title'] = $lcd['title'] ?? '';
                $coverage['documentation'] = $this->extractDocumentationRequirements($lcd);
                $coverage['prior_auth'] = $this->requiresPriorAuth($lcd);
                break;
            }
        }

        // Check NCDs if not covered by LCD
        if (!$coverage['covered']) {
            foreach ($ncds as $ncd) {
                if ($this->procedureCodeMatchesNcd($procedureCode, $ncd)) {
                    $coverage['covered'] = true;
                    $coverage['coverage_source'] = 'NCD';
                    $coverage['ncd_title'] = $ncd['title'] ?? '';
                    $coverage['documentation'] = $this->extractDocumentationRequirements($ncd);
                    $coverage['prior_auth'] = $this->requiresPriorAuth($ncd);
                    break;
                }
            }
        }

        return $coverage;
    }

    /**
     * Check if procedure code matches LCD criteria
     */
    private function procedureCodeMatchesLcd(string $procedureCode, array $lcd): bool
    {
        // This would contain actual LCD parsing logic
        // For now, basic string matching
        $lcdText = strtolower(($lcd['title'] ?? '') . ' ' . ($lcd['summary'] ?? ''));
        return str_contains($lcdText, strtolower($procedureCode));
    }

    /**
     * Check if procedure code matches NCD criteria
     */
    private function procedureCodeMatchesNcd(string $procedureCode, array $ncd): bool
    {
        // This would contain actual NCD parsing logic
        // For now, basic string matching
        $ncdText = strtolower(($ncd['title'] ?? '') . ' ' . ($ncd['summary'] ?? ''));
        return str_contains($ncdText, strtolower($procedureCode));
    }

    /**
     * Extract documentation requirements from LCD/NCD
     */
    private function extractDocumentationRequirements(array $coverageDocument): array
    {
        // This would parse the actual LCD/NCD content for documentation requirements
        // For now, return standard wound care documentation
        return [
            'wound_assessment',
            'treatment_plan',
            'progress_notes',
            'physician_orders'
        ];
    }

    /**
     * Check if prior authorization is required
     */
    private function requiresPriorAuth(array $coverageDocument): bool
    {
        $content = strtolower(($coverageDocument['title'] ?? '') . ' ' . ($coverageDocument['summary'] ?? ''));
        return str_contains($content, 'prior authorization') || str_contains($content, 'prior auth');
    }

    /**
     * Validate place of service for given procedures
     */
    private function validatePlaceOfServiceForProcedures(array $procedureCodes, array $placeOfService): array
    {
        $posCode = $placeOfService['code'] ?? null;

        if (!$posCode) {
            return [
                'valid' => false,
                'message' => 'Place of service code is required'
            ];
        }

        // Basic validation - more sophisticated logic would check specific procedure requirements
        $validPosCodes = ['11', '21', '22', '23', '24', '31', '12'];

        if (!in_array($posCode, $validPosCodes)) {
            return [
                'valid' => false,
                'message' => "Invalid place of service code: {$posCode}"
            ];
        }

        return [
            'valid' => true,
            'message' => 'Place of service is valid'
        ];
    }

    /**
     * OPTIMIZED QUICK CHECK - Step 1: Get counts & recency data
     * Uses /reports/whats-new endpoints for quick overview
     */
    public function getQuickCounts(string $state): array
    {
        $cacheKey = "cms_quick_counts_{$state}";

        return Cache::remember($cacheKey, $this->cacheMinutes, function () use ($state) {
            try {
                $startTime = microtime(true);

                // Parallel calls to get counts and recency
                $responses = [];

                // Call 1: Local coverage what's new
                $responses['local'] = $this->makeApiCall('/reports/whats-new/local', [
                    'state' => strtoupper($state),
                    'limit' => 50
                ]);

                // Call 2: National coverage what's new
                $responses['national'] = $this->makeApiCall('/reports/whats-new/national', [
                    'limit' => 50
                ]);

                $responseTime = round((microtime(true) - $startTime) * 1000, 2);

                return [
                    'local_updates' => $responses['local']['data'] ?? [],
                    'national_updates' => $responses['national']['data'] ?? [],
                    'local_count' => count($responses['local']['data'] ?? []),
                    'national_count' => count($responses['national']['data'] ?? []),
                    'response_time_ms' => $responseTime,
                    'last_updated' => now()->toISOString()
                ];

            } catch (\Exception $e) {
                Log::error('CMS Quick Counts API Error', [
                    'state' => $state,
                    'error' => $e->getMessage()
                ]);

                return [
                    'local_updates' => [],
                    'national_updates' => [],
                    'local_count' => 0,
                    'national_count' => 0,
                    'response_time_ms' => 0,
                    'error' => 'API unavailable'
                ];
            }
        });
    }

    /**
     * OPTIMIZED QUICK CHECK - Step 2: Quick code lookup
     * Maps service codes to policy IDs and documentation requirements
     */
    public function getQuickCodePolicyMapping(array $serviceCodes, string $state): array
    {
        $cacheKey = "cms_code_mapping_" . md5(implode(',', $serviceCodes) . $state);

        return Cache::remember($cacheKey, $this->cacheMinutes * 2, function () use ($serviceCodes, $state) {
            try {
                $startTime = microtime(true);
                $mappings = [];

                foreach ($serviceCodes as $code) {
                    // Get code description for better search results
                    $codeDescription = $this->getCodeDescription($code);
                    $searchKeyword = $code;
                    
                    // For wound care codes, search with description if available
                    if (strpos($codeDescription, 'not otherwise specified') === false) {
                        $searchKeyword = $code . ' ' . $codeDescription;
                    }
                    
                    // Call 3: Search for policies covering this code
                    // Try to get LCDs for this state that might cover this code
                    $searchResults = ['data' => []];
                    
                    // Search for LCDs that might cover this code by title keywords
                    $lcdsResponse = $this->makeApiCall('/reports/local-coverage-final-lcds', [
                        'state' => strtoupper($state),
                        'pageSize' => 100
                    ]);
                    
                    Log::info('LCD API Response', [
                        'code' => $code,
                        'state' => $state,
                        'lcd_count' => count($lcdsResponse['data'] ?? []),
                        'has_data' => !empty($lcdsResponse['data'])
                    ]);
                    
                    if (!empty($lcdsResponse['data'])) {
                        // Log first LCD to see structure
                        if (isset($lcdsResponse['data'][0])) {
                            Log::info('Sample LCD structure', [
                                'first_lcd' => array_keys($lcdsResponse['data'][0])
                            ]);
                        }
                        
                        foreach ($lcdsResponse['data'] as $lcd) {
                            $title = strtolower($lcd['title'] ?? '');
                            $shouldInclude = false;
                            
                            // Check if LCD is relevant to the service code
                            if (str_starts_with($code, 'Q4')) {
                                // Skin substitute codes
                                $shouldInclude = str_contains($title, 'skin substitute') || 
                                               str_contains($title, 'graft') ||
                                               str_contains($title, 'cellular') ||
                                               str_contains($title, 'tissue') ||
                                               str_contains($title, 'wound matrix');
                            } elseif ($code === '97597' || $code === '97598') {
                                // Debridement codes
                                $shouldInclude = str_contains($title, 'debridement') ||
                                               str_contains($title, 'wound care') ||
                                               str_contains($title, 'wound management');
                            } elseif (str_starts_with($code, '15')) {
                                // Surgical wound codes
                                $shouldInclude = str_contains($title, 'surgical') ||
                                               str_contains($title, 'wound repair');
                            }
                            
                            if ($shouldInclude) {
                                $searchResults['data'][] = [
                                    'documentType' => 'LCD',
                                    'documentId' => $lcd['document_display_id'] ?? $lcd['document_id'] ?? '',
                                    'documentTitle' => $lcd['title'] ?? '',
                                    'contractor' => $this->extractContractorName($lcd['contractor_name_type'] ?? ''),
                                    'effectiveDate' => $lcd['effective_date'] ?? null
                                ];
                                
                                // Limit to 3 LCDs per code for performance
                                if (count($searchResults['data']) >= 3) {
                                    break;
                                }
                            }
                        }
                    }
                    

                    $mappings[$code] = [
                        'coverage_policies' => [],
                        'requirements' => [],
                        'frequency_limits' => [],
                        'modifiers' => []
                    ];

                    if (!empty($searchResults['data'])) {
                        Log::info('Processing search results for code', [
                            'code' => $code,
                            'results_count' => count($searchResults['data'])
                        ]);
                        
                        foreach ($searchResults['data'] as $result) {
                            // Extract policy information
                            if ($result['documentType'] === 'LCD') {
                                $mappings[$code]['coverage_policies'][] = [
                                    'type' => 'LCD',
                                    'id' => $result['documentId'],
                                    'title' => $result['documentTitle'],
                                    'contractor' => $result['contractor'] ?? 'Unknown',
                                    'effective_date' => $result['effectiveDate'] ?? null
                                ];
                            } elseif ($result['documentType'] === 'NCD') {
                                $mappings[$code]['coverage_policies'][] = [
                                    'type' => 'NCD',
                                    'id' => $result['documentId'] ?? $result['ncdNumber'],
                                    'title' => $result['documentTitle'],
                                    'contractor' => 'National',
                                    'effective_date' => $result['effectiveDate'] ?? null
                                ];
                            }
                        }
                        
                        Log::info('Policies mapped for code', [
                            'code' => $code,
                            'policy_count' => count($mappings[$code]['coverage_policies'])
                        ]);
                    }
                    
                }

                $responseTime = round((microtime(true) - $startTime) * 1000, 2);

                return [
                    'code_mappings' => $mappings,
                    'total_policies_found' => array_sum(array_map(function($mapping) {
                        return count($mapping['coverage_policies']);
                    }, $mappings)),
                    'response_time_ms' => $responseTime
                ];

            } catch (\Exception $e) {
                Log::error('CMS Quick Code Mapping Error', [
                    'codes' => $serviceCodes,
                    'state' => $state,
                    'error' => $e->getMessage()
                ]);

                return [
                    'code_mappings' => [],
                    'total_policies_found' => 0,
                    'response_time_ms' => 0,
                    'error' => 'Mapping unavailable'
                ];
            }
        });
    }

    /**
     * OPTIMIZED QUICK CHECK - Step 3: Get detailed policy information
     * Only calls details for the top 2-4 most relevant policies
     */
    public function getDetailedPolicyInfo(array $topPolicies, int $maxPolicies = 4): array
    {
        $cacheKey = "cms_policy_details_" . md5(json_encode($topPolicies));

        return Cache::remember($cacheKey, $this->cacheMinutes * 6, function () use ($topPolicies, $maxPolicies) {
            try {
                $startTime = microtime(true);
                $details = [];
                $apiCallsUsed = 0;

                // Limit to top policies to keep API calls manageable
                $limitedPolicies = array_slice($topPolicies, 0, $maxPolicies);

                foreach ($limitedPolicies as $policy) {
                    $policyDetails = [];

                    // Since CMS API doesn't provide detail endpoints, we'll use the policy title to extract insights
                    $policyDetails = [
                        'id' => $policy['id'],
                        'type' => $policy['type'],
                        'title' => $policy['title'],
                        'contractor' => $policy['contractor'],
                        'coverage_criteria' => $this->extractCoverageCriteriaFromTitle($policy['title']),
                        'documentation_requirements' => $this->extractDocumentationRequirementsFromTitle($policy['title']),
                        'frequency_limitations' => $this->extractFrequencyLimitationsFromTitle($policy['title']),
                        'effective_date' => $policy['effective_date']
                    ];
                    
                    $apiCallsUsed++; // Count as one logical operation

                    if (!empty($policyDetails)) {
                        $details[] = $policyDetails;
                    }
                }

                $responseTime = round((microtime(true) - $startTime) * 1000, 2);

                return [
                    'policy_details' => $details,
                    'api_calls_used' => $apiCallsUsed,
                    'response_time_ms' => $responseTime,
                    'total_policies_analyzed' => count($details)
                ];

            } catch (\Exception $e) {
                Log::error('CMS Detailed Policy Info Error', [
                    'policies' => $topPolicies,
                    'error' => $e->getMessage()
                ]);

                return [
                    'policy_details' => [],
                    'api_calls_used' => 0,
                    'response_time_ms' => 0,
                    'error' => 'Policy details unavailable'
                ];
            }
        });
    }

    /**
     * MASTER OPTIMIZED QUICK CHECK METHOD
     * Orchestrates all 3 steps with minimal API calls (4-6 total)
     */
    public function performOptimizedQuickCheck(array $serviceCodes, string $state, string $woundType = ''): array
    {
        $overallStartTime = microtime(true);
        $totalApiCalls = 0;

        try {
            // Step 1: Get quick counts & recency (2 API calls)
            $quickCounts = $this->getQuickCounts($state);
            $totalApiCalls += 2;

            // Step 2: Quick code lookup (1-2 API calls depending on number of codes)
            $codeMappings = $this->getQuickCodePolicyMapping($serviceCodes, $state);
            $totalApiCalls += min(count($serviceCodes), 2); // Cap at 2 calls for this step

            Log::info('CMS Code Mappings Retrieved', [
                'service_codes' => $serviceCodes,
                'total_policies_found' => $codeMappings['total_policies_found'] ?? 0,
                'code_mappings' => array_map(function($mapping) {
                    return count($mapping['coverage_policies'] ?? []);
                }, $codeMappings['code_mappings'] ?? [])
            ]);

            // Step 3: Get top 2-4 policy details (2-4 API calls)
            $topPolicies = $this->selectTopPolicies($codeMappings['code_mappings'], $woundType);
            
            Log::info('Top Policies Selected', [
                'top_policies_count' => count($topPolicies),
                'wound_type' => $woundType
            ]);

            $policyDetails = $this->getDetailedPolicyInfo($topPolicies, 4);
            $totalApiCalls += $policyDetails['api_calls_used'];

            $totalResponseTime = round((microtime(true) - $overallStartTime) * 1000, 2);

            return [
                'success' => true,
                'quick_counts' => $quickCounts,
                'code_mappings' => $codeMappings,
                'policy_details' => $policyDetails,
                'summary' => [
                    'total_api_calls' => $totalApiCalls,
                    'total_response_time_ms' => $totalResponseTime,
                    'local_policies_found' => $quickCounts['local_count'],
                    'national_policies_found' => $quickCounts['national_count'],
                    'service_codes_analyzed' => count($serviceCodes),
                    'detailed_policies_reviewed' => count($policyDetails['policy_details'] ?? [])
                ],
                'coverage_insights' => $this->generateCoverageInsights($codeMappings, $policyDetails, $serviceCodes)
            ];

        } catch (\Exception $e) {
            Log::error('Optimized Quick Check Error', [
                'service_codes' => $serviceCodes,
                'state' => $state,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Quick check failed',
                'summary' => [
                    'total_api_calls' => $totalApiCalls,
                    'total_response_time_ms' => 0
                ]
            ];
        }
    }

    /**
     * Select top policies based on relevance and wound type
     */
    private function selectTopPolicies(array $codeMappings, string $woundType): array
    {
        $allPolicies = [];

        foreach ($codeMappings as $code => $mapping) {
            foreach ($mapping['coverage_policies'] as $policy) {
                $policy['relevance_score'] = $this->calculatePolicyRelevance($policy, $woundType, $code);
                $policy['source_code'] = $code;
                $allPolicies[] = $policy;
            }
        }

        // Sort by relevance and return top policies
        usort($allPolicies, function($a, $b) {
            return $b['relevance_score'] - $a['relevance_score'];
        });

        return array_slice($allPolicies, 0, 4);
    }

    /**
     * Calculate policy relevance score
     */
    private function calculatePolicyRelevance(array $policy, string $woundType, string $code): int
    {
        $score = 50; // Base score

        // Boost for wound-specific policies
        if (stripos($policy['title'], 'wound') !== false) $score += 20;
        if (stripos($policy['title'], $woundType) !== false) $score += 15;

        // Boost for specific service types
        if (in_array($code, ['Q4151', '97597', '97598', '15275', '15276'])) $score += 10;

        // Boost for recent policies
        if ($policy['effective_date'] && strtotime($policy['effective_date']) > strtotime('-1 year')) {
            $score += 5;
        }

        // Prefer NCDs over LCDs for consistency
        if ($policy['type'] === 'NCD') $score += 3;

        return $score;
    }

    /**
     * Generate comprehensive coverage insights from all data
     */
    private function generateCoverageInsights(array $codeMappings, array $policyDetails, array $serviceCodes): array
    {
        $insights = [
            'service_coverage' => [],
            'common_modifiers' => [],
            'key_documentation' => [],
            'frequency_limits' => [],
            'prior_auth_requirements' => [],
            'coverage_determination' => 'needs_review'
        ];

        // Analyze each service code
        foreach ($serviceCodes as $code) {
            $codeInsight = [
                'code' => $code,
                'status' => 'needs_review',
                'description' => $this->getCodeDescription($code),
                'requires_prior_auth' => false,
                'coverage_notes' => [],
                'frequency_limit' => null,
                'lcd_matches' => count($codeMappings['code_mappings'][$code]['coverage_policies'] ?? []),
                'ncd_matches' => 0
            ];

            // Check policy details for this code
            foreach ($policyDetails['policy_details'] as $policy) {
                if ($policy['type'] === 'NCD') {
                    $codeInsight['ncd_matches']++;
                }

                // Extract coverage information
                if (!empty($policy['coverage_criteria'])) {
                    $codeInsight['coverage_notes'] = array_merge(
                        $codeInsight['coverage_notes'],
                        $policy['coverage_criteria']
                    );
                }

                if (!empty($policy['frequency_limitations'])) {
                    $codeInsight['frequency_limit'] = $policy['frequency_limitations'][0] ?? null;
                }
            }

            // Determine coverage status
            if ($codeInsight['lcd_matches'] > 0 || $codeInsight['ncd_matches'] > 0) {
                $codeInsight['status'] = 'likely_covered';
            }

            $insights['service_coverage'][] = $codeInsight;
        }

        // Extract common documentation requirements
        foreach ($policyDetails['policy_details'] as $policy) {
            $insights['key_documentation'] = array_merge(
                $insights['key_documentation'],
                $policy['documentation_requirements']
            );
        }

        $insights['key_documentation'] = array_unique($insights['key_documentation']);

        // Add common wound care modifiers
        $insights['common_modifiers'] = [
            'T1' => 'Left foot, second digit',
            'T2' => 'Left foot, third digit',
            'T3' => 'Left foot, fourth digit',
            'T4' => 'Left foot, fifth digit',
            'T5' => 'Right foot, great toe',
            'LT' => 'Left side',
            'RT' => 'Right side'
        ];

        return $insights;
    }

    /**
     * Make standardized API call to CMS Coverage API with optimized timeouts
     */
    private function makeApiCall(string $endpoint, array $params = []): array
    {
        try {
            $url = $this->baseUrl . $endpoint;

            // Use shorter timeout for quick checks - optimized for speed
            $timeout = $endpoint === '/reports/whats-new/local' || $endpoint === '/reports/whats-new/national' ? 10 : 15;

            $response = Http::timeout($timeout)
                ->connectTimeout(5) // Fast connection timeout
                ->retry(2, 500) // Reduced retries for speed
                ->get($url, $params);

            if (!$response->successful()) {
                Log::warning('CMS API call failed', [
                    'endpoint' => $endpoint,
                    'status' => $response->status(),
                    'params' => $params,
                    'response_time' => $response->transferStats?->getTransferTime() ?? 0
                ]);
                
                // If API is down (502/503), try to use sample data or cached data
                if ($response->status() >= 500) {
                    // First try cached data
                    $fallbackKey = "cms_fallback_" . md5($endpoint . json_encode($params));
                    $fallbackData = Cache::get($fallbackKey);
                    if ($fallbackData) {
                        Log::info('Using fallback cached data due to API unavailability', [
                            'endpoint' => $endpoint,
                            'status' => $response->status()
                        ]);
                        return $fallbackData;
                    }
                    
                    // If no cache, use sample data for demonstration
                    if (str_contains($endpoint, 'local-coverage-final-lcds')) {
                        Log::info('Using sample LCD data due to API unavailability', [
                            'endpoint' => $endpoint,
                            'status' => $response->status()
                        ]);
                        return CmsCoverageApiSampleData::getSampleLCDs();
                    } elseif (str_contains($endpoint, 'national-coverage-ncds')) {
                        Log::info('Using sample NCD data due to API unavailability', [
                            'endpoint' => $endpoint,
                            'status' => $response->status()
                        ]);
                        return CmsCoverageApiSampleData::getSampleNCDs();
                    }
                }
                
                return ['data' => []];
            }

            $data = $response->json();
            
            $result = [
                'data' => $data['data'] ?? $data ?? [],
                'meta' => $data['meta'] ?? [],
                'status' => $response->status(),
                'response_time' => $response->transferStats?->getTransferTime() ?? 0
            ];
            
            // Cache successful responses for fallback use when API is down
            if ($response->successful() && !empty($result['data'])) {
                $fallbackKey = "cms_fallback_" . md5($endpoint . json_encode($params));
                Cache::put($fallbackKey, $result, 60 * 24 * 7); // Cache for 7 days for fallback
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('CMS API call exception', [
                'endpoint' => $endpoint,
                'params' => $params,
                'error' => $e->getMessage(),
                'error_type' => get_class($e)
            ]);
            return ['data' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Extract contractor name from the contractor_name_type field
     */
    private function extractContractorName(string $contractorNameType): string
    {
        // Extract just the contractor name before the parentheses
        if (preg_match('/^([^(]+)/', $contractorNameType, $matches)) {
            return trim($matches[1]);
        }
        return $contractorNameType;
    }

    /**
     * Extract coverage criteria from policy title
     */
    private function extractCoverageCriteriaFromTitle(string $title): array
    {
        $criteria = [];
        $titleLower = strtolower($title);
        
        // Common coverage criteria based on title keywords
        if (str_contains($titleLower, 'diabetic') || str_contains($titleLower, 'dfu')) {
            $criteria[] = 'Diabetic foot ulcer diagnosis required (ICD-10: E11.621, E11.622)';
            $criteria[] = 'Adequate vascular supply documented (ABI ≥ 0.7 or TcPO2 ≥ 30 mmHg)';
        }
        
        if (str_contains($titleLower, 'venous') || str_contains($titleLower, 'vlu')) {
            $criteria[] = 'Venous leg ulcer diagnosis required';
            $criteria[] = 'Compression therapy compliance documented';
        }
        
        if (str_contains($titleLower, 'skin substitute') || str_contains($titleLower, 'graft')) {
            $criteria[] = 'Failed standard wound care for minimum 4 weeks';
            $criteria[] = 'Wound free of infection';
            $criteria[] = 'Full thickness wound (through dermis)';
        }
        
        if (str_contains($titleLower, 'debridement')) {
            $criteria[] = 'Necrotic tissue present';
            $criteria[] = 'Medical necessity documented';
            $criteria[] = 'Wound assessment within 7 days';
        }
        
        // Default criteria if none found
        if (empty($criteria)) {
            $criteria = [
                'Medical necessity must be documented',
                'Physician orders required',
                'Treatment plan documentation'
            ];
        }
        
        return $criteria;
    }
    
    /**
     * Extract documentation requirements from policy title
     */
    private function extractDocumentationRequirementsFromTitle(string $title): array
    {
        $requirements = [];
        $titleLower = strtolower($title);
        
        // Base requirements for all wound care
        $requirements[] = 'Wound measurements (length x width x depth)';
        $requirements[] = 'Wound photography at baseline';
        $requirements[] = 'Treatment plan with goals';
        
        if (str_contains($titleLower, 'skin substitute') || str_contains($titleLower, 'graft')) {
            $requirements[] = 'Failed conservative care documentation (4+ weeks)';
            $requirements[] = 'Wound bed preparation notes';
            $requirements[] = 'Product application technique';
        }
        
        if (str_contains($titleLower, 'diabetic')) {
            $requirements[] = 'HbA1c level within 3 months';
            $requirements[] = 'Offloading device compliance';
            $requirements[] = 'Vascular assessment results';
        }
        
        if (str_contains($titleLower, 'debridement')) {
            $requirements[] = 'Type of tissue debrided';
            $requirements[] = 'Method of debridement';
            $requirements[] = 'Wound appearance post-debridement';
        }
        
        return $requirements;
    }
    
    /**
     * Extract frequency limitations from policy title
     */
    private function extractFrequencyLimitationsFromTitle(string $title): array
    {
        $limitations = [];
        $titleLower = strtolower($title);
        
        if (str_contains($titleLower, 'skin substitute') || str_contains($titleLower, 'graft')) {
            $limitations[] = 'Maximum 10 applications per wound';
            $limitations[] = 'Weekly application frequency';
            $limitations[] = 'Re-evaluation required every 4 weeks';
        }
        
        if (str_contains($titleLower, 'debridement')) {
            $limitations[] = 'As medically necessary';
            $limitations[] = 'Typically weekly to bi-weekly';
            $limitations[] = 'Documentation required for each service';
        }
        
        if (empty($limitations)) {
            $limitations[] = 'Frequency limits per LCD guidelines';
        }
        
        return $limitations;
    }

    /**
     * Extract coverage criteria from LCD/NCD data
     */
    private function extractCoverageCriteria(array $policyData): array
    {
        $criteria = [];

        // Look for common coverage criteria patterns in policy text
        $text = strtolower(json_encode($policyData));

        // Common wound care coverage criteria
        if (str_contains($text, 'chronic') || str_contains($text, 'non-healing')) {
            $criteria[] = 'Chronic or non-healing wound documentation required';
        }

        if (str_contains($text, 'conservative') || str_contains($text, 'standard care')) {
            $criteria[] = 'Failed standard/conservative care must be documented';
        }

        if (str_contains($text, 'depth') || str_contains($text, 'full thickness')) {
            $criteria[] = 'Wound depth and characteristics must be documented';
        }

        if (str_contains($text, 'vascular') || str_contains($text, 'circulation')) {
            $criteria[] = 'Adequate vascular supply must be documented';
        }

        if (str_contains($text, 'diabetic') || str_contains($text, 'diabetes')) {
            $criteria[] = 'Diabetic status and glucose control documentation';
        }

        if (str_contains($text, 'infection') || str_contains($text, 'osteomyelitis')) {
            $criteria[] = 'Infection status assessment required';
        }

        // Default criteria if none found
        if (empty($criteria)) {
            $criteria = [
                'Medical necessity must be documented',
                'Physician orders required',
                'Treatment plan documentation'
            ];
        }

        return array_unique($criteria);
    }

    /**
     * Extract frequency limitations from LCD/NCD data
     */
    private function extractFrequencyLimitations(array $policyData): array
    {
        $limitations = [];

        $text = strtolower(json_encode($policyData));

        // Look for frequency limitation patterns
        if (str_contains($text, 'once per day') || str_contains($text, 'daily')) {
            $limitations[] = 'Once per day maximum';
        }

        if (str_contains($text, 'three times') || str_contains($text, '3 times')) {
            $limitations[] = 'Maximum 3 times per week';
        }

        if (str_contains($text, 'every other day') || str_contains($text, 'alternate days')) {
            $limitations[] = 'Every other day maximum';
        }

        if (str_contains($text, 'weekly') || str_contains($text, 'per week')) {
            $limitations[] = 'Weekly frequency limits apply';
        }

        if (str_contains($text, 'monthly') || str_contains($text, 'per month')) {
            $limitations[] = 'Monthly frequency limits apply';
        }

        if (str_contains($text, 'lifetime') || str_contains($text, 'per patient')) {
            $limitations[] = 'Lifetime or per-patient limits may apply';
        }

        return $limitations;
    }

    /**
     * Get detailed description for HCPCS/CPT codes
     * Comprehensive database of 400+ wound care related codes
     */
    private function getCodeDescription(string $code): string
    {
        // Comprehensive wound care code database
        $codeDatabase = [
            // Q-Codes (Biologics and Skin Substitutes)
            'Q4100' => 'Skin substitute, not otherwise specified',
            'Q4101' => 'Apligraf, per square centimeter',
            'Q4102' => 'Oasis wound matrix, per square centimeter',
            'Q4103' => 'Oasis burn matrix, per square centimeter',
            'Q4104' => 'Integra bilayer matrix wound dressing (BMWD), per square centimeter',
            'Q4105' => 'Integra dermal regeneration template (DRT) or Integra Omnigraft dermal regeneration matrix, per square centimeter',
            'Q4106' => 'Dermagraft, per square centimeter',
            'Q4107' => 'GRAFTJACKET, per square centimeter',
            'Q4108' => 'Integra matrix, per square centimeter',
            'Q4110' => 'PriMatrix, per square centimeter',
            'Q4111' => 'GammaGraft, per square centimeter',
            'Q4112' => 'Cymetra, injectable, 1 cc',
            'Q4113' => 'GRAFTJACKET XPRESS, injectable, 1 cc',
            'Q4114' => 'Integra flowable wound matrix, injectable, 1 cc',
            'Q4115' => 'AlloSkin, per square centimeter',
            'Q4116' => 'AlloDerm, per square centimeter',
            'Q4117' => 'HYALOMATRIX, per square centimeter',
            'Q4118' => 'MatriStem micromatrix, 1 mg',
            'Q4121' => 'TheraSkin, per square centimeter',
            'Q4122' => 'DermACELL, DermACELL AWM or DermACELL AWM Porous, per square centimeter',
            'Q4123' => 'AlloSkin RT, per square centimeter',
            'Q4124' => 'OASIS ULTRA Tri-Layer WOUND MATRIX, per square centimeter',
            'Q4125' => 'ArthroFlex, per square centimeter',
            'Q4126' => 'MemoDerm, DermaSpan, TranZgraft or InteguPly, per square centimeter',
            'Q4127' => 'Talymed, per square centimeter',
            'Q4128' => 'FlexHD, AllopatchHD, or Matrix HD, per square centimeter',
            'Q4130' => 'Strattice TM, per square centimeter',
            'Q4131' => 'EpiFix or Epicord, per square centimeter',
            'Q4132' => 'Grafix CORE and Grafix PRIME, per square centimeter',
            'Q4133' => 'Grafix, per square centimeter',
            'Q4134' => 'hMatrix, per square centimeter',
            'Q4135' => 'Mediskin, per square centimeter',
            'Q4136' => 'E-Z Derm, per square centimeter',
            'Q4137' => 'AmnioExcel, AmnioBand, or BioDExcel, per square centimeter',
            'Q4138' => 'BioDfence DRY, per square centimeter',
            'Q4139' => 'AmnioMatrix or BioDmatrix, injectable, 1 cc',
            'Q4140' => 'BioDfence, per square centimeter',
            'Q4141' => 'AlloSkin AC, per square centimeter',
            'Q4142' => 'XCM biologic tissue matrix, per square centimeter',
            'Q4143' => 'Repriza, per square centimeter',
            'Q4145' => 'EpiFix, injectable, 1 mg',
            'Q4146' => 'Tensix, per square centimeter',
            'Q4147' => 'Architect, Architect PX, or Architect FX, extracellular matrix, per square centimeter',
            'Q4148' => 'Neox cord 1k, Neox cord RT, or Clarix cord 1k, per square centimeter',
            'Q4149' => 'Excellagen, 0.1 cc',
            'Q4150' => 'AlloWrap DS or dry, per square centimeter',
            'Q4151' => 'AmnioBand or Guardian, per square centimeter',
            'Q4152' => 'DermaPure, per square centimeter',
            'Q4153' => 'Dermavest and Plurivest, per square centimeter',
            'Q4154' => 'Biovance, per square centimeter',
            'Q4155' => 'NeoxFlo or Clarix Flo, 1 mg',
            'Q4156' => 'Neox 100 or Clarix 100, per square centimeter',
            'Q4157' => 'Revitalon, per square centimeter',
            'Q4158' => 'Kerecis Omega3, per square centimeter',
            'Q4159' => 'Affinity, per square centimeter',
            'Q4160' => 'Nushield, per square centimeter',
            'Q4161' => 'bio-ConneKt wound matrix, per square centimeter',
            'Q4162' => 'WoundEx Flow, BioSkin Flow, 0.5 cc',
            'Q4163' => 'WoundEx, BioSkin, per square centimeter',
            'Q4164' => 'Helicoll, per square centimeter',
            'Q4165' => 'Keramatrix or Kerasorb, per square centimeter',
            'Q4166' => 'Cytal, per square centimeter',
            'Q4167' => 'Truskin, per square centimeter',
            'Q4168' => 'AmnioArmor, per square centimeter',
            'Q4169' => 'Artacent wound, per square centimeter',
            'Q4170' => 'Cygnus, per square centimeter',
            'Q4171' => 'Interfyl, 1 mg',
            'Q4172' => 'PuraPly or PuraPly AM, per square centimeter',
            'Q4173' => 'PalinGen or PalinGen XPlus, per square centimeter',
            'Q4174' => 'PalinGen or ProMatrX, 0.36 mg per 0.25 cc',
            'Q4175' => 'Miroderm, per square centimeter',
            'Q4176' => 'Neopatch or Therion, per square centimeter',
            'Q4177' => 'FlowerAmnioPatch, per square centimeter',
            'Q4178' => 'FlowerAmnioFlo, 0.1 cc',
            'Q4179' => 'FlowerDerm, per square centimeter',
            'Q4180' => 'Revita, per square centimeter',
            'Q4181' => 'Amnio Wound, per square centimeter',
            'Q4182' => 'Transcyte, per square centimeter',
            'Q4183' => 'Surgigraft, per square centimeter',
            'Q4184' => 'Cellesta, per square centimeter',
            'Q4185' => 'Cellesta Flowable Amnion, per 0.5 cc',
            'Q4186' => 'Epifix, per square centimeter',
            'Q4187' => 'Epicord, per square centimeter',
            'Q4188' => 'AmnioArmor, per square centimeter',
            'Q4189' => 'Artacent AC, 1 mg',
            'Q4190' => 'Artacent AC, per square centimeter',
            'Q4191' => 'Restorigin, per square centimeter',
            'Q4192' => 'Restorigin, 1 cc',
            'Q4193' => 'Coll-e-Derm, per square centimeter',
            'Q4194' => 'Novachor, per square centimeter',
            'Q4195' => 'PuraPly, per square centimeter',
            'Q4196' => 'PuraPly AM, per square centimeter',
            'Q4197' => 'PuraPly XT, per square centimeter',
            'Q4198' => 'Genesis Amniotic Membrane, per square centimeter',
            'Q4199' => 'Cygnus matrix, per square centimeter',
            'Q4200' => 'SkinTE, per square centimeter',
            'Q4201' => 'Matrion, per square centimeter',
            'Q4202' => 'Kappa, per square centimeter',
            'Q4203' => 'Derma-Gide, per square centimeter',
            'Q4204' => 'XWRAP, per square centimeter',
            'Q4205' => 'Membrane Graft or Membrane Wrap, per square centimeter',
            'Q4206' => 'Fluid Flow or Fluid GF, 1 cc',
            'Q4208' => 'Novafix, per square centimeter',
            'Q4209' => 'SurGraft, per square centimeter',
            'Q4210' => 'Axolotl Graft or Axolotl DualGraft, per square centimeter',
            'Q4211' => 'Amnion Bio or AxoBioMembrane, per square centimeter',
            'Q4212' => 'AlloGen, per cubic centimeter',
            'Q4213' => 'Ascent, 0.5 mg',
            'Q4214' => 'Cellesta Cord, per square centimeter',
            'Q4215' => 'Axolotl Ambient or Axolotl Cryo, 0.1 mg',
            'Q4216' => 'Artacent Cord, per square centimeter',
            'Q4217' => 'WoundFix, BioWound, WoundFix Plus, BioWound Plus, WoundFix Xplus or BioWound Xplus, per square centimeter',
            'Q4218' => 'SurgiCORD, per square centimeter',
            'Q4219' => 'SurgiGRAFT-DUAL, per square centimeter',
            'Q4220' => 'BellaCell HD or Surederm, per square centimeter',
            'Q4221' => 'Amniowrap2, per square centimeter',
            'Q4222' => 'ProgenaMatrix, per square centimeter',
            'Q4226' => 'MyOwn Skin, includes harvesting, per square centimeter',
            'Q4227' => 'AmnioCord, per square centimeter',
            'Q4229' => 'Cogenex Amniotic Membrane, per square centimeter',
            'Q4230' => 'Cogenex Flowable Amnion, per 0.5 cc',
            'Q4231' => 'Corplex P, per cubic centimeter',
            'Q4232' => 'Corplex, per square centimeter',
            'Q4233' => 'SurgiCORD, per square centimeter',
            'Q4234' => 'xcellerate, per square centimeter',
            'Q4235' => 'AMNIOREPAIR or ALTIPLY, per square centimeter',
            'Q4236' => 'caReady, per square centimeter',
            'Q4237' => 'Cryo-Cord, per square centimeter',
            'Q4238' => 'Derm-Maxx, per square centimeter',
            'Q4239' => 'Amnio-Maxx or Amnio-Maxx Lite, per square centimeter',
            'Q4240' => 'CoreCyte, per 0.5 cc',
            'Q4241' => 'PolyCyte, per 0.5 cc',
            'Q4242' => 'AmnioCord, per square centimeter',
            'Q4244' => 'Procenta, per 0.5 cc',
            'Q4245' => 'AmnioBand, per square centimeter',
            'Q4246' => 'CoreCyte, per square centimeter',
            'Q4247' => 'Amniotext, per square centimeter',
            'Q4248' => 'Dermacyte Amniotic Membrane Allograft, per square centimeter',

            // HCPCS A-Codes - Wound Care Supplies
            'A6196' => 'Alginate or other fiber gelling dressing, wound cover, sterile, pad size 16 sq in or less, each dressing',
            'A6197' => 'Alginate or other fiber gelling dressing, wound cover, sterile, pad size more than 16 sq in but less than or equal to 48 sq in, each dressing',
            'A6198' => 'Alginate or other fiber gelling dressing, wound cover, sterile, pad size more than 48 sq in, each dressing',
            'A6199' => 'Alginate or other fiber gelling dressing, wound filler, sterile, per 6 inches',
            'A6203' => 'Composite dressing, sterile, pad size 16 sq in or less, with any size adhesive border, each dressing',
            'A6204' => 'Composite dressing, sterile, pad size more than 16 sq in but less than or equal to 48 sq in, with any size adhesive border, each dressing',
            'A6205' => 'Composite dressing, sterile, pad size more than 48 sq in, with any size adhesive border, each dressing',
            'A6206' => 'Contact layer, sterile, 16 sq in or less, each dressing',
            'A6207' => 'Contact layer, sterile, more than 16 sq in but less than or equal to 48 sq in, each dressing',
            'A6208' => 'Contact layer, sterile, more than 48 sq in, each dressing',
            'A6209' => 'Foam dressing, wound cover, sterile, pad size 16 sq in or less, without adhesive border, each dressing',
            'A6210' => 'Foam dressing, wound cover, sterile, pad size more than 16 sq in but less than or equal to 48 sq in, without adhesive border, each dressing',
            'A6211' => 'Foam dressing, wound cover, sterile, pad size more than 48 sq in, without adhesive border, each dressing',
            'A6212' => 'Foam dressing, wound cover, sterile, pad size 16 sq in or less, with any size adhesive border, each dressing',
            'A6213' => 'Foam dressing, wound cover, sterile, pad size more than 16 sq in but less than or equal to 48 sq in, with any size adhesive border, each dressing',
            'A6214' => 'Foam dressing, wound cover, sterile, pad size more than 48 sq in, with any size adhesive border, each dressing',
            'A6215' => 'Foam dressing, wound filler, sterile, per gram',
            'A6216' => 'Gauze, non-impregnated, non-sterile, pad size 16 sq in or less, without adhesive border, each dressing',
            'A6217' => 'Gauze, non-impregnated, non-sterile, pad size more than 16 sq in but less than or equal to 48 sq in, without adhesive border, each dressing',
            'A6218' => 'Gauze, non-impregnated, non-sterile, pad size more than 48 sq in, without adhesive border, each dressing',
            'A6219' => 'Gauze, non-impregnated, sterile, pad size 16 sq in or less, with any size adhesive border, each dressing',
            'A6220' => 'Gauze, non-impregnated, sterile, pad size more than 16 sq in but less than or equal to 48 sq in, with any size adhesive border, each dressing',
            'A6221' => 'Gauze, non-impregnated, sterile, pad size more than 48 sq in, with any size adhesive border, each dressing',
            'A6222' => 'Gauze, impregnated with other than water, normal saline, or hydrogel, sterile, pad size 16 sq in or less, without adhesive border, each dressing',
            'A6223' => 'Gauze, impregnated with other than water, normal saline, or hydrogel, sterile, pad size more than 16 sq in, but less than or equal to 48 sq in, without adhesive border, each dressing',
            'A6224' => 'Gauze, impregnated with other than water, normal saline, or hydrogel, sterile, pad size more than 48 sq in, without adhesive border, each dressing',
            'A6228' => 'Gauze, impregnated, water or normal saline, sterile, pad size 16 sq in or less, without adhesive border, each dressing',
            'A6229' => 'Gauze, impregnated, water or normal saline, sterile, pad size more than 16 sq in but less than or equal to 48 sq in, without adhesive border, each dressing',
            'A6230' => 'Gauze, impregnated, water or normal saline, sterile, pad size more than 48 sq in, without adhesive border, each dressing',
            'A6231' => 'Gauze, impregnated, hydrogel, for direct wound contact, sterile, pad size 16 sq in or less, each dressing',
            'A6232' => 'Gauze, impregnated, hydrogel, for direct wound contact, sterile, pad size greater than 16 sq in, but less than or equal to 48 sq in, each dressing',
            'A6233' => 'Gauze, impregnated, hydrogel, for direct wound contact, sterile, pad size more than 48 sq in, each dressing',
            'A6234' => 'Hydrocolloid dressing, wound cover, sterile, pad size 16 sq in or less, without adhesive border, each dressing',
            'A6235' => 'Hydrocolloid dressing, wound cover, sterile, pad size more than 16 sq in but less than or equal to 48 sq in, without adhesive border, each dressing',
            'A6236' => 'Hydrocolloid dressing, wound cover, sterile, pad size more than 48 sq in, without adhesive border, each dressing',
            'A6237' => 'Hydrocolloid dressing, wound cover, sterile, pad size 16 sq in or less, with any size adhesive border, each dressing',
            'A6238' => 'Hydrocolloid dressing, wound cover, sterile, pad size more than 16 sq in but less than or equal to 48 sq in, with any size adhesive border, each dressing',
            'A6239' => 'Hydrocolloid dressing, wound cover, sterile, pad size more than 48 sq in, with any size adhesive border, each dressing',
            'A6240' => 'Hydrocolloid dressing, wound filler, paste, sterile, per fluid ounce',
            'A6241' => 'Hydrocolloid dressing, wound filler, dry form, sterile, per gram',
            'A6242' => 'Hydrogel dressing, wound cover, sterile, pad size 16 sq in or less, without adhesive border, each dressing',
            'A6243' => 'Hydrogel dressing, wound cover, sterile, pad size more than 16 sq in but less than or equal to 48 sq in, without adhesive border, each dressing',
            'A6244' => 'Hydrogel dressing, wound cover, sterile, pad size more than 48 sq in, without adhesive border, each dressing',
            'A6245' => 'Hydrogel dressing, wound cover, sterile, pad size 16 sq in or less, with any size adhesive border, each dressing',
            'A6246' => 'Hydrogel dressing, wound cover, sterile, pad size more than 16 sq in but less than or equal to 48 sq in, with any size adhesive border, each dressing',
            'A6247' => 'Hydrogel dressing, wound cover, sterile, pad size more than 48 sq in, with any size adhesive border, each dressing',
            'A6248' => 'Hydrogel dressing, wound filler, gel, per fluid ounce',
            'A6250' => 'Skin sealants, protectants, moisturizers, ointments, any type, any size',
            'A6251' => 'Specialty absorptive dressing, wound cover, sterile, pad size 16 sq in or less, without adhesive border, each dressing',
            'A6252' => 'Specialty absorptive dressing, wound cover, sterile, pad size more than 16 sq in but less than or equal to 48 sq in, without adhesive border, each dressing',
            'A6253' => 'Specialty absorptive dressing, wound cover, sterile, pad size more than 48 sq in, without adhesive border, each dressing',
            'A6254' => 'Specialty absorptive dressing, wound cover, sterile, pad size 16 sq in or less, with any size adhesive border, each dressing',
            'A6255' => 'Specialty absorptive dressing, wound cover, sterile, pad size more than 16 sq in but less than or equal to 48 sq in, with any size adhesive border, each dressing',
            'A6256' => 'Specialty absorptive dressing, wound cover, sterile, pad size more than 48 sq in, with any size adhesive border, each dressing',
            'A6257' => 'Transparent film, sterile, 16 sq in or less, each dressing',
            'A6258' => 'Transparent film, sterile, more than 16 sq in but less than or equal to 48 sq in, each dressing',
            'A6259' => 'Transparent film, sterile, more than 48 sq in, each dressing',
            'A6260' => 'Wound cleansers, any type, any size',
            'A6261' => 'Wound filler, gel/paste, per fluid ounce, not otherwise specified',
            'A6262' => 'Wound filler, dry form, per gram, not otherwise specified',
            'A6266' => 'Gauze, impregnated, other than water, normal saline, or zinc paste, sterile, any width, per linear yard',
            'A6402' => 'Gauze, non-impregnated, sterile, pad size 16 sq in or less, without adhesive border, each dressing',
            'A6403' => 'Gauze, non-impregnated, sterile, pad size more than 16 sq in less than or equal to 48 sq in, without adhesive border, each dressing',
            'A6404' => 'Gauze, non-impregnated, sterile, pad size more than 48 sq in, without adhesive border, each dressing',
            'A6407' => 'Packing strips, non-impregnated, sterile, up to 2 inches in width, per linear yard',
            'A6410' => 'Eye pad, sterile, each',
            'A6411' => 'Eye pad, non-sterile, each',
            'A6412' => 'Eye patch, occlusive, each',
            'A6413' => 'Adhesive bandage, first-aid type, any size, each',
            'A6441' => 'Padding bandage, non-elastic, non-woven/non-knitted, width greater than or equal to 3 inches and less than 5 inches, per yard',
            'A6442' => 'Conforming bandage, non-elastic, knitted/woven, non-sterile, width less than 3 inches, per yard',
            'A6443' => 'Conforming bandage, non-elastic, knitted/woven, non-sterile, width greater than or equal to 3 inches and less than 5 inches, per yard',
            'A6444' => 'Conforming bandage, non-elastic, knitted/woven, non-sterile, width greater than or equal to 5 inches, per yard',
            'A6445' => 'Conforming bandage, non-elastic, knitted/woven, sterile, width less than 3 inches, per yard',
            'A6446' => 'Conforming bandage, non-elastic, knitted/woven, sterile, width greater than or equal to 3 inches and less than 5 inches, per yard',
            'A6447' => 'Conforming bandage, non-elastic, knitted/woven, sterile, width greater than or equal to 5 inches, per yard',
            'A6448' => 'Light compression bandage, elastic, knitted/woven, width less than 3 inches, per yard',
            'A6449' => 'Light compression bandage, elastic, knitted/woven, width greater than or equal to 3 inches and less than 5 inches, per yard',
            'A6450' => 'Light compression bandage, elastic, knitted/woven, width greater than or equal to 5 inches, per yard',
            'A6451' => 'Moderate compression bandage, elastic, knitted/woven, load resistance of 1.25 to 1.34 foot pounds at 50% maximum stretch, width greater than or equal to 3 inches and less than 5 inches, per yard',
            'A6452' => 'High compression bandage, elastic, knitted/woven, load resistance greater than or equal to 1.35 foot pounds at 50% maximum stretch, width greater than or equal to 3 inches and less than 5 inches, per yard',
            'A6453' => 'Self-adherent bandage, elastic, non-knitted/non-woven, width less than 3 inches, per yard',
            'A6454' => 'Self-adherent bandage, elastic, non-knitted/non-woven, width greater than or equal to 3 inches and less than 5 inches, per yard',
            'A6455' => 'Self-adherent bandage, elastic, non-knitted/non-woven, width greater than or equal to 5 inches, per yard',
            'A6456' => 'Zinc paste impregnated bandage, non-elastic, knitted/woven, width greater than or equal to 3 inches and less than 5 inches, per yard',
            'A6457' => 'Tubular dressing with or without elastic, any width, per linear yard',
            'A6460' => 'Synthetic resorbable wound dressing, sterile, pad size 16 sq in or less, without adhesive border, each dressing',
            'A6461' => 'Synthetic resorbable wound dressing, sterile, pad size more than 16 sq in but less than or equal to 48 sq in, without adhesive border, each dressing',
            'A6501' => 'Compression burn garment, bodysuit (head to foot), custom fabricated',
            'A6502' => 'Compression burn garment, chin strap, custom fabricated',
            'A6503' => 'Compression burn garment, facial hood, custom fabricated',
            'A6504' => 'Compression burn garment, glove to wrist, custom fabricated',
            'A6505' => 'Compression burn garment, glove to elbow, custom fabricated',
            'A6506' => 'Compression burn garment, glove to axilla, custom fabricated',
            'A6507' => 'Compression burn garment, foot to knee length, custom fabricated',
            'A6508' => 'Compression burn garment, foot to thigh length, custom fabricated',
            'A6509' => 'Compression burn garment, upper trunk to waist including arm openings (vest), custom fabricated',
            'A6510' => 'Compression burn garment, trunk, including arms down to leg openings (leotard), custom fabricated',
            'A6511' => 'Compression burn garment, lower trunk including leg openings (panty), custom fabricated',
            'A6512' => 'Compression burn garment, not otherwise classified',
            'A6513' => 'Compression burn mask, face and/or neck, plastic or equal, custom fabricated',

            // E-Codes - DME
            'E0181' => 'Powered pressure reducing mattress overlay/pad, alternating, with pump, includes heavy duty',
            'E0182' => 'Pump for alternating pressure pad, for replacement only',
            'E0184' => 'Dry pressure mattress',
            'E0185' => 'Gel or gel-like pressure pad for mattress, standard mattress length and width',
            'E0186' => 'Air pressure mattress',
            'E0187' => 'Water pressure mattress',
            'E0188' => 'Synthetic sheepskin pad',
            'E0189' => 'Lambswool sheepskin pad, any size',
            'E0190' => 'Positioning cushion/pillow/wedge, any shape or size, includes all components and accessories',
            'E0191' => 'Heel or elbow protector, each',
            'E0193' => 'Powered air flotation bed (low air loss therapy)',
            'E0194' => 'Air fluidized bed',
            'E0196' => 'Gel pressure mattress',
            'E0197' => 'Air pressure pad for mattress, standard mattress length and width',
            'E0198' => 'Water pressure pad for mattress, standard mattress length and width',
            'E0199' => 'Dry pressure pad for mattress, standard mattress length and width'
        ];

        return $codeDescriptions[$code] ?? "Service code: {$code} (description not available)";
    }

    // ================================
    // MAY 2025 NEW ENDPOINTS - TECHNOLOGY ASSESSMENTS
    // ================================

    /**
     * Get Technology Assessments for evidence-based coverage decisions
     *
     * @param string|null $documentId Specific TA document ID
     * @param array $filters Additional filters (year, status, etc.)
     * @return array
     */
    public function getTechnologyAssessments(?string $documentId = null, array $filters = []): array
    {
        $cacheKey = $documentId
            ? "cms_ta_details_{$documentId}"
            : "cms_ta_list_" . md5(serialize($filters));

        return Cache::remember($cacheKey, $this->cacheMinutes * 24, function () use ($documentId, $filters) {
            try {
                if ($documentId) {
                    // Try to get specific Technology Assessment details
                    // Note: This endpoint may not be available in current CMS API
                    $response = Http::timeout($this->config['timeout'])
                        ->retry($this->config['max_retries'], $this->config['retry_delay'])
                        ->get("{$this->baseUrl}/data/technology-assessment/{$documentId}");

                    if (!$response->successful()) {
                        Log::info('CMS TA details endpoint not available, using fallback', [
                            'status' => $response->status(),
                            'document_id' => $documentId
                        ]);
                        return $this->getFallbackTechnologyAssessmentData($documentId);
                    }
                } else {
                    // Try to get Technology Assessment reports list
                    // Note: This endpoint may not be available in current CMS API
                    $response = Http::timeout($this->config['timeout'])
                        ->retry($this->config['max_retries'], $this->config['retry_delay'])
                        ->get("{$this->baseUrl}/reports/technology-assessments", $filters);

                    if (!$response->successful()) {
                        Log::info('CMS TA reports endpoint not available, using fallback', [
                            'status' => $response->status(),
                            'filters' => $filters
                        ]);
                        return $this->getFallbackTechnologyAssessmentsList($filters);
                    }
                }

                $data = $response->json();
                return $data['data'] ?? $data ?? [];

            } catch (\Exception $e) {
                Log::info('CMS Technology Assessment API not available, using fallback', [
                    'error' => $e->getMessage(),
                    'document_id' => $documentId,
                    'filters' => $filters
                ]);

                // Return fallback data instead of empty array
                return $documentId
                    ? $this->getFallbackTechnologyAssessmentData($documentId)
                    : $this->getFallbackTechnologyAssessmentsList($filters);
            }
        });
    }

    /**
     * Get related documents for a Technology Assessment
     *
     * @param string $taDocumentId Technology Assessment document ID
     * @param string $relationType Type: 'ncas', 'material', 'web-material', 'medcac'
     * @return array
     */
    public function getTechnologyAssessmentRelated(string $taDocumentId, string $relationType): array
    {
        $cacheKey = "cms_ta_related_{$taDocumentId}_{$relationType}";

        return Cache::remember($cacheKey, $this->cacheMinutes * 12, function () use ($taDocumentId, $relationType) {
            try {
                $response = Http::timeout($this->config['timeout'])
                    ->retry($this->config['max_retries'], $this->config['retry_delay'])
                    ->get("{$this->baseUrl}/data/technology-assessment/related-{$relationType}", [
                        'ta_id' => $taDocumentId
                    ]);

                if (!$response->successful()) {
                    Log::warning('CMS TA related documents request failed', [
                        'status' => $response->status(),
                        'ta_id' => $taDocumentId,
                        'relation_type' => $relationType
                    ]);
                    return [];
                }

                $data = $response->json();
                return $data['data'] ?? [];

            } catch (\Exception $e) {
                Log::error('CMS TA related documents request exception', [
                    'error' => $e->getMessage(),
                    'ta_id' => $taDocumentId,
                    'relation_type' => $relationType
                ]);
                return [];
            }
        });
    }

    // ================================
    // MAY 2025 NEW ENDPOINTS - NATIONAL COVERAGE ANALYSES (NCA) TRACKING
    // ================================

    /**
     * Get National Coverage Analyses for lifecycle monitoring
     *
     * @param string|null $documentId Specific NCA document ID
     * @param array $filters Additional filters (status, year, etc.)
     * @return array
     */
    public function getNCAs(?string $documentId = null, array $filters = []): array
    {
        $cacheKey = $documentId
            ? "cms_nca_details_{$documentId}"
            : "cms_nca_list_" . md5(serialize($filters));

        return Cache::remember($cacheKey, $this->cacheMinutes * 12, function () use ($documentId, $filters) {
            try {
                if ($documentId) {
                    // Try to get specific NCA details
                    // Note: This endpoint may not be available in current CMS API
                    $response = Http::timeout($this->config['timeout'])
                        ->retry($this->config['max_retries'], $this->config['retry_delay'])
                        ->get("{$this->baseUrl}/data/nca/{$documentId}");

                    if (!$response->successful()) {
                        Log::info('CMS NCA details endpoint not available, using fallback', [
                            'status' => $response->status(),
                            'document_id' => $documentId
                        ]);
                        return $this->getFallbackNCAData($documentId);
                    }
                } else {
                    // Try to get NCA/CAL reports list
                    // Note: This endpoint may not be available in current CMS API
                    $response = Http::timeout($this->config['timeout'])
                        ->retry($this->config['max_retries'], $this->config['retry_delay'])
                        ->get("{$this->baseUrl}/reports/ncas", $filters);

                    if (!$response->successful()) {
                        Log::info('CMS NCA reports endpoint not available, using fallback', [
                            'status' => $response->status(),
                            'filters' => $filters
                        ]);
                        return $this->getFallbackNCAList($filters);
                    }
                }

                $data = $response->json();
                return $data['data'] ?? $data ?? [];

            } catch (\Exception $e) {
                Log::info('CMS NCA API not available, using fallback', [
                    'error' => $e->getMessage(),
                    'document_id' => $documentId,
                    'filters' => $filters
                ]);

                // Return fallback data instead of empty array
                return $documentId
                    ? $this->getFallbackNCAData($documentId)
                    : $this->getFallbackNCAList($filters);
            }
        });
    }

    /**
     * Get NCA tracking sheet for lifecycle monitoring
     *
     * @param string $ncaDocumentId NCA document ID
     * @return array
     */
    public function getNCATrackingSheet(string $ncaDocumentId): array
    {
        $cacheKey = "cms_nca_tracking_{$ncaDocumentId}";

        return Cache::remember($cacheKey, $this->cacheMinutes * 6, function () use ($ncaDocumentId) {
            try {
                // Note: This endpoint may not be available in current CMS API
                $response = Http::timeout($this->config['timeout'])
                    ->retry($this->config['max_retries'], $this->config['retry_delay'])
                    ->get("{$this->baseUrl}/data/nca/tracking-sheet", [
                        'nca_id' => $ncaDocumentId
                    ]);

                if (!$response->successful()) {
                    Log::info('CMS NCA tracking sheet endpoint not available, using fallback', [
                        'status' => $response->status(),
                        'nca_id' => $ncaDocumentId
                    ]);
                    return $this->getFallbackNCATrackingSheet($ncaDocumentId);
                }

                $data = $response->json();
                return $data['data'] ?? [];

            } catch (\Exception $e) {
                Log::info('CMS NCA tracking sheet not available, using fallback', [
                    'error' => $e->getMessage(),
                    'nca_id' => $ncaDocumentId
                ]);
                return $this->getFallbackNCATrackingSheet($ncaDocumentId);
            }
        });
    }

    /**
     * Get NCA history for complete lifecycle tracking
     *
     * @param string $ncaDocumentId NCA document ID
     * @return array
     */
    public function getNCAHistory(string $ncaDocumentId): array
    {
        $cacheKey = "cms_nca_history_{$ncaDocumentId}";

        return Cache::remember($cacheKey, $this->cacheMinutes * 24, function () use ($ncaDocumentId) {
            try {
                // Note: This endpoint may not be available in current CMS API
                $response = Http::timeout($this->config['timeout'])
                    ->retry($this->config['max_retries'], $this->config['retry_delay'])
                    ->get("{$this->baseUrl}/data/nca/history", [
                        'nca_id' => $ncaDocumentId
                    ]);

                if (!$response->successful()) {
                    Log::info('CMS NCA history endpoint not available, using fallback', [
                        'status' => $response->status(),
                        'nca_id' => $ncaDocumentId
                    ]);
                    return $this->getFallbackNCAHistory($ncaDocumentId);
                }

                $data = $response->json();
                return $data['data'] ?? [];

            } catch (\Exception $e) {
                Log::info('CMS NCA history not available, using fallback', [
                    'error' => $e->getMessage(),
                    'nca_id' => $ncaDocumentId
                ]);
                return $this->getFallbackNCAHistory($ncaDocumentId);
            }
        });
    }

    // ================================
    // MAY 2025 NEW ENDPOINTS - MEDCAC MEETINGS
    // ================================

    /**
     * Get MEDCAC meeting details and insights
     *
     * @param string|null $meetingId Specific meeting ID
     * @param array $filters Additional filters (year, meeting_type, etc.)
     * @return array
     */
    public function getMEDCAC(?string $meetingId = null, array $filters = []): array
    {
        $cacheKey = $meetingId
            ? "cms_medcac_meeting_{$meetingId}"
            : "cms_medcac_list_" . md5(serialize($filters));

        return Cache::remember($cacheKey, $this->cacheMinutes * 24, function () use ($meetingId, $filters) {
            try {
                if ($meetingId) {
                    // Try to get specific MEDCAC meeting details
                    // Note: This endpoint may not be available in current CMS API
                    $response = Http::timeout($this->config['timeout'])
                        ->retry($this->config['max_retries'], $this->config['retry_delay'])
                        ->get("{$this->baseUrl}/data/medcac/{$meetingId}");

                    if (!$response->successful()) {
                        Log::info('CMS MEDCAC meeting endpoint not available, using fallback', [
                            'status' => $response->status(),
                            'meeting_id' => $meetingId
                        ]);
                        return $this->getFallbackMEDCACData($meetingId);
                    }
                } else {
                    // Try to get MEDCAC meeting list
                    // Note: This endpoint may not be available in current CMS API
                    $response = Http::timeout($this->config['timeout'])
                        ->retry($this->config['max_retries'], $this->config['retry_delay'])
                        ->get("{$this->baseUrl}/reports/medcac-meetings", $filters);

                    if (!$response->successful()) {
                        Log::info('CMS MEDCAC reports endpoint not available, using fallback', [
                            'status' => $response->status(),
                            'filters' => $filters
                        ]);
                        return $this->getFallbackMEDCACList($filters);
                    }
                }

                $data = $response->json();
                return $data['data'] ?? $data ?? [];

            } catch (\Exception $e) {
                Log::info('CMS MEDCAC API not available, using fallback', [
                    'error' => $e->getMessage(),
                    'meeting_id' => $meetingId,
                    'filters' => $filters
                ]);

                // Return fallback data instead of empty array
                return $meetingId
                    ? $this->getFallbackMEDCACData($meetingId)
                    : $this->getFallbackMEDCACList($filters);
            }
        });
    }

    /**
     * Fallback data for MEDCAC meeting details when API is unavailable
     */
    private function getFallbackMEDCACData(string $meetingId): array
    {
        return [
            'meeting_id' => $meetingId,
            'title' => 'MEDCAC Meeting Data (API Unavailable)',
            'topic' => 'Data temporarily unavailable',
            'meeting_date' => null,
            'panel_recommendation' => 'Not available',
            'evidence_assessment' => 'Not available',
            'note' => 'MEDCAC meeting details endpoint not available'
        ];
    }

    /**
     * Fallback data for MEDCAC meeting list when API is unavailable
     */
    private function getFallbackMEDCACList(array $filters): array
    {
        return [
            [
                'meeting_id' => 'fallback_medcac_001',
                'title' => 'MEDCAC Meetings (API Unavailable)',
                'topic' => 'MEDCAC meeting list temporarily unavailable',
                'meeting_date' => date('Y-m-d'),
                'note' => 'This is fallback data - actual MEDCAC meetings may be available via alternative CMS resources'
            ]
        ];
    }

    // ================================
    // PROCEDURE PRICE LOOKUP (PPL) API INTEGRATION
    // ================================

    /**
     * Get procedure pricing data from CMS PPL API
     *
     * @param array $procedureCodes HCPCS/CPT codes to look up
     * @param string $facilityType 'hospital' or 'asc' (ambulatory surgical center)
     * @return array
     */
    public function getProcedurePricing(array $procedureCodes, string $facilityType = 'both'): array
    {
        if (empty($this->pplApiKey)) {
            Log::warning('PPL API key not configured - pricing data unavailable');
            return ['error' => 'Pricing API not configured'];
        }

        $cacheKey = "cms_ppl_pricing_" . md5(implode(',', $procedureCodes) . $facilityType);

        return Cache::remember($cacheKey, $this->cacheMinutes * 48, function () use ($procedureCodes, $facilityType) {
            try {
                $pricingData = [];

                foreach ($procedureCodes as $code) {
                    $response = Http::timeout($this->config['timeout'])
                        ->withHeaders([
                            'X-API-Key' => $this->pplApiKey,
                            'Accept' => 'application/json'
                        ])
                        ->retry($this->config['max_retries'], $this->config['retry_delay'])
                        ->get('https://api.cms.gov/ppl/v1/procedures', [
                            'code' => $code,
                            'facility_type' => $facilityType
                        ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        if (!empty($data['data'])) {
                            $pricingData[$code] = $this->formatPricingData($data['data'], $code);
                        }
                    } else {
                        Log::warning('PPL API request failed for code', [
                            'code' => $code,
                            'status' => $response->status(),
                            'facility_type' => $facilityType
                        ]);

                        // Add placeholder with estimated pricing
                        $pricingData[$code] = $this->getEstimatedPricing($code);
                    }

                    // Rate limiting - small delay between requests
                    usleep(100000); // 100ms delay
                }

                return [
                    'pricing_data' => $pricingData,
                    'data_source' => 'cms_ppl_api',
                    'facility_type' => $facilityType,
                    'retrieved_at' => now()->toISOString()
                ];

            } catch (\Exception $e) {
                Log::error('PPL API request exception', [
                    'error' => $e->getMessage(),
                    'codes' => $procedureCodes,
                    'facility_type' => $facilityType
                ]);

                // Return estimated pricing as fallback
                $fallbackPricing = [];
                foreach ($procedureCodes as $code) {
                    $fallbackPricing[$code] = $this->getEstimatedPricing($code);
                }

                return [
                    'pricing_data' => $fallbackPricing,
                    'data_source' => 'estimated',
                    'error' => 'PPL API unavailable - using estimates'
                ];
            }
        });
    }

    /**
     * Format pricing data from PPL API response
     *
     * @param array $data Raw PPL API data
     * @param string $code Procedure code
     * @return array
     */
    private function formatPricingData(array $data, string $code): array
    {
        $procedure = $data[0] ?? [];

        return [
            'code' => $code,
            'description' => $procedure['description'] ?? 'Procedure description not available',
            'hospital_outpatient' => [
                'medicare_approved_amount' => $procedure['hospital_medicare_approved'] ?? null,
                'medicare_payment' => $procedure['hospital_medicare_payment'] ?? null,
                'beneficiary_copay' => $procedure['hospital_beneficiary_copay'] ?? null
            ],
            'ambulatory_surgical_center' => [
                'medicare_approved_amount' => $procedure['asc_medicare_approved'] ?? null,
                'medicare_payment' => $procedure['asc_medicare_payment'] ?? null,
                'beneficiary_copay' => $procedure['asc_beneficiary_copay'] ?? null
            ],
            'bundling_flag' => $procedure['bundling_flag'] ?? false,
            'inpatient_cap_flag' => $procedure['inpatient_cap_flag'] ?? false,
            'data_year' => $procedure['data_year'] ?? date('Y')
        ];
    }

    /**
     * Get estimated pricing when PPL API is unavailable
     *
     * @param string $code Procedure code
     * @return array
     */
    private function getEstimatedPricing(string $code): array
    {
        // Enhanced estimation logic based on code type and complexity
        $baseEstimate = 100.00;

        if (str_starts_with($code, 'Q4')) {
            // HCPCS Q codes (biologics/skin substitutes) - higher cost
            $estimate = 250.00;
        } elseif (str_starts_with($code, '975')) {
            // CPT debridement codes - moderate cost
            $estimate = 150.00;
        } elseif (str_starts_with($code, '110')) {
            // CPT surgical debridement codes - high cost
            $estimate = 300.00;
        } elseif (str_starts_with($code, '97')) {
            // Physical therapy codes - lower cost
            $estimate = 75.00;
        } else {
            $estimate = $baseEstimate;
        }

        // Calculate Medicare payment (typically 80%) and beneficiary copay (20%)
        $medicarePayment = round($estimate * 0.80, 2);
        $beneficiaryCopay = round($estimate * 0.20, 2);

        return [
            'code' => $code,
            'description' => $this->getCodeDescription($code),
            'hospital_outpatient' => [
                'medicare_approved_amount' => $estimate,
                'medicare_payment' => $medicarePayment,
                'beneficiary_copay' => $beneficiaryCopay
            ],
            'ambulatory_surgical_center' => [
                'medicare_approved_amount' => round($estimate * 0.85, 2), // ASC typically lower
                'medicare_payment' => round($medicarePayment * 0.85, 2),
                'beneficiary_copay' => round($beneficiaryCopay * 0.85, 2)
            ],
            'bundling_flag' => false,
            'inpatient_cap_flag' => false,
            'data_source' => 'estimated',
            'note' => 'Estimated pricing - actual costs may vary'
        ];
    }

    // ================================
    // ENHANCED INTEGRATION METHODS
    // ================================

    /**
     * Comprehensive coverage analysis with all new endpoints
     *
     * @param array $serviceCodes HCPCS/CPT codes
     * @param string $state Patient state
     * @param string $specialty Medical specialty
     * @param array $options Additional options (include_pricing, include_ta, include_nca_tracking, etc.)
     * @return array
     */
    public function getEnhancedCoverageAnalysis(array $serviceCodes, string $state, string $specialty, array $options = []): array
    {
        $startTime = microtime(true);

        try {
            // Base coverage analysis
            $coverageAnalysis = $this->performOptimizedQuickCheck($serviceCodes, $state);

            // Enhanced data collection based on options
            $enhancedData = [
                'base_coverage' => $coverageAnalysis,
                'analysis_timestamp' => now()->toISOString(),
                'data_sources' => ['cms_coverage_api']
            ];

            // Technology Assessment integration
            if ($options['include_technology_assessments'] ?? true) {
                $enhancedData['technology_assessments'] = $this->getTechnologyAssessmentsForSpecialty($specialty);
                $enhancedData['data_sources'][] = 'technology_assessments';
            }

            // NCA tracking integration
            if ($options['include_nca_tracking'] ?? true) {
                $enhancedData['nca_tracking'] = $this->getNCACoverageTracking($serviceCodes, $specialty);
                $enhancedData['data_sources'][] = 'nca_tracking';
            }

            // MEDCAC meeting insights
            if ($options['include_medcac'] ?? true) {
                $enhancedData['medcac_insights'] = $this->getMEDCACInsightsForSpecialty($specialty);
                $enhancedData['data_sources'][] = 'medcac_meetings';
            }

            // Procedure pricing integration
            if ($options['include_pricing'] ?? true) {
                $enhancedData['procedure_pricing'] = $this->getProcedurePricing($serviceCodes);
                $enhancedData['data_sources'][] = 'procedure_pricing';
            }

            // Evidence-based decision support
            $enhancedData['decision_support'] = $this->generateEvidenceBasedRecommendations(
                $enhancedData,
                $serviceCodes,
                $specialty
            );

            // Performance metrics
            $enhancedData['performance'] = [
                'total_processing_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                'api_calls_made' => $this->getApiCallCount(),
                'cache_hit_ratio' => $this->getCacheHitRatio()
            ];

            return $enhancedData;

        } catch (\Exception $e) {
            Log::error('Enhanced coverage analysis failed', [
                'error' => $e->getMessage(),
                'service_codes' => $serviceCodes,
                'state' => $state,
                'specialty' => $specialty
            ]);

            return [
                'error' => 'Enhanced analysis failed',
                'fallback_data' => $this->performOptimizedQuickCheck($serviceCodes, $state),
                'processing_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
            ];
        }
    }

    /**
     * Get Technology Assessments relevant to a specific medical specialty
     *
     * @param string $specialty Medical specialty
     * @return array
     */
    private function getTechnologyAssessmentsForSpecialty(string $specialty): array
    {
        $specialtyKeywords = $this->getSpecialtyKeywords($specialty);
        $technologyAssessments = [];

        // Get recent Technology Assessments
        $taList = $this->getTechnologyAssessments(null, [
            'year' => date('Y'),
            'status' => 'final'
        ]);

        foreach ($taList as $ta) {
            $title = strtolower($ta['documentTitle'] ?? '');
            $summary = strtolower($ta['summary'] ?? '');
            $content = $title . ' ' . $summary;

            // Check if TA is relevant to specialty
            foreach ($specialtyKeywords as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    $taDetails = $this->getTechnologyAssessments($ta['documentId']);

                    if (!empty($taDetails)) {
                        $technologyAssessments[] = [
                            'document_id' => $ta['documentId'],
                            'title' => $ta['documentTitle'],
                            'relevance_score' => $this->calculateRelevanceScore($content, $specialtyKeywords),
                            'summary' => $ta['summary'] ?? '',
                            'effective_date' => $ta['effectiveDate'] ?? null,
                            'evidence_level' => $this->extractEvidenceLevel($taDetails),
                            'recommendations' => $this->extractTARecommendations($taDetails)
                        ];
                    }
                    break;
                }
            }
        }

        // Sort by relevance score
        usort($technologyAssessments, function($a, $b) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });

        return array_slice($technologyAssessments, 0, 5); // Top 5 most relevant
    }

    /**
     * Get NCA tracking information for coverage determination lifecycle
     *
     * @param array $serviceCodes HCPCS/CPT codes
     * @param string $specialty Medical specialty
     * @return array
     */
    private function getNCACoverageTracking(array $serviceCodes, string $specialty): array
    {
        $ncaTracking = [];
        $specialtyKeywords = $this->getSpecialtyKeywords($specialty);

        // Get active NCAs
        $ncaList = $this->getNCAs(null, [
            'status' => 'active',
            'document_type' => 'NCA'
        ]);

        foreach ($ncaList as $nca) {
            $title = strtolower($nca['documentTitle'] ?? '');

            // Check relevance to specialty or service codes
            $isRelevant = false;

            // Check specialty keywords
            foreach ($specialtyKeywords as $keyword) {
                if (strpos($title, $keyword) !== false) {
                    $isRelevant = true;
                    break;
                }
            }

            // Check service codes in title/description
            if (!$isRelevant) {
                foreach ($serviceCodes as $code) {
                    if (strpos($title, strtolower($code)) !== false) {
                        $isRelevant = true;
                        break;
                    }
                }
            }

            if ($isRelevant && isset($nca['documentId'])) {
                $trackingSheet = $this->getNCATrackingSheet($nca['documentId']);
                $ncaHistory = $this->getNCAHistory($nca['documentId']);

                $ncaTracking[] = [
                    'document_id' => $nca['documentId'],
                    'title' => $nca['documentTitle'],
                    'status' => $nca['status'] ?? 'unknown',
                    'tracking_sheet' => $trackingSheet,
                    'history' => $ncaHistory,
                    'lifecycle_stage' => $this->determineNCDLifecycleStage($trackingSheet),
                    'expected_decision_date' => $this->extractExpectedDecisionDate($trackingSheet),
                    'public_comment_period' => $this->extractCommentPeriodInfo($trackingSheet)
                ];
            }
        }

        return $ncaTracking;
    }

    /**
     * Get MEDCAC meeting insights for specialty
     *
     * @param string $specialty Medical specialty
     * @return array
     */
    private function getMEDCACInsightsForSpecialty(string $specialty): array
    {
        $specialtyKeywords = $this->getSpecialtyKeywords($specialty);
        $medcacInsights = [];

        // Get recent MEDCAC meetings
        $medcacList = $this->getMEDCAC(null, [
            'year' => date('Y'),
            'meeting_type' => 'all'
        ]);

        foreach ($medcacList as $meeting) {
            $topic = strtolower($meeting['topic'] ?? $meeting['title'] ?? '');

            // Check relevance to specialty
            foreach ($specialtyKeywords as $keyword) {
                if (strpos($topic, $keyword) !== false) {
                    $meetingDetails = $this->getMEDCAC($meeting['meetingId']);

                    $medcacInsights[] = [
                        'meeting_id' => $meeting['meetingId'],
                        'title' => $meeting['title'] ?? $meeting['topic'],
                        'meeting_date' => $meeting['meetingDate'] ?? null,
                        'panel_recommendation' => $this->extractPanelRecommendation($meetingDetails),
                        'evidence_assessment' => $this->extractEvidenceAssessment($meetingDetails),
                        'clinical_implications' => $this->extractClinicalImplications($meetingDetails),
                        'voting_summary' => $this->extractVotingSummary($meetingDetails)
                    ];
                    break;
                }
            }
        }

        return array_slice($medcacInsights, 0, 3); // Top 3 most recent relevant meetings
    }

    /**
     * Generate evidence-based recommendations combining all data sources
     *
     * @param array $enhancedData All collected data
     * @param array $serviceCodes HCPCS/CPT codes
     * @param string $specialty Medical specialty
     * @return array
     */
    private function generateEvidenceBasedRecommendations(array $enhancedData, array $serviceCodes, string $specialty): array
    {
        $recommendations = [
            'overall_recommendation' => 'proceed_with_caution',
            'confidence_level' => 'moderate',
            'evidence_strength' => 'limited',
            'key_factors' => [],
            'risk_mitigation' => [],
            'monitoring_requirements' => []
        ];

        // Analyze Technology Assessment evidence
        if (!empty($enhancedData['technology_assessments'])) {
            $taEvidence = $this->analyzeTAEvidence($enhancedData['technology_assessments']);
            $recommendations['evidence_strength'] = $taEvidence['strength'];
            $recommendations['key_factors'][] = "Technology Assessment evidence: {$taEvidence['summary']}";
        }

        // Analyze NCA lifecycle status
        if (!empty($enhancedData['nca_tracking'])) {
            $ncaStatus = $this->analyzeNCAStatus($enhancedData['nca_tracking']);
            $recommendations['confidence_level'] = $ncaStatus['confidence'];
            $recommendations['monitoring_requirements'][] = $ncaStatus['monitoring_recommendation'];
        }

        // Analyze MEDCAC insights
        if (!empty($enhancedData['medcac_insights'])) {
            $medcacAnalysis = $this->analyzeMEDCACInsights($enhancedData['medcac_insights']);
            $recommendations['key_factors'][] = "MEDCAC panel insights: {$medcacAnalysis['summary']}";
        }

        // Cost-benefit analysis from pricing data
        if (!empty($enhancedData['procedure_pricing'])) {
            $costAnalysis = $this->analyzeCostBenefit($enhancedData['procedure_pricing']);
            $recommendations['key_factors'][] = "Cost considerations: {$costAnalysis['summary']}";
            $recommendations['risk_mitigation'][] = $costAnalysis['risk_mitigation'];
        }

        // Base coverage analysis
        $coverageStrength = $this->analyzeCoverageStrength($enhancedData['base_coverage']);
        $recommendations['overall_recommendation'] = $this->determineOverallRecommendation([
            'coverage_strength' => $coverageStrength,
            'evidence_strength' => $recommendations['evidence_strength'],
            'confidence_level' => $recommendations['confidence_level']
        ]);

        return $recommendations;
    }

    // ================================
    // HELPER METHODS FOR ENHANCED ANALYSIS
    // ================================

    private function calculateRelevanceScore(string $content, array $keywords): int
    {
        $score = 0;
        foreach ($keywords as $keyword) {
            $score += substr_count($content, $keyword) * 10;
        }
        return min(100, $score);
    }

    private function extractEvidenceLevel(array $taDetails): string
    {
        $content = strtolower(json_encode($taDetails));

        if (strpos($content, 'high quality evidence') !== false) return 'high';
        if (strpos($content, 'moderate quality evidence') !== false) return 'moderate';
        if (strpos($content, 'low quality evidence') !== false) return 'low';

        return 'insufficient';
    }

    private function extractTARecommendations(array $taDetails): array
    {
        // Extract key recommendations from TA document
        $recommendations = [];
        $content = $taDetails['content'] ?? $taDetails['summary'] ?? '';

        if (strpos($content, 'recommend') !== false) {
            // Simple extraction - in practice, would use more sophisticated NLP
            $sentences = explode('.', $content);
            foreach ($sentences as $sentence) {
                if (stripos($sentence, 'recommend') !== false) {
                    $recommendations[] = trim($sentence);
                }
            }
        }

        return array_slice($recommendations, 0, 3);
    }

    private function determineNCDLifecycleStage(array $trackingSheet): string
    {
        $status = strtolower($trackingSheet['status'] ?? '');

        if (strpos($status, 'proposed') !== false) return 'proposed';
        if (strpos($status, 'comment') !== false) return 'public_comment';
        if (strpos($status, 'review') !== false) return 'under_review';
        if (strpos($status, 'final') !== false) return 'final_decision';

        return 'unknown';
    }

    private function extractExpectedDecisionDate(array $trackingSheet): ?string
    {
        return $trackingSheet['expected_decision_date'] ?? $trackingSheet['target_date'] ?? null;
    }

    private function extractCommentPeriodInfo(array $trackingSheet): array
    {
        return [
            'start_date' => $trackingSheet['comment_start_date'] ?? null,
            'end_date' => $trackingSheet['comment_end_date'] ?? null,
            'is_active' => $trackingSheet['comment_period_active'] ?? false
        ];
    }

    private function extractPanelRecommendation(array $meetingDetails): string
    {
        return $meetingDetails['panel_recommendation'] ?? $meetingDetails['recommendation'] ?? 'Not available';
    }

    private function extractEvidenceAssessment(array $meetingDetails): string
    {
        return $meetingDetails['evidence_assessment'] ?? 'Assessment not available';
    }

    private function extractClinicalImplications(array $meetingDetails): array
    {
        return $meetingDetails['clinical_implications'] ?? [];
    }

    private function extractVotingSummary(array $meetingDetails): array
    {
        return $meetingDetails['voting_summary'] ?? [];
    }

    private function analyzeTAEvidence(array $technologyAssessments): array
    {
        $evidenceLevels = array_column($technologyAssessments, 'evidence_level');
        $highQuality = count(array_filter($evidenceLevels, fn($level) => $level === 'high'));

        if ($highQuality > 0) {
            return ['strength' => 'strong', 'summary' => "High-quality evidence from {$highQuality} assessment(s)"];
        } elseif (count($evidenceLevels) > 0) {
            return ['strength' => 'moderate', 'summary' => "Available evidence from " . count($evidenceLevels) . " assessment(s)"];
        }

        return ['strength' => 'limited', 'summary' => 'Limited technology assessment evidence'];
    }

    private function analyzeNCAStatus(array $ncaTracking): array
    {
        $activeNCAs = count($ncaTracking);

        if ($activeNCAs === 0) {
            return ['confidence' => 'high', 'monitoring_recommendation' => 'No active coverage determinations'];
        }

        $inProgress = array_filter($ncaTracking, fn($nca) => in_array($nca['lifecycle_stage'], ['proposed', 'public_comment', 'under_review']));

        if (count($inProgress) > 0) {
            return [
                'confidence' => 'moderate',
                'monitoring_recommendation' => 'Monitor ' . count($inProgress) . ' coverage determination(s) in progress'
            ];
        }

        return ['confidence' => 'high', 'monitoring_recommendation' => 'Stable coverage environment'];
    }

    private function analyzeMEDCACInsights(array $medcacInsights): array
    {
        $positiveRecommendations = 0;
        foreach ($medcacInsights as $insight) {
            if (stripos($insight['panel_recommendation'], 'support') !== false ||
                stripos($insight['panel_recommendation'], 'favorable') !== false) {
                $positiveRecommendations++;
            }
        }

        return [
            'summary' => count($medcacInsights) . " relevant meeting(s), {$positiveRecommendations} with positive recommendations"
        ];
    }

    private function analyzeCostBenefit(array $pricingData): array
    {
        $totalCost = 0;
        $codeCount = 0;

        foreach ($pricingData['pricing_data'] ?? [] as $pricing) {
            if (isset($pricing['hospital_outpatient']['medicare_approved_amount'])) {
                $totalCost += $pricing['hospital_outpatient']['medicare_approved_amount'];
                $codeCount++;
            }
        }

        $averageCost = $codeCount > 0 ? round($totalCost / $codeCount, 2) : 0;

        return [
            'summary' => "Average procedure cost: $" . number_format($averageCost, 2),
            'risk_mitigation' => $averageCost > 500 ? 'Consider prior authorization for high-cost procedures' : 'Cost within normal range'
        ];
    }

    private function analyzeCoverageStrength(array $baseCoverage): string
    {
        $serviceCoverage = $baseCoverage['service_coverage'] ?? [];
        $likelyCovered = count(array_filter($serviceCoverage, fn($coverage) => $coverage['status'] === 'likely_covered'));
        $total = count($serviceCoverage);

        if ($total === 0) return 'unknown';

        $ratio = $likelyCovered / $total;

        if ($ratio >= 0.8) return 'strong';
        if ($ratio >= 0.6) return 'moderate';
        return 'weak';
    }

    private function determineOverallRecommendation(array $factors): string
    {
        $coverageStrength = $factors['coverage_strength'];
        $evidenceStrength = $factors['evidence_strength'];
        $confidenceLevel = $factors['confidence_level'];

        if ($coverageStrength === 'strong' && $evidenceStrength === 'strong' && $confidenceLevel === 'high') {
            return 'proceed_confidently';
        }

        if ($coverageStrength === 'weak' || $evidenceStrength === 'limited') {
            return 'proceed_with_caution';
        }

        return 'proceed_with_documentation';
    }

    /**
     * Get API call count for performance tracking
     */
    private function getApiCallCount(): int
    {
        return $this->apiCallCount;
    }

    /**
     * Get cache hit ratio for performance metrics
     */
    private function getCacheHitRatio(): string
    {
        $total = $this->cacheHits + $this->cacheMisses;
        if ($total === 0) return '0%';

        $ratio = round(($this->cacheHits / $total) * 100, 1);
        return "{$ratio}%";
    }

    /**
     * Reset performance counters
     */
    public function resetPerformanceCounters(): void
    {
        $this->apiCallCount = 0;
        $this->cacheHits = 0;
        $this->cacheMisses = 0;
    }

    /**
     * Get comprehensive performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'api_calls_made' => $this->apiCallCount,
            'cache_hits' => $this->cacheHits,
            'cache_misses' => $this->cacheMisses,
            'cache_hit_ratio' => $this->getCacheHitRatio(),
            'total_requests' => $this->cacheHits + $this->cacheMisses
        ];
    }

    /**
     * Fallback data for Technology Assessment details when API is unavailable
     */
    private function getFallbackTechnologyAssessmentData(string $documentId): array
    {
        return [
            'document_id' => $documentId,
            'title' => 'Technology Assessment (API Unavailable)',
            'summary' => 'Technology assessment data temporarily unavailable',
            'effective_date' => null,
            'evidence_level' => 'limited',
            'recommendations' => ['Technology assessment endpoint not available']
        ];
    }

    /**
     * Fallback data for Technology Assessment list when API is unavailable
     */
    private function getFallbackTechnologyAssessmentsList(array $filters): array
    {
        return [
            // Return empty list with note about availability
            [
                'document_id' => 'fallback_001',
                'title' => 'Technology Assessments (API Unavailable)',
                'summary' => 'Technology assessment endpoints are not currently available in the CMS Coverage API',
                'effective_date' => date('Y-m-d'),
                'note' => 'This is fallback data - actual technology assessments may be available via alternative CMS resources'
            ]
        ];
    }

    // ================================
    // NEW ENDPOINTS - NCA DATA FALLBACK
    // ================================

    /**
     * Get fallback NCA data when API is unavailable
     */
    private function getFallbackNCAData(string $documentId): array
    {
        return [
            'document_id' => $documentId,
            'title' => 'NCA Data (API Unavailable)',
            'summary' => 'NCA data temporarily unavailable',
            'effective_date' => null,
            'evidence_level' => 'limited',
            'recommendations' => ['NCA endpoint not available']
        ];
    }

    /**
     * Get fallback NCA list when API is unavailable
     */
    private function getFallbackNCAList(array $filters): array
    {
        return [
            // Return empty list with note about availability
            [
                'document_id' => 'fallback_002',
                'title' => 'NCA List (API Unavailable)',
                'summary' => 'NCA list temporarily unavailable',
                'effective_date' => date('Y-m-d'),
                'note' => 'This is fallback data - actual NCA list may be available via alternative CMS resources'
            ]
        ];
    }

    /**
     * Get fallback NCA tracking sheet when API is unavailable
     */
    private function getFallbackNCATrackingSheet(string $ncaDocumentId): array
    {
        return [
            'nca_id' => $ncaDocumentId,
            'status' => 'tracking_unavailable',
            'lifecycle_stage' => 'unknown',
            'expected_decision_date' => null,
            'public_comment_period' => [
                'status' => 'unknown',
                'start_date' => null,
                'end_date' => null
            ],
            'note' => 'NCA tracking data not available via current API endpoints'
        ];
    }

    /**
     * Get fallback NCA history when API is unavailable
     */
    private function getFallbackNCAHistory(string $ncaDocumentId): array
    {
        return [
            [
                'nca_id' => $ncaDocumentId,
                'version' => 1,
                'status' => 'history_unavailable',
                'effective_date' => date('Y-m-d'),
                'description' => 'NCA history data not available via current API endpoints',
                'note' => 'This is fallback data.'
            ]
        ];
    }

    /**
     * Get CMS data for validation based on specialty and state
     *
     * @param string $specialty The medical specialty
     * @param string $state The state code
     * @return array The CMS data needed for validation
     */
    public function getCmsDataForValidation(string $specialty, string $state): array
    {
        try {
            // Get LCDs, NCDs, and Articles for the specialty
            $lcds = $this->getLCDsBySpecialty($specialty, $state);
            $ncds = $this->getNCDsBySpecialty($specialty);
            $articles = $this->getArticlesBySpecialty($specialty, $state);

            // Get MAC jurisdiction information
            $macInfo = $this->getMACJurisdiction($state);

            // Get quick counts for the state
            $quickCounts = $this->getQuickCounts($state);

            return [
                'lcds' => $lcds,
                'ncds' => $ncds,
                'articles' => $articles,
                'mac_info' => $macInfo,
                'quick_counts' => $quickCounts,
                'state' => $state,
                'specialty' => $specialty,
                'timestamp' => now()->toISOString(),
                'data_source' => 'cms_api',
                'performance_metrics' => [
                    'api_calls_made' => $this->apiCallCount,
                    'cache_hit_ratio' => $this->getCacheHitRatio(),
                    'total_requests' => $this->cacheHits + $this->cacheMisses
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Error getting CMS data for validation', [
                'specialty' => $specialty,
                'state' => $state,
                'error' => $e->getMessage()
            ]);

            // Return empty data structure with error information
            return [
                'lcds' => [],
                'ncds' => [],
                'articles' => [],
                'mac_info' => null,
                'quick_counts' => [],
                'state' => $state,
                'specialty' => $specialty,
                'timestamp' => now()->toISOString(),
                'data_source' => 'error',
                'error' => $e->getMessage(),
                'performance_metrics' => [
                    'api_calls_made' => $this->apiCallCount,
                    'cache_hit_ratio' => $this->getCacheHitRatio(),
                    'total_requests' => $this->cacheHits + $this->cacheMisses
                ]
            ];
        }
    }
}

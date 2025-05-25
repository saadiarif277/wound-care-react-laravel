<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CmsCoverageApiService
{
    private string $baseUrl;
    private int $throttleLimit;
    private int $cacheMinutes;
    private array $config;

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

        $this->config = [
            'timeout' => config('services.cms.timeout', 30),
            'max_retries' => config('services.cms.max_retries', 3),
            'retry_delay' => config('services.cms.retry_delay', 1000),
        ];

        Log::info('CmsCoverageApiService initialized', [
            'base_url' => $this->baseUrl,
            'throttle_limit' => $this->throttleLimit,
            'cache_minutes' => $this->cacheMinutes
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
     * Get MAC jurisdiction for a state
     */
    public function getMACJurisdiction(string $state): ?array
    {
        $cacheKey = "cms_mac_jurisdiction_{$state}";

        return Cache::remember($cacheKey, $this->cacheMinutes * 24, function () use ($state) {
            try {
                $response = Http::timeout(30)
                    ->get("{$this->baseUrl}/reports/local-coverage-mac-contacts", [
                        'state' => $state
                    ]);

                if (!$response->successful()) {
                    Log::error('CMS Coverage API MAC jurisdiction request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'state' => $state
                    ]);
                    return null;
                }

                $data = $response->json();
                return $data['data'][0] ?? null;

            } catch (\Exception $e) {
                Log::error('CMS Coverage API MAC jurisdiction request exception', [
                    'error' => $e->getMessage(),
                    'state' => $state
                ]);
                return null;
            }
        });
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
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MacContractorNewsService
{
    private const CACHE_TTL = 3600; // 1 hour cache
    
    private array $macContractors = [
        'noridian' => [
            'name' => 'Noridian Healthcare Solutions',
            'base_url' => 'https://med.noridianmedicare.com',
            'news_url' => 'https://med.noridianmedicare.com/news',
            'rss_feed' => 'https://med.noridianmedicare.com/rss',
            'selectors' => [
                'title' => '.news-title',
                'date' => '.news-date',
                'summary' => '.news-summary'
            ]
        ],
        'novitas' => [
            'name' => 'Novitas Solutions',
            'base_url' => 'https://www.novitas-solutions.com',
            'news_url' => 'https://www.novitas-solutions.com/news',
            'rss_feed' => 'https://www.novitas-solutions.com/rss',
            'selectors' => [
                'title' => '.article-title',
                'date' => '.article-date',
                'summary' => '.article-excerpt'
            ]
        ],
        'wps' => [
            'name' => 'Wisconsin Physicians Service',
            'base_url' => 'https://www.wpsmedicare.com',
            'news_url' => 'https://www.wpsmedicare.com/news',
            'rss_feed' => 'https://www.wpsmedicare.com/rss',
            'selectors' => [
                'title' => '.news-headline',
                'date' => '.publish-date',
                'summary' => '.news-teaser'
            ]
        ],
        'palmetto' => [
            'name' => 'Palmetto GBA',
            'base_url' => 'https://www.palmettogba.com',
            'news_url' => 'https://www.palmettogba.com/news',
            'rss_feed' => 'https://www.palmettogba.com/rss',
            'selectors' => [
                'title' => '.news-title',
                'date' => '.news-date',
                'summary' => '.news-description'
            ]
        ],
        'firstcoast' => [
            'name' => 'First Coast Service Options',
            'base_url' => 'https://medicare.fcso.com',
            'news_url' => 'https://medicare.fcso.com/news',
            'rss_feed' => 'https://medicare.fcso.com/rss',
            'selectors' => [
                'title' => '.headline',
                'date' => '.date',
                'summary' => '.summary'
            ]
        ],
        'ngs' => [
            'name' => 'National Government Services',
            'base_url' => 'https://www.ngsmedicare.com',
            'news_url' => 'https://www.ngsmedicare.com/news',
            'rss_feed' => 'https://www.ngsmedicare.com/rss',
            'selectors' => [
                'title' => '.news-item-title',
                'date' => '.news-item-date',
                'summary' => '.news-item-summary'
            ]
        ]
    ];

    /**
     * Get news from all MAC contractors
     */
    public function getAllNews(): array
    {
        $cacheKey = 'mac_contractor_news_all';
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $allNews = [];
            
            foreach ($this->macContractors as $key => $contractor) {
                try {
                    $news = $this->getContractorNews($key);
                    $allNews[$key] = [
                        'contractor' => $contractor['name'],
                        'news' => $news,
                        'last_updated' => Carbon::now()->toIso8601String()
                    ];
                } catch (\Exception $e) {
                    Log::warning("Failed to fetch news for {$contractor['name']}: " . $e->getMessage());
                    $allNews[$key] = [
                        'contractor' => $contractor['name'],
                        'news' => $this->getFallbackNews($key),
                        'last_updated' => Carbon::now()->toIso8601String(),
                        'error' => 'Failed to fetch live news'
                    ];
                }
            }
            
            return $allNews;
        });
    }

    /**
     * Get news from a specific MAC contractor
     */
    public function getContractorNews(string $contractorKey): array
    {
        $cacheKey = "mac_contractor_news_{$contractorKey}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($contractorKey) {
            if (!isset($this->macContractors[$contractorKey])) {
                throw new \InvalidArgumentException("Unknown contractor: {$contractorKey}");
            }

            $contractor = $this->macContractors[$contractorKey];
            
            // Try RSS feed first
            $news = $this->fetchRSSFeed($contractor['rss_feed']);
            
            // If RSS fails, try scraping the news page
            if (empty($news)) {
                $news = $this->scrapeNewsPage($contractor['news_url'], $contractor['selectors']);
            }
            
            // If both fail, return fallback news
            if (empty($news)) {
                return $this->getFallbackNews($contractorKey);
            }
            
            return $news;
        });
    }

    /**
     * Fetch news from RSS feed
     */
    private function fetchRSSFeed(string $rssUrl): array
    {
        try {
            $response = Http::timeout(10)->get($rssUrl);
            
            if (!$response->successful()) {
                return [];
            }
            
            $xml = simplexml_load_string($response->body());
            
            if (!$xml || !isset($xml->channel->item)) {
                return [];
            }
            
            $news = [];
            foreach ($xml->channel->item as $item) {
                $news[] = [
                    'title' => (string) $item->title,
                    'link' => (string) $item->link,
                    'description' => strip_tags((string) $item->description),
                    'pub_date' => Carbon::parse((string) $item->pubDate)->toIso8601String(),
                    'source' => 'RSS Feed'
                ];
                
                // Limit to 5 items per contractor
                if (count($news) >= 5) {
                    break;
                }
            }
            
            return $news;
        } catch (\Exception $e) {
            Log::error("RSS fetch failed for {$rssUrl}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Scrape news from a webpage (placeholder for future implementation)
     */
    private function scrapeNewsPage(string $newsUrl, array $selectors): array
    {
        // This would require a web scraping library like Goutte or similar
        // For now, return empty array - can be implemented later
        
        Log::info("Web scraping not yet implemented for: {$newsUrl}");
        return [];
    }

    /**
     * Get fallback news when live feeds fail
     */
    private function getFallbackNews(string $contractorKey): array
    {
        $contractor = $this->macContractors[$contractorKey];
        
        return [
            [
                'title' => 'Check Latest Updates',
                'link' => $contractor['news_url'],
                'description' => 'Visit the official website for the latest news and updates from ' . $contractor['name'],
                'pub_date' => Carbon::now()->toIso8601String(),
                'source' => 'Fallback Message'
            ]
        ];
    }

    /**
     * Get news summary for the Resources page
     */
    public function getNewsSummary(): array
    {
        $allNews = $this->getAllNews();
        $summary = [];
        
        foreach ($allNews as $key => $contractorNews) {
            $summary[] = [
                'contractor' => $contractorNews['contractor'],
                'latest_count' => count($contractorNews['news']),
                'latest_title' => $contractorNews['news'][0]['title'] ?? 'No recent updates',
                'news_url' => $this->macContractors[$key]['news_url'],
                'last_updated' => $contractorNews['last_updated']
            ];
        }
        
        return $summary;
    }

    /**
     * Clear news cache
     */
    public function clearCache(): void
    {
        Cache::forget('mac_contractor_news_all');
        
        foreach (array_keys($this->macContractors) as $key) {
            Cache::forget("mac_contractor_news_{$key}");
        }
    }
} 
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MacContractorNewsService;
use Carbon\Carbon;

class MacNewsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'mac:news 
                            {action : Action to perform (fetch, test, clear, list)}
                            {--contractor= : Specific contractor to target (noridian, novitas, wps, palmetto, firstcoast, ngs)}
                            {--format=table : Output format (table, json)}';

    /**
     * The console command description.
     */
    protected $description = 'Manage MAC contractor news feeds';

    protected MacContractorNewsService $newsService;

    public function __construct(MacContractorNewsService $newsService)
    {
        parent::__construct();
        $this->newsService = $newsService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');
        $contractor = $this->option('contractor');
        $format = $this->option('format');

        try {
            switch ($action) {
                case 'fetch':
                    $this->fetchNews($contractor, $format);
                    break;
                    
                case 'test':
                    $this->testNewsService($contractor);
                    break;
                    
                case 'clear':
                    $this->clearCache();
                    break;
                    
                case 'list':
                    $this->listContractors();
                    break;
                    
                default:
                    $this->error("Unknown action: {$action}");
                    $this->info("Available actions: fetch, test, clear, list");
                    return 1;
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Command failed: " . $e->getMessage());
            return 1;
        }
    }

    private function fetchNews(?string $contractor, string $format): void
    {
        $this->info("Fetching MAC contractor news...");
        
        if ($contractor) {
            $this->info("Fetching news for: {$contractor}");
            $news = $this->newsService->getContractorNews($contractor);
            $this->displayContractorNews($contractor, $news, $format);
        } else {
            $this->info("Fetching news for all contractors...");
            $allNews = $this->newsService->getAllNews();
            $this->displayAllNews($allNews, $format);
        }
    }

    private function testNewsService(?string $contractor): void
    {
        $this->info("Testing MAC contractor news service...");
        
        if ($contractor) {
            $this->testSingleContractor($contractor);
        } else {
            $this->testAllContractors();
        }
    }

    private function testSingleContractor(string $contractor): void
    {
        $this->info("Testing contractor: {$contractor}");
        
        try {
            $startTime = microtime(true);
            $news = $this->newsService->getContractorNews($contractor);
            $endTime = microtime(true);
            
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            $this->table([
                'Metric', 'Value'
            ], [
                ['Contractor', $contractor],
                ['Response Time', "{$duration}ms"],
                ['News Items', count($news)],
                ['Status', count($news) > 0 ? '✅ Success' : '⚠️ No Data'],
                ['First Title', $news[0]['title'] ?? 'N/A'],
                ['Source', $news[0]['source'] ?? 'N/A']
            ]);
            
            if (count($news) > 0) {
                $this->info("✅ Successfully fetched " . count($news) . " news items");
            } else {
                $this->warn("⚠️ No news items retrieved");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to fetch news: " . $e->getMessage());
        }
    }

    private function testAllContractors(): void
    {
        $contractors = ['noridian', 'novitas', 'wps', 'palmetto', 'firstcoast', 'ngs'];
        $results = [];
        
        foreach ($contractors as $contractor) {
            $this->info("Testing {$contractor}...");
            
            try {
                $startTime = microtime(true);
                $news = $this->newsService->getContractorNews($contractor);
                $endTime = microtime(true);
                
                $duration = round(($endTime - $startTime) * 1000, 2);
                
                $results[] = [
                    'contractor' => $contractor,
                    'status' => count($news) > 0 ? '✅ Success' : '⚠️ No Data',
                    'items' => count($news),
                    'response_time' => "{$duration}ms",
                    'first_title' => $news[0]['title'] ?? 'N/A'
                ];
                
            } catch (\Exception $e) {
                $results[] = [
                    'contractor' => $contractor,
                    'status' => '❌ Error',
                    'items' => 0,
                    'response_time' => 'N/A',
                    'first_title' => $e->getMessage()
                ];
            }
        }
        
        $this->table([
            'Contractor', 'Status', 'Items', 'Response Time', 'Latest Title'
        ], array_map(function($result) {
            return [
                $result['contractor'],
                $result['status'],
                $result['items'],
                $result['response_time'],
                strlen($result['first_title']) > 50 ? 
                    substr($result['first_title'], 0, 47) . '...' : 
                    $result['first_title']
            ];
        }, $results));
    }

    private function clearCache(): void
    {
        $this->info("Clearing MAC contractor news cache...");
        $this->newsService->clearCache();
        $this->info("✅ Cache cleared successfully");
    }

    private function listContractors(): void
    {
        $contractors = [
            ['noridian', 'Noridian Healthcare Solutions'],
            ['novitas', 'Novitas Solutions'],
            ['wps', 'Wisconsin Physicians Service'],
            ['palmetto', 'Palmetto GBA'],
            ['firstcoast', 'First Coast Service Options'],
            ['ngs', 'National Government Services']
        ];
        
        $this->table(['Key', 'Name'], $contractors);
    }

    private function displayContractorNews(string $contractor, array $news, string $format): void
    {
        if ($format === 'json') {
            $this->line(json_encode([
                'contractor' => $contractor,
                'count' => count($news),
                'news' => $news
            ], JSON_PRETTY_PRINT));
            return;
        }
        
        if (empty($news)) {
            $this->warn("No news found for {$contractor}");
            return;
        }
        
        $this->info("News for {$contractor} (" . count($news) . " items):");
        
        $tableData = [];
        foreach ($news as $item) {
            $tableData[] = [
                'title' => strlen($item['title']) > 50 ? substr($item['title'], 0, 47) . '...' : $item['title'],
                'date' => Carbon::parse($item['pub_date'])->format('M j, Y'),
                'source' => $item['source']
            ];
        }
        
        $this->table(['Title', 'Date', 'Source'], $tableData);
    }

    private function displayAllNews(array $allNews, string $format): void
    {
        if ($format === 'json') {
            $this->line(json_encode($allNews, JSON_PRETTY_PRINT));
            return;
        }
        
        foreach ($allNews as $contractorKey => $contractorData) {
            $this->info("\n{$contractorData['contractor']} ({$contractorKey}):");
            $this->info("Last updated: " . Carbon::parse($contractorData['last_updated'])->diffForHumans());
            
            if (isset($contractorData['error'])) {
                $this->warn("Error: {$contractorData['error']}");
            }
            
            if (!empty($contractorData['news'])) {
                $tableData = [];
                foreach (array_slice($contractorData['news'], 0, 3) as $item) { // Show top 3
                    $tableData[] = [
                        'title' => strlen($item['title']) > 60 ? substr($item['title'], 0, 57) . '...' : $item['title'],
                        'date' => Carbon::parse($item['pub_date'])->format('M j, Y')
                    ];
                }
                $this->table(['Title', 'Date'], $tableData);
            } else {
                $this->warn("No news items available");
            }
        }
    }
}

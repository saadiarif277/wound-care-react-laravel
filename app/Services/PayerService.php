<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PayerService
{
    /**
     * Get all payers from CSV file
     * 
     * @return Collection
     */
    public function getAllPayers(): Collection
    {
        return Cache::remember('payers_list', 3600, function () {
            $csvPath = base_path('docs/data-and-reference/payers.csv');
            
            if (!file_exists($csvPath)) {
                Log::error('Payers CSV file not found at: ' . $csvPath);
                return collect();
            }
            
            $uniquePayers = [];
            $rowCount = 0;
            
            if (($handle = fopen($csvPath, 'r')) !== false) {
                // Skip header row
                $header = fgetcsv($handle);
                
                while (($data = fgetcsv($handle)) !== false) {
                    $rowCount++;
                    if (count($data) >= 2) {
                        $payerName = trim($data[0] ?? '');
                        $payerId = trim($data[1] ?? '');
                        
                        // Skip empty entries
                        if (empty($payerName) || empty($payerId)) {
                            continue;
                        }
                        
                        // Use combination of name and ID as key to handle duplicates
                        $key = strtolower($payerName . '|' . $payerId);
                        
                        if (!isset($uniquePayers[$key])) {
                            $uniquePayers[$key] = [
                                'name' => $payerName,
                                'payer_id' => $payerId,
                                'display' => $payerName . ' (' . $payerId . ')'
                            ];
                        }
                    }
                }
                
                fclose($handle);
                
                Log::info('Loaded payers from CSV', [
                    'total_rows' => $rowCount,
                    'unique_payers' => count($uniquePayers)
                ]);
            }
            
            // Convert to collection and sort by name
            $payers = collect(array_values($uniquePayers))
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();
            
            return $payers;
        });
    }
    
    /**
     * Search payers by name or ID
     * 
     * @param string $query
     * @param int $limit
     * @return Collection
     */
    public function searchPayers(string $query, int $limit = 50): Collection
    {
        $allPayers = $this->getAllPayers();
        
        if (empty($query)) {
            return $allPayers->take($limit);
        }
        
        $query = strtolower(trim($query));
        
        // First, try to find exact matches or matches that start with the query
        $exactMatches = $allPayers->filter(function ($payer) use ($query) {
            $nameStarts = str_starts_with(strtolower($payer['name']), $query);
            $idStarts = str_starts_with(strtolower($payer['payer_id']), $query);
            return $nameStarts || $idStarts;
        });
        
        // Then find contains matches
        $containsMatches = $allPayers->filter(function ($payer) use ($query) {
            $nameContains = str_contains(strtolower($payer['name']), $query);
            $idContains = str_contains(strtolower($payer['payer_id']), $query);
            return ($nameContains || $idContains) && 
                   !str_starts_with(strtolower($payer['name']), $query) && 
                   !str_starts_with(strtolower($payer['payer_id']), $query);
        });
        
        // Combine results, prioritizing starts-with matches
        return $exactMatches
            ->concat($containsMatches)
            ->take($limit)
            ->values();
    }
    
    /**
     * Get payer by exact payer ID
     * 
     * @param string $payerId
     * @return array|null
     */
    public function getPayerById(string $payerId): ?array
    {
        return $this->getAllPayers()
            ->firstWhere('payer_id', $payerId);
    }
    
    /**
     * Clear payers cache
     */
    public function clearCache(): void
    {
        Cache::forget('payers_list');
    }
}
<?php

namespace App\Console\Commands;

use App\Services\PayerService;
use Illuminate\Console\Command;

class ClearPayersCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payers:clear-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear the payers list cache';

    /**
     * Execute the console command.
     */
    public function handle(PayerService $payerService)
    {
        $payerService->clearCache();
        
        $this->info('Payers cache cleared successfully.');
        
        // Reload the payers to warm the cache
        $this->info('Reloading payers data...');
        $payers = $payerService->getAllPayers();
        
        $this->info('Loaded ' . $payers->count() . ' unique payers from CSV.');
        
        return Command::SUCCESS;
    }
}
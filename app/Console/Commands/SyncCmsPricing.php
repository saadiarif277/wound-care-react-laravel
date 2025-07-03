<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCmsPricing extends Command
{
    protected $signature = 'cms:sync-pricing {--dry-run : Show deprecation notice} {--force : Skip confirmation prompts}';
    protected $description = '[DEPRECATED] This command is no longer used. ASP and MUE values are now managed through the product management interface.';

    public function handle(): int
    {
        $this->warn('⚠️  This command is deprecated!');
        $this->info('');
        $this->info('ASP (Average Sales Price) and MUE (Maximum Units of Eligibility) values are now managed through the product management interface.');
        $this->info('');
        $this->info('To update pricing information:');
        $this->info('1. Go to the Products section in the admin panel');
        $this->info('2. Edit the product you want to update');
        $this->info('3. Update the National ASP and MUE fields');
        $this->info('4. Save the product');
        $this->info('');
        $this->info('All pricing changes are automatically tracked in the pricing history for audit purposes.');
        
        Log::warning('Deprecated cms:sync-pricing command was called', [
            'user' => auth()->user()?->email ?? 'console',
            'timestamp' => now()
        ]);

        return Command::SUCCESS;
    }
}
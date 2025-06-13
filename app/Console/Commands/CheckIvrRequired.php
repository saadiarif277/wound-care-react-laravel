<?php

namespace App\Console\Commands;

use App\Models\Order\ProductRequest;
use Illuminate\Console\Command;

class CheckIvrRequired extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:ivr-required';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check ivr_required values in product requests';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking ivr_required values in product requests...');

        // Bypass global scopes to see all data
        $productRequests = ProductRequest::withoutGlobalScopes()->get();

        if ($productRequests->isEmpty()) {
            $this->warn('No product requests found.');
            return;
        }

        $this->info('Found ' . $productRequests->count() . ' product requests:');

        $this->table(
            ['ID', 'ivr_required', 'ivr_required_type', 'order_status', 'isIvrRequired()'],
            $productRequests->map(function ($pr) {
                return [
                    $pr->id,
                    var_export($pr->ivr_required, true),
                    gettype($pr->ivr_required),
                    $pr->order_status,
                    var_export($pr->isIvrRequired(), true),
                ];
            })->toArray()
        );

        // Test finding a specific record
        $firstId = $productRequests->first()->id;
        $this->info("Testing find by ID {$firstId}:");
        $this->info("- With global scope: " . (ProductRequest::find($firstId) ? 'Found' : 'Not found'));
        $this->info("- Without global scope: " . (ProductRequest::withoutGlobalScopes()->find($firstId) ? 'Found' : 'Not found'));
    }
}

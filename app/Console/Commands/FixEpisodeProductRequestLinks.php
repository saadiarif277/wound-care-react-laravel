<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PatientIVRStatus;
use App\Models\Order\ProductRequest;
use App\Models\Order\Manufacturer;

class FixEpisodeProductRequestLinks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'episode:fix-links {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix existing episode data by linking product requests to episodes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info('🔧 Fixing episode product request links...');

        // Get all episodes
        $episodes = PatientIVRStatus::with('manufacturer')->get();
        $this->info("📋 Found {$episodes->count()} episodes to process");

        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($episodes as $episode) {
            $this->info("\n📌 Processing Episode: {$episode->id}");
            $this->info("   Patient ID: {$episode->patient_id}");
            $this->info("   Patient Display ID: {$episode->patient_display_id}");
            $this->info("   Manufacturer: " . ($episode->manufacturer->name ?? 'Unknown'));

            // Find product requests that match this episode's patient and manufacturer
            $matchingRequests = ProductRequest::where('patient_fhir_id', $episode->patient_id)
                ->whereNull('ivr_episode_id') // Only unlinked requests
                ->get();

            // Filter by manufacturer if we can determine it from the product requests
            $manufacturerFilteredRequests = $matchingRequests->filter(function($request) use ($episode) {
                // Try to get manufacturer from products in the request
                $products = $request->products;
                if ($products->isEmpty()) {
                    return false;
                }

                // Check if any product belongs to this episode's manufacturer
                foreach ($products as $product) {
                    if ($product->manufacturer_id == $episode->manufacturer_id) {
                        return true;
                    }
                }
                return false;
            });

            if ($manufacturerFilteredRequests->isEmpty()) {
                $this->warn("   ⚠️  No matching product requests found for this episode");
                $skippedCount++;
                continue;
            }

            $this->info("   ✅ Found {$manufacturerFilteredRequests->count()} matching product requests");

            if (!$dryRun) {
                // Update the product requests to link to this episode
                $requestIds = $manufacturerFilteredRequests->pluck('id')->toArray();
                ProductRequest::whereIn('id', $requestIds)
                    ->update(['ivr_episode_id' => $episode->id]);

                $this->info("   🔗 Linked {$manufacturerFilteredRequests->count()} product requests to episode");
                $updatedCount += $manufacturerFilteredRequests->count();
            } else {
                $this->info("   🔗 Would link {$manufacturerFilteredRequests->count()} product requests to episode");
                foreach ($manufacturerFilteredRequests as $request) {
                    $this->info("      - Request {$request->id} ({$request->request_number})");
                }
            }
        }

        $this->info("\n📊 Summary:");
        if ($dryRun) {
            $this->info("   🔍 DRY RUN - No changes made");
            $this->info("   📋 Episodes processed: {$episodes->count()}");
            $this->info("   ⚠️  Episodes skipped: {$skippedCount}");
        } else {
            $this->info("   ✅ Product requests updated: {$updatedCount}");
            $this->info("   📋 Episodes processed: {$episodes->count()}");
            $this->info("   ⚠️  Episodes skipped: {$skippedCount}");
        }

        $this->info("\n🎉 Episode link fixing complete!");

        return Command::SUCCESS;
    }
}

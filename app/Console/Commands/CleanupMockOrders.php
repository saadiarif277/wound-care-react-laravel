<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order\ProductRequest;
use App\Models\PatientManufacturerIVREpisode;
use Illuminate\Support\Facades\DB;

class CleanupMockOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:mock-orders {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up mock/test orders and episodes from the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('DRY RUN MODE - No data will be deleted');
        }

        $this->info('Cleaning up mock orders and episodes...');

        // Find mock product requests
        $mockProductRequests = ProductRequest::where(function ($query) {
            $query->where('request_number', 'like', 'TEST-%')
                  ->orWhere('request_number', 'like', 'PR-%')
                  ->orWhere('patient_display_id', 'like', 'TP%')
                  ->orWhere('patient_display_id', 'like', 'JoSm%')
                  ->orWhere('patient_display_id', 'like', 'MaJo%')
                  ->orWhere('patient_display_id', 'like', 'P0%')
                  ->orWhere('patient_fhir_id', 'like', 'test-patient-%')
                  ->orWhere('order_number', 'like', 'REQ-%')
                  ->orWhere('order_number', 'like', 'ORD-%');
        })->get();

        // Find mock episodes
        $mockEpisodes = PatientManufacturerIVREpisode::where(function ($query) {
            $query->where('patient_display_id', 'like', 'P0%')
                  ->orWhere('patient_display_id', 'like', 'PAT%')
                  ->orWhere('patient_fhir_id', 'like', 'FHIR-%')
                  ->orWhereJsonContains('metadata->created_by', 'seeder');
        })->get();

        $this->info("Found {$mockProductRequests->count()} mock product requests");
        $this->info("Found {$mockEpisodes->count()} mock episodes");

        if ($mockProductRequests->count() > 0) {
            $this->table(
                ['ID', 'Request Number', 'Patient ID', 'Order Status', 'Created At'],
                $mockProductRequests->take(10)->map(function ($request) {
                    return [
                        $request->id,
                        $request->request_number,
                        $request->patient_display_id,
                        $request->order_status,
                        $request->created_at?->format('Y-m-d H:i:s') ?? 'N/A'
                    ];
                })->toArray()
            );
            
            if ($mockProductRequests->count() > 10) {
                $this->info("... and " . ($mockProductRequests->count() - 10) . " more");
            }
        }

        if ($mockEpisodes->count() > 0) {
            $this->table(
                ['ID', 'Patient ID', 'Patient Display ID', 'Status', 'Created At'],
                $mockEpisodes->take(10)->map(function ($episode) {
                    return [
                        substr($episode->id, 0, 8) . '...',
                        substr($episode->patient_id, 0, 8) . '...',
                        $episode->patient_display_id,
                        $episode->status,
                        $episode->created_at?->format('Y-m-d H:i:s') ?? 'N/A'
                    ];
                })->toArray()
            );
            
            if ($mockEpisodes->count() > 10) {
                $this->info("... and " . ($mockEpisodes->count() - 10) . " more");
            }
        }

        if ($mockProductRequests->count() === 0 && $mockEpisodes->count() === 0) {
            $this->info('No mock data found to clean up.');
            return 0;
        }

        if (!$dryRun) {
            if (!$this->confirm('Are you sure you want to delete all this mock data?')) {
                $this->info('Cleanup cancelled.');
                return 0;
            }

            DB::beginTransaction();

            try {
                // Delete product requests and their relationships
                $deletedRequests = 0;
                foreach ($mockProductRequests as $request) {
                    // Delete pivot table entries first
                    $request->products()->detach();
                    $request->delete();
                    $deletedRequests++;
                }

                // Delete episodes
                $deletedEpisodes = 0;
                foreach ($mockEpisodes as $episode) {
                    $episode->delete();
                    $deletedEpisodes++;
                }

                DB::commit();

                $this->info("Successfully deleted {$deletedRequests} mock product requests");
                $this->info("Successfully deleted {$deletedEpisodes} mock episodes");
                $this->info('Mock data cleanup completed!');

            } catch (\Exception $e) {
                DB::rollback();
                $this->error('Error during cleanup: ' . $e->getMessage());
                return 1;
            }
        } else {
            $this->info('DRY RUN completed. Use without --dry-run to actually delete the data.');
        }

        return 0;
    }
}

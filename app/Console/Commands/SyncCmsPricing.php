<?php

namespace App\Console\Commands;

use App\Models\Order\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncCmsPricing extends Command
{
    protected $signature = 'cms:sync-pricing {--dry-run : Show what would be updated without making changes} {--force : Skip confirmation prompts}';
    protected $description = 'Fetch and apply CMS ASP/MUE pricing for known Q-codes in product catalog';

    /**
     * CMS ASP/MUE hardcoded data table (as of Q2 2025)
     * This would typically be loaded from CMS APIs or CSV files
     */
    private array $cmsData = [
        'Q4154' => ['asp' => 550.64, 'mue' => 36],
        'Q4262' => ['asp' => 169.86, 'mue' => 300],
        'Q4164' => ['asp' => 322.15, 'mue' => 200],
        'Q4274' => ['asp' => 1838.29, 'mue' => null],
        'Q4275' => ['asp' => 2676.5, 'mue' => null],
        'Q4253' => ['asp' => 71.49, 'mue' => 300],
        'Q4276' => ['asp' => 464.34, 'mue' => 300],
        'Q4271' => ['asp' => 1399.12, 'mue' => 300],
        'Q4281' => ['asp' => 560.29, 'mue' => 200],
        'Q4236' => ['asp' => 482.71, 'mue' => 200],
        'Q4205' => ['asp' => 1055.97, 'mue' => 480],
        'Q4290' => ['asp' => 1841, 'mue' => 480],
        'Q4265' => ['asp' => 1750.26, 'mue' => 180],
        'Q4267' => ['asp' => 274.6, 'mue' => 180],
        'Q4266' => ['asp' => 989.67, 'mue' => 180],
        'Q4191' => ['asp' => 940.15, 'mue' => 120],
        'Q4217' => ['asp' => 273.51, 'mue' => 486],
        'Q4302' => ['asp' => 2008.7, 'mue' => 300],
        'Q4310' => ['asp' => 2213.13, 'mue' => 4],
        'Q4289' => ['asp' => 1602.22, 'mue' => 300],
        'Q4250' => ['asp' => 2863.13, 'mue' => 250],
        'Q4303' => ['asp' => 3397.4, 'mue' => 300],
        'Q4270' => ['asp' => 3370.8, 'mue' => null],
        'Q4234' => ['asp' => 247.91, 'mue' => 120],
        'Q4186' => ['asp' => 158.34, 'mue' => null],
        'Q4187' => ['asp' => 2479.11, 'mue' => null], // Corrected value
        'Q4239' => ['asp' => 2349.92, 'mue' => null],
        'Q4268' => ['asp' => 2862, 'mue' => null],
        'Q4298' => ['asp' => 2279, 'mue' => 180],
        'Q4299' => ['asp' => 2597, 'mue' => 180],
        'Q4294' => ['asp' => 2650, 'mue' => 180],
        'Q4295' => ['asp' => 2332, 'mue' => 180],
        'Q4227' => ['asp' => 1192.5, 'mue' => 180],
        'Q4193' => ['asp' => 1608.27, 'mue' => 180],
        'Q4238' => ['asp' => 1644.99, 'mue' => 128],
        'Q4263' => ['asp' => 1712.99, 'mue' => null],
        'Q4280' => ['asp' => 3246.5, 'mue' => 200],
        'Q4313' => ['asp' => 3337.23, 'mue' => 99],
        'Q4347' => ['asp' => 2850, 'mue' => null],
        'A2005' => ['asp' => 239, 'mue' => null],
    ];

    public function handle(): int
    {
        $this->info('🔄 Starting CMS ASP/MUE pricing sync...');

        // Get all products with Q-codes
        $products = Product::whereNotNull('q_code')
            ->where('q_code', '!=', '')
            ->get();

        if ($products->isEmpty()) {
            $this->warn('⚠️  No products with Q-codes found in catalog.');
            return Command::SUCCESS;
        }

        $this->info("📊 Found {$products->count()} products with Q-codes");

        $updated = 0;
        $skipped = 0;
        $changes = [];

        foreach ($products as $product) {
            $qcode = $this->normalizeQCode($product->q_code);

            if (!isset($this->cmsData[$qcode])) {
                $this->warn("⚠️  No CMS data for Q-code: {$qcode} (Product: {$product->name})");
                $skipped++;
                continue;
            }

            $cmsInfo = $this->cmsData[$qcode];
            $hasChanges = false;
            $productChanges = [];

            // Check ASP changes
            if ($cmsInfo['asp'] !== null && $product->national_asp != $cmsInfo['asp']) {
                $productChanges['national_asp'] = [
                    'old' => $product->national_asp,
                    'new' => $cmsInfo['asp']
                ];
                $hasChanges = true;
            }

            // Check MUE changes
            if ($product->mue != $cmsInfo['mue']) {
                $productChanges['mue'] = [
                    'old' => $product->mue,
                    'new' => $cmsInfo['mue']
                ];
                $hasChanges = true;
            }

            if ($hasChanges) {
                $changes[] = [
                    'product' => $product,
                    'qcode' => $qcode,
                    'changes' => $productChanges
                ];

                if (!$this->option('dry-run')) {
                    $product->update([
                        'national_asp' => $cmsInfo['asp'],
                        'mue' => $cmsInfo['mue'],
                        'cms_last_updated' => now()
                    ]);
                }

                $updated++;
            }
        }

        // Display results
        $this->displayResults($changes, $updated, $skipped);

        if ($this->option('dry-run')) {
            $this->info('🧪 Dry run completed - no changes were made');
        } else {
            $this->info("✅ CMS pricing sync completed");
            Log::info('CMS pricing sync completed', [
                'updated_products' => $updated,
                'skipped_products' => $skipped,
                'total_products' => $products->count()
            ]);
        }

        return Command::SUCCESS;
    }

    private function displayResults(array $changes, int $updated, int $skipped): void
    {
        if (!empty($changes)) {
            $this->info("\n📋 Changes Summary:");
            $this->table(
                ['Product', 'Q-Code', 'Field', 'Old Value', 'New Value'],
                collect($changes)->flatMap(function ($change) {
                    $rows = [];
                    foreach ($change['changes'] as $field => $values) {
                        $rows[] = [
                            $change['product']->name,
                            $change['qcode'],
                            $field,
                            $values['old'] ?? 'null',
                            $values['new'] ?? 'null'
                        ];
                    }
                    return $rows;
                })->toArray()
            );
        }

        $this->info("\n📊 Summary:");
        $this->line("• Updated products: {$updated}");
        $this->line("• Skipped products: {$skipped}");
        $this->line("• Total processed: " . ($updated + $skipped));
    }

    private function normalizeQCode(string $qcode): string
    {
        // Remove any prefixes and normalize to standard format
        $normalized = strtoupper(trim($qcode));

        // Handle cases where Q-code might be stored as just numbers
        if (is_numeric($normalized)) {
            $normalized = 'Q' . $normalized;
        }

        return $normalized;
    }

    /**
     * Fetch CMS data from actual CMS sources (future enhancement)
     */
    private function fetchCmsData(): array
    {
        // Future implementation would fetch from:
        // - CMS ASP pricing files
        // - CMS MUE tables
        // - Other authoritative sources

        return $this->cmsData;
    }
}

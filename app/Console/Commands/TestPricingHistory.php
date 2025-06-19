<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order\Product;
use App\Models\ProductPricingHistory;

class TestPricingHistory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:pricing-history';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the historical pricing tracking functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Historical Pricing Tracking...');

        // Find Complete FT product
        $product = Product::where('q_code', 'Q4271')->first();

        if (!$product) {
            $this->error('Product Q4271 not found!');
            return 1;
        }

        $this->info("📦 Testing with: " . $product->name . " (" . $product->q_code . ")");
        $this->info("💰 Current ASP: $" . $product->national_asp);

        // Show current history count
        $historyCount = ProductPricingHistory::where('product_id', $product->id)->count();
        $this->info("📊 Current history records: {$historyCount}");

        // Update the price to trigger historical tracking
        $oldPrice = $product->national_asp;
        $newPrice = 1500.00;

        $this->info("🔄 Updating ASP from $" . $oldPrice . " to $" . $newPrice . "...");

        $product->update(['national_asp' => $newPrice]);

        // Check new history count
        $newHistoryCount = ProductPricingHistory::where('product_id', $product->id)->count();
        $this->info("📊 New history records: {$newHistoryCount}");

        // Show the latest history record
        $latestHistory = ProductPricingHistory::where('product_id', $product->id)
            ->latest()
            ->first();

        if ($latestHistory) {
            $this->info("📝 Latest history record:");
            $this->info("   - Change Type: {$latestHistory->change_type}");
            $this->info("   - Changed Fields: " . implode(', ', $latestHistory->changed_fields));
            $previousValues = $latestHistory->previous_values;
            $this->info("   - Previous ASP: $" . ($previousValues['national_asp'] ?? 'N/A'));
            $this->info("   - New ASP: $" . $latestHistory->national_asp);
            $this->info("   - Reason: {$latestHistory->change_reason}");
            $this->info("   - Created: {$latestHistory->created_at}");
        }

        // Show product sizes
        $this->info("📏 Available sizes:");
        foreach ($product->sizes as $size) {
            $this->info("   - {$size->size_label} ({$size->area_cm2} cm²)");
        }

        $this->info('✅ Historical pricing tracking test completed!');

        return 0;
    }
}

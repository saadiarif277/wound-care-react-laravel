<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Order\Product;
use App\Models\ProductPricingHistory;

return new class extends Migration
{
    /**
     * ASP values from hard-coded data in CmsEnrichmentService.php
     * These values will be populated into the database and recorded in pricing history
     */
    private array $aspData = [
        'Q4154' => 550.64,
        'Q4262' => 169.86,
        'Q4164' => 322.15,
        'Q4274' => 1838.29,
        'Q4275' => 2676.5,
        'Q4253' => 71.49,
        'Q4276' => 464.34,
        'Q4271' => 1399.12,
        'Q4281' => 560.29,
        'Q4236' => 482.71,
        'Q4205' => 1055.97,
        'Q4290' => 1841,
        'Q4265' => 1750.26,
        'Q4267' => 274.6,
        'Q4266' => 989.67,
        'Q4191' => 940.15,
        'Q4217' => 273.51,
        'Q4302' => 2008.7,
        'Q4310' => 2213.13,
        'Q4289' => 1602.22,
        'Q4250' => 2863.13,
        'Q4303' => 3397.4,
        'Q4270' => 3370.8,
        'Q4234' => 247.91,
        'Q4186' => 158.34,
        'Q4187' => 2479.11,
        'Q4239' => 2349.92,
        'Q4268' => 2862,
        'Q4298' => 2279,
        'Q4299' => 2597,
        'Q4294' => 2650,
        'Q4295' => 2332,
        'Q4227' => 1192.5,
        'Q4193' => 1608.27,
        'Q4238' => 1644.99,
        'Q4263' => 1712.99,
        'Q4280' => 3246.5,
        'Q4313' => 3337.23,
        'Q4347' => 2850,
        'A2005' => 239,
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $updatedCount = 0;
        $historyCreated = 0;

        // Update ASP values for all products matching the Q-codes
        foreach ($this->aspData as $qCode => $aspValue) {
            $products = Product::where('q_code', $qCode)->get();
            
            foreach ($products as $product) {
                // Record the previous value for history
                $previousAsp = $product->national_asp;
                
                // Update the product
                $product->update([
                    'national_asp' => $aspValue,
                    'cms_last_updated' => now()
                ]);
                
                // Create pricing history record
                ProductPricingHistory::create([
                    'product_id' => $product->id,
                    'q_code' => $product->q_code,
                    'product_name' => $product->name,
                    'national_asp' => $aspValue,
                    'price_per_sq_cm' => $product->price_per_sq_cm,
                    'msc_price' => $product->msc_price,
                    'commission_rate' => $product->commission_rate,
                    'mue' => $product->mue,
                    'change_type' => 'migration',
                    'changed_by_type' => 'system',
                    'changed_by_id' => null,
                    'changed_fields' => ['national_asp'],
                    'previous_values' => ['national_asp' => $previousAsp],
                    'change_reason' => 'Initial ASP population from hard-coded CMS data',
                    'effective_date' => now(),
                    'cms_sync_date' => now(),
                    'source' => 'migration',
                    'metadata' => [
                        'migration' => 'populate_asp_values_from_hardcoded_data',
                        'previous_asp' => $previousAsp
                    ],
                ]);
                
                $updatedCount++;
                $historyCreated++;
            }
        }

        // Log the update
        echo "Updated ASP values for {$updatedCount} products.\n";
        echo "Created {$historyCreated} pricing history records.\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove pricing history records created by this migration
        ProductPricingHistory::where('change_type', 'migration')
            ->where('metadata->migration', 'populate_asp_values_from_hardcoded_data')
            ->delete();
            
        // Note: We don't reverse the ASP values as we don't know what they were before
        echo "Removed pricing history records created by this migration.\n";
        echo "Note: ASP values have not been reverted as previous values are unknown.\n";
    }
};
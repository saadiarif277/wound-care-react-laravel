<?php

namespace App\Services;

use App\Models\Order\Product;
use Illuminate\Support\Facades\Log;

class CmsEnrichmentService
{
    /**
     * CMS reimbursement data structure
     */
    private array $cmsReimbursementTable = [
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
        'Q4187' => ['asp' => 2479.11, 'mue' => null],
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

    /**
     * Get CMS reimbursement data for a Q-code
     * Equivalent to the TypeScript getCmsReimbursement function
     */
    public function getCmsReimbursement(string $qcode): array
    {
        $normalized = $this->normalizeQCode($qcode);

        if (!isset($this->cmsReimbursementTable[$normalized])) {
            Log::warning("CMS lookup failed for unknown QCode: {$normalized}");
            return ['asp' => null, 'mue' => null];
        }

        return $this->cmsReimbursementTable[$normalized];
    }

    /**
     * Normalize Q-code to standard format
     */
    public function normalizeQCode(string $qcode): string
    {
        $normalized = strtoupper(trim($qcode));

        // Handle cases where Q-code might be stored as just numbers
        if (is_numeric($normalized)) {
            $normalized = 'Q' . $normalized;
        }

        return $normalized;
    }

    /**
     * Enrich a single product with CMS data
     */
    public function enrichProduct(Product $product): bool
    {
        if (!$product->q_code) {
            return false;
        }

        $cmsData = $this->getCmsReimbursement($product->q_code);

        if ($cmsData['asp'] === null && $cmsData['mue'] === null) {
            return false;
        }

        $product->update([
            'national_asp' => $cmsData['asp'],
            'mue' => $cmsData['mue'],
            'cms_last_updated' => now()
        ]);

        return true;
    }

    /**
     * Bulk enrich products in catalog
     */
    public function enrichCatalog(): array
    {
        $products = Product::whereNotNull('q_code')
            ->where('q_code', '!=', '')
            ->get();

        $updated = 0;
        $skipped = 0;
        $changes = [];

        foreach ($products as $product) {
            $qcode = $this->normalizeQCode($product->q_code);

            if (!isset($this->cmsReimbursementTable[$qcode])) {
                $skipped++;
                continue;
            }

            $cmsInfo = $this->cmsReimbursementTable[$qcode];
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
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'qcode' => $qcode,
                    'changes' => $productChanges
                ];

                $product->update([
                    'national_asp' => $cmsInfo['asp'],
                    'mue' => $cmsInfo['mue'],
                    'cms_last_updated' => now()
                ]);

                $updated++;
            }
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => $products->count(),
            'changes' => $changes
        ];
    }

    /**
     * Validate order quantities against MUE limits
     */
    public function validateOrderQuantities(array $orderItems): array
    {
        $validationResults = [];
        $hasErrors = false;

        foreach ($orderItems as $item) {
            $product = Product::find($item['product_id']);

            if (!$product) {
                $validationResults[] = [
                    'product_id' => $item['product_id'],
                    'valid' => false,
                    'errors' => ['Product not found']
                ];
                $hasErrors = true;
                continue;
            }

            $validation = $product->validateOrderQuantity($item['quantity']);
            $validationResults[] = array_merge([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'q_code' => $product->q_code
            ], $validation);

            if (!$validation['valid']) {
                $hasErrors = true;
            }
        }

        return [
            'valid' => !$hasErrors,
            'results' => $validationResults
        ];
    }

    /**
     * Get sync statistics for dashboard
     */
    public function getSyncStatistics(): array
    {
        $totalWithQCodes = Product::whereNotNull('q_code')
            ->where('q_code', '!=', '')
            ->count();

        $syncedProducts = Product::whereNotNull('cms_last_updated')->count();

        $availableInCms = Product::whereNotNull('q_code')
            ->where('q_code', '!=', '')
            ->get()
            ->filter(function ($product) {
                $cmsData = $this->getCmsReimbursement($product->q_code);
                return $cmsData['asp'] !== null || $cmsData['mue'] !== null;
            })
            ->count();

        return [
            'total_products_with_qcodes' => $totalWithQCodes,
            'synced_products' => $syncedProducts,
            'available_in_cms' => $availableInCms,
            'sync_coverage' => $totalWithQCodes > 0 ?
                round(($syncedProducts / $totalWithQCodes) * 100, 1) : 0,
            'cms_coverage' => $totalWithQCodes > 0 ?
                round(($availableInCms / $totalWithQCodes) * 100, 1) : 0,
        ];
    }
}

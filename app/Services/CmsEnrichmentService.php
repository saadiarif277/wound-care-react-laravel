<?php

namespace App\Services;

use App\Models\Order\Product;
use Illuminate\Support\Facades\Log;

class CmsEnrichmentService
{

    /**
     * Get CMS reimbursement data for a Q-code from database
     * This method now pulls data from the products table instead of hard-coded values
     */
    public function getCmsReimbursement(string $qcode): array
    {
        $normalized = $this->normalizeQCode($qcode);

        $product = Product::where('q_code', $normalized)->first();
        
        if (!$product) {
            Log::warning("CMS lookup failed for unknown QCode: {$normalized}");
            return ['asp' => null];
        }

        return ['asp' => $product->national_asp];
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

        if ($cmsData['asp'] === null) {
            return false;
        }

        $product->update([
            'national_asp' => $cmsData['asp'],
            'cms_last_updated' => now()
        ]);

        return true;
    }

    /**
     * Bulk enrich products in catalog
     * Note: This method is now deprecated as we no longer sync from hard-coded data
     * ASP values should be managed through the product management interface
     */
    public function enrichCatalog(): array
    {
        Log::warning('CmsEnrichmentService::enrichCatalog() called but is deprecated. ASP values should be managed through product management interface.');
        
        return [
            'updated' => 0,
            'skipped' => 0,
            'total' => 0,
            'changes' => [],
            'message' => 'This method is deprecated. ASP values are now managed through the product management interface.'
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
                return $cmsData['asp'] !== null;
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

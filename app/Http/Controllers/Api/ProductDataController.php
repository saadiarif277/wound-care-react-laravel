<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductDataController extends Controller
{
    /**
     * Get products with sizes for quick request
     */
    public function getProductsWithSizes(Request $request)
    {
        try {
            // Get base query for products with their sizes
            $query = Product::with(['sizes' => function ($query) {
                $query->active()->available()->ordered();
            }])
            ->where('is_active', true);

            // Filter by authorized Q-codes if provided
            if ($request->has('authorized_q_codes')) {
                $authorizedQCodes = $request->input('authorized_q_codes');
                if (is_string($authorizedQCodes)) {
                    $authorizedQCodes = explode(',', $authorizedQCodes);
                }
                
                if (!empty($authorizedQCodes)) {
                    $query->whereIn('q_code', $authorizedQCodes);
                }
            }

            $products = $query->get();

            // Transform products to include size data
            $transformedProducts = $products->map(function ($product) {
                $sizes = $product->sizes->map(function ($size) {
                    return [
                        'id' => $size->id,
                        'label' => $size->size_label,
                        'display_label' => $size->display_label,
                        'area_cm2' => $size->area_cm2,
                        'type' => $size->size_type,
                        'formatted_size' => $size->formatted_size,
                        'price' => $size->getEffectivePrice(),
                    ];
                });

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'q_code' => $product->q_code,
                    'manufacturer' => $product->manufacturer,
                    'manufacturer_id' => $product->manufacturer_id,
                    'category' => $product->category,
                    'description' => $product->description,
                    'price_per_sq_cm' => $product->price_per_sq_cm,
                    'msc_price' => $product->msc_price,
                    'mue' => $product->mue,
                    'available_sizes' => $sizes->toArray(),
                    'size_options' => $sizes->pluck('label')->toArray(),
                    'size_pricing' => $sizes->pluck('price', 'label')->toArray(),
                    'size_unit' => $product->size_unit ?? 'cm',
                    'image_url' => $product->image_url,
                    'commission_rate' => $product->commission_rate,
                    'docuseal_template_id' => $product->docuseal_template_id,
                    'signature_required' => $product->signature_required,
                ];
            });

            return response()->json([
                'success' => true,
                'products' => $transformedProducts,
                'count' => $transformedProducts->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching products with sizes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching products',
                'products' => []
            ], 500);
        }
    }

    /**
     * Get specific product with sizes
     */
    public function getProductWithSizes($productId)
    {
        try {
            $product = Product::with(['sizes' => function ($query) {
                $query->active()->available()->ordered();
            }])->findOrFail($productId);

            $sizes = $product->sizes->map(function ($size) {
                return [
                    'id' => $size->id,
                    'label' => $size->size_label,
                    'display_label' => $size->display_label,
                    'area_cm2' => $size->area_cm2,
                    'type' => $size->size_type,
                    'formatted_size' => $size->formatted_size,
                    'price' => $size->getEffectivePrice(),
                ];
            });

            return response()->json([
                'success' => true,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'q_code' => $product->q_code,
                    'manufacturer' => $product->manufacturer,
                    'manufacturer_id' => $product->manufacturer_id,
                    'category' => $product->category,
                    'description' => $product->description,
                    'price_per_sq_cm' => $product->price_per_sq_cm,
                    'msc_price' => $product->msc_price,
                    'mue' => $product->mue,
                    'available_sizes' => $sizes->toArray(),
                    'size_options' => $sizes->pluck('label')->toArray(),
                    'size_pricing' => $sizes->pluck('price', 'label')->toArray(),
                    'size_unit' => $product->size_unit ?? 'cm',
                    'image_url' => $product->image_url,
                    'commission_rate' => $product->commission_rate,
                    'docuseal_template_id' => $product->docuseal_template_id,
                    'signature_required' => $product->signature_required,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching product with sizes', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'product' => null
            ], 404);
        }
    }
}
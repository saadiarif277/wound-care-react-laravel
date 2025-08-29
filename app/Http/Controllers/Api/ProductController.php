<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    /**
     * Get popular/frequently used products
     */
    public function popular(Request $request)
    {
        $limit = $request->input('limit', 50);
        // Cache popular products for 1 hour
        $products = Cache::remember('popular_products_' . $limit, 3600, function () use ($limit) {
            return Product::select('products.*')
                ->leftJoin('provider_products', 'products.id', '=', 'provider_products.product_id')
                ->groupBy('products.id')
                ->orderByRaw('COUNT(provider_products.id) DESC')
                ->limit($limit)
                ->get();
        });
        return response()->json([
            'products' => $products,
            'total' => $products->count()
        ]);
    }

    /**
     * Optimized product search
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        $limit = $request->input('limit', 100);
        $showAll = $request->boolean('show_all', false);

        // If no query and showAll is true, return popular products instead of all
        if (empty($query) && $showAll) {
            return $this->popular($request);
        }

        // Require at least 2 characters for search
        if (strlen($query) < 2) {
            return response()->json([
                'products' => [],
                'total' => 0
            ]);
        }

        $products = Product::query()
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('sku', 'LIKE', "%{$query}%")
                  ->orWhere('manufacturer', 'LIKE', "%{$query}%")
                  ->orWhere('q_code', 'LIKE', "%{$query}%");
            })
            ->limit($limit)
            ->get();

        return response()->json([
            'products' => $products,
            'total' => $products->count()
        ]);
    }
}
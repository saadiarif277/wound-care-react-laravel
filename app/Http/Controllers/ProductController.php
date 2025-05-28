<?php

namespace App\Http\Controllers;

use App\Models\Order\Product;
use App\Models\Order\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Display the product catalog index page
     */
    public function index(Request $request): Response
    {
        $user = Auth::user()->load('roles');

        // Get products with pagination
        $products = Product::with(['category', 'manufacturer'])
            ->active()
            ->when($request->search, function ($query, $search) {
                $query->search($search);
            })
            ->when($request->category, function ($query, $category) {
                $query->byCategory($category);
            })
            ->when($request->manufacturer, function ($query, $manufacturer) {
                $query->byManufacturer($manufacturer);
            })
            ->when($request->sort, function ($query, $sort) use ($request) {
                $direction = $request->direction === 'desc' ? 'desc' : 'asc';

                switch ($sort) {
                    case 'name':
                        $query->orderBy('name', $direction);
                        break;
                    case 'category':
                        $query->orderBy('category', $direction);
                        break;
                    case 'manufacturer':
                        $query->orderBy('manufacturer', $direction);
                        break;
                    case 'price':
                        $query->orderBy('national_asp', $direction);
                        break;
                    default:
                        $query->orderBy('name', 'asc');
                }
            }, function ($query) {
                $query->orderBy('name', 'asc');
            })
            ->paginate(12);

        // Filter product data based on user permissions
        $products->getCollection()->transform(function ($product) use ($user) {
            return $this->filterProductPricingData($product, $user);
        });

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => $request->only(['search', 'category', 'manufacturer', 'sort', 'direction']),
            'categories' => Product::distinct()->pluck('category')->filter()->sort()->values(),
            'manufacturers' => Product::distinct()->pluck('manufacturer')->filter()->sort()->values(),
            'permissions' => [
                'can_view_financials' => $user->hasAnyPermission(['view-financials', 'manage-financials']),
                'can_see_discounts' => $user->hasPermission('view-discounts'),
                'can_see_msc_pricing' => $user->hasPermission('view-msc-pricing'),
                'can_see_order_totals' => $user->hasPermission('view-order-totals'),
                'can_manage_products' => $user->hasPermission('manage-products'),
            ],
        ]);
    }

    /**
     * Display the specified product details
     */
    public function show(Product $product): Response
    {
        $user = Auth::user()->load('roles');

        $product->load(['category', 'manufacturer']);
        $filteredProduct = $this->filterProductPricingData($product, $user);

        return Inertia::render('Products/Show', [
            'product' => $filteredProduct,
            'permissions' => [
                'can_view_financials' => $user->hasAnyPermission(['view-financials', 'manage-financials']),
                'can_see_discounts' => $user->hasPermission('view-discounts'),
                'can_see_msc_pricing' => $user->hasPermission('view-msc-pricing'),
                'can_see_order_totals' => $user->hasPermission('view-order-totals'),
                'can_manage_products' => $user->hasPermission('manage-products'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new product
     */
    public function create()
    {
        return Inertia::render('Products/Create', [
            'categories' => Product::getCategories(),
            'manufacturers' => Product::getManufacturers(),
        ]);
    }

    /**
     * Store a newly created product
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|unique:msc_products,sku',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'manufacturer' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'national_asp' => 'nullable|numeric|min:0',
            'price_per_sq_cm' => 'nullable|numeric|min:0',
            'q_code' => 'nullable|string|max:10',
            'available_sizes' => 'nullable|array',
            'available_sizes.*' => 'numeric|min:0',
            'graph_type' => 'nullable|string|max:255',
            'image_url' => 'nullable|url',
            'document_urls' => 'nullable|array',
            'document_urls.*' => 'url',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
        ]);

        $product = Product::create($validated);

        return redirect()->route('products.index')
            ->with('success', 'Product created successfully.');
    }

    /**
     * Show the form for editing the specified product
     */
    public function edit(Product $product)
    {
        return Inertia::render('Products/Edit', [
            'product' => $product,
            'categories' => Product::getCategories(),
            'manufacturers' => Product::getManufacturers(),
        ]);
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'sku' => 'required|string|unique:msc_products,sku,' . $product->id,
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'manufacturer' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'national_asp' => 'nullable|numeric|min:0',
            'price_per_sq_cm' => 'nullable|numeric|min:0',
            'q_code' => 'nullable|string|max:10',
            'available_sizes' => 'nullable|array',
            'available_sizes.*' => 'numeric|min:0',
            'graph_type' => 'nullable|string|max:255',
            'image_url' => 'nullable|url',
            'document_urls' => 'nullable|array',
            'document_urls.*' => 'url',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
        ]);

        $product->update($validated);

        return redirect()->route('products.index')
            ->with('success', 'Product updated successfully.');
    }

    /**
     * Remove the specified product
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Product deleted successfully.');
    }

    /**
     * Restore the specified product
     */
    public function restore(Product $product)
    {
        $product->restore();

        return redirect()->route('products.index')
            ->with('success', 'Product restored successfully.');
    }

    /**
     * API endpoint to get products for order creation
     */
    public function search(Request $request)
    {
        $user = Auth::user()->load('roles');

        $query = Product::active();

        if ($request->filled('q')) {
            $query->search($request->q);
        }

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        $products = $query
            ->select(['id', 'name', 'sku', 'q_code', 'manufacturer', 'price_per_sq_cm', 'available_sizes'])
            ->limit(20)
            ->get()
            ->map(function ($product) use ($user) {
                $data = [
                    'id' => $product->id,
                    'label' => $product->name,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'q_code' => $product->q_code,
                    'manufacturer' => $product->manufacturer,
                    'nationalAsp' => $product->price_per_sq_cm,
                    'graphSizes' => $product->available_sizes ?? [],
                ];

                // Add MSC pricing only if user has permission
                if ($user->hasPermission('view-msc-pricing')) {
                    $data['pricePerSqCm'] = $product->msc_price;
                    $data['mscPrice'] = $product->msc_price;
                }

                return $data;
            });

        return response()->json($products);
    }

    /**
     * API endpoint to get product details by ID
     */
    public function apiShow(Product $product)
    {
        $user = Auth::user()->load('roles');

        $data = [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'q_code' => $product->q_code,
            'manufacturer' => $product->manufacturer,
            'category' => $product->category,
            'description' => $product->description,
            'national_asp' => $product->price_per_sq_cm,
            'available_sizes' => $product->available_sizes ?? [],
            'image_url' => $product->image_url,
            'document_urls' => $product->document_urls ?? [],
        ];

        // Add financial data only if user has permission
        if ($user->hasPermission('view-msc-pricing')) {
            $data['msc_price'] = $product->msc_price;
        }

        if ($user->hasAnyPermission(['view-financials', 'manage-financials'])) {
            $data['commission_rate'] = $product->commission_rate;
        }

        return response()->json($data);
    }

    /**
     * API endpoint to get all products for selection interfaces
     */
    public function getAll(Request $request)
    {
        $user = Auth::user()->load('roles');

        $query = Product::active();

        // Apply search filter
        if ($request->filled('q')) {
            $query->search($request->q);
        }

        // Apply category filter
        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        // Apply manufacturer filter
        if ($request->filled('manufacturer')) {
            $query->byManufacturer($request->manufacturer);
        }

        $products = $query
            ->select([
                'id', 'name', 'sku', 'q_code', 'manufacturer', 'category',
                'description', 'price_per_sq_cm', 'available_sizes',
                'image_url', 'commission_rate'
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($product) use ($user) {
                $data = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'q_code' => $product->q_code,
                    'manufacturer' => $product->manufacturer,
                    'category' => $product->category,
                    'description' => $product->description,
                    'price_per_sq_cm' => $product->price_per_sq_cm,
                    'available_sizes' => $product->available_sizes ?? [],
                    'image_url' => $product->image_url,
                ];

                // Add MSC pricing only if user has permission
                if ($user->hasPermission('view-msc-pricing')) {
                    $data['msc_price'] = $product->msc_price;
                }

                // Add commission data only if user has permission
                if ($user->hasAnyPermission(['view-financials', 'manage-financials'])) {
                    $data['commission_rate'] = $product->commission_rate;
                }

                return $data;
            });

        $categories = Product::getCategories();
        $manufacturers = Product::getManufacturers();

        return response()->json([
            'products' => $products,
            'categories' => $categories,
            'manufacturers' => $manufacturers,
        ]);
    }

    /**
     * API endpoint to get product recommendations based on clinical data
     */
    public function recommendations(Request $request)
    {
        // For backward compatibility, support simple request parameters
        if ($request->filled('wound_type') && !$request->filled('product_request_id')) {
            return $this->getBasicRecommendations($request);
        }

        // Enhanced recommendations using the recommendation engine
        $validated = $request->validate([
            'product_request_id' => 'required|exists:product_requests,id',
            'use_ai' => 'boolean',
            'max_recommendations' => 'integer|min:1|max:10'
        ]);

        try {
            $productRequest = ProductRequest::find($validated['product_request_id']);

            if (!$productRequest) {
                return response()->json([
                    'success' => false,
                    'error' => 'Product request not found'
                ], 404);
            }
            $user = Auth::user();

            // Use the recommendation engine
            $recommendationService = app(\App\Services\ProductRecommendationEngine\MSCProductRecommendationService::class);

            $options = [
                'use_ai' => $validated['use_ai'] ?? true,
                'max_recommendations' => $validated['max_recommendations'] ?? 6,
                'user_role' => str_replace('-', '_', $user->getPrimaryRole()?->slug ?? 'provider'),
                'show_msc_pricing' => $user->hasPermission('view-discounts') // Only show MSC pricing if user can see discounts
            ];

            // Ensure we have a single ProductRequest model instance
            if ($productRequest instanceof \Illuminate\Database\Eloquent\Collection) {
                $productRequest = $productRequest->first();
            }

            $result = $recommendationService->getRecommendations($productRequest, $options);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate recommendations',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Basic recommendations for backward compatibility
     */
    protected function getBasicRecommendations(Request $request)
    {
        $query = Product::active();

        // Basic filtering based on wound type and clinical indicators
        if ($request->filled('wound_type')) {
            $woundType = $request->wound_type;

            // Filter products based on wound type indications
            if ($woundType === 'DFU') {
                $query->whereIn('category', ['SkinSubstitute', 'Biologic']);
            } elseif ($woundType === 'VLU') {
                $query->where('category', 'SkinSubstitute');
            }
        }

        // Filter by wound size if provided
        if ($request->filled('wound_size')) {
            $woundSize = floatval($request->wound_size);
            $query->where(function ($q) use ($woundSize) {
                $q->whereJsonContains('available_sizes', $woundSize)
                  ->orWhereJsonLength('available_sizes', '>', 0); // Has any sizes available
            });
        }

        $recommendations = $query
            ->orderBy('price_per_sq_cm', 'asc') // Prioritize cost-effective options
            ->limit(6)
            ->get()
            ->map(function ($product) use ($request) {
                $woundSize = $request->filled('wound_size') ? floatval($request->wound_size) : 4;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'q_code' => $product->q_code,
                    'manufacturer' => $product->manufacturer,
                    'category' => $product->category,
                    'msc_price' => $product->msc_price,
                    'recommended_size' => $this->getRecommendedSize($product->available_sizes ?? [], $woundSize),
                    'total_price' => $product->getTotalPrice($woundSize),
                    'image_url' => $product->image_url,
                ];
            });

        return response()->json([
            'success' => true,
            'recommendations' => $recommendations,
            'type' => 'basic'
        ]);
    }

    /**
     * Helper method to get recommended size for a wound
     */
    private function getRecommendedSize(array $availableSizes, float $woundSize)
    {
        if (empty($availableSizes)) {
            return null;
        }

        // Find the smallest size that's larger than the wound size
        $suitableSizes = array_filter($availableSizes, function ($size) use ($woundSize) {
            return $size >= $woundSize;
        });

        if (!empty($suitableSizes)) {
            return min($suitableSizes);
        }

        // If no suitable size found, return the largest available
        return max($availableSizes);
    }

    /**
     * Filter product pricing data based on user role
     */
    private function filterProductPricingData($product, $user)
    {
        $productArray = $product->toArray();

        // Remove MSC pricing if user doesn't have permission
        if (!$user->hasPermission('view-msc-pricing')) {
            unset($productArray['msc_price']);
            unset($productArray['msc_discount_percentage']);
        }

        // Remove financial data if user doesn't have permission
        if (!$user->hasAnyPermission(['view-financials', 'manage-financials'])) {
            unset($productArray['commission_rate']);
            unset($productArray['total_commission']);
        }

        return $productArray;
    }
}

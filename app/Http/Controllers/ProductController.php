<?php

namespace App\Http\Controllers;

use App\Models\Order\Product;
use App\Models\Order\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Order\Manufacturer;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Services\ProductDataService;
use App\Repositories\ProductRepository;

class ProductController extends Controller
{
    protected ProductDataService $productDataService;
    protected ProductRepository $productRepository;

    public function __construct(ProductDataService $productDataService, ProductRepository $productRepository)
    {
        $this->productDataService = $productDataService;
        $this->productRepository = $productRepository;
    }

    /**
     * Display the product catalog index page
     */
    public function index(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->load('roles');

        // Get filtered products using repository
        $filters = $request->only(['search', 'category', 'manufacturer']);
        $products = $this->productRepository->getFilteredProducts($filters, $request->get('per_page', 15));

        // Transform products using the service
        $products->through(function ($product) use ($user) {
            return $this->productDataService->transformProduct($product, $user, ['include_timestamps' => true]);
        });

        // Get statistics
        $stats = $this->productRepository->getRepositoryStats();
        $onboardedProductsCount = 0;

        if ($user->hasRole('provider')) {
            $onboardedProductsCount = $this->productRepository->providerHasOnboardedProducts($user) ? 1 : 0; // Simplified for now
        }

        // Get filter options
        $filterOptions = $this->productRepository->getFilterOptions();

        return Inertia::render('Products/Index', [
            'products' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ],
            'filters' => $request->only(['search', 'category', 'manufacturer', 'sort', 'direction']),
            'categories' => $filterOptions['categories'],
            'manufacturers' => $filterOptions['manufacturers'],
            'stats' => [
                'total_products' => $stats['total_products'],
                'onboarded_products' => $onboardedProductsCount,
            ],
            'permissions' => $this->productDataService->getUserPermissions($user),
        ]);
    }

    /**
     * Display the specified product details
     */
    public function show(Product $product): Response
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->load('roles');

        $product->load(['category', 'manufacturer']);
        $transformedProduct = $this->productDataService->transformProduct($product, $user);

        return Inertia::render('Products/Show', [
            'product' => $transformedProduct,
            'permissions' => $this->productDataService->getUserPermissions($user),
        ]);
    }

    /**
     * Show the form for creating a new product
     */
    public function create()
    {
        return Inertia::render('Products/Create', [
            'categories' => Product::getCategories(),
            'manufacturers' => Manufacturer::active()->orderBy('name')->pluck('name')->values(),
        ]);
    }

    /**
     * Store a newly created product
     */
    public function store(StoreProductRequest $request)
    {
        $validated = $request->validated();
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
            'manufacturers' => Manufacturer::active()->orderBy('name')->pluck('name')->values(),
        ]);
    }

    /**
     * Update the specified product
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $validated = $request->validated();
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
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->load('roles');

        $filters = $request->only(['q', 'category', 'onboarded_q_codes']);
        $providerHasNoProducts = false;

        // If onboarded_q_codes is provided, use it directly for filtering
        if (!empty($filters['onboarded_q_codes'])) {
            // This handles the case where frontend passes specific Q-codes to filter by
            $products = $this->productRepository->getProviderProducts($user, $filters);

            if ($products->isEmpty()) {
                $providerHasNoProducts = true;
            }
        }
        // Handle provider-specific product filtering based on their role
        else if ($user->hasRole('provider') && !$request->boolean('show_all', false)) {
            $products = $this->productRepository->getProviderProducts($user, $filters);

            if ($products->isEmpty()) {
                $providerHasNoProducts = true;
            }
        } else {
            // Get all products for non-providers or when show_all is true
            $products = $this->productRepository->getAllProducts($filters);
        }

        // Transform products for response - convert to array first to avoid type issues
        $transformedProducts = $products->map(function ($product) use ($user) {
            return $this->productDataService->transformProduct($product, $user, ['include_manufacturer_info' => true]);
        })->values()->toArray();

        // Get filter options from the fetched products
        $categories = $products->pluck('category')->unique()->filter()->sort()->values();
        $manufacturers = $products->pluck('manufacturer')->unique()->filter()->sort()->values();

        return response()->json([
            'products' => $transformedProducts,
            'categories' => $categories,
            'manufacturers' => $manufacturers,
            'provider_has_no_products' => $providerHasNoProducts,
            'message' => $providerHasNoProducts ? 'This provider has not been onboarded to any products yet. Please contact your MSC administrator to request product access.' : null,
        ]);
    }

    /**
     * API endpoint to get product details by ID
     */
    public function apiShow(Product $product)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->load('roles');

        // Eager load the active sizes relationship
        $product->load('activeSizes');

        $data = [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'q_code' => $product->q_code,
            'manufacturer' => $product->manufacturer,
            'category' => $product->category,
            'description' => $product->description,
            'national_asp' => $product->price_per_sq_cm,
            'image_url' => $product->image_url,
            'document_urls' => $product->document_urls ?? [],
            'sizes' => $product->activeSizes->map(function ($size) {
                return [
                    'id' => $size->id,
                    'display_label' => $size->display_label,
                    'size_type' => $size->size_type,
                    'length_mm' => $size->length_mm,
                    'width_mm' => $size->width_mm,
                    'diameter_mm' => $size->diameter_mm,
                    'area_cm2' => $size->area_cm2,
                    'formatted_size' => $size->formatted_size,
                ];
            }),
        ];

        // Add financial data only if user has permission
        if ($user->hasPermission('view-msc-pricing')) {
            $data['msc_price'] = $product->msc_price;
        }

        // Add CMS ASP for providers and financial users
        if ($user->hasAnyPermission(['view-providers', 'view-financials', 'manage-financials'])) {
            $data['national_asp'] = $product->national_asp;
        }

        if ($user->hasAnyPermission(['view-financials', 'manage-financials'])) {
            $data['commission_rate'] = $product->commission_rate;
        }

        return response()->json([
            'product' => $data,
            'categories' => Product::distinct()->pluck('category'),
            'manufacturers' => Product::distinct()->pluck('manufacturer'),
            'roleRestrictions' => [
                'can_view_financials' => $user->hasPermission('view-financials'),
                'can_see_discounts' => $user->hasPermission('view-discounts'),
                'can_see_msc_pricing' => $user->hasPermission('view-msc-pricing'),
                'can_see_order_totals' => $user->hasPermission('view-order-totals'),
                'pricing_access_level' => $this->getUserPricingAccessLevel($user),
                'commission_access_level' => $this->getUserCommissionAccessLevel($user),
            ],
        ]);
    }

    /**
     * API endpoint to get all products for selection interfaces
     */
    public function getAll(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->load('roles');

        $filters = $request->only(['q', 'category', 'manufacturer', 'onboarded_q_codes']);
        $providerHasNoProducts = false;

        // If onboarded_q_codes is provided, use it directly for filtering
        if (!empty($filters['onboarded_q_codes'])) {
            // This handles the case where frontend passes specific Q-codes to filter by
            $products = $this->productRepository->getProviderProducts($user, $filters);

            if ($products->isEmpty()) {
                $providerHasNoProducts = true;
            }
        }
        // Handle provider-specific product filtering based on their role
        else if ($user->hasRole('provider') && !$request->boolean('show_all', false)) {
            $products = $this->productRepository->getProviderProducts($user, $filters);

            if ($products->isEmpty()) {
                $providerHasNoProducts = true;
            }
        } else {
            // Get all products for non-providers or when show_all is true
            $products = $this->productRepository->getAllProducts($filters);
        }

        // Transform products for response - convert to array first to avoid type issues
        $transformedProducts = $products->map(function ($product) use ($user) {
            return $this->productDataService->transformProduct($product, $user, ['include_manufacturer_info' => true]);
        })->values()->toArray();

        // Get filter options from the fetched products
        $categories = $products->pluck('category')->unique()->filter()->sort()->values();
        $manufacturers = $products->pluck('manufacturer')->unique()->filter()->sort()->values();

        return response()->json([
            'products' => $transformedProducts,
            'categories' => $categories,
            'manufacturers' => $manufacturers,
            'provider_has_no_products' => $providerHasNoProducts,
            'message' => $providerHasNoProducts ? 'This provider has not been onboarded to any products yet. Please contact your MSC administrator to request product access.' : null,
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
            /** @var \App\Models\User $user */
            $user = Auth::user();

            // Use the recommendation engine
            $recommendationService = app(\App\Services\ProductRecommendationEngine\MSCProductRecommendationService::class);

            $options = [
                'use_ai' => $validated['use_ai'] ?? true,
                'max_recommendations' => $validated['max_recommendations'] ?? 6,
                'user_role' => $user->hasPermission('view-providers') ? 'provider' : ($user->hasPermission('manage-products') ? 'admin' : 'user'),
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

        // Handle CMS ASP visibility - show to providers, hide from office managers and others without permission
        // Providers need ASP visibility for clinical decisions, office managers do not
        if (!$user->hasAnyPermission(['view-providers', 'view-financials', 'manage-financials'])) {
            unset($productArray['national_asp']);
        }

        // Handle MUE visibility - only show raw value to admins
        if (!$user->hasPermission('manage-products')) {
            // For non-admins, remove raw MUE value
            unset($productArray['mue']);
        }

        // Always remove cms_last_updated for non-admins
        if (!$user->hasPermission('manage-products')) {
            unset($productArray['cms_last_updated']);
        }

        // Add processed MUE information for frontend (for validation purposes)
        if ($product->hasMueEnforcement()) {
            $productArray['has_quantity_limits'] = true;
            // Don't expose the actual limit value to non-admins
            if (!$user->hasPermission('manage-products')) {
                $productArray['max_allowed_quantity'] = null; // Frontend will know there's a limit but not the value
            }
        } else {
            $productArray['has_quantity_limits'] = false;
        }

        // Add CMS enrichment status for admins
        if ($user->hasPermission('manage-products')) {
            $productArray['cms_status'] = $product->cms_status;
            $productArray['cms_last_updated'] = $product->cms_last_updated?->format('Y-m-d H:i:s');
        }

        return $productArray;
    }

    /**
     * Display the product management interface for admins
     */
    public function manage(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Authorization check
        if (!$user->hasPermission('manage-products')) {
            abort(403, 'Unauthorized');
        }

        // Get products with pagination (including soft deleted)
        $products = Product::withTrashed()
            ->with(['category', 'manufacturer'])
            ->when($request->search, function ($query, $search) {
                $query->search($search);
            })
            ->when($request->category, function ($query, $category) {
                $query->byCategory($category);
            })
            ->when($request->manufacturer, function ($query, $manufacturer) {
                $query->byManufacturer($manufacturer);
            })
            ->when($request->status, function ($query, $status) {
                if ($status === 'active') {
                    $query->whereNull('deleted_at');
                } elseif ($status === 'inactive') {
                    $query->onlyTrashed();
                }
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
                    case 'created_at':
                        $query->orderBy('created_at', $direction);
                        break;
                    default:
                        $query->orderBy('name', 'asc');
                }
            }, function ($query) {
                $query->orderBy('name', 'asc');
            })
            ->paginate(15);

        // Transform products for display
        $products->getCollection()->transform(function ($product) {
            // Get sizes from ProductSize relationship
            $productSizes = $product->activeSizes()->get();

            // Format sizes for frontend
            $availableSizes = [];
            $sizeOptions = [];
            $sizePricing = [];

            foreach ($productSizes as $size) {
                // Add the display label (e.g., "2x2cm", "4x4cm")
                $sizeOptions[] = $size->size_label;

                // Add to size pricing mapping
                $sizePricing[$size->size_label] = $size->area_cm2;

                // For backward compatibility, also add numeric sizes
                $availableSizes[] = $size->area_cm2;
            }

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'manufacturer' => is_object($product->manufacturer) ? $product->manufacturer->name : $product->manufacturer,
                'category' => $product->category,
                'q_code' => $product->q_code,
                'national_asp' => $product->national_asp,
                'price_per_sq_cm' => $product->price_per_sq_cm,
                'commission_rate' => $product->commission_rate ?? 3.0,
                'mue' => $product->mue,
                'available_sizes' => $availableSizes, // Numeric sizes for backward compatibility
                'size_options' => $sizeOptions, // Actual size labels like "2x2cm", "4x4cm"
                'size_pricing' => $sizePricing, // Maps size labels to area in cm²
                'size_unit' => 'cm', // Default to cm for wound care products
                'graphSizes' => $availableSizes, // Keep for backward compatibility
                'has_onboarding' => $product->manufacturer_requires_onboarding ?? false,
                'signature_required' => $product->manufacturer?->signature_required ?? false,
                'docuseal_template_id' => $product->manufacturer?->docuseal_order_form_template_id ?? null,
            ];
        });

        // Get summary statistics
        $stats = [
            'total_products' => Product::count(),
            'active_products' => Product::whereNull('deleted_at')->count(),
            'inactive_products' => Product::onlyTrashed()->count(),
            'categories_count' => Product::distinct('category')->count('category'),
            'manufacturers_count' => Product::distinct('manufacturer')->count('manufacturer'),
        ];

        return Inertia::render('Products/Manage', [
            'products' => $products,
            'filters' => $request->only(['search', 'category', 'manufacturer', 'status', 'sort', 'direction']),
            'categories' => Product::distinct()->pluck('category')->filter()->sort()->values(),
            'manufacturers' => Manufacturer::active()->orderBy('name')->pluck('name')->values(),
            'stats' => $stats,
            'permissions' => [
                'can_create' => $user->hasPermission('manage-products'),
                'can_edit' => $user->hasPermission('manage-products'),
                'can_delete' => $user->hasPermission('manage-products'),
                'can_restore' => $user->hasPermission('manage-products'),
                'can_sync_cms' => $user->hasPermission('manage-products'),
            ],
        ]);
    }

    /**
     * API endpoint to validate quantity against MUE limits
     */
    public function validateQuantity(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $validation = $product->validateOrderQuantity($validated['quantity']);

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $response = [
            'valid' => $validation['valid'],
            'warnings' => $validation['warnings'],
            'errors' => $validation['errors'],
            'has_limits' => $product->hasMueEnforcement()
        ];

        // Only expose the actual MUE limit to admin users
        if ($user && $user->hasPermission('manage-products')) {
            $response['max_allowed'] = $product->getMaxAllowedQuantity();
        }

        return response()->json($response);
    }

    /**
     * Admin endpoint to trigger CMS pricing sync
     */
    public function syncCmsPricing(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->hasPermission('manage-products')) {
            abort(403, 'Unauthorized');
        }

        try {
            // Run the CMS sync command
            Artisan::call('cms:sync-pricing', [
                '--force' => true
            ]);

            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'message' => 'CMS pricing sync completed successfully',
                'output' => $output
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'CMS pricing sync failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get CMS sync status for admin dashboard
     */
    public function getCmsSyncStatus()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->hasPermission('manage-products')) {
            abort(403, 'Unauthorized');
        }

        $totalProducts = Product::whereNotNull('q_code')->count();
        $syncedProducts = Product::whereNotNull('cms_last_updated')->count();
        $staleProducts = Product::whereNotNull('cms_last_updated')
            ->where('cms_last_updated', '<', now()->subDays(90))
            ->count();
        $needsUpdateProducts = Product::whereNotNull('cms_last_updated')
            ->where('cms_last_updated', '<', now()->subDays(30))
            ->where('cms_last_updated', '>=', now()->subDays(90))
            ->count();

        $lastSync = Product::whereNotNull('cms_last_updated')
            ->max('cms_last_updated');

        return response()->json([
            'total_products_with_qcodes' => $totalProducts,
            'synced_products' => $syncedProducts,
            'stale_products' => $staleProducts,
            'needs_update_products' => $needsUpdateProducts,
            'last_sync' => $lastSync ? \Carbon\Carbon::parse($lastSync)->format('Y-m-d H:i:s') : null,
            'sync_coverage_percentage' => $totalProducts > 0 ? round(($syncedProducts / $totalProducts) * 100, 1) : 0
        ]);
    }

    /**
     * Get pricing history for a product
     */
    public function getPricingHistory(Product $product)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user->hasAnyPermission(['manage-products', 'view-financials'])) {
            abort(403, 'Unauthorized');
        }

        $history = \App\Models\ProductPricingHistory::forProduct($product->id)
            ->with('changedBy:id,first_name,last_name,email')
            ->orderBy('effective_date', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'effective_date' => $record->effective_date->format('Y-m-d H:i:s'),
                    'national_asp' => $record->national_asp,
                    'price_per_sq_cm' => $record->price_per_sq_cm,
                    'msc_price' => $record->msc_price,
                    'commission_rate' => $record->commission_rate,
                    'mue' => $record->mue,
                    'change_type' => $record->change_type,
                    'changed_by' => $record->changedBy ? [
                        'name' => $record->changedBy->first_name . ' ' . $record->changedBy->last_name,
                        'email' => $record->changedBy->email
                    ] : null,
                    'changed_fields' => $record->changed_fields,
                    'previous_values' => $record->previous_values,
                    'change_reason' => $record->change_reason,
                    'source' => $record->source,
                    'price_change_percentage' => $record->getPriceChangePercentage(),
                    'is_price_increase' => $record->isPriceIncrease(),
                    'is_price_decrease' => $record->isPriceDecrease(),
                ];
            });

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'q_code' => $product->q_code,
                'current_asp' => $product->national_asp,
                'current_mue' => $product->mue,
            ],
            'history' => $history,
            'total_records' => $history->count()
        ]);
    }
}

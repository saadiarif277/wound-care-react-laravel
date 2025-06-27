<?php

namespace App\Http\Controllers;

use App\Models\Order\Product;
use App\Models\Order\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\Order\Manufacturer;

class ProductController extends Controller
{
    /**
     * Display the product catalog index page
     */
    public function index(Request $request): Response
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->load('roles');

        $products = Product::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->get('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('q_code', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('manufacturer'), function ($query) use ($request) {
                $query->where('manufacturer', $request->get('manufacturer'));
            })
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->where('category', $request->get('category'));
            })
            ->latest()
            ->paginate($request->get('per_page', 15))
            ->through(function ($product) use ($user) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'q_code' => $product->q_code,
                    'manufacturer' => $product->manufacturer,
                    'category' => $product->category,
                    'price_per_sq_cm' => $product->price_per_sq_cm,
                    'available_sizes' => $product->size_options ?? $product->available_sizes ?? [],
                    'size_options' => $product->size_options,
                    'size_pricing' => $product->size_pricing,
                    'size_unit' => $product->size_unit,
                    'is_active' => $product->is_active,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                    'msc_price' => $user->hasPermission('view-msc-pricing') ? $product->msc_price : null,
                ];
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
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->load('roles');

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
            'manufacturers' => Manufacturer::active()->orderBy('name')->pluck('name')->values(),
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
            'available_sizes.*' => 'string|max:20',
            'size_options' => 'nullable|array',
            'size_options.*' => 'string|max:20',
            'size_pricing' => 'nullable|array',
            'size_unit' => 'nullable|string|in:in,cm',
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
            'manufacturers' => Manufacturer::active()->orderBy('name')->pluck('name')->values(),
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
            'available_sizes.*' => 'string|max:20',
            'size_options' => 'nullable|array',
            'size_options.*' => 'string|max:20',
            'size_pricing' => 'nullable|array',
            'size_unit' => 'nullable|string|in:in,cm',
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
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->load('roles');

        $query = Product::active();

        // Filter by specific onboarded Q-codes if provided (for performance optimization)
        if ($request->filled('onboarded_q_codes')) {
            $qCodes = explode(',', $request->get('onboarded_q_codes'));
            $qCodes = array_map('trim', $qCodes);
            $qCodes = array_filter($qCodes); // Remove empty values

            if (!empty($qCodes)) {
                $query->whereIn('q_code', $qCodes);
            }
        }
        // Fallback to original provider filtering if no specific Q-codes provided
        elseif ($user->hasPermission('view-providers') && !$request->boolean('show_all', false)) {
            // Only show products the provider is onboarded with
            $query->whereHas('activeProviders', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        if ($request->filled('q')) {
            $query->search($request->q);
        }

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        $products = $query
            ->with(['activeSizes']) // Load active sizes
            ->select(['id', 'name', 'sku', 'q_code', 'manufacturer', 'category', 'price_per_sq_cm', 'available_sizes', 'size_options', 'size_pricing', 'size_unit'])
            ->get();

        // Transform products for response
        $transformedProducts = $products->map(function ($product) {
            // Get sizes from new system or fall back to old
            $availableSizes = $product->size_options ?? $product->available_sizes ?? [];

            // Get manufacturer info
            $manufacturer = Manufacturer::find($product->manufacturer_id);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku ?? '',
                'code' => $product->q_code ?? $product->hcpcs_code ?? '',
                'q_code' => $product->q_code,
                'hcpcs_code' => $product->hcpcs_code,
                'manufacturer' => $product->manufacturer,
                'manufacturer_id' => $product->manufacturer_id,
                'category' => $product->category,
                'price_per_sq_cm' => $product->price_per_sq_cm,
                'available_sizes' => $availableSizes,
                'size_options' => $product->size_options ?? [],
                'size_pricing' => $product->size_pricing ?? [],
                'size_unit' => $product->size_unit ?? 'in',
                'graphSizes' => $product->available_sizes ?? [], // Keep for backward compatibility
                'has_onboarding' => $product->manufacturer_requires_onboarding ?? false,
                'signature_required' => $manufacturer?->signature_required ?? false,
                'docuseal_template_id' => $manufacturer?->docuseal_order_form_template_id,
            ];
        });

        // Also get categories and manufacturers for filtering
        $categories = $query->distinct()->pluck('category')->filter()->sort()->values();
        $manufacturers = $query->distinct()->pluck('manufacturer')->filter()->sort()->values();

        return response()->json([
            'products' => $transformedProducts,
            'categories' => $categories,
            'manufacturers' => $manufacturers,
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

        $query = Product::active();

        // Filter by provider's onboarded products if user has provider viewing permission
        // The 'show_all' parameter allows showing all products for catalog viewing
        if ($user->hasPermission('view-providers') && !$request->boolean('show_all', false)) {
            // Only show products the provider is onboarded with
            $query->whereHas('activeProviders', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

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

                // Add onboarding status for providers
                if ($user->hasPermission('view-providers')) {
                    $data['is_onboarded'] = $product->isAvailableForProvider($user->id);
                }

                return $data;
            });

        $categories = Product::getCategories();
        $manufacturers = Manufacturer::active()->orderBy('name')->pluck('name')->values();

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

        // Never expose raw MUE values directly - handle enforcement behind the scenes
        unset($productArray['mue']);
        unset($productArray['cms_last_updated']);

        // Add processed MUE information for frontend
        if ($product->hasMueEnforcement()) {
            $productArray['has_quantity_limits'] = true;
            $productArray['max_allowed_quantity'] = $product->getMaxAllowedQuantity();
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
            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'manufacturer' => $product->manufacturer,
                'category' => $product->category,
                'q_code' => $product->q_code,
                'national_asp' => $product->national_asp,
                'price_per_sq_cm' => $product->price_per_sq_cm,
                'commission_rate' => $product->commission_rate,
                'is_active' => $product->is_active,
                'deleted_at' => $product->deleted_at,
                'created_at' => $product->created_at->format('M j, Y'),
                'updated_at' => $product->updated_at->format('M j, Y'),
                'image_url' => $product->image_url,
                'available_sizes' => $product->available_sizes,
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

        return response()->json([
            'valid' => $validation['valid'],
            'warnings' => $validation['warnings'],
            'errors' => $validation['errors'],
            'max_allowed' => $product->getMaxAllowedQuantity(),
            'has_limits' => $product->hasMueEnforcement()
        ]);
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
}

<?php

namespace App\Services;

use App\Models\Order\Product;
use App\Models\User;
use App\Models\Order\Manufacturer;
use Illuminate\Database\Eloquent\Collection;

class ProductDataService
{
    /**
     * Transform a single product for API response based on user permissions
     *
     * @param Product $product
     * @param User $user
     * @param array $options Additional options for transformation
     * @return array
     */
    public function transformProduct(Product $product, User $user, array $options = []): array
    {
        // Load the ProductSize relationship if not already loaded
        if (!$product->relationLoaded('activeSizes')) {
            $product->load('activeSizes');
        }

        // Get sizes from ProductSize relationship
        $productSizes = $product->activeSizes;

        // Format sizes for frontend
        $availableSizes = [];
        $sizeOptions = [];
        $sizePricing = [];
        $sizeSpecificPricing = [];

        foreach ($productSizes as $size) {
            // Add the display label (e.g., "2x2cm", "4x4cm")
            $sizeOptions[] = $size->size_label;

            // Add to size pricing mapping (area in cm²)
            $sizePricing[$size->size_label] = $size->area_cm2;

            // Add size-specific pricing data
            $sizeSpecificPricing[$size->size_label] = [
                'area_cm2' => $size->area_cm2,
                'size_specific_price' => $size->size_specific_price,
                'price_per_unit' => $size->price_per_unit,
                'effective_price' => $size->getEffectivePrice(),
                'display_label' => $size->display_label,
                'formatted_size' => $size->formatted_size,
            ];

            // For backward compatibility, also add numeric sizes
            $availableSizes[] = $size->area_cm2;
        }

        $data = [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku ?? '',
            'q_code' => $product->q_code,
            'hcpcs_code' => $product->hcpcs_code,
            'manufacturer' => $product->manufacturer,
            'manufacturer_id' => $product->manufacturer_id,
            'category' => $product->category,
            'description' => $product->description ?? '',
            'available_sizes' => $availableSizes, // Numeric sizes for backward compatibility
            'size_options' => $sizeOptions, // Actual size labels like "2x2cm", "4x4cm"
            'size_pricing' => $sizePricing, // Maps size labels to area in cm²
            'size_specific_pricing' => $sizeSpecificPricing, // Detailed pricing data for each size
            'size_unit' => 'cm', // Default to cm for wound care products
            'is_active' => $product->is_active,
            'image_url' => $product->image_url,
            'document_urls' => $product->document_urls ?? [],
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ];

        // Add pricing data based on user role
        $this->addPricingData($data, $product, $user);

        // Add permission-based data
        $this->addPermissionBasedData($data, $product, $user, $options);

        // Add onboarding status for providers
        if ($user->hasRole('provider')) {
            $data['is_onboarded'] = $product->isAvailableForProvider($user->id);
        }

        return $data;
    }

    /**
     * Transform a collection of products for API response based on user permissions
     *
     * @param Collection $products
     * @param User $user
     * @param array $options Additional options for transformation
     * @return Collection
     */
    public function transformProductCollection(Collection $products, User $user, array $options = []): Collection
    {
        return $products->map(function ($product) use ($user, $options) {
            return $this->transformProduct($product, $user, $options);
        });
    }



    /**
     * Add pricing data based on user permissions (not roles)
     *
     * @param array &$data
     * @param Product $product
     * @param User $user
     */
    private function addPricingData(array &$data, Product $product, User $user): void
    {
        // Add MSC pricing only if user has permission
        if ($user->hasPermission('view-msc-pricing')) {
            $data['msc_price'] = $product->price_per_sq_cm * 0.6;
            $data['display_price'] = $product->price_per_sq_cm * 0.6;
            $data['price_label'] = 'MSC Price';
        }

        // Add CMS ASP only for users with financial permissions (NOT office managers)
        if ($user->hasAnyPermission(['view-national-asp', 'view-financials', 'manage-financials'])) {
            $data['national_asp'] = $product->price_per_sq_cm;
            // Only set as display price if no MSC price was set
            if (!isset($data['display_price'])) {
                $data['display_price'] = $product->price_per_sq_cm;
                $data['price_label'] = 'National ASP';
            }
        }

        // Full pricing for admins
        if ($user->hasPermission('manage-products')) {
            $data['price_per_sq_cm'] = $product->price_per_sq_cm;
            $data['national_asp'] = $product->price_per_sq_cm;
            $data['msc_price'] = $product->price_per_sq_cm * 0.6;
            $data['display_price'] = $product->price_per_sq_cm;
            $data['price_label'] = 'Price/cm²';
        }
    }

    /**
     * Add permission-based data
     *
     * @param array &$data
     * @param Product $product
     * @param User $user
     * @param array $options
     */
    private function addPermissionBasedData(array &$data, Product $product, User $user, array $options = []): void
    {
        // Add commission rate only for authorized users
        if ($user->hasAnyPermission(['view-financials', 'manage-financials'])) {
            $data['commission_rate'] = $product->commission_rate;
        }

        // Add MUE only for admins
        if ($user->hasPermission('manage-products')) {
            $data['mue'] = $product->mue;
            $data['cms_status'] = $product->cms_status;
            $data['cms_last_updated'] = $product->cms_last_updated?->format('Y-m-d H:i:s');
        } else {
            // For non-admins, add processed MUE information for validation purposes
            if ($product->hasMueEnforcement()) {
                $data['has_quantity_limits'] = true;
                $data['max_allowed_quantity'] = null; // Frontend knows there's a limit but not the value
            } else {
                $data['has_quantity_limits'] = false;
            }
        }

        // Add manufacturer-specific data if requested
        if ($options['include_manufacturer_info'] ?? $options['include_manufacturer_data'] ?? false) {
            $manufacturer = Manufacturer::find($product->manufacturer_id);
            if ($manufacturer) {
                $data['signature_required'] = $manufacturer->signature_required ?? false;
                $data['docuseal_template_id'] = $manufacturer->docuseal_order_form_template_id;
                $data['has_onboarding'] = $product->manufacturer_requires_onboarding ?? false;
            }
        }

        // Add specific fields for search results
        if ($options['include_manufacturer_info'] ?? false) {
            $data['code'] = $product->q_code ?? $product->hcpcs_code ?? '';
            $data['graphSizes'] = $data['available_sizes'] ?? [];
        }

        // Add timestamps if requested
        if ($options['include_timestamps'] ?? false) {
            $data['created_at'] = $product->created_at;
            $data['updated_at'] = $product->updated_at;
        }
    }

    /**
     * Get user permissions for frontend use
     *
     * @param User $user
     * @return array
     */
    public function getUserPermissions(User $user): array
    {
        return [
            'can_view_financials' => $user->hasAnyPermission(['view-financials', 'manage-financials']),
            'can_see_discounts' => $user->hasPermission('view-discounts'),
            'can_see_msc_pricing' => $user->hasPermission('view-msc-pricing'),
            'can_see_order_totals' => $user->hasPermission('view-order-totals'),
            'can_manage_products' => $user->hasPermission('manage-products'),
            'is_provider' => $user->hasRole('provider'),
            'is_office_manager' => $user->hasRole('office_manager'),
            'is_admin' => $user->hasRole('admin') || $user->hasRole('super_admin'),
        ];
    }

    /**
     * Get user's pricing access level
     *
     * @param User $user
     * @return string
     */
    private function getUserPricingAccessLevel(User $user): string
    {
        if ($user->hasPermission('manage-products')) {
            return 'full';
        } elseif ($user->hasPermission('view-msc-pricing')) {
            return 'msc';
        } elseif ($user->hasPermission('view-national-asp')) {
            return 'asp';
        }

        return 'none';
    }

    /**
     * Get user's commission access level
     *
     * @param User $user
     * @return string
     */
    private function getUserCommissionAccessLevel(User $user): string
    {
        if ($user->hasPermission('manage-financials')) {
            return 'full';
        } elseif ($user->hasPermission('view-financials')) {
            return 'view';
        }

        return 'none';
    }

    /**
     * Get product statistics for dashboard
     *
     * @param User $user
     * @return array
     */
    public function getProductStats(User $user): array
    {
        $stats = [
            'total_products' => Product::count(),
        ];

        if ($user->hasRole('provider')) {
            $stats['onboarded_products'] = Product::whereHas('activeProviders', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })->count();
        } else {
            $stats['active_products'] = Product::whereNull('deleted_at')->count();
            $stats['inactive_products'] = Product::onlyTrashed()->count();
        }

        if ($user->hasPermission('manage-products')) {
            $stats['categories_count'] = Product::distinct('category')->count('category');
            $stats['manufacturers_count'] = Product::distinct('manufacturer')->count('manufacturer');
        }

        return $stats;
    }
}

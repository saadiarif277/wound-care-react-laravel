<?php

namespace App\Repositories;

use App\Models\Order\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository
{
    /**
     * Get products with filtering and pagination
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getFilteredProducts(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Product::query();

        // Apply common filters
        $this->applyCommonFilters($query, $filters);

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get products for provider based on onboarding status
     *
     * @param User $user
     * @param array $filters
     * @return Collection
     */
    public function getProviderProducts(User $user, array $filters = []): Collection
    {
        $query = Product::active();

        // Handle Q-code optimization for performance
        if (!empty($filters['onboarded_q_codes'])) {
            $qCodes = is_array($filters['onboarded_q_codes']) 
                ? $filters['onboarded_q_codes'] 
                : explode(',', $filters['onboarded_q_codes']);
            
            $qCodes = array_map('trim', $qCodes);
            $qCodes = array_filter($qCodes);

            if (!empty($qCodes)) {
                $query->whereIn('q_code', $qCodes);
            } else {
                // Provider has no onboarded products
                return collect();
            }
        } else {
            // Filter by provider's onboarded products
            $query->whereHas('activeProviders', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        // Apply additional filters
        $this->applyCommonFilters($query, $filters);

        return $query->with(['activeSizes'])
            ->orderBy('price_per_sq_cm', 'desc')
            ->get();
    }

    /**
     * Get all active products with basic filtering
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllProducts(array $filters = []): Collection
    {
        $query = Product::active();

        $this->applyCommonFilters($query, $filters);

        return $query->select([
                'id', 'name', 'sku', 'q_code', 'manufacturer', 'category',
                'description', 'price_per_sq_cm', 'available_sizes',
                'image_url', 'commission_rate', 'size_options', 'size_pricing'
            ])
            ->orderBy('price_per_sq_cm', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get product recommendations based on clinical criteria
     *
     * @param array $criteria
     * @param int $limit
     * @return Collection
     */
    public function getRecommendations(array $criteria, int $limit = 6): Collection
    {
        $query = Product::active();

        // Filter by wound type if provided
        if (!empty($criteria['wound_type'])) {
            $woundType = $criteria['wound_type'];
            
            if ($woundType === 'DFU') {
                $query->whereIn('category', ['SkinSubstitute', 'Biologic']);
            } elseif ($woundType === 'VLU') {
                $query->where('category', 'SkinSubstitute');
            }
        }

        // Filter by wound size if provided
        if (!empty($criteria['wound_size'])) {
            $woundSize = floatval($criteria['wound_size']);
            $query->where(function ($q) use ($woundSize) {
                $q->whereJsonContains('available_sizes', $woundSize)
                    ->orWhereJsonLength('available_sizes', '>', 0);
            });
        }

        // Apply category filter using common filters
        if (!empty($criteria['category'])) {
            $this->applyCommonFilters($query, ['category' => $criteria['category']]);
        }

        return $query->orderBy('price_per_sq_cm', 'asc') // Cost-effective first
            ->limit($limit)
            ->get();
    }

    /**
     * Get products by specific criteria
     *
     * @param array $criteria
     * @return Collection
     */
    public function getProductsByCriteria(array $criteria): Collection
    {
        $query = Product::query();

        // Active status
        if (isset($criteria['active'])) {
            $query->where('is_active', $criteria['active']);
        }

        // Specific IDs
        if (!empty($criteria['ids'])) {
            $query->whereIn('id', $criteria['ids']);
        }

        // Q-codes
        if (!empty($criteria['q_codes'])) {
            $query->whereIn('q_code', $criteria['q_codes']);
        }

        // Apply common filters for manufacturer and category
        $commonFilters = [];
        if (!empty($criteria['manufacturer'])) {
            $commonFilters['manufacturer'] = $criteria['manufacturer'];
        }
        if (!empty($criteria['category'])) {
            $commonFilters['category'] = $criteria['category'];
        }
        if (!empty($commonFilters)) {
            $this->applyCommonFilters($query, $commonFilters);
        }

        // Price range
        if (!empty($criteria['min_price'])) {
            $query->where('price_per_sq_cm', '>=', $criteria['min_price']);
        }
        if (!empty($criteria['max_price'])) {
            $query->where('price_per_sq_cm', '<=', $criteria['max_price']);
        }

        return $query->get();
    }

    /**
     * Check if provider has onboarded products
     *
     * @param User $user
     * @return bool
     */
    public function providerHasOnboardedProducts(User $user): bool
    {
        return Product::whereHas('activeProviders', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        })->exists();
    }

    /**
     * Get repository-level product statistics
     *
     * @return array
     */
    public function getRepositoryStats(): array
    {
        return [
            'total_products' => Product::count(),
            'active_products' => Product::where('is_active', true)->count(),
            'inactive_products' => Product::where('is_active', false)->count(),
            'categories' => Product::distinct()->whereNotNull('category')->count('category'),
            'manufacturers' => Product::distinct()->whereNotNull('manufacturer')->count('manufacturer'),
        ];
    }

    /**
     * Get distinct values for filtering
     *
     * @return array
     */
    public function getFilterOptions(): array
    {
        return [
            'categories' => Product::distinct()->whereNotNull('category')->pluck('category')->filter()->sort()->values(),
            'manufacturers' => Product::distinct()->whereNotNull('manufacturer')->pluck('manufacturer')->filter()->sort()->values(),
        ];
    }

    /**
     * Apply common filters to query
     *
     * @param Builder $query
     * @param array $filters
     */
    private function applyCommonFilters(Builder $query, array $filters): void
    {
        // Search filter (supports both 'q' and 'search' parameters)
        $searchTerm = $filters['q'] ?? $filters['search'] ?? null;
        if (!empty($searchTerm)) {
            $query->search($searchTerm);
        }

        // Category filter
        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }

        // Manufacturer filter
        if (!empty($filters['manufacturer'])) {
            $query->byManufacturer($filters['manufacturer']);
        }

        // Active filter (if include_inactive is not set or false, filter to active only)
        if (!isset($filters['include_inactive']) || !$filters['include_inactive']) {
            $query->where('is_active', true);
        }
    }
} 
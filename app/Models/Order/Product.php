<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'msc_products';

    protected $fillable = [
        'sku',
        'name',
        'description',
        'manufacturer',
        'manufacturer_id',
        'category',
        'category_id',
        'national_asp',
        'mue',
        'cms_last_updated',
        'price_per_sq_cm',
        'q_code',
        'available_sizes',
        'graph_type',
        'image_url',
        'document_urls',
        'is_active',
        'commission_rate',
    ];

    protected $casts = [
        'available_sizes' => 'array',
        'document_urls' => 'array',
        'national_asp' => 'decimal:2',
        'mue' => 'integer',
        'cms_last_updated' => 'datetime',
        'price_per_sq_cm' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Scope to get only active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter by manufacturer
     */
    public function scopeByManufacturer($query, $manufacturer)
    {
        return $query->where('manufacturer', $manufacturer);
    }

    /**
     * Scope to search products
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function ($query) use ($term) {
            $query->where('name', 'LIKE', "%{$term}%")
                  ->orWhere('q_code', 'LIKE', "%{$term}%")
                  ->orWhere('manufacturer', 'LIKE', "%{$term}%")
                  ->orWhere('description', 'LIKE', "%{$term}%");
        });
    }

    /**
     * Get the manufacturer that owns this product
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class, 'manufacturer_id');
    }

    /**
     * Get the category that owns this product
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Get the product requests that include this product
     */
    public function productRequests(): BelongsToMany
    {
        return $this->belongsToMany(ProductRequest::class, 'product_request_products')
            ->withPivot(['quantity', 'size', 'unit_price', 'total_price'])
            ->withTimestamps();
    }

    /**
     * Calculate MSC price (40% discount from National ASP)
     */
    public function getMscPriceAttribute()
    {
        return $this->price_per_sq_cm * 0.6;
    }

    /**
     * Get total price for a specific size
     */
    public function getTotalPrice($size, $useNationalAsp = false)
    {
        $pricePerSqCm = $useNationalAsp ? $this->price_per_sq_cm : $this->msc_price;
        return $pricePerSqCm * $size;
    }

    /**
     * Get formatted price per sq cm
     */
    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->price_per_sq_cm, 2);
    }

    /**
     * Get formatted MSC price per sq cm
     */
    public function getFormattedMscPriceAttribute()
    {
        return '$' . number_format($this->msc_price, 2);
    }

    /**
     * Check if product has specific size available
     */
    public function hasSize($size)
    {
        return in_array($size, $this->available_sizes ?? []);
    }

    /**
     * Get product categories for filtering
     */
    public static function getCategories()
    {
        return self::active()
            ->distinct()
            ->pluck('category')
            ->filter()
            ->sort()
            ->values();
    }

    /**
     * Get manufacturers for filtering
     */
    public static function getManufacturers()
    {
        return self::active()
            ->distinct()
            ->pluck('manufacturer')
            ->filter()
            ->sort()
            ->values();
    }

    /**
     * Providers that have been onboarded with this product
     */
    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'provider_products', 'product_id', 'user_id')
            ->withPivot(['onboarded_at', 'onboarding_status', 'expiration_date', 'notes'])
            ->withTimestamps();
    }

    /**
     * Active providers who can use this product
     */
    public function activeProviders(): BelongsToMany
    {
        return $this->providers()
            ->wherePivot('onboarding_status', 'active')
            ->where(function ($query) {
                $query->whereNull('provider_products.expiration_date')
                    ->orWhere('provider_products.expiration_date', '>=', now());
            });
    }

    /**
     * Check if product is available for a specific provider
     */
    public function isAvailableForProvider($userId): bool
    {
        return $this->activeProviders()->where('users.id', $userId)->exists();
    }

    /**
     * Check if a requested quantity exceeds CMS MUE limits
     */
    public function exceedsMueLimit(int $quantity): bool
    {
        return $this->mue !== null && $quantity > $this->mue;
    }

    /**
     * Get maximum allowed quantity based on MUE
     */
    public function getMaxAllowedQuantity(): ?int
    {
        return $this->mue;
    }

    /**
     * Check if product has MUE enforcement
     */
    public function hasMueEnforcement(): bool
    {
        return $this->mue !== null;
    }

    /**
     * Get CMS enrichment status
     */
    public function getCmsStatusAttribute(): string
    {
        if (!$this->cms_last_updated) {
            return 'not_synced';
        }

        $daysSinceUpdate = $this->cms_last_updated->diffInDays(now());

        if ($daysSinceUpdate > 90) {
            return 'stale';
        } elseif ($daysSinceUpdate > 30) {
            return 'needs_update';
        }

        return 'current';
    }

    /**
     * Validate order quantity against MUE limits
     */
    public function validateOrderQuantity(int $quantity): array
    {
        $result = [
            'valid' => true,
            'warnings' => [],
            'errors' => []
        ];

        if ($this->exceedsMueLimit($quantity)) {
            $result['valid'] = false;
            $result['errors'][] = "Requested quantity ({$quantity}) exceeds CMS MUE limit ({$this->mue}) for Q-code {$this->q_code}";
        }

        return $result;
    }
}

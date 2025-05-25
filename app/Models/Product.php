<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'price_per_sq_cm' => 'decimal:4',
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


}

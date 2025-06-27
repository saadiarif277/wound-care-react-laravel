<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Boot the model and set up event listeners
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::updating(function ($category) {
            if ($category->isDirty('name')) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * Get all products in this category
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Get active products in this category
     */
    public function activeProducts(): HasMany
    {
        return $this->products()->where('is_active', true);
    }

    /**
     * Scope to get only active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Scope to search categories by name
     */
    public function scopeSearch($query, $term)
    {
        return $query->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('description', 'LIKE', "%{$term}%");
    }

    /**
     * Get the route key for the model
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * Get category's total products count
     */
    public function getProductsCountAttribute()
    {
        return $this->products()->count();
    }

    /**
     * Get category's active products count
     */
    public function getActiveProductsCountAttribute()
    {
        return $this->activeProducts()->count();
    }

    /**
     * Check if category has any active products
     */
    public function hasActiveProducts()
    {
        return $this->activeProducts()->exists();
    }

    /**
     * Get all categories for dropdown options
     */
    public static function getOptions()
    {
        return self::active()
            ->ordered()
            ->pluck('name', 'id');
    }

    /**
     * Get categories with product counts
     */
    public static function withProductCounts()
    {
        return self::active()
            ->withCount(['products', 'activeProducts'])
            ->ordered()
            ->get();
    }

    /**
     * Create category from name if it doesn't exist
     */
    public static function findOrCreateByName($name)
    {
        if (empty($name)) {
            return null;
        }

        return self::firstOrCreate(
            ['name' => $name],
            [
                'slug' => Str::slug($name),
                'is_active' => true,
                'sort_order' => self::max('sort_order') + 1
            ]
        );
    }

    /**
     * Get common product categories for wound care
     */
    public static function getWoundCareCategories()
    {
        $commonCategories = [
            'SkinSubstitute' => 'Skin Substitutes',
            'Biologic' => 'Biologics',
            'CollageMatrix' => 'Collagen Matrix',
            'Antimicrobial' => 'Antimicrobial Dressings',
            'Foam' => 'Foam Dressings',
            'Hydrogel' => 'Hydrogel Dressings',
            'Alginate' => 'Alginate Dressings',
            'Compression' => 'Compression Systems',
            'Offloading' => 'Offloading Devices',
            'WoundCare' => 'General Wound Care'
        ];

        return $commonCategories;
    }
}

<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Models\Docuseal\DocusealFolder;
use App\Models\Docuseal\DocusealTemplate;

class Manufacturer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'contact_email',
        'contact_phone',
        'address',
        'website',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'address' => 'array',
    ];

    /**
     * Boot the model and set up event listeners
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($manufacturer) {
            if (empty($manufacturer->slug)) {
                $manufacturer->slug = Str::slug($manufacturer->name);
            }
        });

        static::updating(function ($manufacturer) {
            if ($manufacturer->isDirty('name')) {
                $manufacturer->slug = Str::slug($manufacturer->name);
            }
        });
    }

    /**
     * Get all products for this manufacturer
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'manufacturer_id');
    }

    /**
     * Get active products for this manufacturer
     */
    public function activeProducts(): HasMany
    {
        return $this->products()->where('is_active', true);
    }

    /**
     * Get the Docuseal folder for this manufacturer
     */
    public function docusealFolder(): HasOne
    {
        return $this->hasOne(DocusealFolder::class, 'manufacturer_id');
    }

    /**
     * Get the Docuseal templates for this manufacturer
     */
    public function docusealTemplates(): HasMany
    {
        return $this->hasMany(DocusealTemplate::class, 'manufacturer_id');
    }

    /**
     * Get the IVR template for this manufacturer
     */
    public function ivrTemplate()
    {
        return $this->docusealTemplates()
            ->where('document_type', 'IVR')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get the onboarding template for this manufacturer
     */
    public function onboardingTemplate()
    {
        return $this->docusealTemplates()
            ->where('document_type', 'OnboardingForm')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get the order form template for this manufacturer
     */
    public function orderFormTemplate()
    {
        return $this->docusealTemplates()
            ->where('document_type', 'OrderForm')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Scope to get only active manufacturers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to search manufacturers by name
     */
    public function scopeSearch($query, $term)
    {
        return $query->where('name', 'LIKE', "%{$term}%");
    }

    /**
     * Get the route key for the model
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * Get manufacturer's total products count
     */
    public function getProductsCountAttribute()
    {
        return $this->products()->count();
    }

    /**
     * Get manufacturer's active products count
     */
    public function getActiveProductsCountAttribute()
    {
        return $this->activeProducts()->count();
    }

    /**
     * Get formatted contact information
     */
    public function getFormattedContactAttribute()
    {
        $contact = [];

        if ($this->contact_email) {
            $contact[] = $this->contact_email;
        }

        if ($this->contact_phone) {
            $contact[] = $this->contact_phone;
        }

        return implode(' | ', $contact);
    }

    /**
     * Check if manufacturer has any active products
     */
    public function hasActiveProducts()
    {
        return $this->activeProducts()->exists();
    }

    /**
     * Get all manufacturers for dropdown options
     */
    public static function getOptions()
    {
        return self::active()
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    /**
     * Create manufacturer from name if it doesn't exist
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
                'is_active' => true
            ]
        );
    }
}

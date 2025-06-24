<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSize extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'size_label',
        'size_type',
        'length_mm',
        'width_mm',
        'diameter_mm',
        'area_cm2',
        'display_label',
        'sort_order',
        'is_active',
        'size_specific_price',
        'price_per_unit',
        'sku_suffix',
        'is_available',
        'availability_notes',
    ];

    protected $casts = [
        'length_mm' => 'decimal:2',
        'width_mm' => 'decimal:2',
        'diameter_mm' => 'decimal:2',
        'area_cm2' => 'decimal:2',
        'size_specific_price' => 'decimal:2',
        'price_per_unit' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_available' => 'boolean',
    ];

    /**
     * Get the product that this size belongs to
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Order\Product::class);
    }

    /**
     * Scope to get active sizes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get available sizes
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    /**
     * Scope to get sizes by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('size_type', $type);
    }

    /**
     * Scope to order by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('area_cm2');
    }

    /**
     * Parse size label and extract dimensions
     */
    public static function parseSizeLabel($sizeLabel)
    {
        $sizeLabel = trim($sizeLabel);
        $result = [
            'size_type' => 'custom',
            'length_mm' => null,
            'width_mm' => null,
            'diameter_mm' => null,
            'area_cm2' => null,
            'display_label' => $sizeLabel,
        ];

        // Handle circular sizes (disc format)
        if (preg_match('/(\d+(?:\.\d+)?)\s*mm\s*disc?/i', $sizeLabel, $matches)) {
            $diameter = floatval($matches[1]);
            $result['size_type'] = 'circular';
            $result['diameter_mm'] = $diameter;
            $result['area_cm2'] = round(pi() * pow($diameter / 20, 2), 2); // Convert to cm² (π * r²)
            return $result;
        }

        // Handle rectangular sizes (e.g., "2x4cm", "1.5x1.5cm")
        if (preg_match('/(\d+(?:\.\d+)?)\s*x\s*(\d+(?:\.\d+)?)\s*cm/i', $sizeLabel, $matches)) {
            $length = floatval($matches[1]) * 10; // Convert cm to mm
            $width = floatval($matches[2]) * 10;

            $result['size_type'] = ($length == $width) ? 'square' : 'rectangular';
            $result['length_mm'] = $length;
            $result['width_mm'] = $width;
            $result['area_cm2'] = round(($length * $width) / 100, 2); // Convert mm² to cm²
            return $result;
        }

        return $result;
    }

    /**
     * Create size from label
     */
    public static function createFromLabel($productId, $sizeLabel, $sortOrder = 0)
    {
        $parsed = static::parseSizeLabel($sizeLabel);

        return static::firstOrCreate(
            [
                'product_id' => $productId,
                'size_label' => $sizeLabel,
            ],
            [
                'size_type' => $parsed['size_type'],
                'length_mm' => $parsed['length_mm'],
                'width_mm' => $parsed['width_mm'],
                'diameter_mm' => $parsed['diameter_mm'],
                'area_cm2' => $parsed['area_cm2'],
                'display_label' => $parsed['display_label'],
                'sort_order' => $sortOrder,
            ]
        );
    }

    /**
     * Get formatted size for display
     */
    public function getFormattedSizeAttribute()
    {
        switch ($this->size_type) {
            case 'circular':
                return $this->diameter_mm . 'mm disc';
            case 'rectangular':
                return ($this->length_mm / 10) . 'x' . ($this->width_mm / 10) . 'cm';
            case 'square':
                return ($this->length_mm / 10) . 'x' . ($this->length_mm / 10) . 'cm';
            default:
                return $this->display_label;
        }
    }

    /**
     * Get size in square centimeters
     */
    public function getAreaCm2Attribute()
    {
        if ($this->attributes['area_cm2']) {
            return $this->attributes['area_cm2'];
        }

        // Calculate area if not stored
        switch ($this->size_type) {
            case 'circular':
                if ($this->diameter_mm) {
                    return round(pi() * pow($this->diameter_mm / 20, 2), 2);
                }
                break;
            case 'rectangular':
            case 'square':
                if ($this->length_mm && $this->width_mm) {
                    return round(($this->length_mm * $this->width_mm) / 100, 2);
                }
                break;
        }

        return 0;
    }

    /**
     * Get the effective price for this size
     */
    public function getEffectivePrice()
    {
        if ($this->size_specific_price) {
            return $this->size_specific_price;
        }

        if ($this->price_per_unit) {
            return $this->price_per_unit;
        }

        // Fall back to product's price per cm² if available
        if ($this->product && $this->product->price_per_sq_cm && $this->area_cm2) {
            return $this->product->price_per_sq_cm * $this->area_cm2;
        }

        return $this->product?->national_asp ?? 0;
    }

    /**
     * Get full SKU including size suffix
     */
    public function getFullSkuAttribute()
    {
        $baseSku = $this->product?->sku ?? '';
        return $this->sku_suffix ? $baseSku . '-' . $this->sku_suffix : $baseSku;
    }

    /**
     * Check if size is suitable for a given wound area
     */
    public function isSuitableForWoundArea($woundAreaCm2, $marginPercentage = 20)
    {
        $requiredArea = $woundAreaCm2 * (1 + $marginPercentage / 100);
        return $this->area_cm2 >= $requiredArea;
    }

    /**
     * Get recommended sizes for a wound area
     */
    public static function getRecommendedSizes($productId, $woundAreaCm2, $marginPercentage = 20)
    {
        $requiredArea = $woundAreaCm2 * (1 + $marginPercentage / 100);

        return static::where('product_id', $productId)
            ->active()
            ->available()
            ->where('area_cm2', '>=', $requiredArea)
            ->ordered()
            ->get();
    }

    /**
     * Auto-generate sort order based on area
     */
    public function generateSortOrder()
    {
        if (!$this->area_cm2) {
            return 999; // Put unknown sizes at the end
        }

        // Sort by area (smaller first)
        return intval($this->area_cm2 * 10); // Multiply by 10 to handle decimals
    }

    /**
     * Boot method to auto-calculate values
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Auto-calculate area if not provided
            if (!$model->area_cm2) {
                $model->area_cm2 = $model->getAreaCm2Attribute();
            }

            // Auto-generate sort order if not provided
            if (!$model->sort_order) {
                $model->sort_order = $model->generateSortOrder();
            }

            // Auto-generate display label if not provided
            if (!$model->display_label) {
                $model->display_label = $model->size_label;
            }
        });
    }
}

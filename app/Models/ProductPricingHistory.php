<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ProductPricingHistory extends Model
{
    use HasFactory;

    protected $table = 'product_pricing_history';

    protected $fillable = [
        'product_id',
        'q_code',
        'product_name',
        'national_asp',
        'price_per_sq_cm',
        'msc_price',
        'commission_rate',
        'mue',
        'change_type',
        'changed_by_type',
        'changed_by_id',
        'changed_fields',
        'previous_values',
        'change_reason',
        'effective_date',
        'cms_sync_date',
        'source',
        'metadata',
    ];

    protected $casts = [
        'national_asp' => 'decimal:2',
        'price_per_sq_cm' => 'decimal:2',
        'msc_price' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'mue' => 'integer',
        'changed_fields' => 'array',
        'previous_values' => 'array',
        'metadata' => 'array',
        'effective_date' => 'datetime',
        'cms_sync_date' => 'datetime',
    ];

    /**
     * Get the product that this pricing history belongs to
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Order\Product::class);
    }

    /**
     * Get the user who made the change (if applicable)
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }

    /**
     * Scope to get pricing history for a specific product
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope to get pricing history for a specific Q-code
     */
    public function scopeForQCode($query, $qCode)
    {
        return $query->where('q_code', $qCode);
    }

    /**
     * Scope to get pricing history by change type
     */
    public function scopeByChangeType($query, $changeType)
    {
        return $query->where('change_type', $changeType);
    }

    /**
     * Scope to get pricing history within a date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('effective_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get CMS-synced pricing history
     */
    public function scopeCmsSynced($query)
    {
        return $query->where('source', 'cms')->whereNotNull('cms_sync_date');
    }

    /**
     * Get the pricing that was effective at a specific date
     */
    public static function getPricingAsOf($productId, $date)
    {
        return static::forProduct($productId)
            ->where('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc')
            ->first();
    }

    /**
     * Get pricing history for audit trail
     */
    public static function getAuditTrail($productId, $limit = 50)
    {
        return static::forProduct($productId)
            ->with('changedBy:id,first_name,last_name,email')
            ->orderBy('effective_date', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Create a new pricing history record
     */
    public static function recordChange(
        $product,
        $changeType,
        $changedFields,
        $previousValues = null,
        $changedBy = null,
        $reason = null,
        $metadata = null
    ) {
        return static::create([
            'product_id' => $product->id,
            'q_code' => $product->q_code,
            'product_name' => $product->name,
            'national_asp' => $product->national_asp,
            'price_per_sq_cm' => $product->price_per_sq_cm,
            'msc_price' => $product->msc_price,
            'commission_rate' => $product->commission_rate,
            'mue' => $product->mue,
            'change_type' => $changeType,
            'changed_by_type' => $changedBy ? 'user' : 'system',
            'changed_by_id' => $changedBy?->id,
            'changed_fields' => $changedFields,
            'previous_values' => $previousValues,
            'change_reason' => $reason,
            'effective_date' => now(),
            'cms_sync_date' => $changeType === 'cms_sync' ? now() : null,
            'source' => $changeType === 'cms_sync' ? 'cms' : 'manual',
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get formatted change summary
     */
    public function getChangeSummaryAttribute()
    {
        if (!$this->changed_fields || !$this->previous_values) {
            return 'Initial record';
        }

        $changes = [];
        foreach ($this->changed_fields as $field) {
            $oldValue = $this->previous_values[$field] ?? 'null';
            $newValue = $this->attributes[$field] ?? 'null';
            $changes[] = "{$field}: {$oldValue} → {$newValue}";
        }

        return implode(', ', $changes);
    }

    /**
     * Check if this record represents a price increase
     */
    public function isPriceIncrease(): bool
    {
        if (!$this->previous_values || !in_array('national_asp', $this->changed_fields ?? [])) {
            return false;
        }

        $oldPrice = $this->previous_values['national_asp'] ?? 0;
        return $this->national_asp > $oldPrice;
    }

    /**
     * Check if this record represents a price decrease
     */
    public function isPriceDecrease(): bool
    {
        if (!$this->previous_values || !in_array('national_asp', $this->changed_fields ?? [])) {
            return false;
        }

        $oldPrice = $this->previous_values['national_asp'] ?? 0;
        return $this->national_asp < $oldPrice;
    }

    /**
     * Get price change percentage
     */
    public function getPriceChangePercentage(): ?float
    {
        if (!$this->previous_values || !in_array('national_asp', $this->changed_fields ?? [])) {
            return null;
        }

        $oldPrice = $this->previous_values['national_asp'] ?? 0;
        if ($oldPrice == 0) {
            return null;
        }

        return (($this->national_asp - $oldPrice) / $oldPrice) * 100;
    }
}

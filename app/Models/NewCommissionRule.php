<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NewCommissionRule extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'commission_rules';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the ID.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'rule_name',
        'applies_to_products',
        'applies_to_categories',
        'applies_to_facilities',
        'commission_type',
        'base_rate',
        'tier_definitions',
        'split_rules',
        'effective_date',
        'end_date',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'applies_to_products' => 'array',
        'applies_to_categories' => 'array',
        'applies_to_facilities' => 'array',
        'base_rate' => 'decimal:4',
        'tier_definitions' => 'array',
        'split_rules' => 'array',
        'effective_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
        });
    }

    /**
     * Get the tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Get commission records using this rule.
     */
    public function commissionRecords(): HasMany
    {
        return $this->hasMany(NewCommissionRecord::class, 'rule_id');
    }

    /**
     * Scope to active rules.
     */
    public function scopeActive($query)
    {
        return $query->where('effective_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });
    }

    /**
     * Scope by commission type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('commission_type', $type);
    }

    /**
     * Check if rule is active.
     */
    public function isActive(): bool
    {
        return $this->effective_date->isPast() && 
               (is_null($this->end_date) || $this->end_date->isFuture());
    }

    /**
     * Check if rule applies to a product.
     */
    public function appliesToProduct(string $productId): bool
    {
        if (empty($this->applies_to_products)) {
            return false;
        }
        return in_array($productId, $this->applies_to_products);
    }

    /**
     * Check if rule applies to a category.
     */
    public function appliesToCategory(string $category): bool
    {
        if (empty($this->applies_to_categories)) {
            return false;
        }
        return in_array($category, $this->applies_to_categories);
    }

    /**
     * Check if rule applies to a facility.
     */
    public function appliesToFacility(string $facilityId): bool
    {
        if (empty($this->applies_to_facilities)) {
            return false;
        }
        return in_array($facilityId, $this->applies_to_facilities);
    }

    /**
     * Calculate commission amount.
     */
    public function calculateCommission(float $baseAmount): float
    {
        switch ($this->commission_type) {
            case 'percentage':
                return $baseAmount * ($this->base_rate / 100);
                
            case 'flat_amount':
                return $this->base_rate;
                
            case 'tiered':
                return $this->calculateTieredCommission($baseAmount);
                
            default:
                return 0;
        }
    }

    /**
     * Calculate tiered commission.
     */
    protected function calculateTieredCommission(float $baseAmount): float
    {
        if (empty($this->tier_definitions)) {
            return 0;
        }

        // Find applicable tier
        foreach ($this->tier_definitions as $tier) {
            $min = $tier['min'] ?? 0;
            $max = $tier['max'] ?? PHP_FLOAT_MAX;
            
            if ($baseAmount >= $min && $baseAmount <= $max) {
                return $baseAmount * (($tier['rate'] ?? 0) / 100);
            }
        }

        return 0;
    }

    /**
     * Get split rules for parent/sub-rep.
     */
    public function getSplitRules(): array
    {
        return $this->split_rules ?? [
            'rep_percentage' => 80,
            'parent_percentage' => 20,
        ];
    }

    /**
     * Calculate split amounts.
     */
    public function calculateSplitAmounts(float $totalCommission): array
    {
        $splitRules = $this->getSplitRules();
        
        return [
            'rep_amount' => $totalCommission * ($splitRules['rep_percentage'] / 100),
            'parent_amount' => $totalCommission * ($splitRules['parent_percentage'] / 100),
        ];
    }
}
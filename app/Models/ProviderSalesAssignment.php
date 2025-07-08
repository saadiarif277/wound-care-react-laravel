<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProviderSalesAssignment extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'provider_sales_assignments';

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
        'provider_fhir_id',
        'sales_rep_id',
        'facility_id',
        'relationship_type',
        'commission_split_percentage',
        'override_commission_rate',
        'can_create_orders',
        'assigned_from',
        'assigned_until',
        'is_active',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'commission_split_percentage' => 'decimal:2',
        'override_commission_rate' => 'decimal:2',
        'can_create_orders' => 'boolean',
        'is_active' => 'boolean',
        'assigned_from' => 'date',
        'assigned_until' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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
     * Get the sales rep.
     */
    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRep::class, 'sales_rep_id');
    }

    /**
     * Get the facility.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'facility_id');
    }

    /**
     * Scope to active assignments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('assigned_until')
                    ->orWhere('assigned_until', '>=', now());
            });
    }

    /**
     * Scope to primary assignments.
     */
    public function scopePrimary($query)
    {
        return $query->where('relationship_type', 'primary');
    }

    /**
     * Scope by provider.
     */
    public function scopeForProvider($query, string $providerFhirId)
    {
        return $query->where('provider_fhir_id', $providerFhirId);
    }

    /**
     * Check if assignment is active.
     */
    public function isActive(): bool
    {
        return $this->is_active && 
               (is_null($this->assigned_until) || $this->assigned_until->isFuture());
    }

    /**
     * Check if is primary assignment.
     */
    public function isPrimary(): bool
    {
        return $this->relationship_type === 'primary';
    }

    /**
     * Get effective commission rate.
     */
    public function getEffectiveCommissionRate(): float
    {
        // Use override rate if set, otherwise fall back to rep's default
        if (!is_null($this->override_commission_rate)) {
            return $this->override_commission_rate;
        }

        return $this->salesRep->commission_rate_direct;
    }

    /**
     * Calculate commission amount.
     */
    public function calculateCommission(float $orderAmount): float
    {
        $rate = $this->getEffectiveCommissionRate();
        $splitPercentage = $this->commission_split_percentage / 100;
        
        return $orderAmount * ($rate / 100) * $splitPercentage;
    }
}
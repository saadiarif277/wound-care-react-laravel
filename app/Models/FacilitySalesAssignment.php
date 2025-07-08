<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FacilitySalesAssignment extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'facility_sales_assignments';

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
        'facility_id',
        'sales_rep_id',
        'relationship_type',
        'commission_split_percentage',
        'can_create_orders',
        'can_view_all_providers',
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
        'can_create_orders' => 'boolean',
        'can_view_all_providers' => 'boolean',
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
     * Scope to coordinator assignments.
     */
    public function scopeCoordinators($query)
    {
        return $query->where('relationship_type', 'coordinator');
    }

    /**
     * Scope by facility.
     */
    public function scopeForFacility($query, string $facilityId)
    {
        return $query->where('facility_id', $facilityId);
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
     * Check if is coordinator.
     */
    public function isCoordinator(): bool
    {
        return $this->relationship_type === 'coordinator';
    }

    /**
     * Check if is manager.
     */
    public function isManager(): bool
    {
        return $this->relationship_type === 'manager';
    }

    /**
     * Check if has commission eligibility.
     */
    public function hasCommissionEligibility(): bool
    {
        return $this->commission_split_percentage > 0;
    }
}
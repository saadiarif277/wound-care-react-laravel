<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class SalesRep extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'sales_reps';

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
        'user_id',
        'parent_rep_id',
        'territory',
        'region',
        'commission_rate_direct',
        'sub_rep_parent_share_percentage',
        'rep_type',
        'commission_tier',
        'can_have_sub_reps',
        'performance_metrics',
        'is_active',
        'hired_date',
        'terminated_date',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'commission_rate_direct' => 'decimal:2',
        'sub_rep_parent_share_percentage' => 'decimal:2',
        'can_have_sub_reps' => 'boolean',
        'is_active' => 'boolean',
        'performance_metrics' => 'array',
        'hired_date' => 'date',
        'terminated_date' => 'date',
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
     * Get the user account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(NewUser::class, 'user_id');
    }

    /**
     * Get the parent sales rep.
     */
    public function parentRep(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_rep_id');
    }

    /**
     * Get the sub-reps under this rep.
     */
    public function subReps(): HasMany
    {
        return $this->hasMany(self::class, 'parent_rep_id');
    }

    /**
     * Get provider assignments.
     */
    public function providerAssignments(): HasMany
    {
        return $this->hasMany(ProviderSalesAssignment::class, 'sales_rep_id');
    }

    /**
     * Get facility assignments.
     */
    public function facilityAssignments(): HasMany
    {
        return $this->hasMany(FacilitySalesAssignment::class, 'sales_rep_id');
    }

    /**
     * Get commission records.
     */
    public function commissionRecords(): HasMany
    {
        return $this->hasMany(CommissionRecord::class, 'sales_rep_id');
    }

    /**
     * Get commission records as parent rep.
     */
    public function parentCommissionRecords(): HasMany
    {
        return $this->hasMany(CommissionRecord::class, 'parent_rep_id');
    }

    /**
     * Get commission payouts.
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(CommissionPayout::class, 'sales_rep_id');
    }

    /**
     * Get commission targets.
     */
    public function targets(): HasMany
    {
        return $this->hasMany(CommissionTarget::class, 'sales_rep_id');
    }

    /**
     * Get orders.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'sales_rep_id');
    }

    /**
     * Scope to active reps.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to reps in a territory.
     */
    public function scopeInTerritory($query, string $territory)
    {
        return $query->where('territory', $territory);
    }

    /**
     * Scope to reps in a region.
     */
    public function scopeInRegion($query, string $region)
    {
        return $query->where('region', $region);
    }

    /**
     * Scope to parent reps only.
     */
    public function scopeParentReps($query)
    {
        return $query->where('can_have_sub_reps', true);
    }

    /**
     * Get full name from user.
     */
    public function getFullNameAttribute(): string
    {
        return $this->user ? $this->user->first_name . ' ' . $this->user->last_name : 'Unknown';
    }

    /**
     * Get email from user.
     */
    public function getEmailAttribute(): string
    {
        return $this->user ? $this->user->email : '';
    }

    /**
     * Check if has sub-reps.
     */
    public function hasSubReps(): bool
    {
        return $this->subReps()->exists();
    }

    /**
     * Check if is sub-rep.
     */
    public function isSubRep(): bool
    {
        return !is_null($this->parent_rep_id);
    }

    /**
     * Get commission rate for a specific context.
     */
    public function getEffectiveCommissionRate(?string $type = null): float
    {
        // Could be enhanced to check for override rates in assignments
        return $this->commission_rate_direct;
    }

    /**
     * Get the hierarchical path.
     */
    public function getHierarchyPath(): string
    {
        $path = [$this->full_name];
        $current = $this;

        while ($current->parentRep) {
            $current = $current->parentRep;
            array_unshift($path, $current->full_name);
        }

        return implode(' > ', $path);
    }

    /**
     * Calculate performance metrics.
     */
    public function calculatePerformanceMetrics(string $period = 'monthly'): array
    {
        // This would calculate various metrics based on orders, commissions, etc.
        // Placeholder for now
        return [
            'revenue' => 0,
            'commission_earned' => 0,
            'orders_count' => 0,
            'new_providers' => 0,
            'target_achievement' => 0,
        ];
    }
}
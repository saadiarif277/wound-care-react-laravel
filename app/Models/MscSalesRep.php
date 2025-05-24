<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MscSalesRep extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'territory',
        'commission_rate_direct',
        'sub_rep_parent_share_percentage',
        'parent_rep_id',
        'is_active',
    ];

    protected $casts = [
        'commission_rate_direct' => 'decimal:2',
        'sub_rep_parent_share_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Get the parent sales rep
     */
    public function parentRep(): BelongsTo
    {
        return $this->belongsTo(MscSalesRep::class, 'parent_rep_id');
    }

    /**
     * Get the sub-reps under this rep
     */
    public function subReps(): HasMany
    {
        return $this->hasMany(MscSalesRep::class, 'parent_rep_id');
    }

    /**
     * Get orders assigned to this sales rep
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'sales_rep_id');
    }

    /**
     * Get commission records for this sales rep
     */
    public function commissionRecords(): HasMany
    {
        return $this->hasMany(CommissionRecord::class, 'sales_rep_id');
    }

    /**
     * Scope to get only active sales reps
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the full hierarchical path for this rep
     */
    public function getHierarchyPath(): string
    {
        $path = [$this->name];
        $current = $this;

        while ($current->parentRep) {
            $current = $current->parentRep;
            array_unshift($path, $current->name);
        }

        return implode(' > ', $path);
    }
}

<?php

namespace App\Models\Commissions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\BelongsToOrganization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CommissionRule extends Model
{
    use SoftDeletes;
    use BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'rule_type',
        'target_type',
        'target_id',
        'percentage_rate',
        'effective_from',
        'effective_until',
        'priority',
        'is_active',
        'description',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
        'percentage_rate' => 'decimal:2',
        'priority' => 'integer',
    ];

    public function target()
    {
        return $this->morphTo();
    }

    public function scopeForOrganization(Builder $query, $organizationId): Builder
    {
        return $query->where(function (Builder $q) use ($organizationId) {
            $q->whereNull($this->getOrganizationIdColumn())
              ->orWhere($this->getOrganizationIdColumn(), $organizationId);
        });
    }

    public static function getApplicableRate($productId, $organizationId, $targetType = 'product')
    {
        $query = static::query();

        if (!(Auth::check() && Auth::user()->canAccessAllOrganizations()) && $organizationId) {
            $query->where(function (Builder $q) use ($organizationId) {
                $q->whereNull((new static())->getOrganizationIdColumn())
                  ->orWhere((new static())->getOrganizationIdColumn(), $organizationId);
            });
        } elseif (Auth::check() && Auth::user()->canAccessAllOrganizations() && $organizationId) {
            $query->where(function (Builder $q) use ($organizationId) {
                $q->whereNull((new static())->getOrganizationIdColumn())
                  ->orWhere((new static())->getOrganizationIdColumn(), $organizationId);
            });
        } else if (!(Auth::check() && Auth::user()->canAccessAllOrganizations()) && !$organizationId){
            $query->whereNull((new static())->getOrganizationIdColumn());
        }

        return $query
            ->where('target_type', $targetType)
            ->where('target_id', $productId)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', now())
            ->where(function (Builder $q) {
                $q->whereNull('effective_until')
                  ->orWhereDate('effective_until', '>=', now());
            })
            ->orderBy($dbRawOrganizationIdSort = DB::raw((new static())->getOrganizationIdColumn() . ' IS NULL'), 'asc')
            ->orderBy('priority', 'desc')
            ->first();
    }
}

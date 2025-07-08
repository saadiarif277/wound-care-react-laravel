<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CommissionTarget extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'commission_targets';

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
        'sales_rep_id',
        'target_year',
        'target_month',
        'target_quarter',
        'revenue_target',
        'commission_target',
        'order_count_target',
        'new_provider_target',
        'category_targets',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'target_year' => 'integer',
        'target_month' => 'integer',
        'target_quarter' => 'integer',
        'revenue_target' => 'decimal:2',
        'commission_target' => 'decimal:2',
        'order_count_target' => 'integer',
        'new_provider_target' => 'integer',
        'category_targets' => 'array',
        'is_active' => 'boolean',
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
     * Scope to active targets.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by year.
     */
    public function scopeForYear($query, int $year)
    {
        return $query->where('target_year', $year);
    }

    /**
     * Scope by month.
     */
    public function scopeForMonth($query, int $month)
    {
        return $query->where('target_month', $month);
    }

    /**
     * Scope by quarter.
     */
    public function scopeForQuarter($query, int $quarter)
    {
        return $query->where('target_quarter', $quarter);
    }

    /**
     * Get period type.
     */
    public function getPeriodTypeAttribute(): string
    {
        if (!is_null($this->target_month)) {
            return 'monthly';
        } elseif (!is_null($this->target_quarter)) {
            return 'quarterly';
        } else {
            return 'yearly';
        }
    }

    /**
     * Get period display.
     */
    public function getPeriodDisplayAttribute(): string
    {
        if (!is_null($this->target_month)) {
            return date('F Y', mktime(0, 0, 0, $this->target_month, 1, $this->target_year));
        } elseif (!is_null($this->target_quarter)) {
            return 'Q' . $this->target_quarter . ' ' . $this->target_year;
        } else {
            return (string) $this->target_year;
        }
    }

    /**
     * Calculate achievement percentage.
     */
    public function calculateAchievement(array $actuals): array
    {
        $achievements = [];

        if ($this->revenue_target > 0 && isset($actuals['revenue'])) {
            $achievements['revenue_achievement'] = ($actuals['revenue'] / $this->revenue_target) * 100;
        }

        if ($this->commission_target > 0 && isset($actuals['commission'])) {
            $achievements['commission_achievement'] = ($actuals['commission'] / $this->commission_target) * 100;
        }

        if ($this->order_count_target > 0 && isset($actuals['order_count'])) {
            $achievements['order_count_achievement'] = ($actuals['order_count'] / $this->order_count_target) * 100;
        }

        if ($this->new_provider_target > 0 && isset($actuals['new_providers'])) {
            $achievements['new_provider_achievement'] = ($actuals['new_providers'] / $this->new_provider_target) * 100;
        }

        // Calculate category achievements
        if (!empty($this->category_targets) && isset($actuals['categories'])) {
            $achievements['category_achievements'] = [];
            foreach ($this->category_targets as $category => $target) {
                if ($target > 0 && isset($actuals['categories'][$category])) {
                    $achievements['category_achievements'][$category] = 
                        ($actuals['categories'][$category] / $target) * 100;
                }
            }
        }

        // Calculate overall achievement
        $achievementValues = array_filter([
            $achievements['revenue_achievement'] ?? null,
            $achievements['commission_achievement'] ?? null,
            $achievements['order_count_achievement'] ?? null,
            $achievements['new_provider_achievement'] ?? null,
        ]);

        if (!empty($achievementValues)) {
            $achievements['overall_achievement'] = array_sum($achievementValues) / count($achievementValues);
        }

        return $achievements;
    }
}
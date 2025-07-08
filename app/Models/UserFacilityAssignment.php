<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserFacilityAssignment extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'user_facility_assignments';

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
        'facility_id',
        'role',
        'can_order',
        'can_view_orders',
        'can_view_financial',
        'can_manage_verifications',
        'can_order_for_providers',
        'is_primary_facility',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'can_order' => 'boolean',
        'can_view_orders' => 'boolean',
        'can_view_financial' => 'boolean',
        'can_manage_verifications' => 'boolean',
        'can_order_for_providers' => 'array',
        'is_primary_facility' => 'boolean',
        'assigned_at' => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

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
            
            $model->assigned_at = now();
        });
    }

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the facility (organization).
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'facility_id');
    }

    /**
     * Check if user can order for a specific provider.
     */
    public function canOrderForProvider(string $providerFhirId): bool
    {
        if (!$this->can_order) {
            return false;
        }

        $allowedProviders = $this->can_order_for_providers ?? [];
        
        // Empty array means can order for all providers
        if (empty($allowedProviders)) {
            return true;
        }

        return in_array($providerFhirId, $allowedProviders);
    }

    /**
     * Scope to primary facility assignments.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary_facility', true);
    }

    /**
     * Scope to assignments with order permissions.
     */
    public function scopeCanOrder($query)
    {
        return $query->where('can_order', true);
    }

    /**
     * Scope to assignments with financial view permissions.
     */
    public function scopeCanViewFinancial($query)
    {
        return $query->where('can_view_financial', true);
    }
}
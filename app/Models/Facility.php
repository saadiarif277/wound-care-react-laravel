<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'facility_type',
        'address',
        'city',
        'state',
        'zip_code',
        'phone',
        'email',
        'npi',
        'business_hours',
        'active',
    ];

    protected $casts = [
        'business_hours' => 'array',
        'active' => 'boolean',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Get the organization this facility belongs to
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get orders associated with this facility
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get users associated with this facility
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Scope to get only active facilities
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Get full address as a formatted string
     */
    public function getFullAddressAttribute(): string
    {
        return sprintf(
            '%s, %s, %s %s',
            $this->address,
            $this->city,
            $this->state,
            $this->zip_code
        );
    }

    /**
     * Get formatted phone number
     */
    public function getFormattedPhoneAttribute(): ?string
    {
        if (!$this->phone) {
            return null;
        }

        $phone = preg_replace('/\D/', '', $this->phone);

        if (strlen($phone) === 10) {
            return sprintf('(%s) %s-%s',
                substr($phone, 0, 3),
                substr($phone, 3, 3),
                substr($phone, 6, 4)
            );
        }

        return $this->phone;
    }
}

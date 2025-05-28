<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'addressable_id',
        'addressable_type',
        'street_1',
        'street_2',
        'city',
        'state_province',
        'postal_code',
        'country_code',
        'address_type', // e.g., 'physical', 'billing', 'shipping'
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /**
     * Get the parent addressable model (organization, facility, user, etc.).
     */
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }
}

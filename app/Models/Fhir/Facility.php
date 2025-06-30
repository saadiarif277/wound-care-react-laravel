<?php

namespace App\Models\Fhir;

use App\Models\Order\Order;
use App\Models\User;
use App\Models\Users\Organization\Organization;
use App\Models\Fhir\Address;
use App\Models\Users\OnboardingChecklist;
use App\Models\Users\OnboardingDocument;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Facility extends Model
{
    use HasFactory, SoftDeletes, BelongsToOrganization;

    protected $fillable = [
        'organization_id',
        'name',
        'facility_type',
        'npi',
        'group_npi',
        'tax_id',
        'ptan',
<<<<<<< HEAD
=======
        'medicaid_number',
>>>>>>> origin/provider-side
        'medicare_admin_contractor',
        'default_place_of_service',
        'status',
        'npi_verified_at',
        'address',
        'city',
        'state',
        'zip_code',
        'phone',
<<<<<<< HEAD
=======
        'fax',
>>>>>>> origin/provider-side
        'email',
        'contact_name',
        'contact_phone',
        'contact_email',
        'contact_fax',
        'business_hours',
        'active',
    ];

    protected $casts = [
        'business_hours' => 'array',
        'active' => 'boolean',
        'npi_verified_at' => 'datetime',
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
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'facility_user')
            ->withPivot(['relationship_type', 'role', 'created_at', 'updated_at'])
            ->withTimestamps();
    }

    /**
     * Get users with the 'provider' role associated with this facility
     */
    public function providers()
    {
        return $this->users()->wherePivot('role', 'provider');
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

    /**
     * Get all addresses for the facility.
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    /**
     * Get the primary address for the facility.
     */
    public function primaryAddress(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable')->where('is_primary', true);
    }

    /**
     * Get all onboarding checklists associated with the facility.
     */
    public function onboardingChecklists(): MorphMany
    {
        return $this->morphMany(OnboardingChecklist::class, 'entity');
    }

    /**
     * Get all onboarding documents associated with the facility.
     */
    public function onboardingDocuments(): MorphMany
    {
        return $this->morphMany(OnboardingDocument::class, 'entity');
    }
}

<?php

namespace App\Models\Users\Organization;

use App\Models\Account;
use App\Models\User;
use App\Models\Fhir\Facility;
use App\Models\Users\Organization\OrganizationOnboarding;
use App\Models\Users\Provider\ProviderInvitation;
use App\Models\Users\OnboardingChecklist;
use App\Models\Users\OnboardingDocument;
use App\Models\Fhir\Address;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'account_id',   // Foreign key to Account model
        'tax_id',
        'type',         // e.g., 'Hospital', 'Clinic Group'
        'status',       // e.g., 'active', 'pending', 'inactive'
        'sales_rep_id', // Foreign key to User model
        'email',
        'phone',
        'address',
        'city',
        'region',
        'country',
        'postal_code',
        'billing_address',
        'billing_city',
        'billing_state',
        'billing_zip',
        'ap_contact_name',
        'ap_contact_phone',
        'ap_contact_email',
        'fhir_id', // Added Organization FHIR ID
    ];

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? 'id';
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
            throw new \InvalidArgumentException('Invalid column name');
        }
        return $this->where($field, $value)->withTrashed()->firstOrFail();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the facilities associated with the organization.
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    /**
     * Get the sales representative for this organization.
     */
    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    /**
     * Get the users that belong to this organization.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_users', 'organization_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Get the onboarding record for this organization.
     */
    public function onboardingRecord(): HasOne
    {
        // The foreign key in 'organization_onboarding' is 'organization_id'
        return $this->hasOne(OrganizationOnboarding::class, 'organization_id', 'id');
    }

    /**
     * Get all provider invitations sent by this organization.
     */
    public function providerInvitations(): HasMany
    {
        return $this->hasMany(ProviderInvitation::class, 'organization_id', 'id');
    }

    /**
     * Get all onboarding checklists associated with the organization.
     */
    public function onboardingChecklists(): MorphMany // Corrected to MorphMany
    {
        return $this->morphMany(OnboardingChecklist::class, 'entity');
    }

    /**
     * Get all onboarding documents associated with the organization.
     */
    public function onboardingDocuments(): MorphMany // Corrected to MorphMany
    {
        return $this->morphMany(OnboardingDocument::class, 'entity');
    }

    /**
     * Get the primary address for the organization.
     * Assuming an organization can have multiple addresses, and one is primary, or just one.
     * This is an example, adjust based on actual Address model setup.
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function primaryAddress(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable')->where('is_primary', true); // Example
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where('name', 'like', '%'.$search.'%');
        })->when($filters['trashed'] ?? null, function ($query, $trashed) {
            if ($trashed === 'with') {
                $query->withTrashed();
            } elseif ($trashed === 'only') {
                $query->onlyTrashed();
            }
        });
    }
}

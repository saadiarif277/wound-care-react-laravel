<?php

namespace App\Models;

use App\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasPermissions;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
        'photo',
        'account_id',
        'owner',
        'npi_number',
        'dea_number',
        'license_number',
        'license_state',
        'license_expiry',
        'credentials',
        'is_verified',
        'last_activity',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner' => 'boolean',
            'email_verified_at' => 'datetime',
            'license_expiry' => 'date',
            'credentials' => 'array',
            'is_verified' => 'boolean',
            'last_activity' => 'datetime',
        ];
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? 'id', $value)->withTrashed()->firstOrFail();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get facilities this user is associated with
     */
    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'facility_user')
            ->withPivot(['relationship_type', 'is_primary', 'is_active', 'notes'])
            ->withTimestamps();
    }

    /**
     * Get the user's primary facility
     */
    public function primaryFacility()
    {
        return $this->facilities()->wherePivot('is_primary', true)->first();
    }

    /**
     * Get active facility relationships
     */
    public function activeFacilities(): BelongsToMany
    {
        return $this->facilities()->wherePivot('is_active', true);
    }

    public function getNameAttribute()
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function setPasswordAttribute($password)
    {
        $this->attributes['password'] = Hash::needsRehash($password) ? Hash::make($password) : $password;
    }

    public function isDemoUser()
    {
        return $this->email === 'johndoe@example.com';
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles->contains('slug', $roleName);
    }

    /**
     * Check if user is a provider
     */
    public function isProvider(): bool
    {
        return $this->hasRole('provider');
    }

    /**
     * Check if user is an office manager
     */
    public function isOfficeManager(): bool
    {
        return $this->hasRole('office-manager');
    }

    /**
     * Check if user is an MSC rep
     */
    public function isMscRep(): bool
    {
        return $this->hasRole('msc-rep');
    }

    /**
     * Check if user is an MSC sub-rep
     */
    public function isMscSubRep(): bool
    {
        return $this->hasRole('msc-subrep');
    }

    /**
     * Check if user is an MSC admin
     */
    public function isMscAdmin(): bool
    {
        return $this->hasRole('msc-admin');
    }

    /**
     * Check if user is a super admin (handles legacy inconsistencies)
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin') || $this->hasRole('superadmin');
    }

    /**
     * Check if user can access financial information
     */
    public function canAccessFinancials(): bool
    {
        return $this->hasAnyPermission(['view-financials', 'manage-financials']);
    }

    /**
     * Check if user can see discounted pricing
     */
    public function canSeeDiscounts(): bool
    {
        return $this->hasPermission('view-discounts');
    }

    /**
     * Get primary role for business logic
     */
    public function getPrimaryRole(): ?Role
    {
        return $this->roles->first();
    }

    /**
     * Get primary role slug
     */
    public function getPrimaryRoleSlug(): ?string
    {
        return $this->getPrimaryRole()?->slug;
    }

    public function scopeOrderByName($query)
    {
        $query->orderBy('last_name')->orderBy('first_name');
    }

    public function scopeWhereRole($query, $role)
    {
        // Support legacy role checking
        switch ($role) {
            case 'user': return $query->where('owner', false);
            case 'owner': return $query->where('owner', true);
            default:
                // Use robust RBAC system
                return $query->whereHas('roles', function ($q) use ($role) {
                    $q->where('slug', $role);
                });
        }
    }

    /**
     * Scope to filter users by role system (robust RBAC)
     */
    public function scopeWithRole($query, string $roleName)
    {
        return $query->whereHas('roles', function ($q) use ($roleName) {
            $q->where('slug', $roleName);
        });
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        })->when($filters['role'] ?? null, function ($query, $role) {
            $query->whereRole($role);
        })->when($filters['trashed'] ?? null, function ($query, $trashed) {
            if ($trashed === 'with') {
                $query->withTrashed();
            } elseif ($trashed === 'only') {
                $query->onlyTrashed();
            }
        });
    }

    /**
     * Get the organizations this user is a sales representative for.
     */
    public function representedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'sales_rep_id');
    }

    /**
     * Provider invitations initiated by this user.
     */
    public function initiatedProviderInvitations(): HasMany
    {
        return $this->hasMany(ProviderInvitation::class, 'invited_by_user_id');
    }

    /**
     * Provider invitation that led to the creation of this user account.
     * (If this user was created via an invitation)
     */
    public function createdViaInvitation(): HasOne
    {
        return $this->hasOne(ProviderInvitation::class, 'created_user_id');
    }

    /**
     * Onboarding documents uploaded by this user.
     */
    public function uploadedOnboardingDocuments(): HasMany
    {
        return $this->hasMany(OnboardingDocument::class, 'uploaded_by');
    }

    /**
     * Onboarding documents reviewed by this user.
     */
    public function reviewedOnboardingDocuments(): HasMany
    {
        return $this->hasMany(OnboardingDocument::class, 'reviewed_by');
    }

    /**
     * Get all onboarding checklists associated with the user (e.g., as a provider).
     */
    public function onboardingChecklists(): MorphMany
    {
        return $this->morphMany(OnboardingChecklist::class, 'entity');
    }

    /**
     * Get all onboarding documents associated with the user (e.g. credentials).
     */
    public function onboardingDocuments(): MorphMany
    {
        return $this->morphMany(OnboardingDocument::class, 'entity');
    }

    // Note: roles() relationship and hasPermission() method are provided by HasPermissions trait
}

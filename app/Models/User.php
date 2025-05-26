<?php

namespace App\Models;

use App\Traits\HasPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'user_role_id',
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
     * Get the user's role
     */
    public function userRole(): BelongsTo
    {
        return $this->belongsTo(UserRole::class);
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
        return $this->userRole?->name === $roleName;
    }

    /**
     * Check if user is a provider
     */
    public function isProvider(): bool
    {
        return $this->hasRole(UserRole::PROVIDER);
    }

    /**
     * Check if user is an office manager
     */
    public function isOfficeManager(): bool
    {
        return $this->hasRole(UserRole::OFFICE_MANAGER);
    }

    /**
     * Check if user is an MSC rep
     */
    public function isMscRep(): bool
    {
        return $this->hasRole(UserRole::MSC_REP);
    }

    /**
     * Check if user is an MSC sub-rep
     */
    public function isMscSubRep(): bool
    {
        return $this->hasRole(UserRole::MSC_SUBREP);
    }

    /**
     * Check if user is an MSC admin
     */
    public function isMscAdmin(): bool
    {
        return $this->hasRole(UserRole::MSC_ADMIN);
    }

    /**
     * Check if user is a super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole(UserRole::SUPER_ADMIN);
    }

    /**
     * Check if user can access financial information
     */
    public function canAccessFinancials(): bool
    {
        return $this->userRole?->canAccessFinancials() ?? false;
    }

    /**
     * Check if user can see discounted pricing
     */
    public function canSeeDiscounts(): bool
    {
        return $this->userRole?->canSeeDiscounts() ?? false;
    }

    /**
     * Get role-specific dashboard configuration
     */
    public function getDashboardConfig(): array
    {
        return $this->userRole?->getDashboardConfig() ?? [];
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
                // Support new role system
                return $query->whereHas('userRole', function ($q) use ($role) {
                    $q->where('name', $role);
                });
        }
    }

    /**
     * Scope to filter users by MSC role system
     */
    public function scopeWithRole($query, string $roleName)
    {
        return $query->whereHas('userRole', function ($q) use ($roleName) {
            $q->where('name', $roleName);
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

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()->whereHas('permissions', function ($query) use ($permission) {
            $query->where('slug', $permission);
        })->exists();
    }
}

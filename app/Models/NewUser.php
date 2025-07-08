<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class NewUser extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * The table associated with the model.
     */
    protected $table = 'users';

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
        'email',
        'password_hash',
        'first_name',
        'last_name',
        'phone',
        'provider_fhir_id',
        'user_type',
        'status',
        'settings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'full_name',
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
     * Get the password attribute name for authentication.
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Set the password attribute.
     */
    public function setPasswordAttribute($value)
    {
        $this->attributes['password_hash'] = bcrypt($value);
    }

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get user roles with scoping.
     */
    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /**
     * Get roles through user_roles.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['scope_type', 'scope_id', 'deleted_at'])
            ->withTimestamps();
    }

    /**
     * Get facility assignments.
     */
    public function facilityAssignments(): HasMany
    {
        return $this->hasMany(UserFacilityAssignment::class);
    }

    /**
     * Get facilities through assignments.
     */
    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'user_facility_assignments', 'user_id', 'facility_id')
            ->withPivot(['role', 'can_order', 'can_view_orders', 'can_view_financial', 'can_manage_verifications', 'is_primary_facility'])
            ->withTimestamps('assigned_at');
    }

    /**
     * Get episodes created by user.
     */
    public function createdEpisodes(): HasMany
    {
        return $this->hasMany(Episode::class, 'created_by');
    }

    /**
     * Get product requests by user.
     */
    public function productRequests(): HasMany
    {
        return $this->hasMany(ProductRequest::class, 'requested_by');
    }

    /**
     * Get orders placed by user.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'ordered_by_user_id');
    }

    /**
     * Get audit logs for user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $permission, ?string $scopeType = null, ?string $scopeId = null): bool
    {
        return $this->userRoles()
            ->when($scopeType, function ($query) use ($scopeType, $scopeId) {
                return $query->where('scope_type', $scopeType)
                    ->where('scope_id', $scopeId);
            })
            ->whereHas('role.permissions', function ($query) use ($permission) {
                $query->where('name', $permission);
            })
            ->exists();
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $roleName, ?string $scopeType = null, ?string $scopeId = null): bool
    {
        return $this->userRoles()
            ->when($scopeType, function ($query) use ($scopeType, $scopeId) {
                return $query->where('scope_type', $scopeType)
                    ->where('scope_id', $scopeId);
            })
            ->whereHas('role', function ($query) use ($roleName) {
                $query->where('name', $roleName);
            })
            ->exists();
    }

    /**
     * Check if user is a provider.
     */
    public function isProvider(): bool
    {
        return $this->user_type === 'provider';
    }

    /**
     * Check if user is an office manager.
     */
    public function isOfficeManager(): bool
    {
        return $this->user_type === 'office_manager';
    }

    /**
     * Check if user is a sales rep.
     */
    public function isSalesRep(): bool
    {
        return $this->user_type === 'sales_rep';
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->user_type === 'admin';
    }

    /**
     * Check if user is a manufacturer rep.
     */
    public function isManufacturerRep(): bool
    {
        return $this->user_type === 'manufacturer_rep';
    }

    /**
     * Check if user is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
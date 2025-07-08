<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class UserRole extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'user_roles';

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
        'role_id',
        'scope_type',
        'scope_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'deleted_at' => 'datetime',
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
            
            $model->created_at = now();
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
     * Get the role.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the scope organization (when scope is organization/facility/manufacturer).
     */
    public function scopeOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'scope_id');
    }

    /**
     * Get the scope tenant (when scope is tenant).
     */
    public function scopeTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'scope_id');
    }

    /**
     * Check if this role assignment is global.
     */
    public function isGlobal(): bool
    {
        return $this->scope_type === 'global';
    }

    /**
     * Check if this role assignment is scoped to an organization.
     */
    public function isScopedToOrganization(): bool
    {
        return in_array($this->scope_type, ['organization', 'facility', 'manufacturer']);
    }

    /**
     * Check if this role assignment is scoped to a tenant.
     */
    public function isScopedToTenant(): bool
    {
        return $this->scope_type === 'tenant';
    }
}
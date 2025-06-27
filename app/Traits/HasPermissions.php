<?php

namespace App\Traits;

use App\Models\Permission;
use App\Models\Role;

trait HasPermissions
{
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('slug', $role)->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()->whereHas('permissions', function ($query) use ($permission) {
            $query->where('slug', $permission);
        })->exists();
    }

    public function hasAnyPermission(array $permissions): bool
    {
        return $this->roles()->whereHas('permissions', function ($query) use ($permissions) {
            $query->whereIn('slug', $permissions);
        })->exists();
    }

    public function hasAllPermissions(array $permissions): bool
    {
        $userPermissions = $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('slug')
            ->unique()
            ->toArray();

        return empty(array_diff($permissions, $userPermissions));
    }

    public function assignRole(Role $role): void
    {
        $this->roles()->syncWithoutDetaching($role);
    }

    public function removeRole(Role $role): void
    {
        $this->roles()->detach($role);
    }

    public function syncRoles(array $roles): void
    {
        $this->roles()->sync($roles);
    }
}

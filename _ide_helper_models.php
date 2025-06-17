<?php

/**
 * A helper file for your IDE to understand the project better
 */

namespace App\Models {
    /**
     * App\Models\User
     *
     * @method bool hasPermission(string $permission)
     * @method bool hasRole(string $role)
     * @method bool hasAnyPermission(array $permissions)
     * @method bool hasAllPermissions(array $permissions)
     * @method \Illuminate\Database\Eloquent\Relations\BelongsToMany roles()
     */
    class User extends \Illuminate\Foundation\Auth\User
    {
        use \App\Traits\HasPermissions;
    }
}

namespace Illuminate\Support\Facades {
    /**
     * @method static \App\Models\User|null user()
     */
    class Auth
    {
    }
}
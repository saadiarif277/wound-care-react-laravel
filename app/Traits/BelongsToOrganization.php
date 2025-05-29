<?php

namespace App\Traits;

use App\Models\Scopes\OrganizationScope;
use Illuminate\Support\Facades\Auth;

/**
 * Trait BelongsToOrganization
 * Automatically applies a global scope to filter models by the authenticated user's organization_id.
 * Skips scoping for users with 'manage-all-organizations' permission.
 */
trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope());
    }

    /**
     * Get the name of the column that stores the organization ID.
     * Defaults to 'organization_id'. Override in model if different.
     */
    public function getOrganizationIdColumn(): string
    {
        return defined(static::class . '::ORGANIZATION_ID_COLUMN') ? static::ORGANIZATION_ID_COLUMN : 'organization_id';
    }

    /**
     * Optional: Define a relationship to the User model for the organization.
     * This is not strictly required by the scope but can be useful.
     */
    // public function organization()
    // {
    //    // Assuming your User model has an organization_id
    //    // and your current model also has an organization_id
    //    return $this->belongsTo(User::class, $this->getOrganizationIdColumn(), 'organization_id');
    // }
}

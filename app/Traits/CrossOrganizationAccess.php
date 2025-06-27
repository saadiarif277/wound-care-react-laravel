<?php

namespace App\Traits;

// Assuming your User model uses Laravel's built-in HasRoles from a package like Spatie/laravel-permission
// or has a similar mechanism to check roles and permissions.
// Adjust the hasRole and hasPermission checks if your implementation differs.

trait CrossOrganizationAccess
{
    // This property is not used in the current version of OrganizationScope directly,
    // but kept if you have other local scopes that might use it.
    protected $bypassOrganizationScope = false;

    /**
     * Checks if the user has roles/permissions that grant access across all organizations.
     * This method is used by OrganizationScope and BelongsToOrganizationThroughScope.
     */
    public function canAccessAllOrganizations(): bool
    {
        // Check if user has the appropriate role
        if (method_exists($this, 'hasRole')) {
            if ($this->hasRole('msc-admin') || $this->hasRole('super-admin')) {
                return true;
            }
        }
        
        // Check for specific permission if hasPermissionTo method exists
        if (method_exists($this, 'hasPermissionTo')) {
            if ($this->hasPermissionTo('view_all_organizations')) {
                return true;
            }
        }
        
        // Check for specific permission using hasPermission method (alternative naming)
        if (method_exists($this, 'hasPermission')) {
            if ($this->hasPermission('manage-all-organizations')) {
                return true;
            }
        }
        
        return false; // Default to no cross-organization access
    }

    /**
     * Local scope to temporarily bypass organization scoping for a specific query chain.
     * This is not used by the global scopes but can be useful for specific repository methods
     * or service layer logic where a super admin needs to operate outside the current org context temporarily.
     */
    public function scopeWithoutOrganization($query)
    {
        // This sets a flag on the model instance. Global scopes are static and won't see this instance property.
        // To truly bypass global scopes for a query, use Model::withoutGlobalScope(OrganizationScope::class)->get().
        // This method here is more for indicating intent if you have local scopes checking this flag.
        $this->bypassOrganizationScope = true;
        return $query; // Generally, local scopes should return the query builder.
    }

    // The applyOrganizationScope method you had is more suited for local scopes or query builders
    // within repositories, not directly for a trait on the User model that primarily provides
    // the canAccessAllOrganizations() check for global scopes.
}

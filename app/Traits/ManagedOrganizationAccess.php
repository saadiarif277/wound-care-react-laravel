<?php

namespace App\Traits;

use App\Models\Users\Organization; // Adjust path as needed
use Illuminate\Support\Facades\Auth;

/**
 * Trait ManagedOrganizationAccess
 * Provides methods and scopes for users (especially Sales Reps)
 * who need to access data across multiple assigned organizations.
 */
trait ManagedOrganizationAccess
{
    // This is more of a helper for local scopes or repository logic
    // than a global scope enforcer. Global scopes use CurrentOrganization.

    /**
     * Local scope to filter records for organizations assigned to the current sales rep.
     * This should be explicitly applied in queries where a sales rep needs to see data
     * across all their managed organizations rather than just the current active one.
     */
    public function scopeForSalesRepManagedOrganizations($query)
    {
        $user = Auth::user(); // Or pass user as an argument

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('msc_sales_rep') && method_exists($user, 'salesRepOrganizations')) {
            $assignedOrgIds = $user->salesRepOrganizations()
                ->where(function ($q) {
                    $q->where('assigned_until', '>=', now())
                      ->orWhereNull('assigned_until');
                })
                ->pluck('organization_id'); // Ensure this pivot column name is correct for sales_rep_organizations

            // Ensure the model using this scope has an organization_id column
            // This assumes the model being queried (e.g., Order) has an organization_id.
            return $query->whereIn($this->getOrganizationIdColumn(), $assignedOrgIds);
        }

        // If not a sales rep or methods don't exist, it might fall back to CurrentOrganization
        // or return no results if this scope is exclusively for sales reps.
        // For safety, if this scope is applied and the user isn't a sales rep, restrict heavily.
        // However, typical use is: if (user is salesrep) query->forSalesRepManagedOrganizations() else query->forCurrentOrganization()
        return $query->whereRaw('1 = 0'); // Default to no results if not a sales rep in this specific scope
    }

    /**
     * Check if the authenticated user (presumably a sales rep or admin)
     * can access a specific organization.
     */
    public function canAccessAssignedOrganization(string $organizationId): bool
    {
        if (!Auth::check()) {
            return false;
        }

        $user = Auth::user();

        // Super admins can access any organization
        if (method_exists($user, 'canAccessAllOrganizations') && $user->canAccessAllOrganizations()) {
            return true;
        }

        // Check for sales rep specific assignments
        if (method_exists($user, 'hasRole') && $user->hasRole('msc_sales_rep') && method_exists($user, 'salesRepOrganizations')) {
            return $user->salesRepOrganizations()
                ->wherePivot('organization_id', $organizationId) // Check pivot table for direct assignment
                ->where(function ($q) {
                    $q->where('assigned_until', '>=', now())
                      ->orWhereNull('assigned_until');
                })
                ->exists();
        }

        // Fallback for regular users to check against their general organization memberships (not sales-rep specific)
        // This part might be redundant if currentOrganization context switching is strictly enforced for non-salesreps.
        if (method_exists($user, 'organizations')) {
             return $user->organizations()->where('organization_id', $organizationId)->exists();
        }

        return false;
    }

    // The User model should define its salesRepOrganizations relationship as you outlined:
    // public function salesRepOrganizations()
    // {
    //     return $this->belongsToMany(Organization::class, 'sales_rep_organizations')
    //         ->withPivot([...])->withTimestamps();
    // }
}

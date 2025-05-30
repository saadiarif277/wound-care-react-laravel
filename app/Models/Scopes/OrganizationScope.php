<?php

namespace App\Models\Scopes;

use App\Services\CurrentOrganization; // Assuming this path, adjust if different
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class OrganizationScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        // Check if a user is authenticated
        if (Auth::check()) {
            $user = Auth::user();

            // Use canAccessAllOrganizations() from User model/trait
            if (method_exists($user, 'canAccessAllOrganizations') && $user->canAccessAllOrganizations()) {
                return; // User can see all organizations
            }

            // Get current organization ID from the service
            // Ensure CurrentOrganization service is registered and resolves correctly
            $currentOrganizationId = null;
            if (app()->bound(CurrentOrganization::class)) {
                $currentOrganizationService = app(CurrentOrganization::class);
                // Assuming the service has a method getId() or a public property id
                $currentOrganizationId = $currentOrganizationService->id ?? (method_exists($currentOrganizationService, 'getId') ? $currentOrganizationService->getId() : null);
            }

            if ($currentOrganizationId) {
                $builder->where($model->getTable() . '.' . $model->getOrganizationIdColumn(), $currentOrganizationId);
            } else {
                // If user is not a super admin and no current organization is set (or resolvable),
                // they should not see any organization-specific data.
                $builder->whereRaw('1 = 0');
            }
        } else {
            // For unauthenticated users, they should not see any organization-specific data.
            // You might adjust this if you have public-facing pages that show some aggregated
            // or anonymized organization-specific data, but that would be a special case.
            $builder->whereRaw('1 = 0');
        }
    }
}

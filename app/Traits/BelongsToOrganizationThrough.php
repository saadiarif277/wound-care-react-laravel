<?php

namespace App\Traits;

use App\Models\Scopes\OrganizationScope; // This scope is for direct organization_id
use App\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Trait BelongsToOrganizationThrough
 * Applies organization scoping through a defined parent relationship.
 * Models using this trait MUST implement `getOrganizationParentRelationName()` static method.
 */
trait BelongsToOrganizationThrough
{
    /**
     * The model automatically boots this static method.
     */
    protected static function bootBelongsToOrganizationThrough(): void
    {
        // Get relation name statically from the model using this trait
        $relationName = static::getOrganizationParentRelationName();

        static::addGlobalScope('organizationThrough', function (Builder $builder) use ($relationName) {
            if (Auth::check()) {
                $user = Auth::user();

                if (method_exists($user, 'canAccessAllOrganizations') && $user->canAccessAllOrganizations()) {
                    return; // Super admin sees all, no need to scope through parent
                }

                $currentOrganizationId = null;
                if (app()->bound(CurrentOrganization::class)) {
                    $currentOrganizationService = app(CurrentOrganization::class);
                    $currentOrganizationId = $currentOrganizationService->id ?? (method_exists($currentOrganizationService, 'getId') ? $currentOrganizationService->getId() : null);
                }

                if ($currentOrganizationId) {
                    $builder->whereHas($relationName, function ($query) use ($currentOrganizationId) {
                        $parentModel = $query->getModel();
                        $organizationIdColumn = 'organization_id'; // Default
                        if (method_exists($parentModel, 'getOrganizationIdColumn')) {
                            // Ensure the parent model (e.g. Order) has this method, likely via BelongsToOrganization trait
                            $organizationIdColumn = $parentModel->getOrganizationIdColumn();
                        }
                        $query->where($parentModel->getTable() . '.' . $organizationIdColumn, $currentOrganizationId);
                    });
                } else {
                    $builder->whereRaw('1 = 0'); // No current organization, no data
                }
            } else {
                $builder->whereRaw('1 = 0'); // Unauthenticated, no data
            }
        });
    }

    /**
     * Instance method for accessor or other instance logic if needed.
     * Not directly used by the global scope, but good for consistency if an accessor is present.
     */
    abstract public function getOrganizationRelationName(): string;

    /**
     * Static method that MUST be implemented by the model using this trait.
     * It should return the name of the relationship that connects to the organization-aware parent.
     * Example: return 'order';
     */
    abstract protected static function getOrganizationParentRelationName(): string;

    /**
     * Accessor to get the organization_id from the parent relationship.
     * This is useful for convenience but not directly used by the global scope here.
     */
    public function getOrganizationIdAttribute()
    {
        $relationName = $this->getOrganizationRelationName();
        // Ensure the relation and the parent's organization_id attribute exist
        if ($this->relationLoaded($relationName) && $this->{$relationName}) {
            return $this->{$relationName}->organization_id ?? null;
        }
        // If not loaded, optionally load it. Be careful with N+1 issues.
        // return $this->{$relationName}()->first()->organization_id ?? null;
        return null;
    }
}

<?php

namespace App\Traits;

use App\Models\Users\Organization; // Adjust if your Organization model path is different
use App\Models\Fhir\Patient; // Adjust if your Patient model path is different

/**
 * Trait BelongsToPolymorphicOrganization
 * For polymorphic models like Address or Contact.
 * Automatically denormalizes and scopes by organization_id based on the parent.
 */
trait BelongsToPolymorphicOrganization
{
    use BelongsToOrganization; // This will apply the OrganizationScope

    protected static function bootBelongsToPolymorphicOrganization(): void
    {
        static::creating(function ($model) {
            // If organization_id is not already set (e.g., manually or by other means)
            if (is_null($model->{$model->getOrganizationIdColumn()}) && $model->addressable) {
                $model->{$model->getOrganizationIdColumn()} = $model->determineOrganizationIdFromParent();
            }
        });

        static::updating(function ($model) {
            // If the parent polymorphic relation changes, re-determine the organization_id
            if ($model->isDirty($model->getMorphType()) || $model->isDirty($model->getForeignKey())) {
                $model->{$model->getOrganizationIdColumn()} = $model->determineOrganizationIdFromParent();
            }
        });
    }

    /**
     * Determines the organization_id from the polymorphic parent.
     */
    protected function determineOrganizationIdFromParent(): ?string // Changed to string to match typical UUIDs or IDs
    {
        if (!$this->addressable) {
            return null;
        }

        $parent = $this->addressable;

        // 1. Parent is an Organization itself
        if ($parent instanceof Organization) {
            return $parent->id;
        }

        // 2. Parent has a direct organization_id property/column
        //    (implies parent uses BelongsToOrganization or has the column)
        if (method_exists($parent, 'getOrganizationIdColumn') && !is_null($parent->{$parent->getOrganizationIdColumn()})) {
             return $parent->{$parent->getOrganizationIdColumn()};
        } elseif (property_exists($parent, 'organization_id') && !is_null($parent->organization_id)) {
            return $parent->organization_id;
        }


        // 3. Parent has an organization() relationship method
        if (method_exists($parent, 'organization')) {
            return $parent->organization?->id;
        }

        // 4. Special case for Patient (managingOrganization)
        //    Ensure Patient model and managingOrganization relationship exist.
        if ($parent instanceof Patient && method_exists($parent, 'managingOrganization')) {
            return $parent->managingOrganization?->id;
        }

        // 5. Parent might use BelongsToOrganizationThrough
        if (method_exists($parent, 'getOrganizationIdAttribute')) { // Checks for the accessor from BTOThrough
            return $parent->organization_id; // Uses the accessor which gets it from its parent
        }


        return null;
    }

    /**
     * The polymorphic relationship (e.g., addressable, contactable).
     * This method must be defined in the model using this trait.
     */
    abstract public function getPolymorphicParentRelationName(): string;

    // This ensures the model defines its morphTo relationship, e.g., addressable()
    // public function addressable()
    // {
    //     return $this->morphTo($this->getPolymorphicParentRelationName());
    // }
}

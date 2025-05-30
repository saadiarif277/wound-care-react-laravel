<?php

namespace App\Traits;

/**
 * Trait InheritsOrganizationFromParent
 * For child models that get a denormalized organization_id from their direct parent.
 * This trait also uses BelongsToOrganization to apply the standard OrganizationScope.
 */
trait InheritsOrganizationFromParent
{
    use BelongsToOrganization; // Applies the standard OrganizationScope

    /**
     * Boots the trait and registers a creating event to set organization_id.
     */
    protected static function bootInheritsOrganizationFromParent(): void
    {
        static::creating(function ($model) {
            // Check if organization_id is already set
            if (is_null($model->{$model->getOrganizationIdColumn()})) {
                $parentRelationName = $model->getParentRelationNameForOrganizationInheritance();
                $parent = $model->{$parentRelationName};

                if ($parent) {
                    // Check if parent has getOrganizationIdColumn (from BelongsToOrganization)
                    if (method_exists($parent, 'getOrganizationIdColumn')) {
                        $model->{$model->getOrganizationIdColumn()} = $parent->{$parent->getOrganizationIdColumn()};
                    // Fallback to direct property access if method doesn't exist but property might
                    } elseif (isset($parent->organization_id)) {
                        $model->{$model->getOrganizationIdColumn()} = $parent->organization_id;
                    }
                }
            }
        });

        // Optional: Consider an updating event if the parent relationship can change
        // and the organization_id needs to be re-derived. This is less common for simple parent-child.
        // static::updating(function ($model) {
        //     if ($model->isDirty($model->getParentRelationForeignKeyName())) { // e.g., isDirty('order_id')
        //         // Logic to re-derive organization_id from new parent
        //     }
        // });
    }

    /**
     * Abstract method to define the name of the relationship to the parent model
     * from which the organization_id should be inherited.
     * Example: return 'order';
     */
    abstract protected function getParentRelationNameForOrganizationInheritance(): string;

    // Optional: If you want to also define the foreign key name for the parent relation for the updating event
    // abstract protected function getParentRelationForeignKeyName(): string;
}

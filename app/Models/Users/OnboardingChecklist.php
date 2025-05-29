<?php

namespace App\Models\Users;

use App\Models\User;
use App\Models\Fhir\Facility;
use App\Models\Users\Organization\Organization;
use App\Traits\BelongsToOrganizationThrough;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class OnboardingChecklist extends Model
{
    use HasFactory, BelongsToOrganizationThrough;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'entity_id',
        'entity_type',
        'checklist_type',
        'items',
        'total_items',
        'completed_items',
        'completion_percentage',
        'last_activity_at',
    ];

    protected $casts = [
        'items' => 'json',
        'completion_percentage' => 'decimal:2',
        'last_activity_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the parent entity model (organization, facility, or provider).
     */
    public function entity()
    {
        return $this->morphTo();
    }

    /**
     * Get the organization relationship through the entity
     */
    protected function getOrganizationParentRelationName(): string
    {
        // The entity could be User, Facility, etc.
        // We'll need to check the entity type and route accordingly
        return 'entity';
    }

    /**
     * Get the organization relationship name on the parent
     */
    public function getOrganizationRelationName(): string
    {
        // This will vary based on entity type
        if ($this->entity_type === 'App\\Models\\Fhir\\Facility') {
            return 'organization';
        }
        if ($this->entity_type === 'App\\Models\\User') {
            return 'currentOrganization';
        }
        return 'organization';
    }
}

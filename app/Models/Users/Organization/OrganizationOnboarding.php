<?php

namespace App\Models\Users\Organization;

use App\Models\User; // Assuming User is App\Models\User
// Organization will be in the same namespace App\Models\Users\Organization
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OrganizationOnboarding extends Model
{
    use HasFactory;

    public $table = 'organization_onboarding'; // Explicitly set table name

    public $incrementing = false; // Since 'id' is UUID
    protected $keyType = 'string';   // Since 'id' is UUID

    protected $fillable = [
        'id',
        'organization_id', // Foreign key to organizations table
        'status',
        'completed_steps',
        'pending_items',
        'onboarding_manager_id', // Foreign key to users table (manager)
        'initiated_at',
        'target_go_live_date',
        'actual_go_live_date',
        'completed_at',
    ];

    protected $casts = [
        'completed_steps' => 'json',
        'pending_items' => 'json',
        'initiated_at' => 'datetime',
        'target_go_live_date' => 'datetime',
        'actual_go_live_date' => 'datetime',
        'completed_at' => 'datetime',
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
     * Get the organization this onboarding record belongs to.
     */
    public function organization(): BelongsTo
    {
        // Assuming 'organizations' table uses 'id' as primary key
        // and 'organization_id' is the foreign key in 'organization_onboarding' table.
        // The placeholder 'organization_id_placeholder' was in the migration for the foreignId constraint.
        // We should use the actual column name here.
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Get the user who is the onboarding manager.
     */
    public function onboardingManager(): BelongsTo
    {
        // Assuming 'users' table uses 'id' as primary key
        // and 'onboarding_manager_id' is the foreign key.
        return $this->belongsTo(User::class, 'onboarding_manager_id');
    }
}

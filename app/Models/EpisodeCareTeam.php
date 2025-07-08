<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EpisodeCareTeam extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'episode_care_team';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the ID.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'episode_id',
        'user_id',
        'provider_fhir_id',
        'role',
        'can_order',
        'can_modify',
        'can_view_financial',
        'assigned_date',
        'removed_date',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'can_order' => 'boolean',
        'can_modify' => 'boolean',
        'can_view_financial' => 'boolean',
        'assigned_date' => 'date',
        'removed_date' => 'date',
    ];

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }

            if (empty($model->assigned_date)) {
                $model->assigned_date = now();
            }
        });
    }

    /**
     * Get the episode.
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to active care team members.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('removed_date');
    }

    /**
     * Scope to care team members by role.
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope to care team members who can order.
     */
    public function scopeCanOrder($query)
    {
        return $query->where('can_order', true);
    }

    /**
     * Check if member is active.
     */
    public function isActive(): bool
    {
        return is_null($this->removed_date);
    }

    /**
     * Check if member is the primary surgeon.
     */
    public function isPrimarySurgeon(): bool
    {
        return $this->role === 'primary_surgeon';
    }

    /**
     * Check if member is the attending physician.
     */
    public function isAttendingPhysician(): bool
    {
        return $this->role === 'attending_physician';
    }

    /**
     * Check if member is a care coordinator.
     */
    public function isCareCoordinator(): bool
    {
        return $this->role === 'care_coordinator';
    }

    /**
     * Check if member is an office manager.
     */
    public function isOfficeManager(): bool
    {
        return $this->role === 'office_manager';
    }

    /**
     * Remove member from care team.
     */
    public function remove(): void
    {
        $this->update(['removed_date' => now()]);
    }
}
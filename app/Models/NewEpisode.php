<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NewEpisode extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'episodes';

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
        'tenant_id',
        'episode_number',
        'patient_fhir_id',
        'primary_provider_fhir_id',
        'primary_facility_id',
        'type',
        'sub_type',
        'status',
        'diagnosis_fhir_refs',
        'procedure_fhir_refs',
        'estimated_duration_days',
        'priority',
        'start_date',
        'target_date',
        'end_date',
        'tags',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'diagnosis_fhir_refs' => 'array',
        'procedure_fhir_refs' => 'array',
        'tags' => 'array',
        'start_date' => 'date',
        'target_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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

            if (empty($model->episode_number)) {
                $model->episode_number = self::generateEpisodeNumber();
            }
        });
    }

    /**
     * Generate a unique episode number.
     */
    public static function generateEpisodeNumber(): string
    {
        $year = date('Y');
        $lastEpisode = static::where('episode_number', 'like', "EP-{$year}-%")
            ->orderBy('episode_number', 'desc')
            ->first();

        if ($lastEpisode) {
            $lastNumber = intval(substr($lastEpisode->episode_number, -6));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return 'EP-' . $year . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get the tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the primary facility.
     */
    public function primaryFacility(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'primary_facility_id');
    }

    /**
     * Get the patient reference.
     */
    public function patientReference(): BelongsTo
    {
        return $this->belongsTo(PatientReference::class, 'patient_fhir_id', 'patient_fhir_id');
    }

    /**
     * Get the creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the care team.
     */
    public function careTeam(): HasMany
    {
        return $this->hasMany(EpisodeCareTeam::class);
    }

    /**
     * Get product requests.
     */
    public function productRequests(): HasMany
    {
        return $this->hasMany(ProductRequest::class);
    }

    /**
     * Get orders.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get verifications.
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class);
    }

    /**
     * Scope to active episodes.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to episodes by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to episodes by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to episodes by priority.
     */
    public function scopeWithPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Check if episode is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if episode is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if episode is urgent.
     */
    public function isUrgent(): bool
    {
        return $this->priority === 'urgent';
    }

    /**
     * Check if episode is emergent.
     */
    public function isEmergent(): bool
    {
        return $this->priority === 'emergent';
    }

    /**
     * Get the display name for the episode type.
     */
    public function getTypeDisplayAttribute(): string
    {
        return str_replace('_', ' ', ucwords($this->type));
    }

    /**
     * Get the display name for the episode status.
     */
    public function getStatusDisplayAttribute(): string
    {
        return ucfirst($this->status);
    }

    /**
     * Get the display name for the episode priority.
     */
    public function getPriorityDisplayAttribute(): string
    {
        return ucfirst($this->priority);
    }
}
<?php

namespace App\Models\Fhir;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Encounter extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'practitioner_id',
        'azure_fhir_id', // Reference to FHIR Encounter resource
        'status', // planned, arrived, triaged, in-progress, onleave, finished, cancelled, entered-in-error, unknown
        'class', // inpatient, outpatient, ambulatory, emergency, etc.
        'type_code',
        'type_display',
        'priority', // routine, urgent, asap, stat
        'period_start',
        'period_end',
        'length_minutes',
        'reason_code',
        'reason_display',
        'diagnosis_condition_id',
        'diagnosis_rank',
        'location_name',
        'location_type',
        // MSC Extensions
        'visit_purpose',
        'facility_id',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'length_minutes' => 'integer',
        'diagnosis_rank' => 'integer',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Get the patient for this encounter
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the primary practitioner for this encounter
     */
    public function practitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class);
    }

    /**
     * Get the facility where this encounter occurred
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Get the diagnosis condition
     */
    public function diagnosisCondition(): BelongsTo
    {
        return $this->belongsTo(Condition::class, 'diagnosis_condition_id');
    }

    /**
     * Scope for active encounters
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['planned', 'arrived', 'triaged', 'in-progress']);
    }

    /**
     * Scope for completed encounters
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'finished');
    }

    /**
     * Scope by encounter class
     */
    public function scopeByClass($query, $class)
    {
        return $query->where('class', $class);
    }

    /**
     * Get encounter duration
     */
    public function getDurationAttribute(): ?int
    {
        if ($this->period_start && $this->period_end) {
            return $this->period_start->diffInMinutes($this->period_end);
        }

        return $this->length_minutes;
    }

    /**
     * Check if encounter is currently in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in-progress';
    }

    /**
     * Get formatted encounter type
     */
    public function getEncounterTypeDisplayAttribute(): string
    {
        return $this->type_display ?: ucfirst(str_replace('-', ' ', $this->class));
    }
}

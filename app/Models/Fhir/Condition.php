<?php

namespace App\Models\Fhir;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Condition extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'azure_fhir_id', // Reference to FHIR Condition resource
        'clinical_status', // active, recurrence, relapse, inactive, remission, resolved
        'verification_status', // unconfirmed, provisional, differential, confirmed, refuted, entered-in-error
        'category', // problem-list-item, encounter-diagnosis
        'severity', // mild, moderate, severe
        'code_system', // ICD-10, SNOMED CT
        'code',
        'display_name',
        'body_site_code',
        'body_site_display',
        'onset_date',
        'onset_age',
        'abatement_date',
        'recorded_date',
        'recorder_id', // reference to practitioner
        'asserter_id', // reference to practitioner
        // MSC Wound Care Extensions
        'wound_type',
        'wound_stage',
        'wound_duration_weeks',
        'wound_location_details',
        'notes',
    ];

    protected $casts = [
        'onset_date' => 'date',
        'abatement_date' => 'date',
        'recorded_date' => 'date',
        'onset_age' => 'integer',
        'wound_duration_weeks' => 'integer',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Get the patient this condition belongs to
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the recorder (practitioner who recorded this condition)
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class, 'recorder_id');
    }

    /**
     * Get the asserter (practitioner who asserted this condition)
     */
    public function asserter(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class, 'asserter_id');
    }

    /**
     * Scope for active conditions
     */
    public function scopeActive($query)
    {
        return $query->where('clinical_status', 'active');
    }

    /**
     * Scope for confirmed conditions
     */
    public function scopeConfirmed($query)
    {
        return $query->where('verification_status', 'confirmed');
    }

    /**
     * Scope for wound-related conditions
     */
    public function scopeWoundRelated($query)
    {
        return $query->whereNotNull('wound_type');
    }

    /**
     * Scope by wound type
     */
    public function scopeByWoundType($query, $woundType)
    {
        return $query->where('wound_type', $woundType);
    }

    /**
     * Check if this is an active wound condition
     */
    public function isActiveWound(): bool
    {
        return $this->clinical_status === 'active' && !empty($this->wound_type);
    }

    /**
     * Get condition display with code
     */
    public function getFullDisplayAttribute(): string
    {
        return "{$this->display_name} ({$this->code})";
    }

    /**
     * Get wound severity display
     */
    public function getWoundSeverityDisplayAttribute(): ?string
    {
        if (!$this->wound_stage) {
            return $this->severity;
        }

        return "Stage {$this->wound_stage}" . ($this->severity ? " - {$this->severity}" : '');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Observation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'azure_fhir_id', // Reference to FHIR Observation resource
        'status', // registered, preliminary, final, amended, corrected, cancelled, entered-in-error, unknown
        'category', // vital-signs, survey, exam, therapy, activity, etc.
        'code_system', // LOINC, SNOMED CT
        'code',
        'display_name',
        'effective_date_time',
        'issued',
        'performer_id', // reference to practitioner
        'value_type', // quantity, string, boolean, codeable_concept, etc.
        'value_quantity',
        'value_unit',
        'value_string',
        'value_boolean',
        'value_code',
        'value_display',
        'interpretation', // normal, high, low, critical, etc.
        'reference_range_low',
        'reference_range_high',
        'reference_range_unit',
        'body_site_code',
        'body_site_display',
        'method_code',
        'method_display',
        'device_display',
        'component_data', // JSON for complex observations
        // MSC Wound Care Extensions
        'measurement_technique',
        'assessment_tool',
        'wound_location_details',
        'notes',
    ];

    protected $casts = [
        'effective_date_time' => 'datetime',
        'issued' => 'datetime',
        'value_quantity' => 'decimal:3',
        'value_boolean' => 'boolean',
        'reference_range_low' => 'decimal:3',
        'reference_range_high' => 'decimal:3',
        'component_data' => 'array',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Get the patient this observation belongs to
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the performer (practitioner who performed this observation)
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class, 'performer_id');
    }

    /**
     * Scope for final observations
     */
    public function scopeFinal($query)
    {
        return $query->where('status', 'final');
    }

    /**
     * Scope by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope by code
     */
    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Scope for wound measurements
     */
    public function scopeWoundMeasurements($query)
    {
        return $query->where('category', 'exam')
                    ->where(function($q) {
                        $q->where('display_name', 'like', '%wound%')
                          ->orWhere('display_name', 'like', '%ulcer%')
                          ->orWhereNotNull('wound_location_details');
                    });
    }

    /**
     * Get formatted value with unit
     */
    public function getFormattedValueAttribute(): string
    {
        switch ($this->value_type) {
            case 'quantity':
                return $this->value_quantity . ($this->value_unit ? ' ' . $this->value_unit : '');
            case 'string':
                return $this->value_string;
            case 'boolean':
                return $this->value_boolean ? 'Yes' : 'No';
            case 'codeable_concept':
                return $this->value_display ?: $this->value_code;
            default:
                return '';
        }
    }

    /**
     * Check if value is within normal range
     */
    public function isWithinNormalRange(): ?bool
    {
        if ($this->value_type !== 'quantity' || !$this->value_quantity) {
            return null;
        }

        if ($this->reference_range_low && $this->value_quantity < $this->reference_range_low) {
            return false;
        }

        if ($this->reference_range_high && $this->value_quantity > $this->reference_range_high) {
            return false;
        }

        return true;
    }

    /**
     * Get interpretation display
     */
    public function getInterpretationDisplayAttribute(): string
    {
        if ($this->interpretation) {
            return ucfirst($this->interpretation);
        }

        $withinRange = $this->isWithinNormalRange();
        if ($withinRange === true) {
            return 'Normal';
        } elseif ($withinRange === false) {
            return 'Abnormal';
        }

        return 'Unknown';
    }
}

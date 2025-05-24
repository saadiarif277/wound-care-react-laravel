<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coverage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'azure_fhir_id', // Reference to FHIR Coverage resource
        'status', // active, cancelled, draft, entered-in-error
        'type', // medical, dental, mental health, etc.
        'policy_holder',
        'subscriber_id',
        'beneficiary',
        'period_start',
        'period_end',
        'payor_name',
        'payor_identifier',
        'plan_name',
        'class_type', // group, plan, etc.
        'class_value',
        'cost_to_beneficiary', // JSON array
        // MSC Extensions
        'mac_jurisdiction',
        'eligibility_source',
        'validation_details', // JSON
        'last_verified_at',
        'verification_status',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'cost_to_beneficiary' => 'array',
        'validation_details' => 'array',
        'last_verified_at' => 'datetime',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Get the patient this coverage belongs to
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get eligibility checks for this coverage
     */
    public function eligibilityChecks(): HasMany
    {
        return $this->hasMany(EligibilityCheck::class);
    }

    /**
     * Get prior authorizations for this coverage
     */
    public function priorAuthorizations(): HasMany
    {
        return $this->hasMany(PriorAuthorization::class);
    }

    /**
     * Scope for active coverage
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for current coverage (within period)
     */
    public function scopeCurrent($query)
    {
        $today = now()->toDateString();
        return $query->where('period_start', '<=', $today)
                    ->where(function($q) use ($today) {
                        $q->whereNull('period_end')
                          ->orWhere('period_end', '>=', $today);
                    });
    }

    /**
     * Check if coverage is currently active and valid
     */
    public function isCurrentlyValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $today = now()->toDate();

        if ($this->period_start > $today) {
            return false;
        }

        if ($this->period_end && $this->period_end < $today) {
            return false;
        }

        return true;
    }

    /**
     * Get formatted payor display name
     */
    public function getPayorDisplayAttribute(): string
    {
        return $this->plan_name ?: $this->payor_name;
    }
}

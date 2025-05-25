<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Patient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'azure_fhir_id', // Reference to FHIR Patient resource in Azure
        'mrn', // Medical Record Number
        'member_id', // Insurance member ID
        'active',
        'gender',
        'birth_date',
        'phone',
        'email',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'managing_organization_id',
        'general_practitioner_id',
        // MSC Extensions
        'wound_care_consent_status',
        'platform_status',
        'consent_date',
        'emergency_contact_name',
        'emergency_contact_phone',
        'preferred_language',
        'communication_preferences',
    ];

    protected $casts = [
        'active' => 'boolean',
        'birth_date' => 'date',
        'consent_date' => 'date',
        'communication_preferences' => 'array',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Get the managing organization
     */
    public function managingOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'managing_organization_id');
    }

    /**
     * Get the general practitioner
     */
    public function generalPractitioner(): BelongsTo
    {
        return $this->belongsTo(Practitioner::class, 'general_practitioner_id');
    }

    /**
     * Get all coverage records for this patient
     */
    public function coverages(): HasMany
    {
        return $this->hasMany(Coverage::class);
    }

    /**
     * Get all conditions for this patient
     */
    public function conditions(): HasMany
    {
        return $this->hasMany(Condition::class);
    }

    /**
     * Get all observations for this patient
     */
    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    /**
     * Get all encounters for this patient
     */
    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    /**
     * Get all document references for this patient
     */
    public function documentReferences(): HasMany
    {
        return $this->hasMany(DocumentReference::class);
    }

    /**
     * Get orders for this patient (via FHIR reference)
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'patient_fhir_id', 'azure_fhir_id');
    }

    /**
     * Scope for active patients
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope for patients with wound care consent
     */
    public function scopeWithWoundCareConsent($query)
    {
        return $query->where('wound_care_consent_status', 'active');
    }

    /**
     * Get patient's age
     */
    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? $this->birth_date->diffInYears(now()) : null;
    }

    /**
     * Get full name (placeholder for non-PHI display)
     */
    public function getDisplayNameAttribute(): string
    {
        return "Patient {$this->mrn}";
    }

    /**
     * Get formatted address
     */
    public function getFullAddressAttribute(): string
    {
        $address = $this->address_line1;
        if ($this->address_line2) {
            $address .= ', ' . $this->address_line2;
        }
        $address .= ", {$this->city}, {$this->state} {$this->postal_code}";

        return $address;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Practitioner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'azure_fhir_id', // Reference to FHIR Practitioner resource
        'npi', // National Provider Identifier
        'active',
        'name_prefix',
        'given_name',
        'family_name',
        'name_suffix',
        'phone',
        'email',
        'specialty',
        'license_number',
        'license_state',
        'license_expiration',
        'qualification_code',
        'qualification_display',
        'issuer_organization',
        // MSC Extensions
        'msc_provider_id', // Link to Supabase providers table
        'platform_status',
        'preferred_contact_method',
        'timezone',
    ];

    protected $casts = [
        'active' => 'boolean',
        'license_expiration' => 'date',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Get patients where this practitioner is the general practitioner
     */
    public function patientsAsGP(): HasMany
    {
        return $this->hasMany(Patient::class, 'general_practitioner_id');
    }

    /**
     * Get conditions recorded by this practitioner
     */
    public function recordedConditions(): HasMany
    {
        return $this->hasMany(Condition::class, 'recorder_id');
    }

    /**
     * Get conditions asserted by this practitioner
     */
    public function assertedConditions(): HasMany
    {
        return $this->hasMany(Condition::class, 'asserter_id');
    }

    /**
     * Get observations performed by this practitioner
     */
    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class, 'performer_id');
    }

    /**
     * Get encounters with this practitioner
     */
    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class, 'practitioner_id');
    }

    /**
     * Get document references authored by this practitioner
     */
    public function documentReferences(): HasMany
    {
        return $this->hasMany(DocumentReference::class, 'author_id');
    }

    /**
     * Scope for active practitioners
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope by specialty
     */
    public function scopeBySpecialty($query, $specialty)
    {
        return $query->where('specialty', $specialty);
    }

    /**
     * Get full name
     */
    public function getFullNameAttribute(): string
    {
        $name = '';

        if ($this->name_prefix) {
            $name .= $this->name_prefix . ' ';
        }

        $name .= trim($this->given_name . ' ' . $this->family_name);

        if ($this->name_suffix) {
            $name .= ', ' . $this->name_suffix;
        }

        return $name;
    }

    /**
     * Get display name with credentials
     */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->full_name;

        if ($this->qualification_display) {
            $name .= ', ' . $this->qualification_display;
        }

        return $name;
    }

    /**
     * Check if license is valid/current
     */
    public function hasValidLicense(): bool
    {
        if (!$this->license_number || !$this->license_expiration) {
            return false;
        }

        return $this->license_expiration->isFuture();
    }
}

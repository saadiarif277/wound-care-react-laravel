<?php

namespace App\Models;

use App\Models\Users\Organization\Organization;
use App\Models\Fhir\Facility;
use App\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientAssociation extends Model
{
    use HasFactory, BelongsToOrganization;

    protected $fillable = [
        'patient_fhir_id',
        'provider_id',
        'facility_id',
        'organization_id',
        'association_type',
        'is_primary_provider',
        'established_at',
        'terminated_at',
    ];

    protected $casts = [
        'is_primary_provider' => 'boolean',
        'established_at' => 'datetime',
        'terminated_at' => 'datetime',
    ];

    /**
     * Get the provider (user) associated with this patient
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /**
     * Get the facility associated with this patient
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Get the organization associated with this patient
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Scope to get only active associations
     */
    public function scopeActive($query)
    {
        return $query->whereNull('terminated_at');
    }

    /**
     * Scope to get only terminated associations
     */
    public function scopeTerminated($query)
    {
        return $query->whereNotNull('terminated_at');
    }

    /**
     * Scope to get associations by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('association_type', $type);
    }

    /**
     * Scope to get primary provider associations
     */
    public function scopePrimaryProvider($query)
    {
        return $query->where('is_primary_provider', true);
    }

    /**
     * Scope to get associations for a specific patient
     */
    public function scopeForPatient($query, $patientFhirId)
    {
        return $query->where('patient_fhir_id', $patientFhirId);
    }

    /**
     * Scope to get associations for a specific provider
     */
    public function scopeForProvider($query, $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    /**
     * Scope to get associations for a specific facility
     */
    public function scopeForFacility($query, $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    /**
     * Terminate this association
     */
    public function terminate(): void
    {
        $this->update(['terminated_at' => now()]);
    }

    /**
     * Reactivate this association
     */
    public function reactivate(): void
    {
        $this->update(['terminated_at' => null]);
    }

    /**
     * Check if this association is active
     */
    public function isActive(): bool
    {
        return $this->terminated_at === null;
    }

    /**
     * Get the duration of this association
     */
    public function getDurationAttribute(): string
    {
        $end = $this->terminated_at ?? now();
        return $this->established_at->diffForHumans($end, true);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'requested_role',

        // Provider fields
        'npi_number',
        'medical_license',
        'license_state',
        'specialization',
        'facility_name',
        'facility_address',

        // Office Manager fields
        'manager_name',
        'manager_email',

        // MSC Rep fields
        'territory',
        'manager_contact',
        'experience_years',

        // MSC SubRep fields
        'main_rep_name',
        'main_rep_email',

        // MSC Admin fields
        'department',
        'supervisor_name',
        'supervisor_email',

        // Request management
        'status',
        'request_notes',
        'admin_notes',
        'reviewed_at',
        'reviewed_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /**
     * Available user roles for access requests
     */
    const ROLES = [
        'provider' => 'Healthcare Provider',
        'office_manager' => 'Office Manager',
        'msc_rep' => 'MSC Sales Representative',
        'msc_subrep' => 'MSC Sub-Representative',
        'msc_admin' => 'MSC Administrator',
    ];

    /**
     * Request statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_DENIED = 'denied';

    /**
     * Get the user who reviewed this request
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope for pending requests
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for approved requests
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for denied requests
     */
    public function scopeDenied($query)
    {
        return $query->where('status', self::STATUS_DENIED);
    }

    /**
     * Get the display name for the requested role
     */
    public function getRoleDisplayNameAttribute()
    {
        return self::ROLES[$this->requested_role] ?? $this->requested_role;
    }

    /**
     * Get the full name of the requester
     */
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Check if the request is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the request is approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if the request is denied
     */
    public function isDenied(): bool
    {
        return $this->status === self::STATUS_DENIED;
    }

    /**
     * Approve the access request
     */
    public function approve(User $reviewedBy, string $notes = null): bool
    {
        return $this->update([
            'status' => self::STATUS_APPROVED,
            'reviewed_by' => $reviewedBy->id,
            'reviewed_at' => now(),
            'admin_notes' => $notes,
        ]);
    }

    /**
     * Deny the access request
     */
    public function deny(User $reviewedBy, string $notes = null): bool
    {
        return $this->update([
            'status' => self::STATUS_DENIED,
            'reviewed_by' => $reviewedBy->id,
            'reviewed_at' => now(),
            'admin_notes' => $notes,
        ]);
    }

    /**
     * Get role-specific fields that should be shown in the admin interface
     */
    public function getRoleSpecificFields(): array
    {
        switch ($this->requested_role) {
            case 'provider':
                return [
                    'NPI Number' => $this->npi_number,
                    'Medical License' => $this->medical_license,
                    'License State' => $this->license_state,
                    'Specialization' => $this->specialization,
                    'Facility Name' => $this->facility_name,
                    'Facility Address' => $this->facility_address,
                ];

            case 'office_manager':
                return [
                    'Facility Name' => $this->facility_name,
                    'Facility Address' => $this->facility_address,
                    'Manager Name' => $this->manager_name,
                    'Manager Email' => $this->manager_email,
                ];

            case 'msc_rep':
                return [
                    'Territory' => $this->territory,
                    'Manager Contact' => $this->manager_contact,
                    'Years of Experience' => $this->experience_years,
                ];

            case 'msc_subrep':
                return [
                    'Territory' => $this->territory,
                    'Main Rep Name' => $this->main_rep_name,
                    'Main Rep Email' => $this->main_rep_email,
                ];

            case 'msc_admin':
                return [
                    'Department' => $this->department,
                    'Supervisor Name' => $this->supervisor_name,
                    'Supervisor Email' => $this->supervisor_email,
                ];

            default:
                return [];
        }
    }
}

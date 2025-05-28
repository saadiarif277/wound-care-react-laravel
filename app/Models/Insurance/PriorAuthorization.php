<?php

namespace App\Models\Insurance;

use App\Models\Fhir\Coverage;
use App\Models\Order\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriorAuthorization extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'coverage_id',
        'order_id',
        'request_date',
        'status', // pending, approved, denied, expired, cancelled
        'authorization_number',
        'requested_service_codes', // JSON array
        'approved_service_codes', // JSON array
        'denied_service_codes', // JSON array
        'approval_date',
        'effective_date',
        'expiration_date',
        'units_approved',
        'visit_limit',
        'clinical_criteria', // JSON
        'denial_reason',
        'appeal_deadline',
        'reviewer_name',
        'reviewer_phone',
        'submitter_user_id',
        'submission_method', // fax, portal, phone, etc.
        'reference_number',
        'notes',
    ];

    protected $casts = [
        'request_date' => 'datetime',
        'approval_date' => 'datetime',
        'effective_date' => 'date',
        'expiration_date' => 'date',
        'appeal_deadline' => 'date',
        'requested_service_codes' => 'array',
        'approved_service_codes' => 'array',
        'denied_service_codes' => 'array',
        'clinical_criteria' => 'array',
        'units_approved' => 'integer',
        'visit_limit' => 'integer',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Get the coverage this prior auth belongs to
     */
    public function coverage(): BelongsTo
    {
        return $this->belongsTo(Coverage::class);
    }

    /**
     * Get the order this prior auth is for
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who submitted the request
     */
    public function submitterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitter_user_id');
    }

    /**
     * Scope for approved authorizations
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for pending authorizations
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for current/valid authorizations
     */
    public function scopeCurrent($query)
    {
        $today = now()->toDate();
        return $query->where('status', 'approved')
                    ->where('effective_date', '<=', $today)
                    ->where('expiration_date', '>=', $today);
    }

    /**
     * Check if authorization is currently valid
     */
    public function isCurrentlyValid(): bool
    {
        if ($this->status !== 'approved') {
            return false;
        }

        $today = now()->toDate();

        if ($this->effective_date && $this->effective_date > $today) {
            return false;
        }

        if ($this->expiration_date && $this->expiration_date < $today) {
            return false;
        }

        return true;
    }

    /**
     * Check if authorization is expiring soon
     */
    public function isExpiringSoon($days = 30): bool
    {
        if (!$this->expiration_date || $this->status !== 'approved') {
            return false;
        }

        return $this->expiration_date->isBefore(now()->addDays($days));
    }

    /**
     * Get days until expiration
     */
    public function getDaysUntilExpirationAttribute(): ?int
    {
        if (!$this->expiration_date) {
            return null;
        }

        return now()->diffInDays($this->expiration_date, false);
    }

    /**
     * Get authorization status display
     */
    public function getStatusDisplayAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    /**
     * Get approved services display
     */
    public function getApprovedServicesDisplayAttribute(): string
    {
        if (empty($this->approved_service_codes)) {
            return 'None specified';
        }

        return implode(', ', $this->approved_service_codes);
    }
}

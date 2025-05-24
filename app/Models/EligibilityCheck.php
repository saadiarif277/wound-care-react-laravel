<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EligibilityCheck extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'coverage_id',
        'order_id',
        'check_date',
        'status', // eligible, not_eligible, needs_review, pending, error
        'eligibility_status',
        'benefit_details', // JSON
        'copay_amount',
        'deductible_amount',
        'out_of_pocket_max',
        'coverage_percentage',
        'prior_authorization_required',
        'prior_auth_status',
        'effective_date',
        'termination_date',
        'response_raw', // JSON - raw response from eligibility service
        'error_message',
        'checked_by_user_id',
        'verification_source', // clearinghouse, manual, etc.
    ];

    protected $casts = [
        'check_date' => 'datetime',
        'effective_date' => 'date',
        'termination_date' => 'date',
        'benefit_details' => 'array',
        'response_raw' => 'array',
        'copay_amount' => 'decimal:2',
        'deductible_amount' => 'decimal:2',
        'out_of_pocket_max' => 'decimal:2',
        'coverage_percentage' => 'decimal:2',
        'prior_authorization_required' => 'boolean',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Get the coverage this check belongs to
     */
    public function coverage(): BelongsTo
    {
        return $this->belongsTo(Coverage::class);
    }

    /**
     * Get the order this check is for
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who performed the check
     */
    public function checkedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by_user_id');
    }

    /**
     * Scope for eligible checks
     */
    public function scopeEligible($query)
    {
        return $query->where('status', 'eligible');
    }

    /**
     * Scope for recent checks
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('check_date', '>=', now()->subDays($days));
    }

    /**
     * Check if eligibility is current/valid
     */
    public function isCurrentlyValid(): bool
    {
        if ($this->status !== 'eligible') {
            return false;
        }

        // Check if within effective dates
        $today = now()->toDate();

        if ($this->effective_date && $this->effective_date > $today) {
            return false;
        }

        if ($this->termination_date && $this->termination_date < $today) {
            return false;
        }

        return true;
    }

    /**
     * Get formatted coverage percentage
     */
    public function getCoveragePercentageDisplayAttribute(): string
    {
        if ($this->coverage_percentage) {
            return $this->coverage_percentage . '%';
        }

        return 'Unknown';
    }

    /**
     * Get patient responsibility amount
     */
    public function getPatientResponsibilityAttribute(): ?float
    {
        if ($this->copay_amount) {
            return $this->copay_amount;
        }

        return null;
    }
}

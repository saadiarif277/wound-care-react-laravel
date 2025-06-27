<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceVerification extends Model
{
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'episode_id',
        'insurance_type',
        'status',
        'eligibility_status',
        'coverage_details',
        'benefits',
        'copay_amount',
        'deductible_remaining',
        'out_of_pocket_remaining',
        'verified_at',
        'response_data',
        'error_message',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'coverage_details' => 'array',
        'benefits' => 'array',
        'copay_amount' => 'decimal:2',
        'deductible_remaining' => 'decimal:2',
        'out_of_pocket_remaining' => 'decimal:2',
        'verified_at' => 'datetime',
        'response_data' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the episode that owns the insurance verification.
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Scope a query to only include verified insurances.
     */
    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }

    /**
     * Scope a query to only include failed verifications.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Check if the insurance is eligible.
     */
    public function isEligible(): bool
    {
        return $this->status === 'verified' 
            && in_array($this->eligibility_status, ['active', 'eligible']);
    }

    /**
     * Get the formatted copay amount.
     */
    public function getFormattedCopayAttribute(): string
    {
        return $this->copay_amount 
            ? '$' . number_format($this->copay_amount, 2)
            : 'N/A';
    }

    /**
     * Get the formatted deductible remaining.
     */
    public function getFormattedDeductibleRemainingAttribute(): string
    {
        return $this->deductible_remaining !== null
            ? '$' . number_format($this->deductible_remaining, 2)
            : 'N/A';
    }
}
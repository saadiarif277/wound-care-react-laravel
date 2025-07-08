<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NewCommissionRecord extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'commission_records';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the ID.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'order_id',
        'user_id',
        'rule_id',
        'sales_rep_id',
        'parent_rep_id',
        'base_amount',
        'commission_amount',
        'status',
        'payout_id',
        'split_type',
        'approved_by',
        'approved_at',
        'paid_at',
        'invoice_number',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'base_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
        });
    }

    /**
     * Get the order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Get the user who earned the commission.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(NewUser::class, 'user_id');
    }

    /**
     * Get the commission rule.
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'rule_id');
    }

    /**
     * Get the sales rep.
     */
    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRep::class, 'sales_rep_id');
    }

    /**
     * Get the parent rep (for splits).
     */
    public function parentRep(): BelongsTo
    {
        return $this->belongsTo(SalesRep::class, 'parent_rep_id');
    }

    /**
     * Get the payout.
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(CommissionPayout::class, 'payout_id');
    }

    /**
     * Get the approver.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(NewUser::class, 'approved_by');
    }

    /**
     * Scope to pending records.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to approved records.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to paid records.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope to cancelled records.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Scope to clawback records.
     */
    public function scopeClawback($query)
    {
        return $query->where('status', 'clawback');
    }

    /**
     * Scope by split type.
     */
    public function scopeBySplitType($query, string $type)
    {
        return $query->where('split_type', $type);
    }

    /**
     * Scope to records without payout.
     */
    public function scopeWithoutPayout($query)
    {
        return $query->whereNull('payout_id');
    }

    /**
     * Check if pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if can be included in payout.
     */
    public function canBeIncludedInPayout(): bool
    {
        return $this->status === 'approved' && is_null($this->payout_id);
    }

    /**
     * Approve the commission.
     */
    public function approve(string $userId): void
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }

    /**
     * Cancel the commission.
     */
    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    /**
     * Mark as clawback.
     */
    public function clawback(): void
    {
        $this->update(['status' => 'clawback']);
    }

    /**
     * Get display amount.
     */
    public function getDisplayAmountAttribute(): string
    {
        return '$' . number_format($this->commission_amount, 2);
    }

    /**
     * Get effective rate.
     */
    public function getEffectiveRateAttribute(): float
    {
        if ($this->base_amount > 0) {
            return ($this->commission_amount / $this->base_amount) * 100;
        }
        return 0;
    }
}
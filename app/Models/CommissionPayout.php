<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CommissionPayout extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'commission_payouts';

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
        'sales_rep_id',
        'period_start',
        'period_end',
        'gross_amount',
        'deductions',
        'net_amount',
        'commission_count',
        'status',
        'batch_number',
        'approved_by',
        'approved_at',
        'paid_at',
        'payment_method',
        'payment_reference',
        'summary_data',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'gross_amount' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'commission_count' => 'integer',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'summary_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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

            if (empty($model->batch_number)) {
                $model->batch_number = self::generateBatchNumber();
            }
        });
    }

    /**
     * Generate a unique batch number.
     */
    public static function generateBatchNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        
        $lastPayout = static::where('batch_number', 'like', "PAY-{$year}{$month}-%")
            ->orderBy('batch_number', 'desc')
            ->first();

        if ($lastPayout) {
            $lastNumber = intval(substr($lastPayout->batch_number, -4));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return 'PAY-' . $year . $month . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get the sales rep.
     */
    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRep::class, 'sales_rep_id');
    }

    /**
     * Get the approver.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(NewUser::class, 'approved_by');
    }

    /**
     * Get commission records in this payout.
     */
    public function commissionRecords(): HasMany
    {
        return $this->hasMany(CommissionRecord::class, 'payout_id');
    }

    /**
     * Scope to draft payouts.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope to calculated payouts.
     */
    public function scopeCalculated($query)
    {
        return $query->where('status', 'calculated');
    }

    /**
     * Scope to approved payouts.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to paid payouts.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope by period.
     */
    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->where('period_start', '>=', $startDate)
                     ->where('period_end', '<=', $endDate);
    }

    /**
     * Check if draft.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if calculated.
     */
    public function isCalculated(): bool
    {
        return $this->status === 'calculated';
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
     * Check if cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Calculate totals from commission records.
     */
    public function calculateTotals(): void
    {
        $records = $this->commissionRecords;
        
        $this->commission_count = $records->count();
        $this->gross_amount = $records->sum('commission_amount');
        $this->net_amount = $this->gross_amount - $this->deductions;
        
        $this->save();
    }

    /**
     * Approve the payout.
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
     * Mark as paid.
     */
    public function markAsPaid(string $paymentReference, string $paymentMethod = 'bank_transfer'): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_reference' => $paymentReference,
            'payment_method' => $paymentMethod,
        ]);

        // Update all commission records to paid
        $this->commissionRecords()->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    /**
     * Cancel the payout.
     */
    public function cancel(string $reason = null): void
    {
        $this->update([
            'status' => 'cancelled',
            'notes' => $reason,
        ]);

        // Remove payout reference from commission records
        $this->commissionRecords()->update([
            'payout_id' => null,
            'status' => 'approved', // Revert to approved so they can be included in future payouts
        ]);
    }
}
<?php

namespace App\Models\Commissions;

use App\Models\MscSalesRep;
use App\Models\User;
use App\Models\Commissions\CommissionRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionPayout extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'rep_id',
        'period_start',
        'period_end',
        'total_amount',
        'status',
        'approved_by',
        'approved_at',
        'processed_at',
        'payment_reference',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    public function rep()
    {
        return $this->belongsTo(MscSalesRep::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function commissionRecords()
    {
        return $this->hasMany(CommissionRecord::class, 'payout_id');
    }

    public function scopeCalculated($query)
    {
        return $query->where('status', 'calculated');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }
}

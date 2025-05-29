<?php

namespace App\Models\Commissions;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\MscSalesRep;
use App\Models\User;
use App\Models\Commissions\CommissionPayout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommissionRecord extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'order_item_id',
        'rep_id',
        'parent_rep_id',
        'amount',
        'percentage_rate',
        'type',
        'status',
        'calculation_date',
        'approved_by',
        'approved_at',
        'payout_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage_rate' => 'decimal:2',
        'calculation_date' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function rep()
    {
        return $this->belongsTo(MscSalesRep::class, 'rep_id');
    }

    public function parentRep()
    {
        return $this->belongsTo(MscSalesRep::class, 'parent_rep_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payout()
    {
        return $this->belongsTo(CommissionPayout::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeIncludedInPayout($query)
    {
        return $query->where('status', 'included_in_payout');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}

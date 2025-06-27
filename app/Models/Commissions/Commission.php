<?php

namespace App\Models\Commissions;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    use HasFactory;

    protected $fillable = [
        'rep_id',
        'period_start',
        'period_end',
        'total_amount',
        'status',
        'notes',
        'approved_by',
        'approved_at',
        'processed_at',
        'payment_reference',
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
        return $this->belongsTo(User::class, 'rep_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

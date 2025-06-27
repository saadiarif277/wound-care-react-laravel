<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Order\Order;

class Payment extends Model
{
    protected $fillable = [
        'provider_id',
        'order_id',
        'amount',
        'payment_method',
        'reference_number',
        'payment_date',
        'notes',
        'status',
        'posted_by_user_id',
        'paid_to', // 'msc' or 'manufacturer'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the provider (User) who made this payment.
     */
    public function provider()
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /**
     * Get the order this payment is for.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who posted this payment.
     */
    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }
}

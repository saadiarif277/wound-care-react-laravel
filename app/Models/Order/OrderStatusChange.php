<?php

namespace App\Models\Order;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Order\ProductRequest;

class OrderStatusChange extends Model
{
    protected $fillable = [
        'order_id',
        'previous_status',
        'new_status',
        'changed_by',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the order that owns the status change
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductRequest::class, 'order_id');
    }

    /**
     * Get the user who made the change
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'changed_by');
    }

    /**
     * Scope to get changes for a specific order
     */
    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Scope to get changes by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('new_status', $status);
    }

    /**
     * Scope to get recent changes
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get formatted status change description
     */
    public function getStatusChangeDescription(): string
    {
        $statusLabels = [
            'pending' => 'Pending',
            'pending_ivr' => 'Pending IVR',
            'ivr_sent' => 'IVR Sent',
            'ivr_confirmed' => 'IVR Confirmed',
            'approved' => 'Approved',
            'sent_back' => 'Sent Back',
            'denied' => 'Denied',
            'submitted_to_manufacturer' => 'Submitted to Manufacturer',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];

        $previous = $statusLabels[$this->previous_status] ?? $this->previous_status;
        $new = $statusLabels[$this->new_status] ?? $this->new_status;

        return "Changed from {$previous} to {$new}";
    }

    /**
     * Check if this is a significant status change
     */
    public function isSignificantChange(): bool
    {
        $significantChanges = [
            'pending' => ['approved', 'denied', 'cancelled'],
            'pending_ivr' => ['ivr_sent', 'approved', 'denied'],
            'ivr_sent' => ['ivr_confirmed', 'approved', 'denied'],
            'approved' => ['submitted_to_manufacturer', 'shipped'],
            'submitted_to_manufacturer' => ['shipped', 'delivered'],
        ];

        return isset($significantChanges[$this->previous_status]) &&
               in_array($this->new_status, $significantChanges[$this->previous_status]);
    }
}

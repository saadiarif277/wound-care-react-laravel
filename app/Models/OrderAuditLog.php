<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'action',
        'details',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the order this audit log belongs to
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user who performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for filtering by action type
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', 'like', "%{$action}%");
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get formatted action description
     */
    public function getFormattedActionAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->action));
    }

    /**
     * Get human-readable details
     */
    public function getHumanReadableDetailsAttribute(): string
    {
        if (!$this->details) {
            return '';
        }

        $details = [];
        foreach ($this->details as $key => $value) {
            if ($value !== null) {
                $details[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
            }
        }

        return implode(', ', $details);
    }
}

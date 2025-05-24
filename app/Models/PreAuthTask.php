<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PreAuthTask extends Model
{
    use HasUuids;

    protected $fillable = [
        'order_id',
        'external_task_id',
        'status',
        'task_name',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The order this pre-auth task belongs to
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope to get pending tasks
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get completed tasks
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope to get failed tasks
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}

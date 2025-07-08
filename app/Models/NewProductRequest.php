<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class NewProductRequest extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'product_requests';

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
        'episode_id',
        'request_number',
        'requested_by',
        'requested_for_provider_fhir_id',
        'request_type',
        'status',
        'clinical_need',
        'urgency',
        'product_categories',
        'specific_products',
        'needed_by_date',
        'submitted_at',
        'reviewed_at',
        'converted_to_order_id',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'product_categories' => 'array',
        'specific_products' => 'array',
        'needed_by_date' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
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

            if (empty($model->request_number)) {
                $model->request_number = self::generateRequestNumber();
            }
        });
    }

    /**
     * Generate a unique request number.
     */
    public static function generateRequestNumber(): string
    {
        $year = date('Y');
        $lastRequest = static::where('request_number', 'like', "REQ-{$year}-%")
            ->orderBy('request_number', 'desc')
            ->first();

        if ($lastRequest) {
            $lastNumber = intval(substr($lastRequest->request_number, -6));
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return 'REQ-' . $year . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get the episode.
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /**
     * Get the requester.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the converted order.
     */
    public function order(): HasOne
    {
        return $this->hasOne(Order::class, 'product_request_id');
    }

    /**
     * Scope to submitted requests.
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope to approved requests.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to requests by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('request_type', $type);
    }

    /**
     * Scope to urgent requests.
     */
    public function scopeUrgent($query)
    {
        return $query->whereIn('urgency', ['urgent', 'stat']);
    }

    /**
     * Check if request is submitted.
     */
    public function isSubmitted(): bool
    {
        return $this->status === 'submitted' && !is_null($this->submitted_at);
    }

    /**
     * Check if request is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if request is converted to order.
     */
    public function isConvertedToOrder(): bool
    {
        return $this->status === 'converted_to_order' && !is_null($this->converted_to_order_id);
    }

    /**
     * Check if request is urgent.
     */
    public function isUrgent(): bool
    {
        return in_array($this->urgency, ['urgent', 'stat']);
    }

    /**
     * Submit the request.
     */
    public function submit(): void
    {
        $this->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    /**
     * Approve the request.
     */
    public function approve(): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Mark as converted to order.
     */
    public function markAsConverted(string $orderId): void
    {
        $this->update([
            'status' => 'converted_to_order',
            'converted_to_order_id' => $orderId,
        ]);
    }

    /**
     * Cancel the request.
     */
    public function cancel(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    /**
     * Get the display name for the request type.
     */
    public function getTypeDisplayAttribute(): string
    {
        return str_replace('_', ' ', ucwords($this->request_type));
    }

    /**
     * Get the display name for the urgency.
     */
    public function getUrgencyDisplayAttribute(): string
    {
        return ucfirst($this->urgency);
    }
}
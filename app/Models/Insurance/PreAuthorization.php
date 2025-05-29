<?php

namespace App\Models\Insurance;

use App\Models\Order\ProductRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreAuthorization extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_request_id',
        'authorization_number',
        'payer_name',
        'patient_id',
        'diagnosis_codes',
        'procedure_codes',
        'clinical_documentation',
        'urgency',
        'status',
        'submitted_at',
        'submitted_by',
        'estimated_approval_date',
        'payer_transaction_id',
        'payer_confirmation',
        'payer_response',
        'approved_at',
        'approved_amount',
        'expires_at',
        'denied_at',
        'denial_reason',
        'last_status_check',
    ];

    protected $casts = [
        'diagnosis_codes' => 'array',
        'procedure_codes' => 'array',
        'payer_response' => 'array',
        'submitted_at' => 'datetime',
        'estimated_approval_date' => 'datetime',
        'approved_at' => 'datetime',
        'expires_at' => 'datetime',
        'denied_at' => 'datetime',
        'last_status_check' => 'datetime',
        'approved_amount' => 'decimal:2',
    ];

    public function productRequest(): BelongsTo
    {
        return $this->belongsTo(ProductRequest::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
} 
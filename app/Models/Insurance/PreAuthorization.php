<?php

namespace App\Models\Insurance;

use App\Models\Order\ProductRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreAuthorization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_request_id',
        'authorization_number',
        'payer_name',
        'patient_id',
        'clinical_documentation',
        'urgency',
        'status',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'denied_at',
        'last_status_check',
        'payer_transaction_id',
        'payer_confirmation',
        'payer_response',
        'estimated_approval_date',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'payer_response' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'denied_at' => 'datetime',
        'last_status_check' => 'datetime',
        'estimated_approval_date' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the product request that this pre-authorization belongs to.
     */
    public function productRequest(): BelongsTo
    {
        return $this->belongsTo(ProductRequest::class, 'product_request_id');
    }

    /**
     * Get the user who submitted this pre-authorization.
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the ICD-10 diagnosis codes for this pre-authorization.
     */
    public function diagnosisCodes(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\Medical\Icd10Code::class,
            'pre_authorization_diagnosis_codes',
            'pre_authorization_id',
            'icd10_code_id'
        )->withPivot(['type', 'sequence'])
          ->withTimestamps()
          ->orderBy('pivot_sequence');
    }

    /**
     * Get the CPT procedure codes for this pre-authorization.
     */
    public function procedureCodes(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\Medical\CptCode::class,
            'pre_authorization_procedure_codes',
            'pre_authorization_id',
            'cpt_code_id'
        )->withPivot(['quantity', 'modifier', 'sequence'])
          ->withTimestamps()
          ->orderBy('pivot_sequence');
    }

    /**
     * Get primary diagnosis codes.
     */
    public function primaryDiagnosisCodes(): BelongsToMany
    {
        return $this->diagnosisCodes()->wherePivot('type', 'primary');
    }

    /**
     * Get secondary diagnosis codes.
     */
    public function secondaryDiagnosisCodes(): BelongsToMany
    {
        return $this->diagnosisCodes()->wherePivot('type', 'secondary');
    }

    /**
     * Check if pre-authorization is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if pre-authorization is denied.
     */
    public function isDenied(): bool
    {
        return $this->status === 'denied';
    }

    /**
     * Check if pre-authorization is pending.
     */
    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'submitted']);
    }

    /**
     * Check if pre-authorization is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get the status color for display.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'green',
            'denied' => 'red',
            'cancelled' => 'gray',
            'submitted' => 'blue',
            'pending' => 'yellow',
            default => 'gray',
        };
    }

    /**
     * Get the urgency color for display.
     */
    public function getUrgencyColorAttribute(): string
    {
        return match ($this->urgency) {
            'emergency' => 'red',
            'urgent' => 'yellow',
            'routine' => 'green',
            default => 'gray',
        };
    }

    /**
     * Scope for pending pre-authorizations.
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'submitted']);
    }

    /**
     * Scope for approved pre-authorizations.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for expired pre-authorizations.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Scope for pre-authorizations expiring soon (within 30 days).
     */
    public function scopeExpiringSoon($query, $days = 30)
    {
        return $query->where('expires_at', '>', now())
                    ->where('expires_at', '<=', now()->addDays($days));
    }

    /**
     * Scope for pre-authorizations by payer.
     */
    public function scopeByPayer($query, $payerName)
    {
        return $query->where('payer_name', 'like', "%{$payerName}%");
    }

    /**
     * Get a formatted list of all diagnosis codes.
     */
    public function getFormattedDiagnosisCodesAttribute(): string
    {
        return $this->diagnosisCodes->map(function ($code) {
            $type = $code->pivot->type === 'primary' ? ' (Primary)' : '';
            return $code->code . $type;
        })->join(', ');
    }

    /**
     * Get a formatted list of all procedure codes.
     */
    public function getFormattedProcedureCodesAttribute(): string
    {
        return $this->procedureCodes->map(function ($code) {
            $modifier = $code->pivot->modifier ? '-' . $code->pivot->modifier : '';
            $quantity = $code->pivot->quantity > 1 ? ' (x' . $code->pivot->quantity . ')' : '';
            return $code->code . $modifier . $quantity;
        })->join(', ');
    }

    /**
     * Create pre-authorization with diagnosis and procedure codes.
     */
    public static function createWithCodes(array $preAuthData, array $diagnosisCodes = [], array $procedureCodes = []): self
    {
        $preAuth = static::create($preAuthData);

        // Attach diagnosis codes
        foreach ($diagnosisCodes as $diagnosisCode) {
            $preAuth->diagnosisCodes()->attach($diagnosisCode['icd10_code_id'], [
                'type' => $diagnosisCode['type'] ?? 'secondary',
                'sequence' => $diagnosisCode['sequence'] ?? 1,
            ]);
        }

        // Attach procedure codes
        foreach ($procedureCodes as $procedureCode) {
            $preAuth->procedureCodes()->attach($procedureCode['cpt_code_id'], [
                'quantity' => $procedureCode['quantity'] ?? 1,
                'modifier' => $procedureCode['modifier'] ?? null,
                'sequence' => $procedureCode['sequence'] ?? 1,
            ]);
        }

        return $preAuth->load(['diagnosisCodes', 'procedureCodes']);
    }
}

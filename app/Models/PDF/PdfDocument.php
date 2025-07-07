<?php

namespace App\Models\PDF;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\Order\Order;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\User;

class PdfDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'document_id',
        'order_id',
        'episode_id',
        'template_id',
        'document_type',
        'status',
        'file_path',
        'azure_container',
        'azure_blob_url',
        'filled_data',
        'signature_status',
        'generated_at',
        'expires_at',
        'completed_at',
        'generated_by',
        'metadata',
        'hash'
    ];

    protected $casts = [
        'filled_data' => 'array',
        'signature_status' => 'array',
        'metadata' => 'array',
        'generated_at' => 'datetime',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->document_id)) {
                $model->document_id = Str::uuid()->toString();
            }
        });
    }

    /**
     * Get the order this document belongs to
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the episode this document belongs to
     */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(PatientManufacturerIVREpisode::class, 'episode_id');
    }

    /**
     * Get the template used to generate this document
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ManufacturerPdfTemplate::class, 'template_id');
    }

    /**
     * Get the user who generated this document
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Get all signatures for this document
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(PdfSignature::class, 'document_id');
    }

    /**
     * Get all access logs for this document
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(PdfAccessLog::class, 'document_id');
    }

    /**
     * Check if document is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if document is fully signed
     */
    public function isFullySigned(): bool
    {
        if ($this->status !== 'partially_signed' && $this->status !== 'completed') {
            return false;
        }

        $requiredSignatures = $this->template->signatureConfigs()
            ->where('is_required', true)
            ->pluck('signature_type')
            ->toArray();

        $completedSignatures = $this->signatures()
            ->where('is_valid', true)
            ->pluck('signature_type')
            ->toArray();

        return empty(array_diff($requiredSignatures, $completedSignatures));
    }

    /**
     * Update signature status
     */
    public function updateSignatureStatus(): void
    {
        $signatureStatus = [];
        $requiredSignatures = $this->template->signatureConfigs;

        foreach ($requiredSignatures as $config) {
            $signature = $this->signatures()
                ->where('signature_type', $config->signature_type)
                ->where('is_valid', true)
                ->first();

            $signatureStatus[$config->signature_type] = [
                'required' => $config->is_required,
                'signed' => !is_null($signature),
                'signed_at' => $signature?->signed_at,
                'signer_name' => $signature?->signer_name
            ];
        }

        $this->update(['signature_status' => $signatureStatus]);

        // Update document status based on signatures
        if ($this->isFullySigned()) {
            $this->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);
        } elseif ($this->signatures()->exists()) {
            $this->update(['status' => 'partially_signed']);
        }
    }

    /**
     * Generate secure download URL with expiration
     */
    public function generateSecureUrl(int $expirationMinutes = 60): string
    {
        // This would integrate with Azure Blob Storage SAS tokens
        // For now, return the basic URL
        return $this->azure_blob_url ?? $this->getAzureUrl();
    }

    /**
     * Get the full Azure URL for this document
     */
    public function getAzureUrl(): string
    {
        return sprintf(
            'https://%s.blob.core.windows.net/%s/%s',
            config('azure.storage.account_name'),
            $this->azure_container,
            $this->file_path
        );
    }

    /**
     * Log access to this document
     */
    public function logAccess(string $action, ?User $user = null, array $context = []): void
    {
        $this->accessLogs()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'context' => $context,
            'accessed_at' => now()
        ]);
    }

    /**
     * Scope for pending documents
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['draft', 'generated', 'pending_signature']);
    }

    /**
     * Scope for completed documents
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for documents expiring soon
     */
    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->where('expires_at', '>', now())
            ->where('status', '!=', 'completed');
    }
}
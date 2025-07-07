<?php

namespace App\Models\PDF;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class PdfSignature extends Model
{
    protected $fillable = [
        'document_id',
        'user_id',
        'signature_type',
        'signer_name',
        'signer_email',
        'signer_title',
        'signature_data',
        'signature_hash',
        'signed_at',
        'ip_address',
        'user_agent',
        'geo_location',
        'audit_data',
        'is_valid',
        'invalidation_reason'
    ];

    protected $casts = [
        'geo_location' => 'array',
        'audit_data' => 'array',
        'is_valid' => 'boolean',
        'signed_at' => 'datetime'
    ];

    /**
     * Get the document this signature belongs to
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(PdfDocument::class, 'document_id');
    }

    /**
     * Get the user who created this signature
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate hash for signature data
     */
    public static function generateSignatureHash(string $signatureData): string
    {
        return hash('sha256', $signatureData);
    }

    /**
     * Verify signature integrity
     */
    public function verifyIntegrity(): bool
    {
        $currentHash = self::generateSignatureHash($this->signature_data);
        return $currentHash === $this->signature_hash;
    }

    /**
     * Invalidate signature
     */
    public function invalidate(string $reason): void
    {
        $this->update([
            'is_valid' => false,
            'invalidation_reason' => $reason
        ]);
    }

    /**
     * Get signature image as data URL
     */
    public function getSignatureDataUrl(): string
    {
        if (str_starts_with($this->signature_data, 'data:image')) {
            return $this->signature_data;
        }
        
        // Assume it's base64 encoded PNG
        return 'data:image/png;base64,' . $this->signature_data;
    }

    /**
     * Save signature image to file
     */
    public function saveToFile(string $path): bool
    {
        $data = $this->signature_data;
        
        // Remove data URL prefix if present
        if (str_starts_with($data, 'data:image')) {
            $data = substr($data, strpos($data, ',') + 1);
        }
        
        $imageData = base64_decode($data);
        return file_put_contents($path, $imageData) !== false;
    }

    /**
     * Scope for valid signatures
     */
    public function scopeValid($query)
    {
        return $query->where('is_valid', true);
    }

    /**
     * Scope for signatures by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('signature_type', $type);
    }

    /**
     * Create audit entry for signature
     */
    public function createAuditEntry(array $data): void
    {
        $auditData = $this->audit_data ?? [];
        $auditData[] = array_merge($data, [
            'timestamp' => now()->toISOString(),
            'ip_address' => request()->ip()
        ]);
        
        $this->update(['audit_data' => $auditData]);
    }
}
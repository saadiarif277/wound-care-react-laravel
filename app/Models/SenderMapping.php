<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class SenderMapping extends Model
{
    protected $fillable = [
        'manufacturer_id',
        'document_type',
        'organization',
        'sender_id',
        'priority',
        'is_active',
        'conditions',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'conditions' => 'array',
    ];

    // Relationships
    public function verifiedSender(): BelongsTo
    {
        return $this->belongsTo(VerifiedSender::class, 'sender_id');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByManufacturer(Builder $query, string $manufacturerId): Builder
    {
        return $query->where('manufacturer_id', $manufacturerId);
    }

    public function scopeByDocumentType(Builder $query, string $documentType): Builder
    {
        return $query->where('document_type', $documentType);
    }

    public function scopeByOrganization(Builder $query, string $organization): Builder
    {
        return $query->where('organization', $organization);
    }

    public function scopeOrderByPriority(Builder $query): Builder
    {
        return $query->orderBy('priority', 'desc');
    }

    // Helper Methods
    public function matches(array $context): bool
    {
        // Check manufacturer
        if ($this->manufacturer_id && isset($context['manufacturer_id'])) {
            if ($this->manufacturer_id !== $context['manufacturer_id']) {
                return false;
            }
        }

        // Check document type
        if ($this->document_type && isset($context['document_type'])) {
            if ($this->document_type !== $context['document_type']) {
                return false;
            }
        }

        // Check organization
        if ($this->organization && isset($context['organization'])) {
            if ($this->organization !== $context['organization']) {
                return false;
            }
        }

        // Check additional conditions
        if ($this->conditions && is_array($this->conditions)) {
            foreach ($this->conditions as $key => $value) {
                if (!isset($context[$key]) || $context[$key] !== $value) {
                    return false;
                }
            }
        }

        return true;
    }

    public function getDescriptionAttribute(): string
    {
        $parts = [];

        if ($this->manufacturer_id) {
            $parts[] = "Manufacturer: {$this->manufacturer_id}";
        }

        if ($this->document_type) {
            $parts[] = "Document: {$this->document_type}";
        }

        if ($this->organization) {
            $parts[] = "Organization: {$this->organization}";
        }

        return empty($parts) ? 'Default mapping' : implode(', ', $parts);
    }

    // Static Methods
    public static function findBestMatch(array $context): ?self
    {
        return self::with('verifiedSender')
            ->active()
            ->orderByPriority()
            ->get()
            ->first(function (self $mapping) use ($context) {
                return $mapping->matches($context) && 
                       $mapping->verifiedSender && 
                       $mapping->verifiedSender->canSendEmails();
            });
    }

    public static function createForManufacturer(
        string $manufacturerId, 
        string $documentType, 
        int $senderId,
        int $priority = 0
    ): self {
        return self::create([
            'manufacturer_id' => $manufacturerId,
            'document_type' => $documentType,
            'sender_id' => $senderId,
            'priority' => $priority,
            'is_active' => true,
        ]);
    }

    public static function createForOrganization(
        string $organization,
        string $documentType,
        int $senderId,
        int $priority = 0
    ): self {
        return self::create([
            'organization' => $organization,
            'document_type' => $documentType,
            'sender_id' => $senderId,
            'priority' => $priority,
            'is_active' => true,
        ]);
    }
}

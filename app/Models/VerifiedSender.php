<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class VerifiedSender extends Model
{
    protected $fillable = [
        'email_address',
        'display_name',
        'organization',
        'is_verified',
        'verification_method',
        'verification_details',
        'azure_domain_verification_code',
        'verified_at',
        'is_active',
        'daily_limit',
        'monthly_limit',
        'metadata',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
        'verification_details' => 'array',
        'metadata' => 'array',
        'daily_limit' => 'integer',
        'monthly_limit' => 'integer',
    ];

    // Relationships
    public function senderMappings(): HasMany
    {
        return $this->hasMany(SenderMapping::class, 'sender_id');
    }

    // Scopes
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByOrganization(Builder $query, string $organization): Builder
    {
        return $query->where('organization', $organization);
    }

    public function scopeByVerificationMethod(Builder $query, string $method): Builder
    {
        return $query->where('verification_method', $method);
    }

    // Helper Methods
    public function canSendEmails(): bool
    {
        return $this->is_verified && $this->is_active;
    }

    public function hasReachedDailyLimit(): bool
    {
        if (!$this->daily_limit) {
            return false;
        }

        // This would need actual email sending tracking
        // For now, return false
        return false;
    }

    public function hasReachedMonthlyLimit(): bool
    {
        if (!$this->monthly_limit) {
            return false;
        }

        // This would need actual email sending tracking
        // For now, return false
        return false;
    }

    public function getVerificationStatusAttribute(): string
    {
        if (!$this->is_verified) {
            return 'pending';
        }

        if (!$this->is_active) {
            return 'inactive';
        }

        return 'verified';
    }

    public function getFromAddressAttribute(): string
    {
        if ($this->verification_method === 'on_behalf') {
            return "{$this->display_name} via MSC Platform <{$this->email_address}>";
        }

        return "{$this->display_name} <{$this->email_address}>";
    }

    public function markAsVerified(): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }

    public function generateVerificationCode(): string
    {
        $code = 'msc-verify-' . bin2hex(random_bytes(16));
        $this->update(['azure_domain_verification_code' => $code]);
        return $code;
    }

    // Static Methods
    public static function getDefaultSender(): ?self
    {
        return self::where('organization', 'MSC Platform')
            ->verified()
            ->active()
            ->first();
    }

    public static function findBestSenderForContext(array $context): ?self
    {
        // Try to find a specific mapping first
        if (isset($context['manufacturer_id']) || isset($context['organization'])) {
            $mappingQuery = SenderMapping::with('verifiedSender')
                ->where('is_active', true)
                ->orderBy('priority', 'desc');

            if (isset($context['manufacturer_id'])) {
                $mappingQuery->where('manufacturer_id', $context['manufacturer_id']);
            }

            if (isset($context['document_type'])) {
                $mappingQuery->where('document_type', $context['document_type']);
            }

            if (isset($context['organization'])) {
                $mappingQuery->where('organization', $context['organization']);
            }

            $mapping = $mappingQuery->first();
            if ($mapping && $mapping->verifiedSender->canSendEmails()) {
                return $mapping->verifiedSender;
            }
        }

        // Fall back to organization-specific sender
        if (isset($context['organization'])) {
            $sender = self::byOrganization($context['organization'])
                ->verified()
                ->active()
                ->first();

            if ($sender) {
                return $sender;
            }
        }

        // Fall back to default MSC sender
        return self::getDefaultSender();
    }
}

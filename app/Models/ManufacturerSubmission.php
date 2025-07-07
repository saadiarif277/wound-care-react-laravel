<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class ManufacturerSubmission extends Model
{
    protected $fillable = [
        'order_id',
        'manufacturer_id',
        'manufacturer_name',
        'token',
        'status',
        'expires_at',
        'responded_at',
        'response_notes',
        'response_ip',
        'response_user_agent',
        'email_message_id',
        'email_recipients',
        'pdf_url',
        'pdf_filename',
        'order_details',
        'notification_sent',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
        'email_recipients' => 'array',
        'order_details' => 'array',
        'metadata' => 'array',
        'notification_sent' => 'boolean',
    ];

    // Scopes
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeDenied(Builder $query): Builder
    {
        return $query->where('status', 'denied');
    }

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeByManufacturer(Builder $query, string $manufacturerId): Builder
    {
        return $query->where('manufacturer_id', $manufacturerId);
    }

    // Helper Methods
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isDenied(): bool
    {
        return $this->status === 'denied';
    }

    public function hasResponded(): bool
    {
        return in_array($this->status, ['approved', 'denied']);
    }

    public function getResponseTimeAttribute(): ?string
    {
        if (!$this->responded_at) {
            return null;
        }

        return $this->responded_at->diffForHumans($this->created_at);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'approved' => 'green',
            'denied' => 'red',
            'expired' => 'gray',
            'pending' => 'orange',
            default => 'blue',
        };
    }

    public function getStatusIconAttribute(): string
    {
        return match($this->status) {
            'approved' => '✅',
            'denied' => '❌',
            'expired' => '⏰',
            'pending' => '⏳',
            default => '❓',
        };
    }

    public function markAsApproved(string $ip = null, string $userAgent = null, string $notes = null): void
    {
        $this->update([
            'status' => 'approved',
            'responded_at' => now(),
            'response_ip' => $ip,
            'response_user_agent' => $userAgent,
            'response_notes' => $notes,
        ]);
    }

    public function markAsDenied(string $ip = null, string $userAgent = null, string $notes = null): void
    {
        $this->update([
            'status' => 'denied',
            'responded_at' => now(),
            'response_ip' => $ip,
            'response_user_agent' => $userAgent,
            'response_notes' => $notes,
        ]);
    }

    public function markAsExpired(): void
    {
        if ($this->status === 'pending') {
            $this->update(['status' => 'expired']);
        }
    }

    public function generateUrls(): array
    {
        $baseUrl = config('app.url');
        
        return [
            'approve' => "{$baseUrl}/manufacturer/quick-response/{$this->token}/approve",
            'deny' => "{$baseUrl}/manufacturer/quick-response/{$this->token}/deny",
            'view' => "{$baseUrl}/manufacturer/view/{$this->token}",
        ];
    }

    // Static Methods
    public static function findByToken(string $token): ?self
    {
        return self::where('token', $token)
            ->notExpired()
            ->first();
    }

    public static function findValidToken(string $token): ?self
    {
        return self::where('token', $token)
            ->pending()
            ->notExpired()
            ->first();
    }

    public static function expireOldSubmissions(): int
    {
        return self::where('status', 'pending')
            ->expired()
            ->update(['status' => 'expired']);
    }

    public static function getStatsForManufacturer(string $manufacturerId): array
    {
        $submissions = self::byManufacturer($manufacturerId);

        return [
            'total' => $submissions->count(),
            'pending' => $submissions->clone()->pending()->count(),
            'approved' => $submissions->clone()->approved()->count(),
            'denied' => $submissions->clone()->denied()->count(),
            'expired' => $submissions->clone()->where('status', 'expired')->count(),
            'avg_response_time' => $submissions->clone()
                ->whereNotNull('responded_at')
                ->get()
                ->avg(function ($submission) {
                    return $submission->responded_at->diffInHours($submission->created_at);
                }),
        ];
    }

    public static function getRecentActivity(int $days = 7): array
    {
        return self::where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy(function ($submission) {
                return $submission->created_at->format('Y-m-d');
            })
            ->map(function ($submissions) {
                return [
                    'total' => $submissions->count(),
                    'approved' => $submissions->where('status', 'approved')->count(),
                    'denied' => $submissions->where('status', 'denied')->count(),
                    'pending' => $submissions->where('status', 'pending')->count(),
                ];
            })
            ->toArray();
    }
}

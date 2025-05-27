<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ProviderCredential extends Model
{
    protected $table = 'provider_credentials';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'provider_id',
        'credential_type',
        'credential_number',
        'credential_display_name',
        'issuing_authority',
        'issuing_state',
        'issue_date',
        'expiration_date',
        'effective_date',
        'verification_status',
        'verified_at',
        'verified_by',
        'verification_notes',
        'document_path',
        'document_type',
        'document_size',
        'document_hash',
        'auto_renewal_enabled',
        'reminder_sent_dates',
        'renewal_period_days',
        'next_reminder_date',
        'credential_metadata',
        'notes',
        'is_active',
        'is_primary',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiration_date' => 'date',
        'effective_date' => 'date',
        'verified_at' => 'datetime',
        'reminder_sent_dates' => 'array',
        'next_reminder_date' => 'date',
        'credential_metadata' => 'array',
        'auto_renewal_enabled' => 'boolean',
        'is_active' => 'boolean',
        'is_primary' => 'boolean',
        'document_size' => 'integer',
        'renewal_period_days' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'verification_status' => 'pending',
        'auto_renewal_enabled' => false,
        'reminder_sent_dates' => '[]',
        'credential_metadata' => '{}',
        'is_active' => true,
        'is_primary' => false,
        'renewal_period_days' => 30,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($credential) {
            if (empty($credential->id)) {
                $credential->id = (string) Str::uuid();
            }
        });

        static::saved(function ($credential) {
            // Update next reminder date when expiration date changes
            if ($credential->isDirty('expiration_date') && $credential->expiration_date) {
                $credential->updateNextReminderDate();
            }

            // Update provider profile completion percentage
            $providerProfile = $credential->providerProfile;
            if ($providerProfile) {
                $providerProfile->updateCompletionPercentage();
            }
        });
    }

    /**
     * Get the provider that owns this credential.
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id', 'id');
    }

    /**
     * Get the provider profile.
     */
    public function providerProfile(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_id', 'provider_id');
    }

    /**
     * Get the user who verified this credential.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by', 'id');
    }

    /**
     * Get the user who created this credential.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Get the user who last updated this credential.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id');
    }

    /**
     * Check if credential is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiration_date !== null && $this->expiration_date->isPast();
    }

    /**
     * Check if credential is expiring soon.
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        if ($this->expiration_date === null) {
            return false;
        }

        return $this->expiration_date->isBetween(now(), now()->addDays($days));
    }

    /**
     * Check if credential is verified.
     */
    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    /**
     * Check if credential needs attention.
     */
    public function needsAttention(): bool
    {
        return $this->isExpired() ||
               $this->isExpiringSoon() ||
               in_array($this->verification_status, ['rejected', 'suspended', 'revoked']);
    }

    /**
     * Get days until expiration.
     */
    public function daysUntilExpiration(): ?int
    {
        if (!$this->expiration_date) {
            return null;
        }

        return now()->diffInDays($this->expiration_date, false);
    }

    /**
     * Get credential status color for UI.
     */
    public function getStatusColor(): string
    {
        if ($this->isExpired()) {
            return 'red';
        }

        if ($this->isExpiringSoon()) {
            return 'yellow';
        }

        return match ($this->verification_status) {
            'verified' => 'green',
            'in_review' => 'blue',
            'rejected', 'suspended', 'revoked' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get credential status label.
     */
    public function getStatusLabel(): string
    {
        if ($this->isExpired()) {
            return 'Expired';
        }

        if ($this->isExpiringSoon()) {
            return 'Expiring Soon';
        }

        return match ($this->verification_status) {
            'pending' => 'Pending Verification',
            'in_review' => 'Under Review',
            'verified' => 'Verified',
            'expired' => 'Expired',
            'rejected' => 'Rejected',
            'suspended' => 'Suspended',
            'revoked' => 'Revoked',
            default => 'Unknown Status',
        };
    }

    /**
     * Get credential type display name.
     */
    public function getTypeDisplayName(): string
    {
        return match ($this->credential_type) {
            'medical_license' => 'Medical License',
            'board_certification' => 'Board Certification',
            'dea_registration' => 'DEA Registration',
            'npi_number' => 'NPI Number',
            'hospital_privileges' => 'Hospital Privileges',
            'malpractice_insurance' => 'Malpractice Insurance',
            'continuing_education' => 'Continuing Education',
            'state_license' => 'State License',
            'specialty_certification' => 'Specialty Certification',
            default => ucwords(str_replace('_', ' ', $this->credential_type)),
        };
    }

    /**
     * Update next reminder date based on expiration date.
     */
    public function updateNextReminderDate(): void
    {
        if ($this->expiration_date === null) {
            $this->next_reminder_date = null;
            return;
        }

        $reminderDays = $this->renewal_period_days ?? 30;
        $this->next_reminder_date = $this->expiration_date->copy()->subDays($reminderDays);
        $this->saveQuietly(); // Avoid triggering events
    }

    /**
     * Mark reminder as sent.
     */
    public function markReminderSent(): void
    {
        $sentDates = $this->reminder_sent_dates ?? [];
        $sentDates[] = now()->toDateString();
        $this->reminder_sent_dates = $sentDates;
        $this->save();
    }

    /**
     * Check if reminder was already sent today.
     */
    public function reminderSentToday(): bool
    {
        $sentDates = $this->reminder_sent_dates ?? [];
        return in_array(now()->toDateString(), $sentDates);
    }

    /**
     * Verify the credential.
     */
    public function verify(User $verifier, ?string $notes = null): void
    {
        $this->verification_status = 'verified';
        $this->verified_at = now();
        $this->verified_by = $verifier->id;
        $this->verification_notes = $notes;
        $this->save();
    }

    /**
     * Reject the credential.
     */
    public function reject(User $verifier, string $reason): void
    {
        $this->verification_status = 'rejected';
        $this->verified_by = $verifier->id;
        $this->verification_notes = $reason;
        $this->save();
    }

    /**
     * Suspend the credential.
     */
    public function suspend(User $user, string $reason): void
    {
        $this->verification_status = 'suspended';
        $this->verified_by = $user->id;
        $this->verification_notes = $reason;
        $this->save();
    }

    /**
     * Get credential types with descriptions.
     */
    public static function getCredentialTypes(): array
    {
        return [
            'medical_license' => [
                'name' => 'Medical License',
                'description' => 'State medical license to practice medicine',
                'required' => true,
            ],
            'board_certification' => [
                'name' => 'Board Certification',
                'description' => 'Specialty board certification',
                'required' => false,
            ],
            'dea_registration' => [
                'name' => 'DEA Registration',
                'description' => 'Drug Enforcement Administration registration',
                'required' => false,
            ],
            'npi_number' => [
                'name' => 'NPI Number',
                'description' => 'National Provider Identifier',
                'required' => true,
            ],
            'hospital_privileges' => [
                'name' => 'Hospital Privileges',
                'description' => 'Hospital admitting privileges',
                'required' => false,
            ],
            'malpractice_insurance' => [
                'name' => 'Malpractice Insurance',
                'description' => 'Professional liability insurance',
                'required' => false,
            ],
            'continuing_education' => [
                'name' => 'Continuing Education',
                'description' => 'Continuing medical education credits',
                'required' => false,
            ],
            'state_license' => [
                'name' => 'State License',
                'description' => 'Additional state professional license',
                'required' => false,
            ],
            'specialty_certification' => [
                'name' => 'Specialty Certification',
                'description' => 'Specialized medical certification',
                'required' => false,
            ],
        ];
    }

    /**
     * Scope for active credentials.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for verified credentials.
     */
    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    /**
     * Scope for expired credentials.
     */
    public function scopeExpired($query)
    {
        return $query->where('expiration_date', '<', now());
    }

    /**
     * Scope for expiring credentials.
     */
    public function scopeExpiring($query, int $days = 30)
    {
        return $query->whereBetween('expiration_date', [now(), now()->addDays($days)]);
    }

    /**
     * Scope for credentials needing reminders.
     */
    public function scopeNeedsReminder($query)
    {
        return $query->where('next_reminder_date', '<=', now())
                    ->where('auto_renewal_enabled', true);
    }

    /**
     * Scope for primary credentials of each type.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope by credential type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('credential_type', $type);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ProfileAuditLog extends Model
{
    protected $table = 'profile_audit_log';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false; // We only use created_at

    protected $fillable = [
        'entity_type',
        'entity_id',
        'entity_display_name',
        'user_id',
        'user_email',
        'user_role',
        'action_type',
        'action_description',
        'field_changes',
        'metadata',
        'reason',
        'notes',
        'ip_address',
        'user_agent',
        'request_id',
        'session_id',
        'is_sensitive_data',
        'compliance_category',
        'requires_approval',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'field_changes' => 'array',
        'metadata' => 'array',
        'is_sensitive_data' => 'boolean',
        'requires_approval' => 'boolean',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected $attributes = [
        'field_changes' => '{}',
        'metadata' => '{}',
        'is_sensitive_data' => false,
        'compliance_category' => 'administrative',
        'requires_approval' => false,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($auditLog) {
            if (empty($auditLog->id)) {
                $auditLog->id = (string) Str::uuid();
            }

            // Auto-populate user context if not provided
            if (Auth::check() && !$auditLog->user_id) {
                $user = Auth::user();
                $auditLog->user_id = $user->id;
                $auditLog->user_email = $user->email;
                $auditLog->user_role = $user->role ?? 'unknown';
            }

            // Auto-populate request context
            if (request()) {
                $auditLog->ip_address = $auditLog->ip_address ?? request()->ip();
                $auditLog->user_agent = $auditLog->user_agent ?? request()->userAgent();
                $auditLog->session_id = $auditLog->session_id ?? session()->getId();
            }
        });
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Get the user who approved the action (if applicable).
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by', 'id');
    }

    /**
     * Log a profile change.
     */
    public static function logProfileChange(
        string $entityType,
        string $entityId,
        string $actionType,
        array $fieldChanges = [],
        array $options = []
    ): self {
        return static::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_display_name' => $options['entity_display_name'] ?? null,
            'action_type' => $actionType,
            'action_description' => $options['action_description'] ?? null,
            'field_changes' => $fieldChanges,
            'metadata' => $options['metadata'] ?? [],
            'reason' => $options['reason'] ?? null,
            'notes' => $options['notes'] ?? null,
            'is_sensitive_data' => $options['is_sensitive_data'] ?? false,
            'compliance_category' => $options['compliance_category'] ?? 'administrative',
            'requires_approval' => $options['requires_approval'] ?? false,
        ]);
    }

    /**
     * Log credential verification.
     */
    public static function logCredentialVerification(
        ProviderCredential $credential,
        string $actionType,
        User $verifier,
        ?string $notes = null
    ): self {
        return static::logProfileChange(
            'provider_credential',
            $credential->id,
            $actionType,
            [
                'credential_type' => $credential->credential_type,
                'verification_status' => [
                    'old' => $credential->getOriginal('verification_status'),
                    'new' => $credential->verification_status,
                ],
            ],
            [
                'entity_display_name' => $credential->getTypeDisplayName(),
                'action_description' => "Credential {$actionType} by {$verifier->name}",
                'notes' => $notes,
                'compliance_category' => 'credential_verification',
                'is_sensitive_data' => false,
            ]
        );
    }

    /**
     * Log profile update.
     */
    public static function logProfileUpdate(
        ProviderProfile $profile,
        array $changes,
        ?string $reason = null
    ): self {
        $sensitiveFields = ['professional_photo_path', 'professional_bio'];
        $isSensitive = !empty(array_intersect(array_keys($changes), $sensitiveFields));

        return static::logProfileChange(
            'provider_profile',
            (string) $profile->provider_id,
            'update',
            $changes,
            [
                'entity_display_name' => $profile->provider->name ?? 'Provider Profile',
                'action_description' => 'Provider profile updated',
                'reason' => $reason,
                'compliance_category' => 'administrative',
                'is_sensitive_data' => $isSensitive,
            ]
        );
    }

    /**
     * Get action type display name.
     */
    public function getActionTypeDisplayName(): string
    {
        return match ($this->action_type) {
            'create' => 'Created',
            'update' => 'Updated',
            'delete' => 'Deleted',
            'verify' => 'Verified',
            'approve' => 'Approved',
            'reject' => 'Rejected',
            'suspend' => 'Suspended',
            'restore' => 'Restored',
            'export' => 'Exported',
            'view_sensitive' => 'Viewed Sensitive Data',
            default => ucfirst($this->action_type),
        };
    }

    /**
     * Get entity type display name.
     */
    public function getEntityTypeDisplayName(): string
    {
        return match ($this->entity_type) {
            'provider_profile' => 'Provider Profile',
            'provider_credential' => 'Provider Credential',
            'organization_profile' => 'Organization Profile',
            'facility_profile' => 'Facility Profile',
            'user_account' => 'User Account',
            default => ucwords(str_replace('_', ' ', $this->entity_type)),
        };
    }

    /**
     * Get compliance category display name.
     */
    public function getComplianceCategoryDisplayName(): string
    {
        return match ($this->compliance_category) {
            'administrative' => 'Administrative',
            'clinical' => 'Clinical',
            'financial' => 'Financial',
            'security' => 'Security',
            'phi_access' => 'PHI Access',
            'credential_verification' => 'Credential Verification',
            default => ucwords(str_replace('_', ' ', $this->compliance_category)),
        };
    }

    /**
     * Check if the action is approved.
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Check if the action requires approval.
     */
    public function needsApproval(): bool
    {
        return $this->requires_approval && !$this->isApproved();
    }

    /**
     * Approve the action.
     */
    public function approve(User $approver, ?string $notes = null): void
    {
        $this->approved_at = now();
        $this->approved_by = $approver->id;
        if ($notes) {
            $this->notes = $this->notes ? $this->notes . "\n\nApproval Notes: " . $notes : "Approval Notes: " . $notes;
        }
        $this->save();
    }

    /**
     * Get formatted field changes for display.
     */
    public function getFormattedFieldChanges(): array
    {
        $changes = $this->field_changes ?? [];
        $formatted = [];

        foreach ($changes as $field => $change) {
            if (is_array($change) && isset($change['old'], $change['new'])) {
                $formatted[] = [
                    'field' => ucwords(str_replace('_', ' ', $field)),
                    'old_value' => $this->formatValue($change['old']),
                    'new_value' => $this->formatValue($change['new']),
                ];
            }
        }

        return $formatted;
    }

    /**
     * Format a value for display.
     */
    private function formatValue($value): string
    {
        if ($value === null) {
            return '(empty)';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Scope for sensitive data access logs.
     */
    public function scopeSensitiveData($query)
    {
        return $query->where('is_sensitive_data', true);
    }

    /**
     * Scope for actions requiring approval.
     */
    public function scopeRequiresApproval($query)
    {
        return $query->where('requires_approval', true);
    }

    /**
     * Scope for pending approvals.
     */
    public function scopePendingApproval($query)
    {
        return $query->where('requires_approval', true)
                    ->whereNull('approved_at');
    }

    /**
     * Scope by entity type.
     */
    public function scopeForEntity($query, string $entityType, string $entityId)
    {
        return $query->where('entity_type', $entityType)
                    ->where('entity_id', $entityId);
    }

    /**
     * Scope by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope by compliance category.
     */
    public function scopeByComplianceCategory($query, string $category)
    {
        return $query->where('compliance_category', $category);
    }

    /**
     * Scope for recent activity.
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class RbacAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type',
        'entity_type',
        'entity_id',
        'entity_name',
        'performed_by',
        'performed_by_name',
        'target_user_id',
        'target_user_email',
        'old_values',
        'new_values',
        'changes',
        'reason',
        'ip_address',
        'user_agent',
        'session_id',
        'risk_level',
        'risk_factors',
        'metadata',
        'requires_review',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changes' => 'array',
        'risk_factors' => 'array',
        'metadata' => 'array',
        'requires_review' => 'boolean',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The user who performed the action
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * The target user (for user role assignments)
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * The user who reviewed this audit log
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Create an audit log entry
     */
    public static function logEvent(
        string $eventType,
        string $entityType,
        ?int $entityId = null,
        ?string $entityName = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $changes = null,
        ?string $reason = null,
        ?int $targetUserId = null,
        ?string $targetUserEmail = null,
        ?array $metadata = null
    ): self {
        $user = Auth::user();
        $request = request();

        // Calculate risk level
        $riskLevel = self::calculateRiskLevel($eventType, $entityType, $changes, $user);

        // Determine if requires review
        $requiresReview = in_array($riskLevel, ['high', 'critical']) ||
                         in_array($eventType, ['role_deleted', 'super_admin_assigned']);

        return self::create([
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_name' => $entityName,
            'performed_by' => $user?->id,
            'performed_by_name' => $user?->name,
            'target_user_id' => $targetUserId,
            'target_user_email' => $targetUserEmail,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changes' => $changes,
            'reason' => $reason,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'session_id' => $request?->session()?->getId(),
            'risk_level' => $riskLevel,
            'risk_factors' => self::calculateRiskFactors($eventType, $entityType, $changes, $user),
            'metadata' => $metadata,
            'requires_review' => $requiresReview,
        ]);
    }

    /**
     * Calculate risk level based on the action
     */
    private static function calculateRiskLevel(
        string $eventType,
        string $entityType,
        ?array $changes,
        ?User $user
    ): string {
        // Critical risk events
        if (in_array($eventType, ['super_admin_assigned', 'role_deleted'])) {
            return 'critical';
        }

        // High risk events
        if (in_array($eventType, ['admin_role_assigned', 'permission_escalation', 'bulk_role_change'])) {
            return 'high';
        }

        // Check for permission escalation
        if ($changes && isset($changes['permissions'])) {
            $addedPermissions = $changes['permissions']['added'] ?? [];
            $adminPermissions = array_filter($addedPermissions, function($perm) {
                return str_contains($perm, 'admin') || str_contains($perm, 'delete') || str_contains($perm, 'manage');
            });

            if (count($adminPermissions) > 0) {
                return 'high';
            }
        }

        // Medium risk for most role/permission changes
        if (in_array($eventType, ['role_updated', 'user_role_changed', 'permission_assigned'])) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Calculate risk factors
     */
    private static function calculateRiskFactors(
        string $eventType,
        string $entityType,
        ?array $changes,
        ?User $user
    ): array {
        $factors = [];

        // Check for privilege escalation
        if ($changes && isset($changes['permissions'])) {
            $addedPermissions = $changes['permissions']['added'] ?? [];
            if (count($addedPermissions) > 5) {
                $factors[] = 'bulk_permission_assignment';
            }
        }

        // Check for admin role assignment
        if ($eventType === 'user_role_changed' && $changes) {
            $newRole = $changes['new_role'] ?? '';
            if (str_contains(strtolower($newRole), 'admin')) {
                $factors[] = 'admin_role_assignment';
            }
        }

        // Check for off-hours activity
        $hour = now()->hour;
        if ($hour < 6 || $hour > 22) {
            $factors[] = 'off_hours_activity';
        }

        // Check for rapid successive changes
        $recentLogs = self::where('performed_by', $user?->id)
            ->where('created_at', '>', now()->subMinutes(5))
            ->count();

        if ($recentLogs > 3) {
            $factors[] = 'rapid_successive_changes';
        }

        return $factors;
    }

    /**
     * Scope for high-risk events
     */
    public function scopeHighRisk($query)
    {
        return $query->whereIn('risk_level', ['high', 'critical']);
    }

    /**
     * Scope for events requiring review
     */
    public function scopeRequiringReview($query)
    {
        return $query->where('requires_review', true)->whereNull('reviewed_at');
    }

    /**
     * Scope for recent events
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}

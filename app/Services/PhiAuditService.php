<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PhiAuditService
{
    /**
     * Log PHI access event
     */
    public static function logAccess(string $action, string $resourceType, string $resourceId, array $context = []): void
    {
        $user = Auth::user();
        $auditData = [
            'timestamp' => now()->toIso8601String(),
            'user_id' => $user ? $user->id : 'system',
            'user_name' => $user ? $user->full_name : 'System',
            'user_ip' => request()->ip(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'session_id' => session()->getId(),
            'request_id' => request()->header('X-Request-ID', uniqid()),
            'context' => $context
        ];

        // Log to dedicated PHI audit log
        Log::channel('phi_audit')->info('PHI Access', $auditData);
        
        // Also store in database for compliance reporting
        try {
            DB::table('phi_audit_logs')->insert([
                'user_id' => $auditData['user_id'],
                'action' => $auditData['action'],
                'resource_type' => $auditData['resource_type'],
                'resource_id' => $auditData['resource_id'],
                'ip_address' => $auditData['user_ip'],
                'user_agent' => request()->userAgent(),
                'metadata' => json_encode($auditData),
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store PHI audit log in database', [
                'error' => $e->getMessage(),
                'audit_data' => $auditData
            ]);
        }
    }

    /**
     * Log PHI creation event
     */
    public static function logCreation(string $resourceType, string $resourceId, array $context = []): void
    {
        self::logAccess('CREATE', $resourceType, $resourceId, $context);
    }

    /**
     * Log PHI read event
     */
    public static function logRead(string $resourceType, string $resourceId, array $context = []): void
    {
        self::logAccess('READ', $resourceType, $resourceId, $context);
    }

    /**
     * Log PHI update event
     */
    public static function logUpdate(string $resourceType, string $resourceId, array $context = []): void
    {
        self::logAccess('UPDATE', $resourceType, $resourceId, $context);
    }

    /**
     * Log PHI deletion event
     */
    public static function logDeletion(string $resourceType, string $resourceId, array $context = []): void
    {
        self::logAccess('DELETE', $resourceType, $resourceId, $context);
    }

    /**
     * Log PHI export event (like IVR generation)
     */
    public static function logExport(string $resourceType, string $resourceId, string $exportType, array $context = []): void
    {
        $context['export_type'] = $exportType;
        self::logAccess('EXPORT', $resourceType, $resourceId, $context);
    }

    /**
     * Log unauthorized PHI access attempt
     */
    public static function logUnauthorizedAccess(string $resourceType, string $resourceId, string $reason): void
    {
        self::logAccess('UNAUTHORIZED_ACCESS_ATTEMPT', $resourceType, $resourceId, [
            'reason' => $reason,
            'severity' => 'HIGH'
        ]);
    }
}
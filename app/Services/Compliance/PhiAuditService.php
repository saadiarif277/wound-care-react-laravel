<?php

namespace App\Services\Compliance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;
use App\Logging\PhiSafeLogger;

class PhiAuditService
{
    private PhiSafeLogger $logger;

    public function __construct(PhiSafeLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Log PHI access event
     */
    public function logAccess(string $action, string $resourceType, string $resourceId, array $context = []): void
    {
        try {
            $user = Auth::user();
            $userId = $user?->id ?? 0;
            $userEmail = $user?->email ?? 'system@localhost';
            DB::table('phi_audit_logs')->insert([
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'user_id' => $userId,
                'user_email' => $userEmail,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'session_id' => session()->getId(),
                'context' => json_encode($this->sanitizeContext($context)),
                'accessed_at' => now(),
                'created_at' => now()
            ]);

            // Also log to file for redundancy
            $this->logger->logPhiAccess($action, array_merge($context, [
                'resource_type' => $resourceType,
                'resource_id' => $resourceId
            ]));

        } catch (\Exception $e) {
            // Critical: PHI access must be logged
            $this->logger->critical('Failed to log PHI access', [
                'error' => $e->getMessage(),
                'action' => $action,
                'resource' => "{$resourceType}/{$resourceId}"
            ]);

            // Could trigger emergency notification here
            $this->notifyComplianceTeam($e, $action, $resourceType, $resourceId);
        }
    }

    /**
     * Log bulk PHI access
     */
    public function logBulkAccess(string $action, array $resources): void
    {
        $logs = [];
        $timestamp = now();
        $user = Auth::user();
        $userId = $user?->id ?? 0;
        $userEmail = $user?->email ?? 'system@localhost';
        foreach ($resources as $resource) {
            $logs[] = [
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'user_id' => $userId,
                'user_email' => $userEmail,
                'action' => $action,
                'resource_type' => $resource['type'],
                'resource_id' => $resource['id'],
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'session_id' => session()->getId(),
                'context' => json_encode($this->sanitizeContext($resource['context'] ?? [])),
                'accessed_at' => $timestamp,
                'created_at' => $timestamp
            ];
        }

        try {
            DB::table('phi_audit_logs')->insert($logs);
        } catch (\Exception $e) {
            $this->logger->critical('Failed to log bulk PHI access', [
                'error' => $e->getMessage(),
                'action' => $action,
                'resource_count' => count($resources)
            ]);
        }
    }

    /**
     * Generate compliance report
     */
    public function generateComplianceReport(string $startDate, string $endDate): array
    {
        $report = [
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'summary' => [],
            'by_user' => [],
            'by_resource_type' => [],
            'by_action' => [],
            'suspicious_activities' => []
        ];

        // Summary statistics
        $report['summary'] = DB::table('phi_audit_logs')
            ->whereBetween('accessed_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_accesses,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT resource_id) as unique_resources,
                COUNT(DISTINCT DATE(accessed_at)) as active_days
            ')
            ->first();

        // Access by user
        $report['by_user'] = DB::table('phi_audit_logs')
            ->whereBetween('accessed_at', [$startDate, $endDate])
            ->selectRaw('user_email, COUNT(*) as access_count')
            ->groupBy('user_email')
            ->orderBy('access_count', 'desc')
            ->limit(20)
            ->get();

        // Access by resource type
        $report['by_resource_type'] = DB::table('phi_audit_logs')
            ->whereBetween('accessed_at', [$startDate, $endDate])
            ->selectRaw('resource_type, COUNT(*) as access_count')
            ->groupBy('resource_type')
            ->orderBy('access_count', 'desc')
            ->get();

        // Access by action
        $report['by_action'] = DB::table('phi_audit_logs')
            ->whereBetween('accessed_at', [$startDate, $endDate])
            ->selectRaw('action, COUNT(*) as access_count')
            ->groupBy('action')
            ->orderBy('access_count', 'desc')
            ->get();

        // Detect suspicious activities
        $report['suspicious_activities'] = $this->detectSuspiciousActivities($startDate, $endDate);

        return $report;
    }

    /**
     * Detect suspicious PHI access patterns
     */
    private function detectSuspiciousActivities(string $startDate, string $endDate): array
    {
        $suspicious = [];

        // Check for after-hours access
        $afterHours = DB::table('phi_audit_logs')
            ->whereBetween('accessed_at', [$startDate, $endDate])
            ->whereRaw('HOUR(accessed_at) NOT BETWEEN 6 AND 22')
            ->selectRaw('user_email, COUNT(*) as count, MIN(accessed_at) as first_access')
            ->groupBy('user_email')
            ->having('count', '>', 5)
            ->get();

        if ($afterHours->isNotEmpty()) {
            $suspicious['after_hours_access'] = $afterHours->toArray();
        }

        // Check for bulk access patterns
        $bulkAccess = DB::table('phi_audit_logs')
            ->whereBetween('accessed_at', [$startDate, $endDate])
            ->selectRaw('
                user_email,
                DATE(accessed_at) as access_date,
                COUNT(*) as daily_count
            ')
            ->groupBy('user_email', 'access_date')
            ->having('daily_count', '>', 100)
            ->get();

        if ($bulkAccess->isNotEmpty()) {
            $suspicious['bulk_access'] = $bulkAccess->toArray();
        }

        // Check for access to terminated patients
        $terminatedAccess = DB::table('phi_audit_logs as a')
            ->join('episodes as e', function ($join) {
                $join->whereRaw("a.resource_id = e.patient_fhir_id")
                    ->where('a.resource_type', 'Patient');
            })
            ->where('e.status', 'terminated')
            ->whereBetween('a.accessed_at', [$startDate, $endDate])
            ->selectRaw('a.user_email, COUNT(*) as count')
            ->groupBy('a.user_email')
            ->get();

        if ($terminatedAccess->isNotEmpty()) {
            $suspicious['terminated_patient_access'] = $terminatedAccess->toArray();
        }

        return $suspicious;
    }

    /**
     * Sanitize context to remove PHI
     */
    private function sanitizeContext(array $context): array
    {
        $allowedKeys = [
            'reason',
            'purpose',
            'authorization_id',
            'request_id',
            'workflow_step',
            'access_type'
        ];

        return array_intersect_key($context, array_flip($allowedKeys));
    }

    /**
     * Notify compliance team of critical audit failure
     */
    private function notifyComplianceTeam(\Exception $e, string $action, string $resourceType, string $resourceId): void
    {
        // This would send immediate notification to compliance team
        // Could use SMS, Slack, PagerDuty, etc.

        $message = sprintf(
            'CRITICAL: PHI audit logging failed. Action: %s, Resource: %s/%s, Error: %s',
            $action,
            $resourceType,
            $resourceId,
            $e->getMessage()
        );

        // Log to emergency channel
        Log::channel('emergency')->critical($message);

        // Could also trigger other notifications here
    }

    /**
     * Verify audit log integrity
     */
    public function verifyIntegrity(string $date): bool
    {
        // Check for gaps in audit logs
        $hourlyCount = DB::table('phi_audit_logs')
            ->whereDate('accessed_at', $date)
            ->selectRaw('HOUR(accessed_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->pluck('count', 'hour')
            ->toArray();

        // During business hours (6 AM - 10 PM), we should have some activity
        for ($hour = 6; $hour <= 22; $hour++) {
            if (!isset($hourlyCount[$hour]) || $hourlyCount[$hour] == 0) {
                $this->logger->warning('No PHI audit logs found for hour', [
                    'date' => $date,
                    'hour' => $hour
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Get audit logs for a specific resource
     */
    public function getAuditLogs(string $resourceType, string $resourceId, int $limit = 50): \Illuminate\Support\Collection
    {
        try {
            $logs = DB::table('phi_audit_logs as pal')
                ->leftJoin('users as u', 'pal.user_id', '=', 'u.id')
                ->where('pal.resource_type', $resourceType)
                ->where('pal.resource_id', $resourceId)
                ->select([
                    'pal.id',
                    'pal.action',
                    'pal.user_email',
                    'pal.ip_address',
                    'pal.user_agent',
                    'pal.context',
                    'pal.accessed_at',
                    'pal.created_at',
                    'u.name as user_name'
                ])
                ->orderBy('pal.accessed_at', 'desc')
                ->limit($limit)
                ->get();

            // Parse context JSON for each log
            $logs = $logs->map(function ($log) {
                $log->details = json_decode($log->context, true) ?? [];
                $log->user = (object) [
                    'name' => $log->user_name ?? $log->user_email ?? 'Unknown User'
                ];
                return $log;
            });

            return $logs;

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve audit logs', [
                'error' => $e->getMessage(),
                'resource_type' => $resourceType,
                'resource_id' => $resourceId
            ]);

            // Return empty collection on error
            return collect([]);
        }
    }

    /**
     * Archive old audit logs
     */
    public function archiveOldLogs(int $daysToKeep = 2190): int
    {
        // HIPAA requires 6 years (2190 days) retention
        $cutoffDate = now()->subDays($daysToKeep);

        // Move to archive table
        $archived = DB::table('phi_audit_logs')
            ->where('accessed_at', '<', $cutoffDate)
            ->count();

        if ($archived > 0) {
            DB::statement('
                INSERT INTO phi_audit_logs_archive
                SELECT * FROM phi_audit_logs
                WHERE accessed_at < ?
            ', [$cutoffDate]);

            DB::table('phi_audit_logs')
                ->where('accessed_at', '<', $cutoffDate)
                ->delete();

            $this->logger->info('Archived old PHI audit logs', [
                'count' => $archived,
                'cutoff_date' => $cutoffDate->toDateString()
            ]);
        }

        return $archived;
    }
}

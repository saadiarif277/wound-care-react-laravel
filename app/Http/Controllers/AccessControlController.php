<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AccessRequest;
use App\Models\Role;
use App\Models\RbacAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AccessControlController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-users')->only(['index']);
        $this->middleware('permission:edit-users')->only(['updateUserRole', 'toggleUserStatus']);
        $this->middleware('permission:delete-users')->only(['revokeAccess']);
        $this->middleware('permission:manage-access-control');
    }

    /**
     * Display the Access Control management interface
     */
    public function index(): Response
    {
        // Use pagination for better performance with large datasets
        $users = User::with(['roles'])
            ->select(['id', 'name', 'email', 'is_active', 'last_login_at', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->paginate(50); // Paginate instead of loading all users

        $userRoles = Role::select(['id', 'name', 'slug'])->get();

        // Optimize access requests query
        $accessRequests = AccessRequest::with(['reviewedBy:id,name,email'])
            ->select(['id', 'full_name', 'email', 'requested_role', 'status', 'created_at', 'reviewed_by'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        // Get access statistics efficiently
        $accessStats = [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'pending_requests' => AccessRequest::where('status', 'pending')->count(),
            'recent_approvals' => AccessRequest::where('status', 'approved')
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];

        // Get role distribution efficiently
        $roleDistribution = Role::withCount(['users' => function ($query) {
            $query->select(DB::raw('count(*)'));
        }])
        ->get()
        ->map(function ($role) use ($accessStats) {
            $activeCount = User::whereHas('roles', function ($q) use ($role) {
                    $q->where('role_id', $role->id);
                })
                ->where('is_active', true)
                ->count();

            return [
                'role' => $role->name,
                'count' => $role->users_count,
                'active_count' => $activeCount,
                'percentage' => $accessStats['total_users'] > 0
                    ? round(($role->users_count / $accessStats['total_users']) * 100, 1)
                    : 0,
            ];
        });

        // Get recent activity
        $recentActivity = $this->getRecentActivity();

        // Get security alerts
        $securityAlerts = $this->getSecurityAlerts();

        return Inertia::render('AccessControl/Index', [
            'users' => [
                'data' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ]
            ],
            'userRoles' => $userRoles,
            'accessRequests' => $accessRequests,
            'accessStats' => $accessStats,
            'roleDistribution' => $roleDistribution,
            'recentActivity' => $recentActivity,
            'securityAlerts' => $securityAlerts,
        ]);
    }

    /**
     * Update user role (using robust RBAC system)
     */
    public function updateUserRole(Request $request, User $user)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,id',
            'reason' => 'required|string|max:500',
        ]);

        return DB::transaction(function () use ($request, $user) {
            $oldRoles = $user->roles->pluck('name')->toArray();
            $newRole = Role::find($request->role_id);

            // Security check: Only super admins can assign super admin role
            if ($newRole->slug === 'super-admin' && !Auth::user()->isSuperAdmin()) {
                return response()->json([
                    'message' => 'Only super administrators can assign super admin role'
                ], 403);
            }

            // Replace all roles with the new role (single role assignment)
            $user->roles()->sync([$request->role_id]);

            // Log the role change
            $this->logSecurityEvent('user_role_changed', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'old_roles' => $oldRoles,
                'new_role' => $newRole->name,
                'reason' => $request->reason,
                'changed_by' => Auth::user()->email,
            ]);

            return response()->json([
                'message' => 'User role updated successfully',
                'user' => $user->fresh(['roles']),
            ]);
        });
    }

    /**
     * Toggle user active status
     */
    public function toggleUserStatus(Request $request, User $user)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        return DB::transaction(function () use ($request, $user) {
            $oldStatus = $user->is_active;

            $user->update([
                'is_active' => !$user->is_active,
            ]);

            $this->logSecurityEvent('user_status_changed', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'old_status' => $oldStatus ? 'active' : 'inactive',
                'new_status' => $user->is_active ? 'active' : 'inactive',
                'reason' => $request->reason,
                'changed_by' => Auth::user()->email,
            ]);

            return response()->json([
                'message' => 'User status updated successfully',
                'user' => $user->fresh(),
            ]);
        });
    }

    /**
     * Revoke user access (soft delete)
     */
    public function revokeAccess(Request $request, User $user)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        // Prevent self-deletion
        if ($user->id === Auth::id()) {
            return response()->json([
                'message' => 'You cannot revoke your own access'
            ], 403);
        }

        // Prevent deletion of super admin by non-super admin
        if ($user->isSuperAdmin() && !Auth::user()->isSuperAdmin()) {
            return response()->json([
                'message' => 'Only super administrators can revoke super admin access'
            ], 403);
        }

        return DB::transaction(function () use ($request, $user) {
            $user->update([
                'is_active' => false,
                'access_revoked_at' => now(),
                'access_revoked_reason' => $request->reason,
                'access_revoked_by' => Auth::id(),
            ]);

            $this->logSecurityEvent('user_access_revoked', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'reason' => $request->reason,
                'revoked_by' => Auth::user()->email,
            ]);

            return response()->json([
                'message' => 'User access revoked successfully',
            ]);
        });
    }

    /**
     * Get recent security activity
     */
    private function getRecentActivity()
    {
        // In a real app, this would come from an audit log table
        return [
            [
                'id' => 1,
                'type' => 'user_login',
                'user' => 'john.doe@example.com',
                'description' => 'User logged in successfully',
                'timestamp' => now()->subMinutes(15),
                'ip_address' => '192.168.1.100',
                'risk_level' => 'low',
            ],
            [
                'id' => 2,
                'type' => 'role_change',
                'user' => 'admin@example.com',
                'description' => 'Changed user role from Office Manager to MSC Admin',
                'timestamp' => now()->subHours(2),
                'ip_address' => '192.168.1.101',
                'risk_level' => 'medium',
            ],
            [
                'id' => 3,
                'type' => 'failed_login',
                'user' => 'unknown@example.com',
                'description' => 'Failed login attempt',
                'timestamp' => now()->subHours(4),
                'ip_address' => '10.0.0.50',
                'risk_level' => 'high',
            ],
        ];
    }

    /**
     * Get security alerts
     */
    private function getSecurityAlerts()
    {
        return [
            [
                'id' => 1,
                'type' => 'suspicious_activity',
                'title' => 'Multiple failed login attempts',
                'description' => '5 failed login attempts from IP 10.0.0.50 in the last hour',
                'severity' => 'high',
                'timestamp' => now()->subMinutes(30),
                'status' => 'active',
            ],
            [
                'id' => 2,
                'type' => 'privilege_escalation',
                'title' => 'Role elevation detected',
                'description' => 'User elevated from Office Manager to MSC Admin',
                'severity' => 'medium',
                'timestamp' => now()->subHours(2),
                'status' => 'reviewed',
            ],
        ];
    }

    /**
     * Log security events (placeholder for audit logging)
     */
    private function logSecurityEvent(string $action, array $data)
    {
        // In a real application, this would write to an audit log table
        // For now, we'll just log to Laravel's log system
        Log::info("Security Event: {$action}", array_merge($data, [
            'timestamp' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]));
    }

    /**
     * Get access control metrics
     */
    public function getMetrics()
    {
        $metrics = [
            'user_growth' => $this->getUserGrowthMetrics(),
            'role_distribution' => $this->getRoleDistributionMetrics(),
            'security_events' => $this->getSecurityEventMetrics(),
            'access_patterns' => $this->getAccessPatternMetrics(),
        ];

        return response()->json(['metrics' => $metrics]);
    }

    private function getUserGrowthMetrics()
    {
        // Mock data - in real app, query actual user creation data
        return [
            ['date' => now()->subDays(6)->format('Y-m-d'), 'users' => 12],
            ['date' => now()->subDays(5)->format('Y-m-d'), 'users' => 15],
            ['date' => now()->subDays(4)->format('Y-m-d'), 'users' => 18],
            ['date' => now()->subDays(3)->format('Y-m-d'), 'users' => 22],
            ['date' => now()->subDays(2)->format('Y-m-d'), 'users' => 25],
            ['date' => now()->subDays(1)->format('Y-m-d'), 'users' => 28],
            ['date' => now()->format('Y-m-d'), 'users' => 30],
        ];
    }

    private function getRoleDistributionMetrics()
    {
        $users = User::with('roles')->get();
        $userRoles = Role::all();

        return $userRoles->map(function ($role) use ($users) {
            $count = $users->filter(function ($user) use ($role) {
                return $user->roles->contains('id', $role->id);
            })->count();

            return [
                'role' => $role->name,
                'count' => $count,
            ];
        });
    }

    private function getSecurityEventMetrics()
    {
        // Mock data - in real app, query actual security events
        return [
            ['event' => 'login_success', 'count' => 145],
            ['event' => 'login_failed', 'count' => 12],
            ['event' => 'role_changed', 'count' => 3],
            ['event' => 'access_revoked', 'count' => 1],
        ];
    }

    private function getAccessPatternMetrics()
    {
        // Mock data - in real app, analyze actual access patterns
        return [
            ['hour' => '00:00', 'logins' => 2],
            ['hour' => '06:00', 'logins' => 15],
            ['hour' => '09:00', 'logins' => 45],
            ['hour' => '12:00', 'logins' => 32],
            ['hour' => '15:00', 'logins' => 28],
            ['hour' => '18:00', 'logins' => 12],
        ];
    }

    /**
     * Get users with their access information (API endpoint)
     */
    public function getUsersApi(Request $request): JsonResponse
    {
        $query = User::with(['roles']);

        // Apply filters
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('role') && $request->role) {
            $query->whereHas('roles', function($q) use ($request) {
                $q->where('slug', $request->role);
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $users = $query->paginate(20);

        // Transform users for frontend
        $transformedUsers = $users->getCollection()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status ?? 'active',
                'roles' => $user->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                    ];
                }),
                'last_login' => $user->last_login_at,
                'created_at' => $user->created_at,
            ];
        });

        return response()->json([
            'users' => $transformedUsers,
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        ]);
    }

    /**
     * Assign role to user (API endpoint)
     */
    public function assignRoleApi(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
            'reason' => 'required|string|max:500',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $user = User::findOrFail($validated['user_id']);
                $role = Role::findOrFail($validated['role_id']);

                // Get current roles for audit
                $oldRoles = $user->roles->pluck('name')->toArray();

                // Assign the role
                $user->roles()->attach($role->id);

                // Log the audit event
                RbacAuditLog::logEvent(
                    eventType: 'user_role_assigned',
                    entityType: 'user_role_assignment',
                    entityId: $user->id,
                    entityName: $user->name,
                    targetUserId: $user->id,
                    targetUserEmail: $user->email,
                    oldValues: ['roles' => $oldRoles],
                    newValues: ['roles' => array_merge($oldRoles, [$role->name])],
                    changes: ['new_role' => $role->name],
                    reason: $validated['reason'],
                    metadata: [
                        'role_assigned' => $role->name,
                        'total_roles_after' => count($oldRoles) + 1,
                    ]
                );

                return response()->json([
                    'message' => 'Role assigned successfully',
                    'user' => $user->fresh(['roles']),
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to assign role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove role from user (API endpoint)
     */
    public function removeRoleApi(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
            'reason' => 'required|string|max:500',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $user = User::findOrFail($validated['user_id']);
                $role = Role::findOrFail($validated['role_id']);

                // Check if user has this role
                if (!$user->roles->contains($role->id)) {
                    return response()->json([
                        'message' => 'User does not have this role',
                    ], 422);
                }

                // Get current roles for audit
                $oldRoles = $user->roles->pluck('name')->toArray();

                // Remove the role
                $user->roles()->detach($role->id);

                // Log the audit event
                RbacAuditLog::logEvent(
                    eventType: 'user_role_removed',
                    entityType: 'user_role_assignment',
                    entityId: $user->id,
                    entityName: $user->name,
                    targetUserId: $user->id,
                    targetUserEmail: $user->email,
                    oldValues: ['roles' => $oldRoles],
                    newValues: ['roles' => array_diff($oldRoles, [$role->name])],
                    changes: ['removed_role' => $role->name],
                    reason: $validated['reason'],
                    metadata: [
                        'role_removed' => $role->name,
                        'total_roles_after' => count($oldRoles) - 1,
                    ]
                );

                return response()->json([
                    'message' => 'Role removed successfully',
                    'user' => $user->fresh(['roles']),
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get access control statistics
     */
    public function getStats(): JsonResponse
    {
        $totalUsers = User::count();
        $activeUsers = User::where('status', 'active')->count();
        $inactiveUsers = User::where('status', 'inactive')->count();
        $suspendedUsers = User::where('status', 'suspended')->count();

        // Role distribution
        $roleDistribution = Role::withCount('users')->get()->map(function ($role) {
            return [
                'role' => $role->name,
                'count' => $role->users_count,
            ];
        });

        // Recent access changes
        $recentChanges = RbacAuditLog::where('entity_type', 'user_role_assignment')
            ->orWhere('event_type', 'user_status_changed')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(function ($log) {
                return [
                    'action' => $log->event_type,
                    'user' => $log->target_user_email,
                    'performed_by' => $log->performed_by_name,
                    'timestamp' => $log->created_at,
                    'risk_level' => $log->risk_level,
                ];
            });

        // Security alerts (high-risk events)
        $securityAlerts = RbacAuditLog::highRisk()
            ->recent(7)
            ->count();

        return response()->json([
            'stats' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'inactive_users' => $inactiveUsers,
                'suspended_users' => $suspendedUsers,
                'role_distribution' => $roleDistribution,
                'recent_changes' => $recentChanges,
                'security_alerts' => $securityAlerts,
            ]
        ]);
    }

    /**
     * Get security monitoring data
     */
    public function getSecurityMonitoring(): JsonResponse
    {
        // High-risk events in the last 30 days
        $highRiskEvents = RbacAuditLog::highRisk()
            ->recent(30)
            ->with(['performedBy', 'targetUser'])
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'event_type' => $log->event_type,
                    'performed_by' => $log->performed_by_name,
                    'target_user' => $log->target_user_email,
                    'risk_level' => $log->risk_level,
                    'risk_factors' => $log->risk_factors,
                    'timestamp' => $log->created_at,
                    'requires_review' => $log->requires_review,
                    'reviewed' => $log->reviewed_at !== null,
                ];
            });

        // Events requiring review
        $pendingReviews = RbacAuditLog::requiringReview()->count();

        // Failed login attempts (if you have this data)
        $failedLogins = 0; // This would come from your authentication logs

        return response()->json([
            'security_monitoring' => [
                'high_risk_events' => $highRiskEvents,
                'pending_reviews' => $pendingReviews,
                'failed_logins_24h' => $failedLogins,
            ]
        ]);
    }

    /**
     * Mark audit log as reviewed
     */
    public function markAsReviewed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audit_log_id' => 'required|exists:rbac_audit_logs,id',
            'review_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $auditLog = RbacAuditLog::findOrFail($validated['audit_log_id']);

            $auditLog->update([
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
                'metadata' => array_merge($auditLog->metadata ?? [], [
                    'review_notes' => $validated['review_notes'] ?? null,
                ])
            ]);

            return response()->json([
                'message' => 'Audit log marked as reviewed',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to mark as reviewed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

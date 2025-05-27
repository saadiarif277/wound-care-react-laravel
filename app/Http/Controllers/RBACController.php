<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\RbacAuditLog;
use App\Http\Requests\RolePermissionUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class RBACController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:manage-rbac')->only([
            'index',
            'getSecurityAudit',
            'toggleRoleStatus',
            'updateRolePermissions',
            'getRolePermissions',
            'getSystemStats'
        ]);
    }

    /**
     * Display the RBAC management interface
     */
    public function index(): Response
    {
        $roles = Role::with(['permissions', 'users'])->get();
        $permissions = Permission::all();
        $users = User::with('roles')->get();

        // Get role statistics
        $roleStats = $roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'user_count' => $role->users->count(),
                'permissions_count' => $role->permissions->count(),
                'permissions' => $role->permissions->pluck('name'),
                'is_active' => $role->is_active ?? true,
            ];
        });

        // Get permission usage statistics
        $permissionStats = $permissions->map(function ($permission) use ($roles) {
            $rolesWithPermission = $roles->filter(function ($role) use ($permission) {
                return $role->permissions->contains('id', $permission->id);
            });

            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'slug' => $permission->slug,
                'description' => $permission->description,
                'roles_count' => $rolesWithPermission->count(),
                'roles' => $rolesWithPermission->pluck('name'),
            ];
        });

        // Get user role distribution
        $userDistribution = $roles->map(function ($role) use ($users) {
            return [
                'role' => $role->name,
                'count' => $role->users->count(),
                'percentage' => $users->count() > 0 ? round(($role->users->count() / $users->count()) * 100, 1) : 0,
            ];
        });

        return Inertia::render('RBAC/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'users' => $users,
            'roleStats' => $roleStats,
            'permissionStats' => $permissionStats,
            'userDistribution' => $userDistribution,
            'totalUsers' => $users->count(),
            'totalRoles' => $roles->count(),
            'totalPermissions' => $permissions->count(),
        ]);
    }

    /**
     * Get role hierarchy and relationships
     */
    public function getRoleHierarchy()
    {
        $roles = Role::with(['permissions', 'users'])->orderBy('name')->get();

        $hierarchy = $roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'user_count' => $role->users->count(),
                'permissions_count' => $role->permissions->count(),
                'permissions' => $role->permissions->pluck('name'),
                'is_active' => $role->is_active ?? true,
            ];
        });

        return response()->json(['hierarchy' => $hierarchy]);
    }

    /**
     * Get detailed role configuration
     */
    public function getRoleConfig(Role $role)
    {
        return response()->json([
            'role' => $role->load('permissions'),
            'permissions' => $role->permissions->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'slug' => $permission->slug,
                    'description' => $permission->description,
                ];
            }),
            'capabilities' => [
                'can_view_users' => $role->permissions->contains('slug', 'view-users'),
                'can_edit_users' => $role->permissions->contains('slug', 'edit-users'),
                'can_delete_users' => $role->permissions->contains('slug', 'delete-users'),
                'can_view_financials' => $role->permissions->contains('slug', 'view-financials'),
                'can_manage_financials' => $role->permissions->contains('slug', 'manage-financials'),
                'can_view_msc_pricing' => $role->permissions->contains('slug', 'view-msc-pricing'),
                'can_view_discounts' => $role->permissions->contains('slug', 'view-discounts'),
                'can_manage_products' => $role->permissions->contains('slug', 'manage-products'),
                'can_manage_orders' => $role->permissions->contains('slug', 'manage-orders'),
                'can_view_commission' => $role->permissions->contains('slug', 'view-commission'),
                'can_manage_commission' => $role->permissions->contains('slug', 'manage-commission'),
            ],
            'user_count' => $role->users()->count(),
        ]);
    }

    /**
     * Get security audit data
     */
    public function getSecurityAudit(Request $request)
    {
        $query = RbacAuditLog::with('performedBy');

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date,
                $request->end_date
            ]);
        }

        // Filter by event type
        if ($request->has('event_type') && !empty($request->event_type)) {
            $query->where('event_type', $request->event_type);
        }

        // Filter by risk level
        if ($request->has('risk_level') && !empty($request->risk_level)) {
            $query->where('risk_level', $request->risk_level);
        }

        // Filter by user
        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('performed_by', $request->user_id);
        }

        $auditLogs = $query->orderBy('created_at', 'desc')
            ->paginate(20);

        // Format the audit logs for display
        $auditLogs->getCollection()->transform(function ($log) {
            return [
                'id' => $log->id,
                'event_type' => $log->event_type,
                'entity_type' => $log->entity_type,
                'entity_name' => $log->entity_name,
                'performed_by' => $log->performedBy ? [
                    'id' => $log->performedBy->id,
                    'name' => $log->performedBy->name,
                    'email' => $log->performedBy->email,
                ] : null,
                'target_user_email' => $log->target_user_email,
                'changes' => $this->formatChangesForDisplay($log->changes),
                'reason' => $log->reason,
                'risk_level' => $log->risk_level,
                'risk_factors' => $log->risk_factors,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at,
                'requires_review' => $log->requires_review,
                'reviewed_at' => $log->reviewed_at,
            ];
        });

        return response()->json([
            'audit_logs' => $auditLogs,
            'summary' => [
                'total_events' => $auditLogs->total(),
                'high_risk_events' => RbacAuditLog::where('risk_level', 'high')->count(),
                'pending_reviews' => RbacAuditLog::where('requires_review', true)
                    ->whereNull('reviewed_at')->count(),
            ]
        ]);
    }

    /**
     * Toggle role active status
     */
    public function toggleRoleStatus(Role $role, Request $request)
    {
        try {
            // Prevent disabling system roles
            if (in_array($role->name, ['super-admin', 'admin'])) {
                return response()->json([
                    'error' => 'System roles cannot be disabled'
                ], 403);
            }

            $user = Auth::user();
            $oldStatus = $role->is_active;
            $newStatus = !$oldStatus;

            $role->update(['is_active' => $newStatus]);

            // Log the role status change
            RbacAuditLog::logEvent(
                eventType: $newStatus ? 'role_enabled' : 'role_disabled',
                entityType: 'role',
                entityId: $role->id,
                entityName: $role->name,
                oldValues: ['is_active' => $oldStatus],
                newValues: ['is_active' => $newStatus],
                changes: ['status_change' => $newStatus ? 'enabled' : 'disabled'],
                reason: $request->input('reason', 'Role status toggled'),
                metadata: [
                    'role_id' => $role->id,
                    'role_name' => $role->name,
                    'affected_users_count' => $role->users()->count(),
                ]
            );

            Log::info('Role status toggled', [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'new_status' => $newStatus,
                'changed_by' => $user->id,
                'affected_users' => $role->users()->count(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Role {$role->name} has been " . ($newStatus ? 'enabled' : 'disabled'),
                'role' => $role->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to toggle role status', [
                'role_id' => $role->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => 'Failed to update role status'
            ], 500);
        }
    }

    /**
     * Update role permissions
     */
    public function updateRolePermissions(Role $role, RolePermissionUpdateRequest $request)
    {
        try {
            $user = Auth::user();
            $oldPermissions = $role->permissions()->pluck('id')->toArray();
            $newPermissions = $request->validated()['permission_ids'];

            // Calculate changes
            $added = array_diff($newPermissions, $oldPermissions);
            $removed = array_diff($oldPermissions, $newPermissions);

            // Update permissions
            $role->permissions()->sync($newPermissions);

            // Log the permission changes
            if (!empty($added) || !empty($removed)) {
                $addedNames = Permission::whereIn('id', $added)->pluck('name')->toArray();
                $removedNames = Permission::whereIn('id', $removed)->pluck('name')->toArray();

                RbacAuditLog::logEvent(
                    eventType: 'role_permissions_updated',
                    entityType: 'role',
                    entityId: $role->id,
                    entityName: $role->name,
                    oldValues: ['permission_ids' => $oldPermissions],
                    newValues: ['permission_ids' => $newPermissions],
                    changes: [
                        'permissions_added' => $addedNames,
                        'permissions_removed' => $removedNames,
                    ],
                    reason: $request->validated()['reason'] ?? 'Role permissions updated',
                    metadata: [
                        'role_id' => $role->id,
                        'role_name' => $role->name,
                        'affected_users_count' => $role->users()->count(),
                        'total_permissions' => count($newPermissions),
                    ]
                );

                Log::info('Role permissions updated', [
                    'role_id' => $role->id,
                    'role_name' => $role->name,
                    'permissions_added' => $addedNames,
                    'permissions_removed' => $removedNames,
                    'updated_by' => $user->id,
                    'affected_users' => $role->users()->count(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Role permissions updated successfully',
                'role' => $role->load('permissions')
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update role permissions', [
                'role_id' => $role->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'error' => 'Failed to update role permissions'
            ], 500);
        }
    }

    /**
     * Get role permissions
     */
    public function getRolePermissions(Role $role)
    {
        return response()->json([
            'role' => $role->load('permissions'),
            'all_permissions' => Permission::all(),
        ]);
    }

    /**
     * Get system statistics
     */
    public function getSystemStats()
    {
        return [
            'total_users' => User::count(),
            'total_roles' => Role::count(),
            'total_permissions' => Permission::count(),
            'active_roles' => Role::where('is_active', true)->count(),
            'recent_audit_events' => RbacAuditLog::where('created_at', '>=', now()->subDays(7))->count(),
            'high_risk_events' => RbacAuditLog::where('risk_level', 'high')->count(),
            'pending_reviews' => RbacAuditLog::where('requires_review', true)
                ->whereNull('reviewed_at')->count(),
        ];
    }

    /**
     * Format changes for display in the audit log
     */
    private function formatChangesForDisplay($changes)
    {
        if (!is_array($changes)) {
            return $changes;
        }

        $formatted = [];
        foreach ($changes as $key => $value) {
            if (is_array($value)) {
                $formatted[$key] = implode(', ', $value);
            } else {
                $formatted[$key] = $value;
            }
        }

        return $formatted;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use App\Models\RbacAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-roles')->only(['index', 'show']);
        $this->middleware('permission:create-roles')->only(['store']);
        $this->middleware('permission:edit-roles')->only(['update']);
        $this->middleware('permission:delete-roles')->only(['destroy']);
    }

    /**
     * Display a listing of roles
     */
    public function index(): Response
    {
        $roles = Role::with(['permissions', 'users'])->get();
        $permissions = Permission::orderBy('name')->get();

        // Transform roles for frontend
        $transformedRoles = $roles->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'permissions' => $role->permissions,
                'users_count' => $role->users->count(),
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ];
        });

        return Inertia::render('Roles/Index', [
            'roles' => $transformedRoles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a newly created role
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'slug' => 'required|string|max:255|unique:roles,slug|regex:/^[a-z0-9-_]+$/',
            'description' => 'required|string|max:1000',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'exists:permissions,id',
        ], [
            'name.required' => 'Role name is required',
            'name.unique' => 'A role with this name already exists',
            'slug.required' => 'Role slug is required',
            'slug.unique' => 'A role with this slug already exists',
            'slug.regex' => 'Slug can only contain lowercase letters, numbers, hyphens, and underscores',
            'description.required' => 'Role description is required',
            'permissions.required' => 'At least one permission must be selected',
            'permissions.min' => 'At least one permission must be selected',
            'permissions.*.exists' => 'One or more selected permissions are invalid',
        ]);

        try {
            $role = Role::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'description' => $validated['description'],
            ]);

            // Attach permissions
            $role->permissions()->attach($validated['permissions']);

            // Log the audit event
            RbacAuditLog::logEvent(
                eventType: 'role_created',
                entityType: 'role',
                entityId: $role->id,
                entityName: $role->name,
                newValues: [
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'description' => $role->description,
                    'permissions' => Permission::whereIn('id', $validated['permissions'])->pluck('name')->toArray(),
                ],
                metadata: [
                    'permission_count' => count($validated['permissions']),
                    'role_type' => 'custom',
                ]
            );

            return response()->json([
                'message' => 'Role created successfully',
                'role' => $role->load('permissions'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified role
     */
    public function show(Role $role): JsonResponse
    {
        $role->load(['permissions', 'users']);

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'permissions' => $role->permissions,
                'users' => $role->users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ];
                }),
                'users_count' => $role->users->count(),
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ]
        ]);
    }

    /**
     * Update the specified role
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        // Prevent modification of system roles
        if (in_array($role->slug, ['super-admin', 'admin'])) {
            return response()->json([
                'message' => 'System roles cannot be modified'
            ], 403);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($role->id)
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9-_]+$/',
                Rule::unique('roles', 'slug')->ignore($role->id)
            ],
            'description' => 'required|string|max:1000',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'exists:permissions,id',
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'Please provide a reason for this change',
        ]);

        try {
            // Store old values for audit
            $oldValues = [
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ];

            // Update role
            $role->update([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'description' => $validated['description'],
            ]);

            // Update permissions
            $oldPermissionIds = $role->permissions->pluck('id')->toArray();
            $newPermissionIds = $validated['permissions'];

            $role->permissions()->sync($newPermissionIds);

            // Calculate permission changes
            $addedPermissions = array_diff($newPermissionIds, $oldPermissionIds);
            $removedPermissions = array_diff($oldPermissionIds, $newPermissionIds);

            $changes = [
                'name' => $oldValues['name'] !== $validated['name'] ? [
                    'old' => $oldValues['name'],
                    'new' => $validated['name']
                ] : null,
                'slug' => $oldValues['slug'] !== $validated['slug'] ? [
                    'old' => $oldValues['slug'],
                    'new' => $validated['slug']
                ] : null,
                'description' => $oldValues['description'] !== $validated['description'] ? [
                    'old' => $oldValues['description'],
                    'new' => $validated['description']
                ] : null,
                'permissions' => [
                    'added' => Permission::whereIn('id', $addedPermissions)->pluck('name')->toArray(),
                    'removed' => Permission::whereIn('id', $removedPermissions)->pluck('name')->toArray(),
                ]
            ];

            // Remove null changes
            $changes = array_filter($changes, function($value) {
                return $value !== null;
            });

            // Log the audit event
            RbacAuditLog::logEvent(
                eventType: 'role_updated',
                entityType: 'role',
                entityId: $role->id,
                entityName: $role->name,
                oldValues: $oldValues,
                newValues: [
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'description' => $role->description,
                    'permissions' => Permission::whereIn('id', $newPermissionIds)->pluck('name')->toArray(),
                ],
                changes: $changes,
                reason: $validated['reason'],
                metadata: [
                    'permissions_added' => count($addedPermissions),
                    'permissions_removed' => count($removedPermissions),
                ]
            );

            return response()->json([
                'message' => 'Role updated successfully',
                'role' => $role->fresh(['permissions']),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified role
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        // Prevent deletion of system roles
        if (in_array($role->slug, ['super-admin', 'admin'])) {
            return response()->json([
                'message' => 'System roles cannot be deleted'
            ], 403);
        }

        // Check if role has users
        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete role that is assigned to users. Please reassign users first.',
                'users_count' => $role->users()->count(),
            ], 422);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'Please provide a reason for deleting this role',
        ]);

        try {
            // Store role data for audit before deletion
            $roleData = [
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ];

            $roleId = $role->id;
            $roleName = $role->name;

            // Delete the role
            $role->delete();

            // Log the audit event
            RbacAuditLog::logEvent(
                eventType: 'role_deleted',
                entityType: 'role',
                entityId: $roleId,
                entityName: $roleName,
                oldValues: $roleData,
                reason: $request->reason,
                metadata: [
                    'permission_count' => count($roleData['permissions']),
                    'deletion_confirmed' => true,
                ]
            );

            return response()->json([
                'message' => 'Role deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get role validation rules for frontend
     */
    public function getValidationRules(): JsonResponse
    {
        return response()->json([
            'rules' => [
                'name' => [
                    'required' => true,
                    'max_length' => 255,
                    'unique' => true,
                ],
                'slug' => [
                    'required' => true,
                    'max_length' => 255,
                    'unique' => true,
                    'pattern' => '^[a-z0-9-_]+$',
                    'description' => 'Only lowercase letters, numbers, hyphens, and underscores allowed',
                ],
                'description' => [
                    'required' => true,
                    'max_length' => 1000,
                ],
                'permissions' => [
                    'required' => true,
                    'min_items' => 1,
                ],
                'reason' => [
                    'required_for_updates' => true,
                    'max_length' => 500,
                ],
            ],
            'messages' => [
                'name.required' => 'Role name is required',
                'name.unique' => 'A role with this name already exists',
                'slug.required' => 'Role slug is required',
                'slug.unique' => 'A role with this slug already exists',
                'slug.pattern' => 'Slug can only contain lowercase letters, numbers, hyphens, and underscores',
                'description.required' => 'Role description is required',
                'permissions.required' => 'At least one permission must be selected',
                'reason.required' => 'Please provide a reason for this change',
            ]
        ]);
    }
}

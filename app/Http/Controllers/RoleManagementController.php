<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RoleManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:msc-admin');
    }

    public function index()
    {
        $roles = Role::with('permissions')->get();
        $permissions = Permission::orderBy('name')->get();

        // Get role statistics
        $roleStats = Role::withCount(['users', 'permissions'])
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'description' => $role->description,
                    'user_count' => $role->users_count,
                    'permissions_count' => $role->permissions_count,
                    'permissions' => $role->permissions->pluck('name'),
                    'is_active' => $role->is_active,
                ];
            });

        // Get permission statistics
        $permissionStats = Permission::withCount('roles')
            ->get()
            ->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'slug' => $permission->slug,
                    'description' => $permission->description,
                    'roles_count' => $permission->roles_count,
                    'roles' => $permission->roles->pluck('name'),
                ];
            });

        return Inertia::render('RBAC/RoleManagement', [
            'roles' => $roles,
            'permissions' => $permissions,
            'roleStats' => $roleStats,
            'permissionStats' => $permissionStats,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles'],
            'slug' => ['required', 'string', 'max:255', 'unique:roles'],
            'description' => ['required', 'string'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        try {
            DB::beginTransaction();

            $role = Role::create([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'description' => $validated['description'],
            ]);

            $role->permissions()->attach($validated['permissions']);

            // Log the role creation
            Log::info('Role created', [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Role created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create role', [
                'error' => $e->getMessage(),
                'role_data' => $validated,
            ]);

            return redirect()->back()->with('error', 'Failed to create role. Please try again.');
        }
    }

    public function update(Request $request, Role $role)
    {
        // Prevent modification of super-admin role
        if ($role->slug === 'super-admin') {
            return redirect()->back()->with('error', 'Cannot modify the super-admin role.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'slug' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'description' => ['required', 'string'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['exists:permissions,id'],
            'reason' => ['required', 'string', 'min:10'],
        ]);

        try {
            DB::beginTransaction();

            // Store old permissions for audit
            $oldPermissions = $role->permissions->pluck('id')->toArray();
            $newPermissions = $validated['permissions'];

            // Update role details
            $role->update([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'description' => $validated['description'],
            ]);

            // Sync permissions
            $role->permissions()->sync($validated['permissions']);

            // Log the role update
            Log::info('Role updated', [
                'role_id' => $role->id,
                'role_name' => $role->name,
                'updated_by' => auth()->id(),
                'reason' => $validated['reason'],
                'old_permissions' => $oldPermissions,
                'new_permissions' => $newPermissions,
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Role updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update role', [
                'error' => $e->getMessage(),
                'role_id' => $role->id,
                'role_data' => $validated,
            ]);

            return redirect()->back()->with('error', 'Failed to update role. Please try again.');
        }
    }

    public function destroy(Request $request, Role $role)
    {
        // Prevent deletion of super-admin role
        if ($role->slug === 'super-admin') {
            return redirect()->back()->with('error', 'Cannot delete the super-admin role.');
        }

        // Check if role has users
        if ($role->users()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete role that has users assigned to it.');
        }

        try {
            DB::beginTransaction();

            // Store role data for audit log
            $roleData = [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'permissions' => $role->permissions->pluck('id')->toArray(),
            ];

            // Delete role
            $role->delete();

            // Log the role deletion
            Log::info('Role deleted', [
                'role_data' => $roleData,
                'deleted_by' => auth()->id(),
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Role deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete role', [
                'error' => $e->getMessage(),
                'role_id' => $role->id,
            ]);

            return redirect()->back()->with('error', 'Failed to delete role. Please try again.');
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-users')->only(['index', 'show']);
        $this->middleware('permission:create-users')->only(['create', 'store']);
        $this->middleware('permission:edit-users')->only(['edit', 'update']);
        $this->middleware('permission:delete-users')->only('destroy');
    }

    public function assignRoles(Request $request, User $user)
    {
        $this->checkPermission('assign-roles');

        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id',
        ]);

        $user->roles()->attach($validated['roles']);

        return response()->json([
            'message' => 'Roles assigned successfully',
            'user' => $user->load('roles'),
        ]);
    }

    public function removeRole(Request $request, User $user, Role $role)
    {
        $this->checkPermission('assign-roles');

        if ($role->slug === 'super-admin' && !auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Only super admins can remove super admin role'], 403);
        }

        $user->roles()->detach($role);

        return response()->json([
            'message' => 'Role removed successfully',
            'user' => $user->load('roles'),
        ]);
    }

    public function syncRoles(Request $request, User $user)
    {
        $this->checkPermission('assign-roles');

        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id',
        ]);

        // Prevent removing super-admin role unless the current user is a super-admin
        if ($user->hasRole('super-admin') && !auth()->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Only super admins can modify super admin roles'], 403);
        }

        $user->roles()->sync($validated['roles']);

        return response()->json([
            'message' => 'Roles synchronized successfully',
            'user' => $user->load('roles'),
        ]);
    }
}

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
        $this->middleware('permission:assign-roles')->only(['assignRoles', 'removeRole', 'syncRoles']);
        $this->middleware('permission:manage-super-admin-roles')->only(['removeRole', 'syncRoles']);
    }

    public function assignRoles(Request $request, User $user)
    {
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
        // Super admin role removal is protected by manage-super-admin-roles middleware
        // for this specific method

        $user->roles()->detach($role);

        return response()->json([
            'message' => 'Role removed successfully',
            'user' => $user->load('roles'),
        ]);
    }

    public function syncRoles(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id',
        ]);

        // Super admin role sync is protected by manage-super-admin-roles middleware
        // for this specific method

        $user->roles()->sync($validated['roles']);

        return response()->json([
            'message' => 'Roles synchronized successfully',
            'user' => $user->load('roles'),
        ]);
    }
}

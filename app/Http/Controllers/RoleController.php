<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-roles')->only(['index', 'show']);
        $this->middleware('permission:create-roles')->only(['create', 'store']);
        $this->middleware('permission:edit-roles')->only(['edit', 'update']);
        $this->middleware('permission:delete-roles')->only('destroy');
    }

    public function index()
    {
        $roles = Role::with('permissions')->get();
        return response()->json(['roles' => $roles]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles',
            'slug' => 'required|string|max:255|unique:roles',
            'description' => 'nullable|string',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'],
        ]);

        $role->permissions()->attach($validated['permissions']);

        return response()->json(['role' => $role->load('permissions')], 201);
    }

    public function show(Role $role)
    {
        return response()->json(['role' => $role->load('permissions')]);
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'slug' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'description' => 'nullable|string',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'],
        ]);

        $role->permissions()->sync($validated['permissions']);

        return response()->json(['role' => $role->load('permissions')]);
    }

    public function destroy(Role $role)
    {
        if ($role->slug === 'super-admin') {
            return response()->json(['message' => 'Cannot delete super-admin role'], 403);
        }

        $role->delete();
        return response()->json(null, 204);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DiagnosticController extends Controller
{
    public function checkPermissions()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $diagnostics = [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
                'direct_permissions' => $user->permissions->pluck('name'),
                'all_permissions' => $user->getAllPermissions()->pluck('name'),
            ],
            'system' => [
                'total_roles' => Role::count(),
                'total_permissions' => Permission::count(),
                'admin_role_exists' => Role::where('name', 'msc-admin')->exists(),
                'admin_permissions' => Role::where('name', 'msc-admin')->first()?->permissions->pluck('name'),
            ],
            'specific_checks' => [
                'has_manage_orders' => $user->can('manage-orders'),
                'has_manage_roles' => $user->can('manage-roles'),
                'has_generate_ivr' => $user->can('generate-ivr'),
                'is_msc_admin' => $user->hasRole('msc-admin'),
            ],
            'routes' => [
                'admin_orders_generate_ivr' => route('admin.orders.generate-ivr', ['id' => 1]),
                'rbac_roles_permissions' => route('rbac.roles.permissions', ['role' => 1]),
            ],
        ];

        return response()->json($diagnostics);
    }
}
<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();

        // Determine user role
        // TODO: Replace with proper role field when RBAC is implemented
        $userRole = $this->getUserRole($user);

        // Allow role testing via query parameter (development only)
        if (request()->has('test_role') && app()->environment(['local', 'development'])) {
            $testRole = request()->get('test_role');
            $validRoles = ['provider', 'office_manager', 'msc_rep', 'msc_subrep', 'msc_admin', 'superadmin'];
            if (in_array($testRole, $validRoles)) {
                $userRole = $testRole;
            }
        }

        return Inertia::render('Dashboard/Index', [
            'userRole' => $userRole,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $userRole,
                'owner' => $user->owner,
            ],
            'showRoleTestSwitcher' => app()->environment(['local', 'development'])
        ]);
    }

    /**
     * Determine user role based on current data structure
     * This is a temporary solution until RBAC is fully implemented
     */
    private function getUserRole($user): string
    {
        // Check if user has a specific role field (when RBAC is implemented)
        if (isset($user->role) && !empty($user->role)) {
            return $user->role;
        }

        // Check for sales rep relationship
        if (isset($user->sales_rep_id) && !empty($user->sales_rep_id)) {
            // Could be msc_rep or msc_subrep based on hierarchy
            // For now, default to msc_rep - this will be refined with RBAC
            return 'msc_rep';
        }

        // Check for facility relationship (office manager)
        if (isset($user->facility_id) && !empty($user->facility_id)) {
            return 'office_manager';
        }

        // Legacy owner field mapping
        if ($user->owner) {
            return 'msc_admin';
        }

        // Default to provider
        return 'provider';
    }
}

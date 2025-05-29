<?php

namespace App\Http\Middleware;

use App\Services\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationContext
{
    public function __construct(
        private CurrentOrganization $currentOrganization
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // Super admins can bypass organization context requirements
        if ($user->canAccessAllOrganizations()) {
            // Set a default organization context if one is requested
            if ($request->has('organization_id')) {
                $this->currentOrganization->setId($request->get('organization_id'));
            }
            return $next($request);
        }

        // Handle organization switching via request parameter
        if ($request->has('switch_organization')) {
            $organizationId = $request->get('switch_organization');
            $organization = $user->organizations()->where('organization_id', $organizationId)->wherePivot('is_active', true)->first();

            if ($organization && $user->switchOrganization($organization)) {
                $this->currentOrganization->setId($organization->id);
                return redirect($request->url())->with('success', 'Switched to ' . $organization->name);
            } else {
                return redirect()->back()->with('error', 'Unable to switch to that organization');
            }
        }

        // Ensure user has a current organization context
        $currentOrgId = $user->current_organization_id;

        if (!$currentOrgId) {
            // Try to set primary organization as current
            $primaryOrg = $user->primaryOrganization();
            if ($primaryOrg) {
                $user->switchOrganization($primaryOrg);
                $currentOrgId = $primaryOrg->id;
            } else {
                // Fall back to first active organization
                $firstOrg = $user->activeOrganizations()->first();
                if ($firstOrg) {
                    $user->switchOrganization($firstOrg);
                    $currentOrgId = $firstOrg->id;
                } else {
                    // User has no organization access - this should be handled by authorization
                    return redirect()->route('unauthorized')->with('error', 'No organization access found');
                }
            }
        }

        // Verify user still has access to their current organization
        if (!$user->hasAccessToOrganization($user->currentOrganization)) {
            // Reset to primary or first available organization
            $fallbackOrg = $user->primaryOrganization() ?? $user->activeOrganizations()->first();
            if ($fallbackOrg) {
                $user->switchOrganization($fallbackOrg);
                $currentOrgId = $fallbackOrg->id;
            } else {
                return redirect()->route('unauthorized')->with('error', 'Organization access revoked');
            }
        }

        // Set the current organization in the service
        $this->currentOrganization->setId($currentOrgId);

        // Add organization context to view data
        view()->share('currentOrganization', $user->currentOrganization);
        view()->share('availableOrganizations', $user->activeOrganizations);

        return $next($request);
    }
}

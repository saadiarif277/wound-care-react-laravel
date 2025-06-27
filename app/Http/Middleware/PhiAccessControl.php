<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\PhiAuditService;
use Symfony\Component\HttpFoundation\Response;

class PhiAccessControl
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $requiredPermission = null): Response
    {
        $user = $request->user();
        
        // Check if user is authenticated
        if (!$user) {
            PhiAuditService::logUnauthorizedAccess(
                'PHI_ENDPOINT',
                $request->path(),
                'Unauthenticated access attempt'
            );
            
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        // Check specific permission if required
        if ($requiredPermission && !$user->hasPermission($requiredPermission)) {
            PhiAuditService::logUnauthorizedAccess(
                'PHI_ENDPOINT',
                $request->path(),
                "Missing required permission: {$requiredPermission}"
            );
            
            return response()->json(['error' => 'Forbidden'], 403);
        }
        
        // Add PHI access header to response
        $response = $next($request);
        $response->headers->set('X-PHI-Access', 'true');
        $response->headers->set('X-PHI-Audit-ID', uniqid('phi_'));
        
        return $response;
    }
}
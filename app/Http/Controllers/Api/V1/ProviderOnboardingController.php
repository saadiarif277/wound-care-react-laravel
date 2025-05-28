<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use App\Models\ProviderInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
// use App\Http\Requests\AcceptInvitationRequest; // To be created if specific validation is needed

class ProviderOnboardingController extends Controller
{
    public function __construct(private OnboardingService $onboardingService)
    {
    }

    /**
     * Verify an invitation token.
     * GET /api/v1/invitations/verify/{token}
     */
    public function verifyInvitation(string $token): JsonResponse
    {
        $invitation = ProviderInvitation::where('invitation_token', $token)
            ->where('status', 'sent') // Only consider 'sent' invitations
            ->where('expires_at', '>', now())
            ->first();

        if (!$invitation) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid or expired invitation token.'
            ], 404);
        }

        // Return some details about the invitation if valid, e.g., organization name
        // This helps the frontend display context to the registering provider.
        $organizationName = $invitation->organization->name ?? 'the organization'; // Eager load if needed: $invitation->load('organization')

        return response()->json([
            'valid' => true,
            'email' => $invitation->email,
            'first_name' => $invitation->first_name,
            'last_name' => $invitation->last_name,
            'organization_name' => $organizationName,
            'message' => 'Invitation token is valid.'
        ]);
    }

    /**
     * Accept an invitation and register a new provider user.
     * POST /api/v1/invitations/accept/{token}
     */
    public function acceptInvitation(Request $request, string $token): JsonResponse // Potentially use AcceptInvitationRequest
    {
        // Basic validation for required fields during registration from invitation
        $request->validate([
            'first_name' => 'sometimes|required|string|max:255', // Can be prefilled from invitation
            'last_name' => 'sometimes|required|string|max:255',  // Can be prefilled from invitation
            'password' => 'required|string|min:8|confirmed',
            'npi' => 'nullable|string|digits:10', // National Provider Identifier
            // Add other fields that provider needs to fill during registration, e.g., license info if not collected later
        ]);

        $registrationData = $request->only([
            'first_name',
            'last_name',
            'password',
            'npi',
            // Collect other registration fields here
        ]);

        $result = $this->onboardingService->acceptProviderInvitation($token, $registrationData);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'] ?? 'Failed to accept invitation.'
            ], 400); // Or 404 if token was invalid by then
        }

        // At this point, user is created. Consider logging them in and returning a token/session.
        // For API, typically you'd return the user resource and an API token.
        // $user = $result['user'];
        // $apiToken = $user->createToken('provider-registration-token')->plainTextToken;

        return response()->json([
            'message' => $result['message'],
            'user' => $result['user'], // Consider using a UserResource here
            // 'token' => $apiToken, // If auto-login and token generation is desired
        ], 201);
    }
}

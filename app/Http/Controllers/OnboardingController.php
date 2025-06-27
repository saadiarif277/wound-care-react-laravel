<?php

namespace App\Http\Controllers;

use App\Models\Users\Organization\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    /**
     * Show organization setup wizard
     * Only accessible by users with permission to complete their organization's onboarding
     */
    public function organizationSetup()
    {
        // Get user's organization
        $user = Auth::user();

        // Check if user has permission to complete organization onboarding
        if (!$user->hasPermission('complete-organization-onboarding')) {
            abort(403, 'You do not have permission to access organization onboarding.');
        }

        // Get user's organization
        $organization = $user->organization;

        if (!$organization) {
            abort(404, 'No organization found for this user.');
        }

        // Get onboarding data
        $onboardingData = [
            'current_step' => $organization->onboarding_current_step ?? 1,
            'completed_steps' => $organization->onboarding_completed_steps ?? [],
            'required_documents' => [
                'business_license',
                'insurance_certificate',
                'w9_form'
            ],
            'progress_percentage' => $organization->onboarding_progress ?? 0,
        ];

        return Inertia::render('Onboarding/OrganizationSetupWizard', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'type' => $organization->type,
                'status' => $organization->status,
            ],
            'onboardingData' => $onboardingData,
        ]);
    }

    /**
     * Save onboarding progress
     */
    public function saveProgress(Request $request)
    {
        $user = Auth::user();

        // Check permission
        if (!$user->hasPermission('complete-organization-onboarding')) {
            abort(403, 'You do not have permission to update organization onboarding.');
        }

        $organization = $user->organization;

        if (!$organization) {
            abort(404, 'No organization found for this user.');
        }

        $request->validate([
            'step' => 'required|integer|min:1|max:6',
            'data' => 'required|array',
        ]);

        // Update onboarding progress
        $completedSteps = $organization->onboarding_completed_steps ?? [];

        if (!in_array($request->step, $completedSteps)) {
            $completedSteps[] = $request->step;
        }

        $organization->update([
            'onboarding_current_step' => min($request->step + 1, 6),
            'onboarding_completed_steps' => $completedSteps,
            'onboarding_progress' => (count($completedSteps) / 6) * 100,
            'onboarding_status' => count($completedSteps) === 6 ? 'completed' : 'in_progress',
        ]);

        // Store step-specific data (documents, compliance acknowledgments, etc.)
        // This would typically be stored in a separate onboarding_data table
        // For now, we'll store it in the organization's metadata

        return response()->json([
            'success' => true,
            'message' => 'Progress saved successfully',
            'onboarding' => [
                'current_step' => $organization->onboarding_current_step,
                'completed_steps' => $organization->onboarding_completed_steps,
                'progress_percentage' => $organization->onboarding_progress,
                'status' => $organization->onboarding_status,
            ]
        ]);
    }
}

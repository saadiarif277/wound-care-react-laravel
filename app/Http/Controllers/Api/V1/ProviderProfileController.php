<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User; // Assuming User model is the provider
use App\Http\Resources\UserResource; // For returning provider data
use App\Services\OnboardingService; // For getting onboarding status
use App\Services\NPIVerificationService; // For NPI verification
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
// use App\Http\Requests\UpdateProviderProfileRequest; // To be created for profile updates
// use App\Models\OnboardingDocument; // If handling document uploads directly here

class ProviderProfileController extends Controller
{
    public function __construct(
        private OnboardingService $onboardingService,
        private NPIVerificationService $npiService
    ) {}

    /**
     * Show the authenticated provider's profile.
     * GET /api/v1/profile
     */
    public function show(Request $request): JsonResponse
    {
        $provider = Auth::user(); // Get the authenticated user (provider)
        // Ensure the user is actually a provider if roles are distinct
        // if (!$provider->hasRole('provider')) { return response()->json(['error' => 'Unauthorized'], 403); }

        // Eager load relationships relevant for profile view if not globally loaded by UserResource
        // $provider->loadMissing('facilities', 'onboardingChecklists');
        return response()->json(new UserResource($provider));
    }

    /**
     * Update the authenticated provider's profile.
     * PUT /api/v1/profile
     */
    public function update(Request $request): JsonResponse // Potentially use UpdateProviderProfileRequest
    {
        $provider = Auth::user();

        $validatedData = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            // 'email' => 'sometimes|required|email|unique:users,email,'.$provider->id, // Email updates need care
            'phone' => 'nullable|string|max:20',
            // Provider-specific fields from User model
            'npi_number' => 'nullable|string|digits:10',
            'dea_number' => 'nullable|string|max:50',
            'license_number' => 'nullable|string|max:100',
            'license_state' => 'nullable|string|size:2',
            'license_expiry' => 'nullable|date',
            'credentials' => 'nullable|array', // For other structured credentials
        ]);

        $provider->update($validatedData);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => new UserResource($provider)
        ]);
    }

    /**
     * Verify NPI for the authenticated provider.
     * POST /api/v1/profile/verify-npi
     */
    public function verifyNPI(Request $request): JsonResponse
    {
        $provider = Auth::user();
        $request->validate(['npi' => 'required|string|digits:10']);
        $npiToVerify = $request->npi;

        $verificationResult = $this->npiService->verifyNPI($npiToVerify);

        if ($verificationResult['valid']) {
            // Update provider's NPI number and verification status if successful
            $provider->update([
                'npi_number' => $npiToVerify,
                // 'npi_verified_at' => now(), // Assuming a verification timestamp field exists
                'is_verified' => true, // Or a specific NPI verification flag
            ]);
            // Potentially update an onboarding checklist item
            $this->onboardingService->updateOnboardingProgress(User::class, $provider->id, 'npi_verification', true);

            return response()->json([
                'message' => 'NPI verified and profile updated.',
                'npi_data' => $verificationResult,
                'user' => new UserResource($provider->fresh()),
            ]);
        } else {
            return response()->json([
                'message' => 'NPI verification failed.',
                'npi_data' => $verificationResult,
            ], 422); // Unprocessable Entity or Bad Request
        }
    }

    /**
     * Add a credential document for the authenticated provider.
     * POST /api/v1/profile/documents  (Matches admin route, ensure distinct handling or separate route)
     * Consider route like /api/v1/profile/onboarding-documents if it's for onboarding specifically
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $provider = Auth::user();
        $request->validate([
            'document_type' => 'required|string', // e.g., 'medical_license', 'dea_certificate', 'malpractice_insurance'
            'document' => 'required|file|max:10240', // 10MB max
            'expiration_date' => 'nullable|date',
        ]);

        $file = $request->file('document');
        $path = $file->store('provider-documents/' . $provider->id, 's3'); // Example: S3 storage

        // Create OnboardingDocument record linked to the provider (User model)
        // This assumes OnboardingDocument model exists and is set up for morphTo relationship with User
        $document = $provider->onboardingDocuments()->create([
            // 'id' will be auto-generated by OnboardingDocument model if using UUIDs
            'document_type' => $request->document_type,
            'document_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'status' => 'uploaded', // Or 'under_review' if admin approval is needed
            'uploaded_by' => $provider->id, // Self-uploaded
            'expiration_date' => $request->expiration_date,
        ]);

        // Update relevant onboarding checklist item
        $checklistItemKey = $this->onboardingService->mapDocumentTypeToChecklistItem($request->document_type); // Assuming mapDocumentTypeToChecklistItem is public or trait
        if ($checklistItemKey) {
            $this->onboardingService->updateOnboardingProgress(User::class, $provider->id, $checklistItemKey, true);
        }

        return response()->json([
            'message' => 'Document uploaded successfully.',
            'document' => $document // Or a new OnboardingDocumentResource($document)
        ], 201);
    }

    /**
     * Get the onboarding status for the authenticated provider.
     * GET /api/v1/profile/onboarding-status
     */
    public function getOnboardingStatus(Request $request): JsonResponse
    {
        $provider = Auth::user();

        // The OnboardingService->getOnboardingDashboard is organization-centric.
        // We need a provider-centric view of their checklist.
        $checklist = $this->onboardingService->getChecklistStatus(User::class, $provider->id); // Assuming getChecklistStatus is public/protected

        if (empty($checklist)) {
            // If no checklist, maybe provider onboarding hasn't started or is managed differently.
            // Could create one here if it's expected upon first profile access by a new provider.
            // For now, return an informative message.
            return response()->json(['message' => 'No onboarding checklist found for this provider.', 'checklist' => null], 200);
        }

        return response()->json([
            'checklist' => $checklist,
            // Potentially add overall status if calculable e.g. based on checklist completion
            'is_fully_onboarded' => ($checklist['completion_percentage'] ?? 0) >= 100,
        ]);
    }

    // Placeholder for addCredential if it's different from uploading a document
    // public function addCredential(Request $request): JsonResponse { /* ... */ }
}

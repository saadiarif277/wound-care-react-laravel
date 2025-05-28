<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProviderProfile;
use App\Models\ProfileAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProviderProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, int $providerId): JsonResponse
    {
        // Check if user can view this profile
        $this->checkProfileAccess('view-provider-profile', $providerId);

        $profile = ProviderProfile::with(['provider', 'credentials.verifier'])
            ->where('provider_id', $providerId)
            ->first();

        if (!$profile) {
            // Create a default profile if it doesn't exist
            $profile = $this->createDefaultProfile($providerId);
        }

        // Log profile view for audit
        ProfileAuditLog::logProfileChange(
            'provider_profile',
            (string) $providerId,
            'view_sensitive',
            [],
            [
                'entity_display_name' => $profile->provider->name ?? 'Provider Profile',
                'action_description' => 'Provider profile viewed',
                'compliance_category' => 'administrative',
                'is_sensitive_data' => false,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'profile' => $this->formatProfileResponse($profile),
                'completion_percentage' => $profile->profile_completion_percentage,
                'verification_status' => $profile->verification_status,
                'credentials_summary' => $this->getCredentialsSummary($profile),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $providerId): JsonResponse
    {
        // Check if user can edit this profile
        $this->checkProfileAccess('edit-provider-profile', $providerId);

        $profile = ProviderProfile::where('provider_id', $providerId)->first();
        if (!$profile) {
            $profile = $this->createDefaultProfile($providerId);
        }

        $validator = $this->validateProfileUpdate($request);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $originalData = $profile->toArray();

        // Handle professional photo upload
        if ($request->hasFile('professional_photo')) {
            $validated['professional_photo_path'] = $this->handlePhotoUpload($request->file('professional_photo'), $providerId);
        }

        // Update profile
        $profile->fill($validated);
        $profile->updated_by = Auth::id();
        $profile->save();

        // Update completion percentage
        $profile->updateCompletionPercentage();

        // Log the changes
        $changes = $this->getFieldChanges($originalData, $profile->toArray());
        if (!empty($changes)) {
            ProfileAuditLog::logProfileUpdate($profile, $changes, $request->input('reason'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'profile' => $this->formatProfileResponse($profile),
                'completion_percentage' => $profile->profile_completion_percentage,
            ],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     * Update notification preferences.
     */
    public function updateNotificationPreferences(Request $request, int $providerId): JsonResponse
    {
        $this->checkProfileAccess('edit-provider-profile', $providerId);

        $validator = Validator::make($request->all(), [
            'email.credential_expiry' => 'boolean',
            'email.profile_updates' => 'boolean',
            'email.system_notifications' => 'boolean',
            'email.marketing' => 'boolean',
            'sms.urgent_alerts' => 'boolean',
            'sms.credential_expiry' => 'boolean',
            'sms.system_notifications' => 'boolean',
            'in_app.all_notifications' => 'boolean',
            'frequency.credential_reminders' => 'in:daily,weekly,monthly',
            'frequency.digest' => 'in:daily,weekly,monthly',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $profile = ProviderProfile::where('provider_id', $providerId)->firstOrFail();
        $originalPreferences = $profile->notification_preferences;

        // Ensure we're working with arrays, not JSON strings
        $currentPreferences = is_array($profile->notification_preferences) 
            ? $profile->notification_preferences 
            : (json_decode($profile->notification_preferences, true) ?? []);
            
        $defaultPreferences = ProviderProfile::getDefaultNotificationPreferences();

        $profile->notification_preferences = array_merge(
            $currentPreferences ?: $defaultPreferences,
            $request->all()
        );
        $profile->updated_by = Auth::id();
        $profile->save();

        // Log the change
        ProfileAuditLog::logProfileChange(
            'provider_profile',
            (string) $providerId,
            'update',
            [
                'notification_preferences' => [
                    'old' => $originalPreferences,
                    'new' => $profile->notification_preferences,
                ],
            ],
            [
                'entity_display_name' => $profile->provider->name ?? 'Provider Profile',
                'action_description' => 'Notification preferences updated',
                'compliance_category' => 'administrative',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification preferences updated successfully',
            'data' => [
                'notification_preferences' => $profile->notification_preferences,
            ],
        ]);
    }

    /**
     * Update practice preferences.
     */
    public function updatePracticePreferences(Request $request, int $providerId): JsonResponse
    {
        $this->checkProfileAccess('edit-provider-profile', $providerId);

        $validator = Validator::make($request->all(), [
            'default_protocols' => 'array',
            'preferred_products' => 'array',
            'documentation_templates' => 'array',
            'clinical_decision_support' => 'boolean',
            'auto_recommendations' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $profile = ProviderProfile::where('provider_id', $providerId)->firstOrFail();
        $originalPreferences = $profile->practice_preferences;

        // Ensure we're working with arrays, not JSON strings  
        $currentPreferences = is_array($profile->practice_preferences)
            ? $profile->practice_preferences
            : (json_decode($profile->practice_preferences, true) ?? []);
            
        $defaultPreferences = ProviderProfile::getDefaultPracticePreferences();

        $profile->practice_preferences = array_merge(
            $currentPreferences ?: $defaultPreferences,
            $request->all()
        );
        $profile->updated_by = Auth::id();
        $profile->save();

        // Log the change
        ProfileAuditLog::logProfileChange(
            'provider_profile',
            (string) $providerId,
            'update',
            [
                'practice_preferences' => [
                    'old' => $originalPreferences,
                    'new' => $profile->practice_preferences,
                ],
            ],
            [
                'entity_display_name' => $profile->provider->name ?? 'Provider Profile',
                'action_description' => 'Practice preferences updated',
                'compliance_category' => 'clinical',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Practice preferences updated successfully',
            'data' => [
                'practice_preferences' => $profile->practice_preferences,
            ],
        ]);
    }

    /**
     * Get profile completion status.
     */
    public function completionStatus(int $providerId): JsonResponse
    {
        $this->checkProfileAccess('view-provider-profile', $providerId);

        $profile = ProviderProfile::where('provider_id', $providerId)->first();
        if (!$profile) {
            $profile = $this->createDefaultProfile($providerId);
        }

        $completionData = [
            'overall_percentage' => $profile->profile_completion_percentage,
            'verification_status' => $profile->verification_status,
            'sections' => [
                'basic_info' => $this->getBasicInfoCompletion($profile),
                'credentials' => $this->getCredentialsCompletion($profile),
                'preferences' => $this->getPreferencesCompletion($profile),
            ],
            'next_steps' => $this->getNextSteps($profile),
        ];

        return response()->json([
            'success' => true,
            'data' => $completionData,
        ]);
    }

    /**
     * Create a default profile for a provider.
     */
    private function createDefaultProfile(int $providerId): ProviderProfile
    {
        $provider = User::findOrFail($providerId);

        $profile = ProviderProfile::create([
            'provider_id' => $providerId,
            'notification_preferences' => ProviderProfile::getDefaultNotificationPreferences(),
            'practice_preferences' => ProviderProfile::getDefaultPracticePreferences(),
            'workflow_settings' => ProviderProfile::getDefaultWorkflowSettings(),
            'created_by' => Auth::id(),
        ]);

        // Log profile creation
        ProfileAuditLog::logProfileChange(
            'provider_profile',
            (string) $providerId,
            'create',
            [],
            [
                'entity_display_name' => $provider->name ?? 'Provider Profile',
                'action_description' => 'Provider profile created with defaults',
                'compliance_category' => 'administrative',
            ]
        );

        return $profile;
    }

    /**
     * Validate profile update request.
     */
    private function validateProfileUpdate(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'professional_bio' => 'nullable|string|max:2000',
            'specializations' => 'nullable|array',
            'specializations.*' => 'string|max:100',
            'languages_spoken' => 'nullable|array',
            'languages_spoken.*' => 'string|max:50',
            'professional_photo' => [
                'nullable',
                'file',
                'image',
                'mimes:jpeg,png,jpg',
                'max:2048', // 2MB max
                'mimetypes:image/jpeg,image/png,image/jpg',
                function ($attribute, $value, $fail) {
                    // Additional security check for suspicious files
                    if ($value && !getimagesize($value->getPathname())) {
                        $fail('The uploaded file is not a valid image.');
                    }
                },
            ],
            'two_factor_enabled' => 'boolean',
            'reason' => 'nullable|string|max:500',
        ]);
    }

    /**
     * Handle professional photo upload with enhanced security.
     */
    private function handlePhotoUpload($file, int $providerId): string
    {
        // Validate file is actually an image
        if (!getimagesize($file->getPathname())) {
            throw new \InvalidArgumentException('Invalid image file');
        }

        // Generate secure filename
        $extension = $file->getClientOriginalExtension();
        $filename = 'provider_' . $providerId . '_' . time() . '_' . uniqid() . '.' . $extension;
        
        // Store in private disk (Supabase S3)
        $path = $file->storeAs('provider-photos', $filename, 'supabase');
        
        if (!$path) {
            throw new \Exception('Failed to store image file');
        }
        
        return $path;
    }

    /**
     * Get field changes between original and updated data.
     */
    private function getFieldChanges(array $original, array $updated): array
    {
        $changes = [];
        $trackableFields = [
            'professional_bio',
            'specializations',
            'languages_spoken',
            'professional_photo_path',
            'two_factor_enabled',
        ];

        foreach ($trackableFields as $field) {
            if (isset($original[$field], $updated[$field]) && $original[$field] !== $updated[$field]) {
                $changes[$field] = [
                    'old' => $original[$field],
                    'new' => $updated[$field],
                ];
            }
        }

        return $changes;
    }

    /**
     * Format profile response for API.
     */
    private function formatProfileResponse(ProviderProfile $profile): array
    {
        return [
            'provider_id' => $profile->provider_id,
            'professional_bio' => $profile->professional_bio,
            'specializations' => $profile->specializations ?? [],
            'languages_spoken' => $profile->languages_spoken ?? [],
            'professional_photo_url' => $profile->professional_photo_path
                ? Storage::url($profile->professional_photo_path)
                : null,
            'verification_status' => $profile->verification_status,
            'verification_status_label' => $profile->getVerificationStatusLabel(),
            'verification_status_color' => $profile->getVerificationStatusColor(),
            'last_profile_update' => $profile->last_profile_update?->toISOString(),
            'two_factor_enabled' => $profile->two_factor_enabled,
            'notification_preferences' => $profile->notification_preferences ?? [],
            'practice_preferences' => $profile->practice_preferences ?? [],
            'workflow_settings' => $profile->workflow_settings ?? [],
            'provider' => [
                'id' => $profile->provider->id,
                'name' => $profile->provider->first_name . ' ' . $profile->provider->last_name,
                'email' => $profile->provider->email,
                'npi_number' => $profile->provider->npi_number,
            ],
        ];
    }

    /**
     * Get credentials summary for profile.
     */
    private function getCredentialsSummary(ProviderProfile $profile): array
    {
        $credentials = $profile->activeCredentials;

        return [
            'total_count' => $credentials->count(),
            'verified_count' => $credentials->where('verification_status', 'verified')->count(),
            'expiring_soon_count' => $credentials->filter(fn($c) => $c->isExpiringSoon())->count(),
            'expired_count' => $credentials->filter(fn($c) => $c->isExpired())->count(),
        ];
    }

    /**
     * Get basic info completion status.
     */
    private function getBasicInfoCompletion(ProviderProfile $profile): array
    {
        $fields = [
            'professional_bio' => !empty($profile->professional_bio),
            'specializations' => !empty($profile->specializations),
            'languages_spoken' => !empty($profile->languages_spoken),
            'professional_photo' => !empty($profile->professional_photo_path),
        ];

        $completed = array_filter($fields);
        $percentage = count($fields) > 0 ? (count($completed) / count($fields)) * 100 : 0;

        return [
            'percentage' => round($percentage),
            'fields' => $fields,
        ];
    }

    /**
     * Get credentials completion status.
     */
    private function getCredentialsCompletion(ProviderProfile $profile): array
    {
        $requiredTypes = ['medical_license', 'npi_number'];
        $credentials = $profile->activeCredentials->where('verification_status', 'verified');
        $credentialTypes = $credentials->pluck('credential_type')->toArray();

        $hasRequired = array_intersect($requiredTypes, $credentialTypes);
        $percentage = count($requiredTypes) > 0 ? (count($hasRequired) / count($requiredTypes)) * 100 : 0;

        return [
            'percentage' => round($percentage),
            'required_completed' => count($hasRequired),
            'required_total' => count($requiredTypes),
            'total_credentials' => $credentials->count(),
        ];
    }

    /**
     * Get preferences completion status.
     */
    private function getPreferencesCompletion(ProviderProfile $profile): array
    {
        $sections = [
            'notification_preferences' => !empty($profile->notification_preferences),
            'practice_preferences' => !empty($profile->practice_preferences),
            'workflow_settings' => !empty($profile->workflow_settings),
        ];

        $completed = array_filter($sections);
        $percentage = count($sections) > 0 ? (count($completed) / count($sections)) * 100 : 0;

        return [
            'percentage' => round($percentage),
            'sections' => $sections,
        ];
    }

    /**
     * Get next steps for profile completion.
     */
    private function getNextSteps(ProviderProfile $profile): array
    {
        $steps = [];

        if (empty($profile->professional_bio)) {
            $steps[] = [
                'title' => 'Add Professional Biography',
                'description' => 'Provide a brief professional biography',
                'priority' => 'medium',
            ];
        }

        if (empty($profile->specializations)) {
            $steps[] = [
                'title' => 'Add Specializations',
                'description' => 'List your medical specializations',
                'priority' => 'high',
            ];
        }

        $requiredCredentials = ['medical_license', 'npi_number'];
        $existingTypes = $profile->activeCredentials->pluck('credential_type')->toArray();
        $missingRequired = array_diff($requiredCredentials, $existingTypes);

        foreach ($missingRequired as $type) {
            $steps[] = [
                'title' => 'Add ' . ucwords(str_replace('_', ' ', $type)),
                'description' => 'Upload and verify your ' . str_replace('_', ' ', $type),
                'priority' => 'high',
            ];
        }

        return $steps;
    }

    /**
     * Check profile access authorization.
     */
    private function checkProfileAccess(string $action, int $providerId): void
    {
        $user = Auth::user();

        // Providers can manage their own profiles
        if ($user->id === $providerId) {
            return;
        }

        // Check for admin permissions
        if (!$user->hasPermissionTo('admin:manage-providers')) {
            abort(403, 'Unauthorized to access this profile');
        }
    }
}

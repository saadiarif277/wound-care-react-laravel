<?php

namespace App\Services;

use App\Models\Users\Organization\Organization;
use App\Models\User;
use App\Models\Users\Provider\ProviderInvitation;
use App\Models\Users\OnboardingChecklist;
use App\Mail\ProviderInvitationMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use App\Models\Fhir\Facility;

class OnboardingService
{
    // Entity type constants to avoid hardcoded class names
    private const ENTITY_TYPE_ORGANIZATION = Organization::class;
    private const ENTITY_TYPE_FACILITY = Facility::class;
    private const ENTITY_TYPE_USER = User::class;

    // Checklist type constants
    private const CHECKLIST_TYPE_ORGANIZATION = 'organization';
    private const CHECKLIST_TYPE_FACILITY = 'facility';
    private const CHECKLIST_TYPE_PROVIDER = 'provider';

    // Token configuration
    private const INVITATION_TOKEN_LENGTH = 64;
    private const INVITATION_EXPIRY_DAYS = 30;

    private array $organizationChecklist = [
        'basic_information' => 'Complete organization profile',
        'tax_documentation' => 'Upload W-9 or tax documents',
        'billing_setup' => 'Configure billing information',
        'insurance_verification' => 'Provide insurance documentation',
        'baa_agreement' => 'Sign Business Associate Agreement',
        'add_facilities' => 'Add at least one facility',
        'invite_providers' => 'Invite at least one provider',
        'admin_training' => 'Complete admin training',
        'provider_training' => 'Ensure providers complete training',
        'test_order' => 'Submit test order successfully'
    ];

    private array $facilityChecklist = [
        'basic_information' => 'Complete facility profile',
        'address_verification' => 'Verify facility address',
        'npi_validation' => 'Validate facility NPI if applicable',
        'service_capabilities' => 'Define services offered',
        'operating_hours' => 'Set operating hours',
        'insurance_accepted' => 'List accepted insurance',
        'mac_jurisdiction' => 'Confirm MAC jurisdiction',
        'contact_information' => 'Add facility contacts'
    ];

    private array $providerChecklist = [
        'personal_information' => 'Complete personal profile',
        'npi_verification' => 'Verify NPI number',
        'medical_license' => 'Upload medical license',
        'dea_registration' => 'Add DEA if applicable',
        'malpractice_insurance' => 'Upload insurance certificate',
        'facility_assignment' => 'Assign to facilities',
        'system_training' => 'Complete platform training',
        'ehr_integration' => 'Set up EHR preferences',
        'first_order' => 'Submit first order'
    ];

    /**
     * Initialize organization onboarding
     */
    public function initiateOrganizationOnboarding(Organization $organization, int $managerId): array
    {
        if (empty($this->organizationChecklist)) {
            Log::error('Organization checklist definition is missing in OnboardingService');
            throw new \RuntimeException('Organization checklist configuration is missing');
        }

        DB::beginTransaction();
        try {
            $onboardingRecordId = $this->generateSecureUuid();

            DB::table('organization_onboarding')->insert([
                'id' => $onboardingRecordId,
                'organization_id' => $organization->id,
                'status' => 'initiated',
                'completed_steps' => json_encode([]),
                'pending_items' => json_encode(array_keys($this->organizationChecklist)),
                'onboarding_manager_id' => $managerId,
                'initiated_at' => now(),
                'target_go_live_date' => now()->addDays(30),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Create onboarding checklist
            $this->createChecklist(
                $organization->id,
                self::ENTITY_TYPE_ORGANIZATION,
                self::CHECKLIST_TYPE_ORGANIZATION,
                $this->organizationChecklist
            );

            // Send welcome email if mail service is configured
            $this->sendOrganizationWelcomeEmail($organization);

            DB::commit();

            Log::info('Organization onboarding initiated successfully', [
                'organization_id' => $organization->id,
                'onboarding_id' => $onboardingRecordId,
                'manager_id' => $managerId
            ]);

            return [
                'success' => true,
                'message' => 'Onboarding initiated successfully',
                'onboarding_id' => $onboardingRecordId
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to initiate organization onboarding', [
                'organization_id' => $organization->id,
                'manager_id' => $managerId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to initiate onboarding: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Invite providers to join organization with comprehensive validation
     */
    public function inviteProviders(array $providers, int $organizationId, int $invitedBy): array
    {
        // Validate input array structure
        $this->validateProviderInvitations($providers);

        $results = [
            'sent' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($providers as $index => $provider) {
            try {
                // Additional per-provider validation
                $this->validateSingleProviderInvitation($provider, $index);

                $invitationToken = $this->generateSecureToken();

                $invitation = ProviderInvitation::create([
                    'email' => strtolower(trim($provider['email'])),
                    'first_name' => trim($provider['first_name']),
                    'last_name' => trim($provider['last_name']),
                    'invitation_token' => $invitationToken,
                    'organization_id' => $organizationId,
                    'invited_by_user_id' => $invitedBy,
                    'assigned_facilities' => $provider['facilities'] ?? [],
                    'assigned_roles' => $provider['roles'] ?? ['provider'],
                    'status' => 'pending',
                    'expires_at' => now()->addDays(self::INVITATION_EXPIRY_DAYS)
                ]);

                // Send invitation email
                $this->sendProviderInvitationEmail($invitation);

                $invitation->update([
                    'status' => 'sent',
                    'sent_at' => now()
                ]);

                $results['sent']++;
                $results['details'][] = [
                    'email' => $provider['email'],
                    'status' => 'sent',
                    'invitation_id' => $invitation->id
                ];

                Log::info('Provider invitation sent successfully', [
                    'email' => $provider['email'],
                    'organization_id' => $organizationId,
                    'invitation_id' => $invitation->id
                ]);

            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'email' => $provider['email'] ?? "Unknown (index {$index})",
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];

                Log::error('Failed to send provider invitation', [
                    'email' => $provider['email'] ?? "Unknown (index {$index})",
                    'organization_id' => $organizationId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Handle provider self-registration from invitation with enhanced validation
     */
    public function acceptProviderInvitation(string $token, array $registrationData): array
    {
        // Validate registration data
        $this->validateRegistrationData($registrationData);

        $invitation = ProviderInvitation::where('invitation_token', $token)
            ->where('status', 'sent')
            ->where('expires_at', '>', now())
            ->first();

        if (!$invitation) {
            Log::warning('Invalid or expired invitation token used', ['token' => substr($token, 0, 8) . '...']);
            return [
                'success' => false,
                'message' => 'Invalid or expired invitation'
            ];
        }

        DB::beginTransaction();
        try {
            // Create user account
            $user = User::create([
                'account_id' => $invitation->organization->account_id,
                'first_name' => $registrationData['first_name'] ?? $invitation->first_name,
                'last_name' => $registrationData['last_name'] ?? $invitation->last_name,
                'email' => $invitation->email,
                'password' => bcrypt($registrationData['password']),
                'email_verified_at' => now()
            ]);

            // Assign provider role
            $providerRole = \App\Models\Role::where('slug', 'provider')->first();
            if ($providerRole) {
                $user->assignRole($providerRole);
            }

            // Create provider checklist
            $this->createChecklist(
                $user->id,
                self::ENTITY_TYPE_USER,
                self::CHECKLIST_TYPE_PROVIDER,
                $this->providerChecklist
            );

            // Update invitation
            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'created_user_id' => $user->id
            ]);

            // Send welcome email
            $this->sendProviderWelcomeEmail($user);

            DB::commit();

            Log::info('Provider registration completed successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'invitation_id' => $invitation->id
            ]);

            return [
                'success' => true,
                'message' => 'Registration successful',
                'user' => $user
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Provider registration failed', [
                'token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update onboarding progress with improved class handling
     */
    public function updateOnboardingProgress(string $entityType, int $entityId, string $checklistItemKey, bool $completed = true): void
    {
        $checklist = OnboardingChecklist::where('entity_id', $entityId)
            ->where('entity_type', $entityType)
            ->first();

        if (!$checklist) {
            Log::warning('Checklist not found for entity', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'checklist_item' => $checklistItemKey
            ]);
            return;
        }

        $items = $checklist->items;
        if (!isset($items[$checklistItemKey])) {
            Log::warning('Checklist item not found', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'checklist_item' => $checklistItemKey
            ]);
            return;
        }

        $items[$checklistItemKey]['completed'] = $completed;
        $items[$checklistItemKey]['completed_at'] = $completed ? now()->toIso8601String() : null;

        $checklist->items = $items;
        $checklist->completed_items = collect($items)->where('completed', true)->count();

        if ($checklist->total_items > 0) {
            $checklist->completion_percentage = ($checklist->completed_items / $checklist->total_items) * 100;
        } else {
            $checklist->completion_percentage = 0;
        }

        $checklist->last_activity_at = now();
        $checklist->save();

        // Use class comparison with constants
        if ($entityType === self::ENTITY_TYPE_ORGANIZATION) {
            $this->updateOrganizationOnboardingStatus($entityId);
        }

        Log::info('Onboarding progress updated', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'checklist_item' => $checklistItemKey,
            'completed' => $completed,
            'completion_percentage' => $checklist->completion_percentage
        ]);
    }

    /**
     * Get onboarding dashboard data with proper error handling
     */
    public function getOnboardingDashboard(int $organizationId): array
    {
        $onboarding = DB::table('organization_onboarding')
            ->where('organization_id', $organizationId)
            ->first();

        if (!$onboarding) {
            Log::warning('No onboarding record found', ['organization_id' => $organizationId]);
            return ['error' => 'No onboarding record found for this organization.'];
        }

        try {
            $orgChecklist = $this->getChecklistStatus(self::ENTITY_TYPE_ORGANIZATION, $organizationId);
            $facilityChecklists = $this->getFacilityChecklists($organizationId);
            $providerChecklists = $this->getProviderChecklists($organizationId);

            $pendingInvitations = ProviderInvitation::where('organization_id', $organizationId)
                ->whereIn('status', ['pending', 'sent'])
                ->count();

            $totalItems = ($orgChecklist['total_items'] ?? 0) +
                         $facilityChecklists->sum('total_items') +
                         $providerChecklists->sum('total_items');

            $completedItems = ($orgChecklist['completed_items'] ?? 0) +
                             $facilityChecklists->sum('completed_items') +
                             $providerChecklists->sum('completed_items');

            $overallProgress = $totalItems > 0 ? round(($completedItems / $totalItems) * 100, 1) : 0;

            return [
                'status' => $onboarding->status,
                'initiated_at' => $onboarding->initiated_at,
                'target_go_live_date' => $onboarding->target_go_live_date,
                'days_until_target' => $onboarding->target_go_live_date ? Carbon::parse($onboarding->target_go_live_date)->diffInDays(now()) : null,
                'overall_progress' => $overallProgress,
                'organization_checklist' => $orgChecklist,
                'facility_count' => $facilityChecklists->count(),
                'facilities_ready' => $facilityChecklists->where('completion_percentage', 100)->count(),
                'provider_count' => $providerChecklists->count(),
                'providers_ready' => $providerChecklists->where('completion_percentage', 100)->count(),
                'pending_invitations' => $pendingInvitations,
                'recent_activity' => $this->getRecentOnboardingActivity($organizationId)
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get onboarding dashboard', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage()
            ]);

            return ['error' => 'Failed to retrieve onboarding dashboard data.'];
        }
    }

    /**
     * Initialize facility onboarding with proper error handling
     */
    public function initiateFacilityOnboarding(Facility $facility): void
    {
        if (empty($this->facilityChecklist)) {
            Log::error('Facility checklist definition is missing in OnboardingService');
            throw new \RuntimeException('Facility checklist configuration is missing');
        }

        try {
            $this->createChecklist(
                $facility->id,
                self::ENTITY_TYPE_FACILITY,
                self::CHECKLIST_TYPE_FACILITY,
                $this->facilityChecklist
            );

            Log::info('Facility onboarding initiated successfully', [
                'facility_id' => $facility->id,
                'facility_name' => $facility->name
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to initiate facility onboarding', [
                'facility_id' => $facility->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get checklist status for an entity
     */
    public function getChecklistStatus(string $entityType, int $entityId): array
    {
        $checklist = OnboardingChecklist::where('entity_id', $entityId)
                                       ->where('entity_type', $entityType)
                                       ->first();
        if ($checklist) {
            return [
                'items' => $checklist->items,
                'total_items' => $checklist->total_items,
                'completed_items' => $checklist->completed_items,
                'completion_percentage' => $checklist->completion_percentage
            ];
        }
        return [];
    }

    /**
     * Maps a document type string to a corresponding checklist item key
     */
    public function mapDocumentTypeToChecklistItem(string $documentType): ?string
    {
        $mapping = [
            // Organization specific
            'w9' => 'tax_documentation',
            'baa' => 'baa_agreement',
            'insurance_verification' => 'insurance_verification',

            // Provider specific
            'medical_license' => 'medical_license',
            'dea_registration' => 'dea_registration',
            'malpractice_insurance' => 'malpractice_insurance',
            'npi_verification_document' => 'npi_verification',

            // Generic
            'insurance' => 'insurance_verification',
        ];

        return $mapping[strtolower(trim($documentType))] ?? null;
    }

    /**
     * Generate cryptographically secure UUID
     */
    private function generateSecureUuid(): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Generate cryptographically secure token
     */
    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(self::INVITATION_TOKEN_LENGTH / 2));
    }

    /**
     * Validate provider invitation array structure
     */
    private function validateProviderInvitations(array $providers): void
    {
        if (empty($providers)) {
            throw new ValidationException(
                validator([], [], ['providers' => 'The providers array cannot be empty.']),
                response()->json(['error' => 'The providers array cannot be empty.'], 422)
            );
        }

        $validator = Validator::make(['providers' => $providers], [
            'providers' => 'required|array|min:1|max:50',
            'providers.*' => 'required|array',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Validate individual provider invitation data
     */
    private function validateSingleProviderInvitation(array $provider, int $index): void
    {
        $validator = Validator::make($provider, [
            'email' => 'required|email|max:255',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'facilities' => 'nullable|array',
            'facilities.*' => 'integer|exists:facilities,id',
            'roles' => 'nullable|array',
            'roles.*' => 'string|in:provider,provider_admin',
        ], [
            'email.required' => "Email is required for provider at index {$index}",
            'first_name.required' => "First name is required for provider at index {$index}",
            'last_name.required' => "Last name is required for provider at index {$index}",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Check for duplicate email in existing invitations
        $existingInvitation = ProviderInvitation::where('email', strtolower(trim($provider['email'])))
            ->whereIn('status', ['pending', 'sent'])
            ->exists();

        if ($existingInvitation) {
            throw new \InvalidArgumentException("An active invitation already exists for email: {$provider['email']}");
        }

        // Check for existing user with same email
        $existingUser = User::where('email', strtolower(trim($provider['email'])))->exists();
        if ($existingUser) {
            throw new \InvalidArgumentException("A user already exists with email: {$provider['email']}");
        }
    }

    /**
     * Validate registration data
     */
    private function validateRegistrationData(array $registrationData): void
    {
        $validator = Validator::make($registrationData, [
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Create onboarding checklist for an entity
     */
    private function createChecklist(int $entityId, string $entityType, string $checklistType, array $items): void
    {
        if (empty($items)) {
            throw new \InvalidArgumentException("Checklist items cannot be empty for type: {$checklistType}");
        }

        $checklistItems = [];
        foreach ($items as $key => $description) {
            $checklistItems[$key] = [
                'description' => $description,
                'completed' => false,
                'completed_at' => null,
                'required' => true
            ];
        }

        OnboardingChecklist::create([
            'id' => $this->generateSecureUuid(),
            'entity_id' => $entityId,
            'entity_type' => $entityType,
            'checklist_type' => $checklistType,
            'items' => $checklistItems,
            'total_items' => count($items),
            'completed_items' => 0,
            'completion_percentage' => 0,
            'last_activity_at' => now()
        ]);
    }

    /**
     * Update organization onboarding status based on checklist progress
     */
    private function updateOrganizationOnboardingStatus(int $organizationId): void
    {
        $checklist = $this->getChecklistStatus(self::ENTITY_TYPE_ORGANIZATION, $organizationId);

        if (empty($checklist) || !isset($checklist['items'])) {
            Log::warning('Cannot update organization status - checklist not found', [
                'organization_id' => $organizationId
            ]);
            return;
        }

        $newStatus = $this->determineOrganizationStatus($checklist);

        DB::table('organization_onboarding')
            ->where('organization_id', $organizationId)
            ->update([
                'status' => $newStatus,
                'completed_steps' => json_encode(collect($checklist['items'])->where('completed', true)->keys()->all()),
                'pending_items' => json_encode(collect($checklist['items'])->where('completed', false)->keys()->all()),
                'updated_at' => now()
            ]);

        Log::info('Organization onboarding status updated', [
            'organization_id' => $organizationId,
            'new_status' => $newStatus
        ]);
    }

    /**
     * Determine organization status based on checklist completion
     */
    private function determineOrganizationStatus(array $checklist): string
    {
        $items = $checklist['items'];

        // Check for completion first
        if (isset($checklist['completion_percentage']) && $checklist['completion_percentage'] >= 100) {
            return 'completed';
        }

        // Check for providers_invited
        if ($this->isItemCompleted($items, 'add_facilities') &&
            $this->isItemCompleted($items, 'invite_providers')) {
            return 'providers_invited';
        }

        // Check for facilities_added
        if ($this->isItemCompleted($items, 'billing_setup') &&
            $this->isItemCompleted($items, 'add_facilities')) {
            return 'facilities_added';
        }

        // Check for billing_setup_complete
        if ($this->isItemCompleted($items, 'basic_information') &&
            $this->isItemCompleted($items, 'tax_documentation') &&
            $this->isItemCompleted($items, 'billing_setup')) {
            return 'billing_setup_complete';
        }

        // Check for basic_info_complete
        if ($this->isItemCompleted($items, 'basic_information') &&
            $this->isItemCompleted($items, 'tax_documentation')) {
            return 'basic_info_complete';
        }

        return 'initiated';
    }

    /**
     * Check if a checklist item is completed
     */
    private function isItemCompleted(array $items, string $itemKey): bool
    {
        return isset($items[$itemKey]['completed']) && $items[$itemKey]['completed'] === true;
    }

    /**
     * Get facility checklists for an organization
     */
    private function getFacilityChecklists(int $organizationId): \Illuminate\Support\Collection
    {
        $facilities = Facility::where('organization_id', $organizationId)->pluck('id');

        return OnboardingChecklist::whereIn('entity_id', $facilities)
            ->where('entity_type', self::ENTITY_TYPE_FACILITY)
            ->get();
    }

    /**
     * Get provider checklists for an organization
     */
    private function getProviderChecklists(int $organizationId): \Illuminate\Support\Collection
    {
        // This would need to be implemented based on user-organization relationships
        // For now, return empty collection
        return collect();
    }

    /**
     * Get recent onboarding activity for an organization
     */
    private function getRecentOnboardingActivity(int $organizationId): array
    {
        // This would typically query an activity log table
        // For now, return empty array
        return [];
    }

    /**
     * Send organization welcome email
     */
    private function sendOrganizationWelcomeEmail(Organization $organization): void
    {
        try {
            // Implementation would go here when email templates are ready
            Log::info('Organization welcome email would be sent', [
                'organization_id' => $organization->id
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to send organization welcome email', [
                'organization_id' => $organization->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send provider invitation email
     */
    private function sendProviderInvitationEmail(ProviderInvitation $invitation): void
    {
        try {
            // Implementation would go here when email templates are ready
            // Mail::to($invitation->email)->queue(new ProviderInvitationMail($invitation));
            Log::info('Provider invitation email would be sent', [
                'email' => $invitation->email,
                'invitation_id' => $invitation->id
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to send provider invitation email', [
                'email' => $invitation->email,
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage()
            ]);
            throw $e; // Re-throw to handle in calling method
        }
    }

    /**
     * Send provider welcome email
     */
    private function sendProviderWelcomeEmail(User $user): void
    {
        try {
            // Implementation would go here when email templates are ready
            Log::info('Provider welcome email would be sent', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to send provider welcome email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage()
            ]);
        }
    }
}

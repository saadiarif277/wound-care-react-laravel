<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use App\Models\ProviderInvitation;
use App\Models\OnboardingChecklist;
use App\Mail\ProviderInvitationMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Facility;

class OnboardingService
{
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
        DB::beginTransaction();
        try {
            // Create onboarding record
            // Assuming 'organization_onboarding' table's 'id' is auto-incrementing or managed elsewhere if not UUID
            // The markdown specified UUID, so we should use Str::uuid()
            $onboardingRecordId = Str::uuid();
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
            $this->createChecklist($organization->id, 'App\\Models\\Organization', 'organization', $this->organizationChecklist);

            // Send welcome email - Placeholder for now
            // $this->sendOrganizationWelcomeEmail($organization);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Onboarding initiated successfully',
                'onboarding_id' => $onboardingRecordId // Return the UUID
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to initiate onboarding: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Invite providers to join organization
     */
    public function inviteProviders(array $providers, int $organizationId, int $invitedBy): array
    {
        $results = [
            'sent' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($providers as $provider) {
            try {
                $invitation = ProviderInvitation::create([
                    // 'id' => Str::uuid(), // Model should handle UUID generation if configured
                    'email' => $provider['email'],
                    'first_name' => $provider['first_name'],
                    'last_name' => $provider['last_name'],
                    'invitation_token' => Str::random(64),
                    'organization_id' => $organizationId,
                    'invited_by_user_id' => $invitedBy,
                    'assigned_facilities' => json_encode($provider['facilities'] ?? []), // Ensure it's JSON
                    'assigned_roles' => json_encode($provider['roles'] ?? ['provider']), // Ensure it's JSON and default
                    'status' => 'pending',
                    'expires_at' => now()->addDays(30)
                ]);

                // Send invitation email - Placeholder, assuming ProviderInvitationMail exists
                // Mail::to($provider['email'])->queue(new ProviderInvitationMail($invitation));

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
            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'email' => $provider['email'],
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Handle provider self-registration from invitation
     */
    public function acceptProviderInvitation(string $token, array $registrationData): array
    {
        $invitation = ProviderInvitation::where('invitation_token', $token)
            ->where('status', 'sent') // Ensure only sent invitations can be accepted
            ->where('expires_at', '>', now())
            ->first();

        if (!$invitation) {
            return [
                'success' => false,
                'message' => 'Invalid or expired invitation'
            ];
        }

        DB::beginTransaction();
        try {
            // Create user account
            $user = User::create([
                'first_name' => $registrationData['first_name'] ?? $invitation->first_name,
                'last_name' => $registrationData['last_name'] ?? $invitation->last_name,
                'email' => $invitation->email,
                'password' => bcrypt($registrationData['password']),
                // 'role' => 'provider', // Assuming User model has a role attribute or similar
                // 'account_id' => $invitation->organization_id // Assuming User model links to an organization/account
            ]);

            // Create provider profile - Placeholder, assumes 'provider_profiles' table and schema
            // DB::table('provider_profiles')->insert([
            //     'provider_id' => $user->id,
            //     'npi' => $registrationData['npi'] ?? null,
            //     'verification_status' => 'pending',
            //     'created_at' => now(),
            //     'updated_at' => now()
            // ]);

            // Assign to facilities - Placeholder, assumes 'facility_user' pivot table
            // foreach ($invitation->assigned_facilities as $facilityId) {
            //     DB::table('facility_user')->insert([
            //         'user_id' => $user->id,
            //         'facility_id' => $facilityId,
            //         'is_primary_location' => false,
            //         'can_order_for_facility' => true,
            //         'start_date' => now(),
            //         'created_at' => now(),
            //         'updated_at' => now()
            //     ]);
            // }

            // Create provider checklist
            $this->createChecklist($user->id, 'App\\Models\\User', 'provider', $this->providerChecklist);

            // Update invitation
            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'created_user_id' => $user->id
            ]);

            // Send welcome email - Placeholder
            // $this->sendProviderWelcomeEmail($user);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Registration successful',
                'user' => $user
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update onboarding progress
     */
    public function updateOnboardingProgress(string $entityType, int $entityId, string $checklistItemKey, bool $completed = true): void
    {
        // Ensure $entityType is the fully qualified class name for morphs
        // Example: App\Models\Organization, App\Models\User
        $checklist = OnboardingChecklist::where('entity_id', $entityId)
            ->where('entity_type', $entityType) // Make sure this matches how it's stored (e.g. App\Models\Organization)
            ->first();

        if ($checklist) {
            $items = $checklist->items; // items is already an array/object due to casts
            if (isset($items[$checklistItemKey])) {
                $items[$checklistItemKey]['completed'] = $completed;
                $items[$checklistItemKey]['completed_at'] = $completed ? now()->toIso8601String() : null;

                $checklist->items = $items; // Eloquent handles JSON encoding on save
                $checklist->completed_items = collect($items)->where('completed', true)->count();
                if ($checklist->total_items > 0) {
                    $checklist->completion_percentage = ($checklist->completed_items / $checklist->total_items) * 100;
                } else {
                    $checklist->completion_percentage = 0;
                }
                $checklist->last_activity_at = now();
                $checklist->save();

                if ($entityType === 'App\\Models\\Organization') { // Match the morph type
                    $this->updateOrganizationOnboardingStatus($entityId);
                }
            }
        }
    }

    /**
     * Get onboarding dashboard data
     */
    public function getOnboardingDashboard(int $organizationId): array
    {
        $onboarding = DB::table('organization_onboarding')
            ->where('organization_id', $organizationId)
            ->first();

        if (!$onboarding) {
            return ['error' => 'No onboarding record found for this organization.'];
        }

        $orgChecklist = $this->getChecklistStatus('App\\Models\\Organization', $organizationId);
        // TODO: Implement getFacilityChecklists and getProviderChecklists if Facility model exists and they have checklists.
        // For now, let's assume they might not exist or return empty collections.
        $facilityChecklists = collect(); // Placeholder
        $providerChecklists = collect(); // Placeholder

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
            'facility_count' => $facilityChecklists->count(), // Placeholder
            'facilities_ready' => $facilityChecklists->where('completion_percentage', 100)->count(), // Placeholder
            'provider_count' => $providerChecklists->count(), // Placeholder
            'providers_ready' => $providerChecklists->where('completion_percentage', 100)->count(), // Placeholder
            'pending_invitations' => $pendingInvitations,
            // 'recent_activity' => $this->getRecentOnboardingActivity($organizationId) // Placeholder
        ];
    }

    private function createChecklist(int $entityId, string $entityType, string $checklistType, array $items): void
    {
        $checklistItems = [];
        foreach ($items as $key => $description) {
            $checklistItems[$key] = [
                'description' => $description,
                'completed' => false,
                'completed_at' => null,
                'required' => true // Assuming all are required by default
            ];
        }

        OnboardingChecklist::create([
            // 'id' => Str::uuid(), // Model should handle UUID generation
            'entity_id' => $entityId,
            'entity_type' => $entityType, // Use fully qualified class name
            'checklist_type' => $checklistType,
            'items' => $checklistItems, // Eloquent handles JSON encoding
            'total_items' => count($items),
            'completed_items' => 0,
            'completion_percentage' => 0,
            'last_activity_at' => now()
        ]);
    }

    private function updateOrganizationOnboardingStatus(int $organizationId): void
    {
        $checklist = $this->getChecklistStatus('App\\Models\\Organization', $organizationId);
        if (empty($checklist) || !isset($checklist['items'])) return; // Checklist might not be created yet or is empty

        $newStatus = 'initiated'; // Default status

        // Check for basic_info_complete
        if (isset($checklist['items']['basic_information']['completed']) && $checklist['items']['basic_information']['completed'] &&
            isset($checklist['items']['tax_documentation']['completed']) && $checklist['items']['tax_documentation']['completed']) {
            $newStatus = 'basic_info_complete';
        }

        // Check for billing_setup_complete
        if ($newStatus === 'basic_info_complete' &&
            isset($checklist['items']['billing_setup']['completed']) && $checklist['items']['billing_setup']['completed']) {
            $newStatus = 'billing_setup_complete';
        }

        // Check for facilities_added
        if ($newStatus === 'billing_setup_complete' &&
            isset($checklist['items']['add_facilities']['completed']) && $checklist['items']['add_facilities']['completed']) {
            $newStatus = 'facilities_added';
        }

        // Check for providers_invited
        if ($newStatus === 'facilities_added' &&
            isset($checklist['items']['invite_providers']['completed']) && $checklist['items']['invite_providers']['completed']) {
            $newStatus = 'providers_invited';
        }

        // Check for completion
        if (isset($checklist['completion_percentage']) && $checklist['completion_percentage'] >= 100) {
            $newStatus = 'completed';
        }

        DB::table('organization_onboarding')
            ->where('organization_id', $organizationId)
            ->update([
                'status' => $newStatus,
                'completed_steps' => json_encode(collect($checklist['items'])->where('completed', true)->keys()->all()),
                'pending_items' => json_encode(collect($checklist['items'])->where('completed', false)->keys()->all()),
                'updated_at' => now()
            ]);
    }

    // Change visibility from private to public
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
     * Maps a document type string to a corresponding checklist item key.
     * Moved from CustomerManagementController to be reusable.
     */
    public function mapDocumentTypeToChecklistItem(string $documentType): ?string
    {
        $mapping = [
            // Organization specific (can be extended)
            'w9' => 'tax_documentation',
            'baa' => 'baa_agreement',

            // Provider specific (can be extended)
            'medical_license' => 'medical_license',
            'dea_registration' => 'dea_registration', // Assuming 'dea' from controller was for DEA registration doc type
            'malpractice_insurance' => 'malpractice_insurance',
            'npi_verification_document' => 'npi_verification', // If NPI verification involves a document

            // Generic or shared
            'insurance' => 'insurance_verification', // Could be org or provider malpractice etc.
        ];

        return $mapping[strtolower(trim($documentType))] ?? null;
    }

    /**
     * Initialize facility onboarding by creating its checklist.
     */
    public function initiateFacilityOnboarding(Facility $facility): void
    {
        if (empty($this->facilityChecklist)) {
            // Log or handle missing checklist definition for facilities
            // Consider throwing an exception or logging an error.
            // error_log('Facility checklist definition is missing in OnboardingService.');
            return;
        }
        // The private createChecklist method expects entityId, entityType, checklistType, and items array
        $this->createChecklist($facility->id, Facility::class, 'facility', $this->facilityChecklist);
    }

    // Placeholder for methods mentioned in the markdown but not fully defined or dependent on other parts
    // private function sendOrganizationWelcomeEmail(Organization $organization) {}
    // private function sendProviderWelcomeEmail(User $user) {}
    // private function getFacilityChecklists(int $organizationId) { return collect(); }
    // private function getProviderChecklists(int $organizationId) { return collect(); }
    // private function getRecentOnboardingActivity(int $organizationId) { return []; }
}

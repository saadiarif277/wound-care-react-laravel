<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use App\Models\Facility;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use App\Models\OnboardingDocument;
use App\Models\OnboardingChecklist;
use App\Models\ProviderInvitation;
use App\Services\OnboardingService;

class CustomerManagementService
{
    public function __construct(private OnboardingService $onboardingService) {}

    /**
     * Get organization hierarchy details.
     * This is a placeholder and needs full implementation.
     */
    public function getOrganizationHierarchy(int $organizationId): ?Organization // Return single Organization model or null
    {
        return Organization::with([
            'facilities.providers.roles', // Eager load facilities, their providers, and providers' roles
            'facilities.addresses',       // Eager load facility addresses
            'salesRep',                 // Eager load the sales rep for the organization
            'onboardingRecord',         // Eager load the organization's onboarding record
            'addresses'                 // Eager load organization's own addresses
        ])
        ->withCount(['facilities']) // Get count of facilities
        // We might need a more complex way to count total_providers and active_providers if it spans across all facilities
        // For now, these can be derived in the resource or controller from the loaded relations.
        ->find($organizationId);
    }

    /**
     * Get enhanced compliance metrics for an organization.
     * This method will require more specific logic based on how compliance is determined.
     * For now, it can aggregate data from onboarding documents and provider verifications.
     */
    public function getEnhancedComplianceMetrics(int $organizationId): array
    {
        $organization = Organization::with([
            'onboardingDocuments',
            'facilities.providers.onboardingDocuments' // Documents for providers within facilities
            // Potentially add 'facilities.providers.user.credentials' if that's where NPI/License status is stored
        ])->find($organizationId);

        if (!$organization) {
            return [
                'error' => 'Organization not found',
                'compliance_score' => 0,
                'expiring_documents_count' => 0,
                'pending_verifications_count' => 0,
                'details' => 'Organization not found.'
            ];
        }

        $expiringDocumentsCount = 0;
        $now = now();
        $thirtyDaysFromNow = now()->addDays(30);

        // Check organization documents
        foreach ($organization->onboardingDocuments as $doc) {
            if ($doc->expiration_date && Carbon::parse($doc->expiration_date)->between($now, $thirtyDaysFromNow)) {
                $expiringDocumentsCount++;
            }
        }

        // Check provider documents within facilities
        // This assumes providers are linked to User model and User model has onboardingDocuments relationship
        // or that provider-specific documents are directly on a ProviderProfile model linked to User.
        // For now, let's assume User model has morphMany onboardingDocuments for credentials.
        $pendingVerificationsCount = 0; // Example: Count users (providers) needing NPI/license verification

        // Example: Compliance score could be based on checklist completion and document approval
        $orgChecklist = $organization->onboardingChecklists()->where('checklist_type', 'organization')->first();
        $complianceScore = $orgChecklist ? $orgChecklist->completion_percentage : 0;

        // Placeholder for pending verifications (e.g., NPI, licenses for providers)
        // This would require iterating through providers and checking their verification status fields
        // or specific credential document statuses.
        // foreach ($organization->facilities as $facility) {
        //     foreach ($facility->providers as $provider) { // Assuming $provider is a User model
        //          // Check $provider->is_verified, $provider->npi_status, $provider->license_status etc.
        //     }
        // }

        return [
            'compliance_score' => (float) $complianceScore,
            'expiring_documents_count' => $expiringDocumentsCount,
            'pending_verifications_count' => $pendingVerificationsCount, // Placeholder
            'details' => 'Aggregated compliance metrics.' // Add more details as needed
        ];
    }

    /**
     * Get providers with their facility assignments.
     * Organization ID or Facility ID can be used to filter.
     */
    public function getProvidersWithFacilities(?int $organizationId, ?int $facilityId): Collection
    {
        $query = User::query()->whereHas('roles', fn($q) => $q->where('slug', 'provider')); // Assuming Spatie roles

        if ($facilityId) {
            $query->whereHas('facilities', fn($q) => $q->where('facilities.id', $facilityId));
        } elseif ($organizationId) {
            // If filtering by org, ensure providers are linked to facilities within that org
            $query->whereHas('facilities', fn($q) => $q->where('organization_id', $organizationId));
        }

        // Eager load facilities for each provider to show their assignments
        // Also load other relevant info for a provider list (e.g., NPI, license status from a profile model if exists)
        return $query->with(['facilities', /* 'providerProfile' */])
            ->withCount('facilities as facility_count') // Count of facilities they are assigned to
            // ->with(['onboardingChecklists' => fn($q) => $q->where('checklist_type', 'provider')]) // Provider specific checklist
            ->get();
    }

    /**
     * Assign a provider (User model) to a facility.
     */
    public function assignProviderToFacility(array $data): bool
    {
        $provider = User::find($data['provider_id']);
        $facility = Facility::find($data['facility_id']);

        if ($provider && $facility && $provider->hasRole('provider')) { // Ensure the user is a provider
            try {
                // Ensure the facility belongs to the same organization as the provider context if applicable
                // (e.g. if an admin is assigning within a specific org they manage)

                // Data for the pivot table
                $pivotData = [
                    // 'relationship_type' => $data['relationship_type'] ?? 'contracted', // Example pivot field
                    'is_primary' => $data['is_primary'] ?? false,
                    'is_active' => $data['is_active'] ?? true, // Default to active assignment
                    // 'notes' => $data['notes'] ?? null,
                    // 'start_date' => $data['start_date'] ?? now() // from controller
                ];
                if(isset($data['start_date'])) $pivotData['start_date'] = $data['start_date'];

                $provider->facilities()->syncWithoutDetaching([$facility->id => $pivotData]);
                return true;
            } catch (\Exception $e) {
                // Log error $e->getMessage()
                return false;
            }
        }
        return false;
    }

    /**
     * Get a specific provider's facility assignments.
     */
    public function getProviderFacilityAssignments(int $providerId): Collection
    {
        $user = User::with(['facilities' => function($query) {
            $query->with('organization:id,name'); // Include facility's organization name for context
        }])->find($providerId);
        return $user ? $user->facilities : collect();
    }

    /**
     * Get facility coverage report for an organization.
     * Shows facilities and their provider counts (total, verified).
     */
    public function getFacilityCoverageReport(int $organizationId): Collection
    {
        return Facility::where('organization_id', $organizationId)
            ->withCount([
                'providers as total_providers',
                'providers as verified_providers' => function ($query) {
                    // Assuming User model has an 'is_verified' field or similar for NPI/License check
                    // This might need to join provider_profiles or check a specific credential status
                    $query->where('users.is_verified', true); // Example, adjust if verification is on a profile or documents
                }
            ])
            ->get();
    }

    /**
     * Get credentials (documents) that are expiring soon for a given organization or all.
     */
    public function getExpiringCredentials(int $days = 30, ?int $organizationId = null): Collection
    {
        $query = OnboardingDocument::query()
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<= ', now()->addDays($days)->toDateString())
            ->where('expiration_date', '>=', now()->toDateString())
            ->where('status', 'approved'); // Only interested in approved, expiring documents

        if ($organizationId) {
            // Documents directly for the organization
            $orgDocQuery = clone $query;
            $orgDocs = $orgDocQuery->where('entity_type', Organization::class)
                                   ->where('entity_id', $organizationId)
                                   ->get();

            // Documents for facilities within the organization
            $facilityIds = Facility::where('organization_id', $organizationId)->pluck('id');
            $facilityDocQuery = clone $query;
            $facilityDocs = $facilityDocQuery->where('entity_type', Facility::class)
                                            ->whereIn('entity_id', $facilityIds)
                                            ->get();

            // Documents for providers (users) linked to facilities within the organization
            $providerIds = User::whereHas('facilities', fn($q) => $q->where('organization_id', $organizationId))->pluck('users.id');
            $providerDocQuery = clone $query;
            $providerDocs = $providerDocQuery->where('entity_type', User::class)
                                            ->whereIn('entity_id', $providerIds)
                                            ->get();

            return $orgDocs->concat($facilityDocs)->concat($providerDocs)->sortBy('expiration_date');
        }

        return $query->with('entity')->orderBy('expiration_date')->get(); // Get all if no org ID
    }

    /**
     * Get providers (users) with pending verifications.
     * This needs a clear definition of what constitutes "pending verification".
     * Example: User.is_verified is false, or specific onboarding documents are 'under_review'.
     */
    public function getPendingProviderVerifications(?int $organizationId = null): Collection
    {
        $query = User::query()->whereHas('roles', fn($q) => $q->where('slug', 'provider'))
                       // Example 1: Based on a user flag
                       // ->where('is_verified', false)
                       // Example 2: Based on existence of 'under_review' or 'uploaded' critical documents
                       ->whereHas('onboardingDocuments', function($docQuery) {
                           $docQuery->whereIn('document_type', ['medical_license', 'npi_verification_document']) // Example critical docs
                                    ->whereIn('status', ['uploaded', 'under_review']);
                       });

        if ($organizationId) {
            $query->whereHas('facilities', fn($q) => $q->where('organization_id', $organizationId));
        }

        return $query->with('facilities:id,name')->get();
    }

    /**
     * Get recent customer-related activity.
     * This is broad. Could be new orgs, new providers, document uploads, status changes.
     * This might be better served by a dedicated audit log system or event sourcing.
     * For now, a simple query on a few relevant models.
     */
    public function getRecentCustomerActivity(int $limit = 20): Collection
    {
        $recentOrgs = Organization::orderBy('created_at', 'desc')->limit($limit / 2)->get()->map(function($item) {
            $item->activity_type = 'New Organization';
            $item->activity_date = $item->created_at;
            return $item;
        });

        $recentInvitations = ProviderInvitation::where('status', 'accepted')
            ->orderBy('accepted_at', 'desc')
            ->limit($limit / 2)
            ->with('createdUser:id,first_name,last_name', 'organization:id,name')
            ->get()->map(function($item) {
                $item->activity_type = 'Provider Joined';
                $item->activity_date = $item->accepted_at;
                return $item;
            });

        return $recentOrgs->concat($recentInvitations)->sortByDesc('activity_date')->take($limit);
    }

}

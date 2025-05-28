<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\CustomerManagementService;
use App\Services\OnboardingService;
use App\Services\NPIVerificationService;
use App\Http\Requests\CreateOrganizationRequest;
use App\Http\Requests\InviteProvidersRequest;
use App\Http\Requests\UploadDocumentRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Models\Facility;
use App\Models\Address;
use App\Models\User;
use App\Models\OnboardingDocument;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CustomerManagementController extends Controller
{
    public function __construct(
        private CustomerManagementService $customerService,
        private OnboardingService $onboardingService,
        private NPIVerificationService $npiService
    ) {}

    /**
     * List all organizations with filtering
     */
    public function listOrganizations(Request $request): JsonResponse
    {
        $query = Organization::query();

        // Apply filters from the request
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('tax_id', 'like', "%{$searchTerm}%");
            });
        }

        // Eager load relationships to prevent N+1 queries
        $query->with(['facilities', 'salesRep']);

        $organizations = $query->paginate($request->get('per_page', 20));

        return OrganizationResource::collection($organizations)->response();
    }

    /**
     * Create new organization and initiate onboarding
     */
    public function createOrganization(CreateOrganizationRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $validatedData = $request->validated();

            // Create organization
            $organization = Organization::create($validatedData);

            // Initiate onboarding with proper validation
            $onboardingResult = $this->onboardingService->initiateOrganizationOnboarding(
                $organization,
                Auth::id()
            );

            // Validate onboarding service response
            if (!is_array($onboardingResult) || !isset($onboardingResult['success'])) {
                Log::error('Invalid onboarding service response', ['response' => $onboardingResult]);
                throw new \Exception('Invalid response from onboarding service');
            }

            if (!$onboardingResult['success']) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Failed to initiate onboarding',
                    'message' => $onboardingResult['message'] ?? 'Unknown error'
                ], 500);
            }

            DB::commit();

            // Eager load relations for resource to prevent N+1 queries
            $organization->load(['facilities', 'salesRep']);

            return response()->json([
                'organization' => new OrganizationResource($organization),
                'onboarding' => $onboardingResult,
                'message' => 'Organization created and onboarding initiated'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create organization', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'error' => 'Failed to create organization',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get organization hierarchy with all details
     */
    public function getOrganizationHierarchy($organizationId): JsonResponse
    {
        // Eager load all needed relationships to prevent N+1 queries
        $organization = Organization::with([
            'facilities.providers' => function($query) {
                $query->select(['id', 'facility_id', 'name', 'status', 'email']);
            },
            'facilities.primaryAddress',
            'salesRep'
        ])->find($organizationId);

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // Get onboarding dashboard with validation
        try {
            $onboarding = $this->onboardingService->getOnboardingDashboard($organizationId);

            // Validate service response
            if (!is_array($onboarding)) {
                Log::error('Invalid onboarding dashboard response', ['organizationId' => $organizationId]);
                $onboarding = ['error' => 'Unable to fetch onboarding data'];
            }
        } catch (\Exception $e) {
            Log::error('Failed to get onboarding dashboard', [
                'organizationId' => $organizationId,
                'error' => $e->getMessage()
            ]);
            $onboarding = ['error' => 'Unable to fetch onboarding data'];
        }

        // Calculate counts more efficiently using collection methods
        $facilitiesCollection = $organization->facilities;
        $totalProviders = $facilitiesCollection->sum(fn($facility) => $facility->providers->count());
        $activeProviders = $facilitiesCollection->sum(fn($facility) =>
            $facility->providers->where('status', 'active')->count()
        );

        // Mocked compliance data - replace with actual service call when available
        $mockedCompliance = [
            'compliance_score' => 75.0,
            'details' => 'Compliance metrics from service'
        ];

        return response()->json([
            'organization' => new OrganizationResource($organization),
            'onboarding' => $onboarding,
            'compliance' => $mockedCompliance,
            'summary' => [
                'total_facilities' => $facilitiesCollection->count(),
                'total_providers' => $totalProviders,
                'active_providers' => $activeProviders,
                'compliance_score' => $mockedCompliance['compliance_score']
            ]
        ]);
    }

    /**
     * Add facility to organization
     */
    public function addFacility(Request $request, $organizationId): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'facility_type' => 'required|string|in:clinic,hospital_outpatient,wound_center,asc',
            'group_npi' => 'nullable|digits:10',
            'address' => 'required|array',
            'address.street' => 'required|string|max:255',
            'address.city' => 'required|string|max:255',
            'address.state' => 'required|string|size:2',
            'address.zip' => 'required|string|max:10'
        ]);

        $organization = Organization::findOrFail($organizationId);

        DB::beginTransaction();
        try {
            $facility = Facility::create([
                'organization_id' => $organizationId,
                'name' => $request->name,
                'facility_type' => $request->facility_type,
                'group_npi' => $request->group_npi,
                'status' => 'active'
            ]);

            Address::create([
                'addressable_id' => $facility->id,
                'addressable_type' => Facility::class,
                'street_1' => $request->address['street'],
                'city' => $request->address['city'],
                'state_province' => $request->address['state'],
                'postal_code' => $request->address['zip'],
                'country_code' => 'US',
                'address_type' => 'physical'
            ]);

            // NPI verification with proper error handling
            if ($request->group_npi && $this->npiService) {
                try {
                    $npiResult = $this->npiService->verifyNPI($request->group_npi);
                    if ($npiResult->valid) {
                        $facility->update(['npi_verified_at' => now()]);

                        Log::info('NPI verification successful', [
                            'npi' => $request->group_npi,
                            'facility_id' => $facility->id,
                            'provider_name' => $npiResult->getPrimaryName(),
                            'from_cache' => $npiResult->fromCache
                        ]);
                    } else {
                        Log::warning('NPI verification failed', [
                            'npi' => $request->group_npi,
                            'facility_id' => $facility->id,
                            'error' => $npiResult->error
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('NPI verification failed with exception', [
                        'npi' => $request->group_npi,
                        'facility_id' => $facility->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Initiate facility onboarding with validation
            try {
                $this->onboardingService->initiateFacilityOnboarding($facility);

                // If no exception thrown, assume success
                $this->onboardingService->updateOnboardingProgress(
                    Organization::class,
                    $organizationId,
                    'add_facilities',
                    true
                );
            } catch (\Exception $e) {
                Log::error('Failed to initiate facility onboarding', [
                    'facility_id' => $facility->id,
                    'error' => $e->getMessage()
                ]);
            }

            DB::commit();

            // Load address relationship for response
            $facility->load('primaryAddress');

            return response()->json([
                'facility' => $facility,
                'message' => 'Facility added successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add facility', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to add facility',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Invite providers to organization
     */
    public function inviteProviders(InviteProvidersRequest $request, $organizationId): JsonResponse
    {
        $organization = Organization::findOrFail($organizationId);

        try {
            $results = $this->onboardingService->inviteProviders(
                $request->validated()['providers'],
                $organizationId,
                Auth::id()
            );

            // Validate service response
            if (!is_array($results) || !isset($results['sent'], $results['failed'])) {
                Log::error('Invalid invite providers response', ['results' => $results]);
                throw new \Exception('Invalid response from invite service');
            }

            if ($results['sent'] > 0) {
                $this->onboardingService->updateOnboardingProgress(
                    Organization::class,
                    $organizationId,
                    'invite_providers',
                    true
                );
            }

            return response()->json([
                'results' => $results,
                'message' => "{$results['sent']} invitations sent, {$results['failed']} failed."
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to invite providers', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to invite providers',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload onboarding document with enhanced security
     */
    public function uploadDocument(UploadDocumentRequest $request): JsonResponse
    {
        $entityTypeClass = match ($request->entity_type) {
            'organization' => Organization::class,
            'facility' => Facility::class,
            'user' => User::class,
            default => throw new \InvalidArgumentException('Invalid entity type specified.'),
        };

        // Validate entity exists
        $entity = $entityTypeClass::findOrFail($request->entity_id);

        DB::beginTransaction();
        try {
            $file = $request->file('document');

            // Generate secure filename and path
            $secureFilename = $this->generateSecureFilename($file);
            $storagePath = $this->getDocumentStoragePath($request->entity_type, $request->entity_id);

            // Store in Supabase S3 (private access by default)
            $path = Storage::disk('supabase')->putFileAs(
                $storagePath,
                $file,
                $secureFilename
            );

            if (!$path) {
                throw new \Exception('Failed to store file in Supabase storage');
            }

            // Use Eloquent model instead of raw DB query
            $document = OnboardingDocument::create([
                'entity_id' => $request->entity_id,
                'entity_type' => $entityTypeClass,
                'document_type' => $request->document_type,
                'document_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'status' => 'uploaded',
                'uploaded_by' => Auth::id(),
            ]);

            // Update onboarding progress with validation
            try {
                $checklistItemKey = $this->onboardingService->mapDocumentTypeToChecklistItem($request->document_type);
                if ($checklistItemKey) {
                    $this->onboardingService->updateOnboardingProgress(
                        $entityTypeClass,
                        $request->entity_id,
                        $checklistItemKey,
                        true
                    );
                }
            } catch (\Exception $e) {
                Log::warning('Failed to update onboarding progress after document upload', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage()
                ]);
            }

            DB::commit();

            Log::info('Document uploaded successfully to Supabase S3', [
                'document_id' => $document->id,
                'entity_type' => $request->entity_type,
                'entity_id' => $request->entity_id,
                'uploaded_by' => Auth::id(),
                'file_path' => $path
            ]);

            return response()->json([
                'message' => 'Document uploaded successfully',
                'document_id' => $document->id,
                'document_name' => $document->document_name
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up file if it was stored but database operation failed
            if (isset($path) && Storage::disk('supabase')->exists($path)) {
                Storage::disk('supabase')->delete($path);
            }

            Log::error('Failed to upload document to Supabase S3', [
                'entity_type' => $request->entity_type,
                'entity_id' => $request->entity_id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'error' => 'Failed to upload document',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a secure filename for uploaded documents
     */
    private function generateSecureFilename($file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = time();
        $randomString = substr(md5(uniqid(rand(), true)), 0, 8);

        // Clean original filename for logging purposes
        $cleanOriginalName = preg_replace('/[^a-zA-Z0-9_-]/', '_',
            pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
        );

        return "{$timestamp}_{$randomString}_{$cleanOriginalName}.{$extension}";
    }

    /**
     * Get the storage path for documents
     */
    private function getDocumentStoragePath(string $entityType, string $entityId): string
    {
        return "onboarding-documents/{$entityType}/{$entityId}";
    }

    /**
     * Get onboarding status for organization
     */
    public function getOnboardingStatus($organizationId): JsonResponse
    {
        $organization = Organization::findOrFail($organizationId);

        try {
            $dashboard = $this->onboardingService->getOnboardingDashboard($organizationId);

            // Validate service response
            if (!is_array($dashboard)) {
                Log::error('Invalid onboarding dashboard response', ['organizationId' => $organizationId]);
                throw new \Exception('Invalid response from onboarding service');
            }

            return response()->json($dashboard);
        } catch (\Exception $e) {
            Log::error('Failed to get onboarding status', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to retrieve onboarding status',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\CustomerManagementService; // Assuming this service will be created
use App\Services\OnboardingService;
use App\Services\NPIVerificationService; // Assuming this service will be created
use App\Http\Requests\CreateOrganizationRequest; // Assuming this form request will be created
use App\Http\Requests\InviteProvidersRequest; // Assuming this form request will be created
use App\Http\Resources\OrganizationResource; // Assuming this resource will be created
// use App\Http\Resources\ProviderResource; // Assuming this resource will be created (commented out as not used in provided snippet)
use App\Models\Organization; // Assuming this model exists
use App\Models\Facility; // Assuming this model exists
use App\Models\Address; // Assuming this model exists
use App\Models\User; // Assuming this model exists
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        $query = Organization::query(); // Use query() for clarity

        // Apply filters from the request
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('tax_id', 'like', "%{$searchTerm}%"); // Assuming Organization model has tax_id
            });
        }

        // Add relationships if needed for the resource, e.g., facilities, salesRep
        // $query->with(['facilities', 'salesRep']); // Example from markdown

        $organizations = $query->paginate($request->get('per_page', 20));

        return OrganizationResource::collection($organizations)->response();
        // The markdown shows a custom JSON structure, let's match that.
        // return response()->json([
        //     'organizations' => OrganizationResource::collection($organizations),
        //     'meta' => [
        //         'total' => $organizations->total(),
        //         'per_page' => $organizations->perPage(),
        //         'current_page' => $organizations->currentPage(),
        //         'last_page' => $organizations->lastPage(), // Good to include
        //         'from' => $organizations->firstItem(), // Good to include
        //         'to' => $organizations->lastItem(), // Good to include
        //     ]
        // ]);
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

            // Initiate onboarding
            $onboardingResult = $this->onboardingService->initiateOrganizationOnboarding(
                $organization,
                auth()->id() // Assuming authenticated user ID is the manager
            );

            if (!$onboardingResult['success']) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Failed to initiate onboarding',
                    'message' => $onboardingResult['message']
                ], 500);
            }

            // Create primary contact user - Placeholder, assuming structure of primary_contact
            // if ($request->has('primary_contact')) {
            //     // $this->createPrimaryContact($organization, $request->primary_contact);
            // }

            DB::commit();

            return response()->json([
                'organization' => new OrganizationResource($organization->loadMissing('facilities', 'salesRep')), // Eager load relations for resource
                'onboarding' => $onboardingResult,
                'message' => 'Organization created and onboarding initiated'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
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
        // $hierarchy = $this->customerService->getOrganizationHierarchy($organizationId); // Requires CustomerManagementService implementation
        // $onboarding = $this->onboardingService->getOnboardingDashboard($organizationId);
        // $compliance = $this->customerService->getEnhancedComplianceMetrics($organizationId); // Requires CustomerManagementService implementation

        // Placeholder response until services are fully implemented
        $organization = Organization::with(['facilities.providers'])->find($organizationId); // Basic hierarchy example
        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }
        $onboarding = $this->onboardingService->getOnboardingDashboard($organizationId);

        // Mocked data to match structure from markdown
        $mockedCompliance = [
            'compliance_score' => 75.0, // Example
            'details' => 'Mocked compliance data'
        ];

        return response()->json([
            // 'organization' => $hierarchy->first(), // Assuming $hierarchy is a collection
            'organization' => new OrganizationResource($organization), // Use resource for consistency
            'onboarding' => $onboarding,
            'compliance' => $mockedCompliance, // $compliance,
            'summary' => [
                'total_facilities' => $organization->facilities->count(),
                // 'total_providers' => $hierarchy->first()->total_providers, // from service
                // 'active_providers' => $hierarchy->first()->facilities->sum('provider_count'), // from service
                'total_providers' => $organization->facilities->reduce(fn($carry, $facility) => $carry + $facility->providers->count(), 0),
                'active_providers' => $organization->facilities->reduce(fn($carry, $facility) => $carry + $facility->providers->where('status', 'active')->count(), 0), // Example, needs status on provider
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

        $organization = Organization::find($organizationId);
        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

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

            if ($request->group_npi && $this->npiService) {
                $npiResult = $this->npiService->verifyNPI($request->group_npi);
                if (isset($npiResult['valid']) && $npiResult['valid']) {
                    $facility->update(['npi_verified_at' => now()]);
                }
            }

            // Use the new method from OnboardingService
            $this->onboardingService->initiateFacilityOnboarding($facility);

            $this->onboardingService->updateOnboardingProgress(
                Organization::class,
                $organizationId,
                'add_facilities',
                true
            );

            DB::commit();

            return response()->json([
                'facility' => $facility->load('address'),
                'message' => 'Facility added successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
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
        $organization = Organization::find($organizationId);
        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        $results = $this->onboardingService->inviteProviders(
            $request->validated()['providers'], // Get validated providers array
            $organizationId,
            auth()->id()
        );

        if ($results['sent'] > 0) {
            $this->onboardingService->updateOnboardingProgress(
                Organization::class, // Use class name
                $organizationId,
                'invite_providers',
                true
            );
        }

        return response()->json([
            'results' => $results,
            'message' => "{$results['sent']} invitations sent, {$results['failed']} failed."
        ]);
    }

    // ... other methods from the markdown would go here ...
    // getProviders, assignProviderToFacility, getFacilityCoverageReport, getComplianceReport, uploadDocument, getOnboardingStatus
    // These will require CustomerManagementService to be fleshed out and potentially other models/resources.

    /**
     * Upload onboarding document
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|string|in:organization,facility,user', // Match morph map keys or specific model names
            'entity_id' => 'required', // ID can be int or uuid depending on model
            'document_type' => 'required|string',
            'document' => 'required|file|max:10240' // 10MB max
        ]);

        $entityTypeClass = match ($request->entity_type) {
            'organization' => Organization::class,
            'facility' => Facility::class,
            'user' => User::class,
            default => null,
        };

        if (!$entityTypeClass) {
            return response()->json(['error' => 'Invalid entity type specified.'], 400);
        }

        // Validate entity_id exists for the given type
        $entity = $entityTypeClass::find($request->entity_id);
        if (!$entity) {
             return response()->json(['error' => 'Entity not found for the given ID and type.'], 404);
        }

        $file = $request->file('document');
        // Example storage path, customize as needed, e.g., using S3
        $path = $file->store('onboarding-documents/' . $request->entity_type, 'public');

        // Create onboarding document record - Assuming OnboardingDocument model will be created
        // DB::table('onboarding_documents')->insert([...]) from markdown is okay, but Eloquent model is better
        // OnboardingDocument::create([...]);

        // Placeholder for OnboardingDocument model creation
        $documentId = Str::uuid();
        DB::table('onboarding_documents')->insert([
            'id' => $documentId,
            'entity_id' => $request->entity_id,
            'entity_type' => $entityTypeClass, // Store FQCN for morphs
            'document_type' => $request->document_type,
            'document_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'status' => 'uploaded',
            'uploaded_by' => auth()->id(), // Placeholder for user ID relation
            'created_at' => now(),
            'updated_at' => now()
        ]);


        $checklistItemKey = $this->onboardingService->mapDocumentTypeToChecklistItem($request->document_type);
        if ($checklistItemKey) {
            $this->onboardingService->updateOnboardingProgress(
                $entityTypeClass,
                $request->entity_id,
                $checklistItemKey,
                true
            );
        }

        return response()->json([
            'message' => 'Document uploaded successfully',
            'path' => $path,
            'document_id' => $documentId
        ], 201);
    }

    /**
     * Get onboarding status for organization
     */
    public function getOnboardingStatus($organizationId): JsonResponse
    {
        $organization = Organization::find($organizationId);
         if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }
        $dashboard = $this->onboardingService->getOnboardingDashboard($organizationId);
        return response()->json($dashboard);
    }

    // Placeholder for createPrimaryContact, if needed as a separate method
    // private function createPrimaryContact(Organization $organization, array $contactData) {}

    // Placeholder for other methods from markdown like getProviders, assignProviderToFacility etc.
    // These will heavily depend on CustomerManagementService and other models.
}

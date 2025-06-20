<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\QuickRequestDocuSealIntegration;
use App\Models\Order\Product;
use App\Models\Order\ProductRequest;
use App\Models\PatientIVRStatus;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\Fhir\Facility;
use App\Services\PatientService;
use App\Services\PayerService;
use App\Services\PhiAuditService;
use App\Services\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Services\DocusealService;

class QuickRequestController extends Controller
{
    use QuickRequestDocuSealIntegration;

    protected $patientService;
    protected $payerService;
    protected $currentOrganization;

    public function __construct(PatientService $patientService, PayerService $payerService, CurrentOrganization $currentOrganization)
    {
        $this->patientService = $patientService;
        $this->payerService = $payerService;
        $this->currentOrganization = $currentOrganization;
    }

    /**
     * Display the quick request form
     */
    public function create()
    {
        $user = Auth::user()->load([
            'roles',
            'providerProfile',
            'providerCredentials',
            'organizations' => fn($q) => $q->where('organization_users.is_active', true),
            'facilities'
        ]);

        $currentOrg = $user->organizations->first();

        // Set the current organization for the scope filtering
        if ($currentOrg) {
            $this->currentOrganization->setId($currentOrg->id);
        }

        $primaryFacility = $user->facilities()->where('facility_user.is_primary', true)->first() ?? $user->facilities->first();

        // Get all providers in the organization
        $providers = [];

        // Add current user as provider if they have provider role
        $userRole = $user->getPrimaryRole()?->slug ?? $user->roles->first()?->slug;
        if ($userRole === 'provider') {
            $providers[] = [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'credentials' => $user->providerProfile?->credentials ?? $user->provider_credentials ?? null,
                'npi' => $user->npi_number ?? $user->providerCredentials->where('credential_type', 'npi_number')->first()?->credential_number ?? null,
            ];
        }

        // Add other providers from organization
        if ($currentOrg) {
            $orgProviders = \App\Models\User::whereHas('organizations', function($q) use ($currentOrg) {
                    $q->where('organizations.id', $currentOrg->id);
                })
                ->whereHas('roles', function($q) {
                    $q->where('slug', 'provider');
                })
                ->where('id', '!=', $user->id) // Exclude current user to avoid duplicates
                ->get(['id', 'first_name', 'last_name', 'npi_number'])
                ->map(function($provider) {
                    return [
                        'id' => $provider->id,
                        'name' => $provider->first_name . ' ' . $provider->last_name,
                        'credentials' => $provider->providerProfile?->credentials ?? $provider->provider_credentials ?? null,
                        'npi' => $provider->npi_number,
                    ];
                })
                ->toArray();

            $providers = array_merge($providers, $orgProviders);
        }

        // Get facilities - temporarily bypass organization scope for debugging
        $facilities = collect();

        // First try user's direct facilities (many-to-many relationship)
        $userFacilities = $user->facilities->map(function($facility) {
            return [
                'id' => $facility->id,
                'name' => $facility->name,
                'address' => $facility->full_address,
                'source' => 'user_relationship'
            ];
        });

        // Also get all facilities without scope to see what's available
        $allFacilities = \App\Models\Fhir\Facility::withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class)
            ->where('active', true)
            ->take(10)
            ->get()
            ->map(function($facility) {
                return [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'address' => $facility->full_address ?? 'No address',
                    'organization_id' => $facility->organization_id,
                    'source' => 'all_facilities'
                ];
            });

        // Use user facilities if available, otherwise use all facilities for now
        $facilities = $userFacilities->count() > 0 ? $userFacilities : $allFacilities;

        // Debug log facilities
        Log::info('QuickRequest facilities debug', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'current_org_id' => $currentOrg?->id,
            'user_facilities_count' => $userFacilities->count(),
            'all_facilities_count' => $allFacilities->count(),
            'final_facilities_count' => $facilities->count(),
            'user_facilities' => $userFacilities->toArray(),
            'all_facilities' => $allFacilities->toArray(),
        ]);

        // Get products with proper structure
        $products = Product::where('is_active', true)
            ->whereNotNull('manufacturer_id')
            ->get()
            ->map(function($product) {
                // Handle available_sizes which might be JSON string or array
                $sizes = $product->available_sizes;
                if (is_string($sizes)) {
                    $sizes = json_decode($sizes, true) ?? [];
                } elseif (!is_array($sizes)) {
                    $sizes = [];
                }

                return [
                    'id' => $product->id,
                    'code' => $product->q_code,
                    'name' => $product->name,
                    'manufacturer' => $product->manufacturer,
                    'available_sizes' => $sizes,
                    'price_per_sq_cm' => $product->price_per_sq_cm ?? 0,
                ];
            });

        // Load wound types from database
        $woundTypes = DB::table('wound_types')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('display_name', 'code')
            ->toArray();

        // Insurance carriers - you might want to get this from a config or database
        $insuranceCarriers = $this->payerService->getAllPayers()
            ->pluck('name')
            ->unique()
            ->values()
            ->toArray();

        // Load all diagnosis codes for initial display
        // Frontend will make API calls to get specific codes by wound type
        $diagnosisCodes = DB::table('diagnosis_codes')
            ->where('is_active', true)
            ->select(['code', 'description', 'category'])
            ->orderBy('category')
            ->orderBy('code')
            ->get()
            ->groupBy('category')
            ->map(function ($group) {
                return $group->map(function ($item) {
                    return [
                        'code' => $item->code,
                        'description' => $item->description
                    ];
                })->values()->toArray();
            })
            ->toArray();

        // Current user data
        $currentUser = [
            'id' => $user->id,
            'name' => $user->first_name . ' ' . $user->last_name,
            'npi' => $user->npi_number ?? $user->providerCredentials->where('credential_type', 'npi_number')->first()?->credential_number ?? null,
            'role' => $user->getPrimaryRole()?->slug ?? $user->roles->first()?->slug,
            'organization' => $currentOrg ? [
                'id' => $currentOrg->id,
                'name' => $currentOrg->name,
                'address' => $currentOrg->billing_address,
                'phone' => $currentOrg->phone,
            ] : null,
        ];

        // Provider products mapping - load from actual provider-product relationships
        $providerProducts = [];

        // Get provider products for all providers (this should come from a provider_products table or similar)
        $providers = collect($providers);
        foreach ($providers as $provider) {
            // For now, leave empty - this should be populated from actual onboarding data
            $providerProducts[$provider['id']] = [];
        }

        return Inertia::render('QuickRequest/CreateNew', [
            'facilities' => $facilities,
            'providers' => $providers,
            'products' => $products,
            'woundTypes' => $woundTypes,
            'insuranceCarriers' => $insuranceCarriers,
            'diagnosisCodes' => $diagnosisCodes,
            'currentUser' => $currentUser,
            'providerProducts' => $providerProducts,
        ]);
    }

    /**
     * Store a new quick request
     * ASHLEY'S REQUIREMENT: Validate that IVR was completed by provider
     */
    public function store(Request $request)
    {
        // ASHLEY'S REQUIREMENT: Validate that IVR was completed by provider
        $validated = $request->validate([
            // Context & Request Type
            'request_type' => 'required|in:new_request,reverification,additional_applications',
            'provider_id' => 'required|exists:users,id',
            'facility_id' => 'required|exists:facilities,id',
            'sales_rep_id' => 'nullable|string',

            // Patient Information
            'patient_first_name' => 'required|string|max:255',
            'patient_last_name' => 'required|string|max:255',
            'patient_dob' => 'required|date',
            'patient_gender' => 'nullable|in:male,female,other,unknown',
            'patient_member_id' => 'nullable|string|max:255',
            'patient_address_line1' => 'nullable|string|max:255',
            'patient_address_line2' => 'nullable|string|max:255',
            'patient_city' => 'nullable|string|max:255',
            'patient_state' => 'nullable|string|max:2',
            'patient_zip' => 'nullable|string|max:10',
            'patient_phone' => 'nullable|string|max:20',
            'patient_email' => 'nullable|email|max:255',
            'patient_is_subscriber' => 'required|boolean',

            // Caregiver (if not subscriber)
            'caregiver_name' => 'nullable|string|max:255',
            'caregiver_relationship' => 'nullable|string|max:255',
            'caregiver_phone' => 'nullable|string|max:20',

            // Service & Shipping
            'expected_service_date' => 'required|date|after:today',
            'shipping_speed' => 'required|string|max:50',
            'delivery_date' => 'nullable|date',

            // Primary Insurance
            'primary_insurance_name' => 'required|string|max:255',
            'primary_member_id' => 'required|string|max:255',
            'primary_payer_phone' => 'nullable|string|max:20',
            'primary_plan_type' => 'required|string|max:50',

            // Secondary Insurance
            'has_secondary_insurance' => 'required|boolean',
            'secondary_insurance_name' => 'nullable|string|max:255',
            'secondary_member_id' => 'nullable|string|max:255',
            'secondary_subscriber_name' => 'nullable|string|max:255',
            'secondary_subscriber_dob' => 'nullable|date',
            'secondary_payer_phone' => 'nullable|string|max:20',
            'secondary_plan_type' => 'nullable|string|max:50',

            // Prior Authorization
            'prior_auth_permission' => 'required|boolean',

            // Clinical Information
            'wound_types' => 'required|array|min:1',
            'wound_other_specify' => 'nullable|string|max:255',
            'wound_location' => 'required|string|max:255',
            'wound_location_details' => 'nullable|string|max:255',
            'yellow_diagnosis_code' => 'nullable|string|max:20',
            'orange_diagnosis_code' => 'nullable|string|max:20',
            'wound_size_length' => 'required|numeric|min:0.1|max:100',
            'wound_size_width' => 'required|numeric|min:0.1|max:100',
            'wound_size_depth' => 'nullable|numeric|min:0|max:100',
            'wound_duration' => 'nullable|string|max:255',
            'previous_treatments' => 'nullable|string|max:1000',

            // Procedure Information
            'application_cpt_codes' => 'required|array|min:1',
            'prior_applications' => 'nullable|string|max:20',
            'anticipated_applications' => 'nullable|string|max:20',

            // Billing Status
            'place_of_service' => 'required|string|max:10',
            'medicare_part_b_authorized' => 'nullable|boolean',
            'snf_days' => 'nullable|string|max:10',
            'hospice_status' => 'nullable|boolean',
            'part_a_status' => 'nullable|boolean',
            'global_period_status' => 'nullable|boolean',
            'global_period_cpt' => 'nullable|string|max:10',
            'global_period_surgery_date' => 'nullable|date',

            // Product Selection (now supports multiple)
            'selected_products' => 'required|array|min:1',
            'selected_products.*.product_id' => 'required|exists:products,id',
            'selected_products.*.quantity' => 'required|integer|min:1|max:100',
            'selected_products.*.size' => 'nullable|string|max:50',

            // Manufacturer Fields
            'manufacturer_fields' => 'nullable|array',

            // Clinical Attestations
            'failed_conservative_treatment' => 'required|boolean',
            'information_accurate' => 'required|boolean',
            'medical_necessity_established' => 'required|boolean',
            'maintain_documentation' => 'required|boolean',
            'authorize_prior_auth' => 'nullable|boolean',

            // Provider Authorization
            'provider_name' => 'nullable|string|max:255',
            'provider_npi' => 'nullable|string|max:20',
            'signature_date' => 'nullable|date',
            'verbal_order' => 'nullable|array',

            // ASHLEY'S REQUIREMENT: IVR must be completed before submission
            'docuseal_submission_id' => 'required|string|min:1',

            // Episode ID from Step7
            'episode_id' => 'required|uuid|exists:patient_manufacturer_ivr_episodes,id',

            // File uploads
            'insurance_card_front' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'insurance_card_back' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'face_sheet' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'clinical_notes' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'wound_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:10240',
        ], [
            'docuseal_submission_id.required' => 'IVR completion is required before submitting your order. Please complete the IVR form in the final step.',
            'expected_service_date.after' => 'Service date must be in the future.',
            'selected_products.required' => 'Please select at least one product.',
            'facility_id.required' => 'Please select a facility.',
        ]);

        DB::beginTransaction();

        try {
            // Prepare patient data for FHIR
            $patientData = [
                'first_name' => $validated['patient_first_name'],
                'last_name' => $validated['patient_last_name'],
                'date_of_birth' => $validated['patient_dob'],
                'gender' => $validated['patient_gender'] ?? 'unknown',
                'member_id' => $validated['patient_member_id'],
                'address' => [
                    'line1' => $validated['patient_address_line1'],
                    'line2' => $validated['patient_address_line2'],
                    'city' => $validated['patient_city'],
                    'state' => $validated['patient_state'],
                    'postal_code' => $validated['patient_zip'],
                ],
                'phone' => $validated['patient_phone'],
                'email' => $validated['patient_email'] ?? null,
                'caregiver' => [
                    'name' => $validated['caregiver_name'],
                    'relationship' => $validated['caregiver_relationship'],
                    'phone' => $validated['caregiver_phone'],
                ],
            ];

            // Create patient in FHIR and get identifiers
            $patientIdentifiers = $this->patientService->createPatientRecord(
                $patientData,
                $validated['facility_id']
            );

            // Process each selected product
            $firstProduct = Product::find($validated['selected_products'][0]['product_id']);
            $manufacturerId = $firstProduct->manufacturer_id ?? $firstProduct->manufacturer;

            // ASHLEY'S REQUIREMENT: Use existing episode created in Step7
            $episode = PatientManufacturerIVREpisode::findOrFail($validated['episode_id']);

            // Update episode with patient FHIR ID if it was created with temporary data
            if ($episode->patient_fhir_id !== $patientIdentifiers['patient_fhir_id']) {
                $episode->update([
                    'patient_fhir_id' => $patientIdentifiers['patient_fhir_id'],
                    'patient_display_id' => $patientIdentifiers['patient_display_id'],
                ]);
            }

            // Create the product request with provider-generated IVR
            $productRequest = new ProductRequest();
            $productRequest->id = Str::uuid();
            $productRequest->request_number = $this->generateRequestNumber();
            $productRequest->requester_id = Auth::id();
            $productRequest->provider_id = $validated['provider_id'];
            $productRequest->facility_id = $validated['facility_id'];
            $productRequest->request_type = $validated['request_type'];

            // ASHLEY'S REQUIREMENT: Status indicates provider completed IVR
            $productRequest->order_status = 'ready_for_review'; // Not 'pending_ivr'
            $productRequest->submission_type = 'quick_request';

            // Store only patient identifiers, NOT PHI
            $productRequest->patient_fhir_id = $patientIdentifiers['patient_fhir_id'];
            $productRequest->patient_display_id = $patientIdentifiers['patient_display_id'];

            // Store insurance information
            $productRequest->payer_name = $validated['primary_insurance_name'];
            $productRequest->payer_id = $validated['primary_member_id'];
            $productRequest->insurance_type = $validated['primary_plan_type'];

            // Set service information
            $productRequest->expected_service_date = $validated['expected_service_date'];
            $productRequest->delivery_date = $validated['delivery_date'] ??
                Carbon::parse($validated['expected_service_date'])->subDay(); // Default to day before

            // Clinical information
            $productRequest->wound_type = implode(', ', $validated['wound_types']);
            $productRequest->place_of_service = $validated['place_of_service'];

            // ASHLEY'S REQUIREMENT: Store IVR completion info
            $productRequest->docuseal_submission_id = $validated['docuseal_submission_id'];
            $productRequest->provider_ivr_completed_at = now();
            $productRequest->ivr_status = 'provider_completed';

            // Store metadata
            $metadata = [
                'products' => $validated['selected_products'],
                'clinical_info' => [
                    'wound_types' => $validated['wound_types'],
                    'wound_other_specify' => $validated['wound_other_specify'],
                    'wound_location' => $validated['wound_location'],
                    'wound_location_details' => $validated['wound_location_details'],
                    'diagnosis_codes' => [
                        'yellow' => $validated['yellow_diagnosis_code'],
                        'orange' => $validated['orange_diagnosis_code'],
                    ],
                    'wound_measurements' => [
                        'length' => $validated['wound_size_length'],
                        'width' => $validated['wound_size_width'],
                        'depth' => $validated['wound_size_depth'],
                    ],
                    'wound_duration' => $validated['wound_duration'],
                    'previous_treatments' => $validated['previous_treatments'],
                    'cpt_codes' => $validated['application_cpt_codes'],
                    'prior_applications' => $validated['prior_applications'],
                    'anticipated_applications' => $validated['anticipated_applications'],
                ],
                'billing_info' => [
                    'medicare_part_b_authorized' => $validated['medicare_part_b_authorized'],
                    'snf_days' => $validated['snf_days'],
                    'hospice_status' => $validated['hospice_status'],
                    'part_a_status' => $validated['part_a_status'],
                    'global_period_status' => $validated['global_period_status'],
                    'global_period_cpt' => $validated['global_period_cpt'],
                    'global_period_surgery_date' => $validated['global_period_surgery_date'],
                ],
                'insurance_info' => [
                    'primary' => [
                        'name' => $validated['primary_insurance_name'],
                        'member_id' => $validated['primary_member_id'],
                        'plan_type' => $validated['primary_plan_type'],
                        'payer_phone' => $validated['primary_payer_phone'],
                    ],
                    'has_secondary' => $validated['has_secondary_insurance'],
                    'secondary' => $validated['has_secondary_insurance'] ? [
                        'name' => $validated['secondary_insurance_name'],
                        'member_id' => $validated['secondary_member_id'],
                        'plan_type' => $validated['secondary_plan_type'],
                        'subscriber_name' => $validated['secondary_subscriber_name'],
                        'subscriber_dob' => $validated['secondary_subscriber_dob'],
                        'payer_phone' => $validated['secondary_payer_phone'],
                    ] : null,
                    'prior_auth_permission' => $validated['prior_auth_permission'],
                ],
                'manufacturer_fields' => $validated['manufacturer_fields'] ?? [],
                'shipping_speed' => $validated['shipping_speed'],
                'attestations' => [
                    'failed_conservative_treatment' => $validated['failed_conservative_treatment'],
                    'information_accurate' => $validated['information_accurate'],
                    'medical_necessity_established' => $validated['medical_necessity_established'],
                    'maintain_documentation' => $validated['maintain_documentation'],
                    'authorize_prior_auth' => $validated['authorize_prior_auth'] ?? false,
                ],
                'provider_authorization' => [
                    'provider_name' => $validated['provider_name'] ?? Auth::user()->first_name . ' ' . Auth::user()->last_name,
                    'provider_npi' => $validated['provider_npi'] ?? Auth::user()->npi_number,
                    'signature_date' => $validated['signature_date'] ?? now()->format('Y-m-d'),
                    'verbal_order' => $validated['verbal_order'] ?? null,
                ],
                'ivr_submission' => [
                    'docuseal_submission_id' => $validated['docuseal_submission_id'],
                    'completed_at' => now(),
                    'completed_by' => Auth::id(),
                ],
            ];

            // Handle file uploads with PHI protection
            $documentMetadata = [];
            $documentTypes = [
                'insurance_card_front' => 'phi/insurance-cards/',
                'insurance_card_back' => 'phi/insurance-cards/',
                'face_sheet' => 'phi/face-sheets/',
                'clinical_notes' => 'phi/clinical-notes/',
                'wound_photo' => 'phi/wound-photos/',
            ];

            foreach ($documentTypes as $fieldName => $storagePath) {
                if ($request->hasFile($fieldName)) {
                    $path = $request->file($fieldName)->store($storagePath . date('Y/m'), 's3-encrypted');
                    $documentMetadata[$fieldName] = [
                        'path' => $path,
                        'uploaded_at' => now(),
                        'size' => $request->file($fieldName)->getSize(),
                        'mime_type' => $request->file($fieldName)->getMimeType()
                    ];

                    // Audit PHI document upload
                    PhiAuditService::logCreation('Document', $path, [
                        'document_type' => $fieldName,
                        'patient_fhir_id' => $patientIdentifiers['patient_fhir_id'],
                        'product_request_id' => $productRequest->id
                    ]);
                }
            }

            $metadata['documents'] = $documentMetadata;
            $productRequest->metadata = $metadata;

            // Set primary product info (for backwards compatibility)
            $productRequest->product_id = $firstProduct->id;
            $productRequest->product_name = $firstProduct->name;
            $productRequest->product_code = $firstProduct->q_code;
            $productRequest->manufacturer = $firstProduct->manufacturer;
            $productRequest->size = $validated['selected_products'][0]['size'] ?? '';
            $productRequest->quantity = $validated['selected_products'][0]['quantity'];

            // Link to episode
            $productRequest->ivr_episode_id = $episode->id;

            $productRequest->save();

            // Update episode status
            $episode->update([
                'status' => 'ready_for_review',
                'ivr_status' => 'provider_completed',
                'last_order_date' => now(),
            ]);

            DB::commit();

            // Store episode ID in session for the frontend to use
            session()->flash('episode_id', $episode->id);

            return redirect()->route('admin.episodes.show', $episode->id)
                ->with('success', 'Order submitted successfully with IVR completed! Your order is now ready for admin review.')
                ->with('episode_id', $episode->id);

        } catch (\Exception $e) {
            DB::rollBack();

            \Illuminate\Support\Facades\Log::error('Failed to submit quick request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to submit order: ' . $e->getMessage()]);
        }
    }

    /**
     * ASHLEY'S REQUIREMENT: Find or create episode for patient+manufacturer combination
     */
    private function findOrCreateEpisode($patientFhirId, $manufacturerId, $patientDisplayId, $docusealSubmissionId = null)
    {
        // First check if there's a temporary episode created for IVR
        $tempEpisode = PatientIVRStatus::where('patient_display_id', $patientDisplayId)
            ->where('manufacturer_id', $manufacturerId)
            ->where('patient_id', 'LIKE', 'TEMP_%')
            ->where('status', 'pending_ivr')
            ->first();

        if ($tempEpisode) {
            // Update the temporary episode with real patient data
            $tempEpisode->update([
                'patient_id' => $patientFhirId,
                'status' => 'ready_for_review',
                'ivr_status' => 'provider_completed',
                'docuseal_submission_id' => $docusealSubmissionId,
                'verification_date' => now(),
                'expiration_date' => now()->addMonths(3),
            ]);
            return $tempEpisode;
        }

        // Find existing episode for this patient+manufacturer combination
        $episode = PatientIVRStatus::where('patient_id', $patientFhirId)
            ->where('manufacturer_id', $manufacturerId)
            ->where(function($q) {
                $q->whereNull('expiration_date')
                  ->orWhere('expiration_date', '>', now());
            })
            ->first();

        if (!$episode) {
            $episode = PatientIVRStatus::create([
                'id' => Str::uuid(),
                'patient_id' => $patientFhirId, // FHIR ID for patient
                'patient_display_id' => $patientDisplayId, // De-identified display ID
                'manufacturer_id' => $manufacturerId,
                'status' => 'ready_for_review', // Provider submitted with IVR
                'ivr_status' => 'provider_completed',
                'docuseal_submission_id' => $docusealSubmissionId, // Store DocuSeal ID
                'verification_date' => now(),
                'expiration_date' => now()->addMonths(3), // Default 3-month expiration
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // Update existing episode with new DocuSeal submission if provided
            if ($docusealSubmissionId && !$episode->docuseal_submission_id) {
                $episode->update([
                    'docuseal_submission_id' => $docusealSubmissionId,
                    'ivr_status' => 'provider_completed',
                    'verification_date' => now(),
                ]);
            }
        }

        return $episode;
    }

    /**
     * Generate a unique request number
     */
    private function generateRequestNumber()
    {
        $prefix = 'QR';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(4));

        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Create episode for DocuSeal integration after product selection
     */
    public function createEpisodeForDocuSeal(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|string',
            'patient_fhir_id' => 'required|string',
            'patient_display_id' => 'required|string',
            'manufacturer_id' => 'required|exists:manufacturers,id',
            'form_data' => 'required|array',
        ]);

        try {
            // Create or find episode
            $episode = PatientManufacturerIVREpisode::firstOrCreate([
                'patient_fhir_id' => $validated['patient_fhir_id'],
                'manufacturer_id' => $validated['manufacturer_id'],
                'status' => '!=' . PatientManufacturerIVREpisode::STATUS_COMPLETED,
            ], [
                'patient_display_id' => $validated['patient_display_id'],
                'status' => PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW,
                'metadata' => [
                    'facility_id' => $validated['form_data']['facility_id'] ?? null,
                    'provider_id' => Auth::id(),
                    'created_from' => 'quick_request',
                    'form_data' => $validated['form_data']
                ]
            ]);

            Log::info('Created episode for QuickRequest DocuSeal', [
                'episode_id' => $episode->id,
                'patient_display_id' => $validated['patient_display_id'],
                'manufacturer_id' => $validated['manufacturer_id'],
            ]);

            return response()->json([
                'success' => true,
                'episode_id' => $episode->id,
                'manufacturer_id' => $validated['manufacturer_id']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create episode for QuickRequest DocuSeal', [
                'error' => $e->getMessage(),
                'patient_display_id' => $validated['patient_display_id'],
                'manufacturer_id' => $validated['manufacturer_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create episode: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate JWT token for DocuSeal builder
     */
    public function generateBuilderToken(Request $request)
    {
        try {
            $data = $request->validate([
                'user_email' => 'required|email',
                'integration_email' => 'nullable|email',
                'template_id' => 'nullable|string',
                'template_name' => 'nullable|string',
                'document_urls' => 'nullable|array',
                'prefill_data' => 'nullable|array',
            ]);

            // Get DocuSeal API key
            $apiKey = config('docuseal.api_key');
            if (!$apiKey) {
                throw new \Exception('DocuSeal API key not configured');
            }

            // Prepare JWT payload
            $payload = [
                'user_email' => $data['user_email'],
                'integration_email' => $data['integration_email'] ?? $data['user_email'],
                'iat' => time(),
                'exp' => time() + (60 * 60), // 1 hour expiration
            ];

            // Add template-specific data
            if (!empty($data['template_id'])) {
                $payload['template_id'] = intval($data['template_id']);
            }

            if (!empty($data['template_name'])) {
                $payload['name'] = $data['template_name'];
            }

            if (!empty($data['document_urls'])) {
                $payload['document_urls'] = $data['document_urls'];
            }

            // Generate JWT token using HS256
            $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
            $payload = json_encode($payload);

            $headerEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
            $payloadEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

            $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, $apiKey, true);
            $signatureEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

            $jwtToken = $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;

            return response()->json([
                'success' => true,
                'jwt_token' => $jwtToken,
                'user_email' => $data['user_email'],
                'integration_email' => $data['integration_email'] ?? $data['user_email'],
                'template_id' => $data['template_id'] ?? null,
                'template_name' => $data['template_name'] ?? 'MSC Wound Care IVR Form',
                'expires_at' => date('Y-m-d H:i:s', time() + (60 * 60))
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating DocuSeal builder token', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate builder token: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create final submission for DocuSeal (updated to support both builder and direct submission)
     */
    public function createFinalSubmission(Request $request)
    {
        try {
            $data = $request->validate([
                'template_type' => 'required|string',
                'use_builder' => 'boolean',
                'prefill_data' => 'required|array',
            ]);

            $prefillData = $data['prefill_data'];

            // Determine manufacturer and template
            $manufacturer = 'BioWound'; // Default
            $templateConfigKey = 'BioWound.default';

            if (!empty($prefillData['selected_products'])) {
                $selectedProducts = $prefillData['selected_products'];
                if (!empty($selectedProducts[0]['product_id'])) {
                    $product = \App\Models\Order\Product::find($selectedProducts[0]['product_id']);
                    if ($product) {
                        $manufacturer = $this->getManufacturerConfigKey($product->manufacturer);
                        $templateConfigKey = $manufacturer . '.default';
                    }
                }
            }

            // If using builder mode, return JWT token instead of creating submission
            if ($data['use_builder'] ?? false) {
                return $this->generateBuilderToken(new Request([
                    'user_email' => 'limitless@mscwoundcare.com',
                    'integration_email' => $prefillData['patient_email'] ?? 'limitless@mscwoundcare.com',
                    'template_id' => config("docuseal.templates.{$templateConfigKey}"),
                    'template_name' => "MSC {$manufacturer} IVR Form",
                    'document_urls' => [],
                    'prefill_data' => $prefillData
                ]));
            }

            // Original submission creation logic (for backward compatibility)
            $templateId = config("docuseal.templates.{$templateConfigKey}");
            if (!$templateId) {
                throw new \Exception("Template not configured for {$templateConfigKey}");
            }

            $docusealService = app(DocusealService::class);

            $submissionData = [
                'template_id' => $templateId,
                'send_email' => false,
                'submitters' => [
                    [
                        'role' => 'Patient',
                        'email' => config('docuseal.account_email', 'limitless@mscwoundcare.com'), // Configured account email
                        'name' => ($prefillData['patient_first_name'] ?? '') . ' ' . ($prefillData['patient_last_name'] ?? ''),
                        'values' => $this->formatPrefillValues($prefillData)
                    ]
                ]
            ];

            Log::info('Creating DocuSeal submission', [
                'template_id' => $templateId,
                'manufacturer' => $manufacturer,
                'template_config_key' => $templateConfigKey,
                'submission_data' => $submissionData
            ]);

            $response = $docusealService->createSubmission($submissionData);

            if (!$response['success']) {
                throw new \Exception($response['error'] ?? 'Failed to create submission');
            }

            $submissionId = $response['submission_id'];
            $embedUrl = "https://api.docuseal.com/s/{$submissionId}";

            return response()->json([
                'success' => true,
                'submission_id' => $submissionId,
                'embed_url' => $embedUrl,
                'template_id' => $templateId,
                'manufacturer' => $manufacturer
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating final submission', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create submission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Map manufacturer name to config key for template lookup
     */
    private function getManufacturerConfigKey($manufacturerName): string
    {
        // TODO: Create manufacturer-specific templates in DocuSeal and map them properly
        // For now, use the existing template for all manufacturers
        $manufacturerMap = [
            'ACZ & Associates' => 'ACZ.default',
            'Advanced Solution' => 'Advanced.default',
            'BioWound Solutions' => 'BioWound.default',
            'MedLife' => 'MedLife.default',
            'Skye Biologics' => 'Skye.default',
            // Add more as templates are created
        ];

        $normalizedName = trim($manufacturerName);
        return $manufacturerMap[$normalizedName] ?? 'BioWound.default'; // Fallback to BioWound template

        // Future manufacturer mapping (when templates are created):
        /*
        $manufacturerMap = [
            'ACZ & Associates' => 'ACZ.default',
            'Advanced Solution' => 'Advanced Health.default',
            'BioWound Solutions' => 'BioWound.default',
            'MedLife' => 'MedLife.default',
            // Add more as needed
        ];

        $normalizedName = trim($manufacturerName);
        return $manufacturerMap[$normalizedName] ?? 'BioWound.default';
        */
    }

    /**
     * Format prefill values for DocuSeal submission
     */
    private function formatPrefillValues($prefillData)
    {
        return $this->mapFormDataToDocuSealFields($prefillData);
    }

    /**
     * Map form data to DocuSeal field format
     */
    private function mapFormDataToDocuSealFields(array $formData): array
    {
        return [
            // Patient Information Fields
            'patient_first_name' => $formData['patient_first_name'] ?? '',
            'patient_last_name' => $formData['patient_last_name'] ?? '',
            'patient_dob' => $formData['patient_dob'] ?? '',
            'patient_gender' => $formData['patient_gender'] ?? '',
            'patient_member_id' => $formData['patient_member_id'] ?? '',
            'patient_address' => $formData['patient_address'] ?? '',
            'patient_city' => $formData['patient_city'] ?? '',
            'patient_state' => $formData['patient_state'] ?? '',
            'patient_zip' => $formData['patient_zip'] ?? '',
            'patient_phone' => $formData['patient_phone'] ?? '',
            'patient_email' => $formData['patient_email'] ?? '',

            // Provider Information Fields
            'provider_name' => $formData['provider_name'] ?? '',
            'provider_npi' => $formData['provider_npi'] ?? '',
            'facility_name' => $formData['facility_name'] ?? '',
            'facility_address' => $formData['facility_address'] ?? '',

            // Clinical Information Fields
            'wound_type' => $formData['wound_type'] ?? '',
            'wound_location' => $formData['wound_location'] ?? '',
            'wound_size' => $formData['wound_size'] ?? '',
            'wound_onset_date' => $formData['wound_onset_date'] ?? '',
            'failed_conservative_treatment' => $formData['failed_conservative_treatment'] ?? '',
            'treatment_tried' => $formData['treatment_tried'] ?? '',
            'current_dressing' => $formData['current_dressing'] ?? '',
            'expected_service_date' => $formData['expected_service_date'] ?? '',

            // Insurance Information Fields
            'primary_insurance' => $formData['primary_insurance'] ?? '',
            'primary_member_id' => $formData['primary_member_id'] ?? '',
            'primary_plan_type' => $formData['primary_plan_type'] ?? '',
            'primary_payer_phone' => $formData['primary_payer_phone'] ?? '',
            'has_secondary_insurance' => $formData['has_secondary_insurance'] ?? '',
            'secondary_insurance' => $formData['secondary_insurance'] ?? '',
            'secondary_member_id' => $formData['secondary_member_id'] ?? '',

            // Product Information Fields
            'selected_product_name' => $formData['selected_product_name'] ?? '',
            'selected_product_code' => $formData['selected_product_code'] ?? '',
            'selected_product_manufacturer' => $formData['selected_product_manufacturer'] ?? '',
            'product_quantity' => $formData['product_quantity'] ?? '',
            'product_size' => $formData['product_size'] ?? '',

            // Shipping Information Fields
            'shipping_same_as_patient' => $formData['shipping_same_as_patient'] ?? '',
            'shipping_address' => $formData['shipping_address'] ?? '',
            'shipping_city' => $formData['shipping_city'] ?? '',
            'shipping_state' => $formData['shipping_state'] ?? '',
            'shipping_zip' => $formData['shipping_zip'] ?? '',
            'delivery_notes' => $formData['delivery_notes'] ?? '',

            // Metadata Fields
            'submission_date' => $formData['submission_date'] ?? '',
            'total_wound_area' => $formData['total_wound_area'] ?? '',
        ];
    }
}

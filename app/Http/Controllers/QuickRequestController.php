<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\QuickRequestDocuSealIntegration;
use App\Models\Order\Product;
use App\Models\Order\ProductRequest;
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
use App\Models\Docuseal\DocusealTemplate;

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
        $user = $this->loadUserWithRelations();
        $currentOrg = $user->organizations->first();

        if ($currentOrg) {
            $this->currentOrganization->setId($currentOrg->id);
        }

        return Inertia::render('QuickRequest/CreateNew', [
            'facilities' => $this->getFacilitiesForUser($user, $currentOrg),
            'providers' => $this->getProvidersForUser($user, $currentOrg),
            'products' => $this->getActiveProducts(),
            'woundTypes' => $this->getWoundTypes(),
            'insuranceCarriers' => $this->getInsuranceCarriers(),
            'diagnosisCodes' => $this->getDiagnosisCodes(),
            'currentUser' => $this->getCurrentUserData($user, $currentOrg),
            'providerProducts' => [],
        ]);
    }

    private function loadUserWithRelations()
    {
        return Auth::user()->load([
            'roles',
            'providerProfile',
            'providerCredentials',
            'organizations' => fn($q) => $q->where('organization_users.is_active', true),
            'facilities'
        ]);
    }

    private function getFacilitiesForUser($user, $currentOrg)
    {
        $userFacilities = $user->facilities->map(function($facility) {
            return [
                'id' => $facility->id,
                'name' => $facility->name,
                'address' => $facility->full_address,
                'source' => 'user_relationship'
            ];
        });

        if ($userFacilities->count() > 0) {
            return $userFacilities;
        }

        return \App\Models\Fhir\Facility::withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class)
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
    }

    private function getProvidersForUser($user, $currentOrg)
    {
        $providers = [];
        $userRole = $user->getPrimaryRole()?->slug ?? $user->roles->first()?->slug;

        if ($userRole === 'provider') {
            $providers[] = [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'credentials' => $user->providerProfile?->credentials ?? $user->provider_credentials ?? null,
                'npi' => $user->npi_number ?? $user->providerCredentials->where('credential_type', 'npi_number')->first()?->credential_number ?? null,
            ];
        }

        if ($currentOrg) {
            $orgProviders = \App\Models\User::whereHas('organizations', function($q) use ($currentOrg) {
                    $q->where('organizations.id', $currentOrg->id);
                })
                ->whereHas('roles', function($q) {
                    $q->where('slug', 'provider');
                })
                ->where('id', '!=', $user->id)
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

        return $providers;
    }

    private function getActiveProducts()
    {
        return Product::where('is_active', true)
            ->get()
            ->map(function($product) {
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
                    'manufacturer_id' => $product->manufacturer_id,
                    'available_sizes' => $sizes,
                    'price_per_sq_cm' => $product->price_per_sq_cm ?? 0,
                ];
            });
    }

    private function getWoundTypes()
    {
        return DB::table('wound_types')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('display_name', 'code')
            ->toArray();
    }

    private function getInsuranceCarriers()
    {
        return $this->payerService->getAllPayers()
            ->pluck('name')
            ->unique()
            ->values()
            ->toArray();
    }

    private function getDiagnosisCodes()
    {
        return DB::table('diagnosis_codes')
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
    }

    private function getCurrentUserData($user, $currentOrg)
    {
        return [
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
    }

    /**
     * Store a new quick request
     * ASHLEY'S REQUIREMENT: Validate that IVR was completed by provider
     */
    public function store(Request $request)
    {
        $validated = $this->validateQuickRequest($request);

        DB::beginTransaction();

        try {
            $patientIdentifiers = $this->createPatientRecord($validated);
            $episode = $this->updateEpisode($validated, $patientIdentifiers);
            $productRequest = $this->createProductRequest($validated, $patientIdentifiers, $episode);
            $this->handleFileUploads($request, $productRequest);

            $productRequest->save();
            $this->updateEpisodeStatus($episode);

            DB::commit();

            session()->flash('episode_id', $episode->id);

            return redirect()->route('admin.episodes.show', $episode->id)
                ->with('success', 'Order submitted successfully with IVR completed! Your order is now ready for admin review.')
                ->with('episode_id', $episode->id);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to submit quick request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to submit order: ' . $e->getMessage()]);
        }
    }

    private function validateQuickRequest(Request $request): array
    {
        return $request->validate([
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
    }

    private function createPatientRecord(array $validated): array
    {
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

        return $this->patientService->createPatientRecord(
            $patientData,
            $validated['facility_id']
        );
    }

    private function updateEpisode(array $validated, array $patientIdentifiers): PatientManufacturerIVREpisode
    {
        $episode = PatientManufacturerIVREpisode::findOrFail($validated['episode_id']);

        if ($episode->patient_fhir_id !== $patientIdentifiers['patient_fhir_id']) {
            $episode->update([
                'patient_fhir_id' => $patientIdentifiers['patient_fhir_id'],
                'patient_display_id' => $patientIdentifiers['patient_display_id'],
            ]);
        }

        return $episode;
    }

    private function createProductRequest(array $validated, array $patientIdentifiers, PatientManufacturerIVREpisode $episode): ProductRequest
    {
        $firstProduct = Product::find($validated['selected_products'][0]['product_id']);

        $productRequest = new ProductRequest();
        $productRequest->id = Str::uuid();
        $productRequest->request_number = $this->generateRequestNumber();
        $productRequest->requester_id = Auth::id();
        $productRequest->provider_id = $validated['provider_id'];
        $productRequest->facility_id = $validated['facility_id'];
        $productRequest->request_type = $validated['request_type'];

        // ASHLEY'S REQUIREMENT: Status indicates provider completed IVR
        $productRequest->order_status = 'ready_for_review';
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
            Carbon::parse($validated['expected_service_date'])->subDay();

        // Clinical information
        $productRequest->wound_type = implode(', ', $validated['wound_types']);
        $productRequest->place_of_service = $validated['place_of_service'];

        // ASHLEY'S REQUIREMENT: Store IVR completion info
        $productRequest->docuseal_submission_id = $validated['docuseal_submission_id'];
        $productRequest->provider_ivr_completed_at = now();
        $productRequest->ivr_status = 'provider_completed';

        // Store metadata
        $productRequest->metadata = $this->buildMetadata($validated);

        // Set primary product info (for backwards compatibility)
        $productRequest->product_id = $firstProduct->id;
        $productRequest->product_name = $firstProduct->name;
        $productRequest->product_code = $firstProduct->q_code;
        $productRequest->manufacturer = $firstProduct->manufacturer;
        $productRequest->size = $validated['selected_products'][0]['size'] ?? '';
        $productRequest->quantity = $validated['selected_products'][0]['quantity'];

        // Link to episode
        $productRequest->ivr_episode_id = $episode->id;

        return $productRequest;
    }

    private function buildMetadata(array $validated): array
    {
        return [
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
    }

    private function handleFileUploads(Request $request, ProductRequest $productRequest): void
    {
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
                    'patient_fhir_id' => $productRequest->patient_fhir_id,
                    'product_request_id' => $productRequest->id
                ]);
            }
        }

        if (!empty($documentMetadata)) {
            $metadata = $productRequest->metadata;
            $metadata['documents'] = $documentMetadata;
            $productRequest->metadata = $metadata;
        }
    }

    private function updateEpisodeStatus(PatientManufacturerIVREpisode $episode): void
    {
        $episode->update([
            'status' => 'ready_for_review',
            'ivr_status' => 'provider_completed',
            'last_order_date' => now(),
        ]);
    }

    /**
     * ASHLEY'S REQUIREMENT: Find or create episode for patient+manufacturer combination
     */
    private function findOrCreateEpisode($patientFhirId, $manufacturerId, $patientDisplayId, $docusealSubmissionId = null)
    {
        // First check if there's a temporary episode created for IVR
        $tempEpisode = PatientManufacturerIVREpisode::where('patient_display_id', $patientDisplayId)
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
        $episode = PatientManufacturerIVREpisode::where('patient_id', $patientFhirId)
            ->where('manufacturer_id', $manufacturerId)
            ->where(function($q) {
                $q->whereNull('expiration_date')
                  ->orWhere('expiration_date', '>', now());
            })
            ->first();

        if (!$episode) {
            $episode = PatientManufacturerIVREpisode::create([
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
            'manufacturer_id' => 'nullable|exists:manufacturers,id',
            'selected_product_id' => 'nullable|exists:products,id',
            'form_data' => 'required|array',
        ]);

        // If manufacturer_id is not provided, try to get it from the selected product
        if (!$validated['manufacturer_id'] && $validated['selected_product_id']) {
            $product = Product::find($validated['selected_product_id']);
            if ($product && $product->manufacturer_id) {
                $validated['manufacturer_id'] = $product->manufacturer_id;
                Log::info('Retrieved manufacturer_id from product', [
                    'product_id' => $validated['selected_product_id'],
                    'manufacturer_id' => $validated['manufacturer_id']
                ]);
            }
        }

        // Ensure we have a manufacturer_id at this point
        if (!$validated['manufacturer_id']) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to determine manufacturer. Please ensure a product is selected.',
            ], 422);
        }

        try {
            // Find existing episode for this patient+manufacturer combination (not completed)
            $episode = PatientManufacturerIVREpisode::where('patient_fhir_id', $validated['patient_fhir_id'])
                ->where('manufacturer_id', $validated['manufacturer_id'])
                ->where('status', '!=', PatientManufacturerIVREpisode::STATUS_COMPLETED)
                ->first();

            if (!$episode) {
                // Create new episode if none exists
                $episode = PatientManufacturerIVREpisode::create([
                    'patient_id' => $validated['patient_id'],
                    'patient_fhir_id' => $validated['patient_fhir_id'],
                    'manufacturer_id' => $validated['manufacturer_id'],
                    'patient_display_id' => $validated['patient_display_id'],
                    'status' => PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW,
                    'metadata' => [
                        'facility_id' => $validated['form_data']['facility_id'] ?? null,
                        'provider_id' => Auth::id(),
                        'created_from' => 'quick_request',
                        'form_data' => $validated['form_data']
                    ]
                ]);
            }

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

            // Determine manufacturer and template dynamically from selected product
            $manufacturer = null;
            $manufacturerId = null;
            $templateId = null;

            // Try to determine manufacturer from form data
            if (!empty($prefillData['selected_products'])) {
                $selectedProducts = $prefillData['selected_products'];
                if (!empty($selectedProducts[0]['product_id'])) {
                    $product = Product::with('manufacturer')->find($selectedProducts[0]['product_id']);
                    if ($product && $product->manufacturer_id) {
                        $manufacturerId = $product->manufacturer_id;
                        $manufacturer = $product->manufacturer;

                        // Look up template in database based on manufacturer
                        $template = DocusealTemplate::getDefaultTemplateForManufacturer($manufacturerId, 'IVR');

                        if ($template) {
                            $templateId = $template->docuseal_template_id;
                        }
                    }
                }
            }

            // Fallback to manufacturer_id from prefill_data if not found from product
            if (!$manufacturerId && !empty($prefillData['manufacturer_id'])) {
                $manufacturerId = $prefillData['manufacturer_id'];

                // Look up manufacturer and template
                $manufacturerModel = \App\Models\Order\Manufacturer::find($manufacturerId);
                if ($manufacturerModel) {
                    $manufacturer = $manufacturerModel->name;

                    $template = \App\Models\Docuseal\DocusealTemplate::getDefaultTemplateForManufacturer($manufacturerId, 'IVR');

                    if ($template) {
                        $templateId = $template->docuseal_template_id;
                    }
                }
            }

            // If no manufacturer found, use fallback logic
            if (!$manufacturer || !$manufacturerId) {
                // Try to find the first available manufacturer and template
                $template = \App\Models\Docuseal\DocusealTemplate::with('manufacturer')
                    ->byDocumentType('IVR')
                    ->active()
                    ->default()
                    ->first();

                if ($template && $template->manufacturer) {
                    $manufacturerId = $template->manufacturer_id;
                    $manufacturer = $template->manufacturer->name;
                    $templateId = $template->docuseal_template_id;
                } else {
                    // Final fallback - use BioWound from config if no database templates
                    $manufacturer = 'BioWound';
                    $templateId = config('docuseal.templates.BioWound.default') ?? config('docuseal.default_templates.BioWound');
                }
            }

            Log::info('DocuSeal template resolution (dynamic)', [
                'manufacturer_id' => $manufacturerId,
                'manufacturer' => $manufacturer,
                'resolved_template_id' => $templateId,
                'use_builder' => $data['use_builder'] ?? false,
                'resolution_method' => $templateId ? 'database' : 'config_fallback'
            ]);

            // If using builder mode, return JWT token instead of creating submission
            if ($data['use_builder'] ?? false) {
                $builderRequest = new Request([
                    'user_email' => 'limitless@mscwoundcare.com',
                    'integration_email' => $prefillData['admin@mscwound.com'] ?? 'limitless@mscwoundcare.com',
                    'template_id' => $templateId,
                    'template_name' => "{$manufacturer} IVR Form",
                    'document_urls' => [],
                    'prefill_data' => $prefillData
                ]);

                $builderResponse = $this->generateBuilderToken($builderRequest);

                // Add additional metadata to the response
                if ($builderResponse->status() === 200) {
                    $responseData = $builderResponse->getData(true);
                    $responseData['manufacturer'] = $manufacturer;
                    $responseData['manufacturer_id'] = $manufacturerId;
                    $responseData['resolved_template_id'] = $templateId;

                    return response()->json($responseData);
                }

                return $builderResponse;
            }

            // Original submission creation logic (for backward compatibility)
            if (!$templateId) {
                throw new \Exception("No DocuSeal template configured for manufacturer: {$manufacturer} (ID: {$manufacturerId}). Please configure templates in the admin panel.");
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

            Log::info('Creating DocuSeal submission (dynamic)', [
                'template_id' => $templateId,
                'manufacturer_id' => $manufacturerId,
                'manufacturer' => $manufacturer,
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
                'manufacturer' => $manufacturer,
                'manufacturer_id' => $manufacturerId
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
     * Get template ID for manufacturer dynamically from database
     */
    private function getManufacturerTemplateId($manufacturerName, $manufacturerId = null): ?string
    {
                // First try by manufacturer ID if provided
        if ($manufacturerId) {
            $template = \App\Models\Docuseal\DocusealTemplate::getDefaultTemplateForManufacturer($manufacturerId, 'IVR');
            if ($template) {
                return $template->docuseal_template_id;
            }
        }

        // Try by manufacturer name if ID lookup fails
        if ($manufacturerName) {
            $manufacturer = \App\Models\Order\Manufacturer::where('name', 'LIKE', "%{$manufacturerName}%")
                ->orWhere('display_name', 'LIKE', "%{$manufacturerName}%")
                ->first();

                        if ($manufacturer) {
                $template = \App\Models\Docuseal\DocusealTemplate::getDefaultTemplateForManufacturer($manufacturer->id, 'IVR');
                if ($template) {
                    return $template->docuseal_template_id;
                }
            }
        }

        // Final fallback to any available template
        $template = \App\Models\Docuseal\DocusealTemplate::getDefaultTemplate('IVR');

        return $template ? $template->docuseal_template_id : null;
    }

    /**
     * Map manufacturer name to config key for template lookup (DEPRECATED - use database instead)
     * @deprecated Use getManufacturerTemplateId() instead for dynamic database-driven lookups
     */
    private function getManufacturerConfigKey($manufacturerName): string
    {
        // This method is deprecated - use database-driven template resolution instead
        Log::warning('Using deprecated getManufacturerConfigKey method', [
            'manufacturer' => $manufacturerName,
            'recommendation' => 'Switch to database-driven template resolution'
        ]);

        // Try to get template ID from database first
        $templateId = $this->getManufacturerTemplateId($manufacturerName);
        if ($templateId) {
            return $templateId;
        }

        // Legacy fallback mapping (for backward compatibility only)
        $manufacturerMap = [
            'ACZ & Associates' => 'ACZ.default',
            'Advanced Solution' => 'Advanced.default',
            'BioWound Solutions' => 'BioWound.default',
            'MedLife' => 'MedLife.default',
            'Skye Biologics' => 'Skye.default',
        ];

        $normalizedName = trim($manufacturerName);
        return $manufacturerMap[$normalizedName] ?? 'BioWound.default'; // Fallback to BioWound template
    }

    /**
     * Format prefill values for DocuSeal submission
     */
    private function formatPrefillValues($prefillData)
    {
        return $this->mapFormDataToDocuSealFields($prefillData);
    }

    /**
     * Map form data to DocuSeal field format with comprehensive field mapping
     */
    private function mapFormDataToDocuSealFields(array $formData): array
    {
        // Enhanced mapping with data transformation and validation
        $mappedFields = [
            // Patient Information Fields
            'patient_first_name' => $this->sanitizeTextValue($formData['patient_first_name'] ?? ''),
            'patient_last_name' => $this->sanitizeTextValue($formData['patient_last_name'] ?? ''),
            'patient_full_name' => $this->sanitizeTextValue(
                trim(($formData['patient_first_name'] ?? '') . ' ' . ($formData['patient_last_name'] ?? ''))
            ),
            'patient_dob' => $this->formatDate($formData['patient_dob'] ?? ''),
            'patient_gender' => $this->formatGender($formData['patient_gender'] ?? ''),
            'patient_member_id' => $this->sanitizeTextValue($formData['patient_member_id'] ?? ''),

            // Address fields with fallback combinations
            'patient_address' => $this->formatAddress($formData),
            'patient_address_line1' => $this->sanitizeTextValue($formData['patient_address_line1'] ?? ''),
            'patient_address_line2' => $this->sanitizeTextValue($formData['patient_address_line2'] ?? ''),
            'patient_city' => $this->sanitizeTextValue($formData['patient_city'] ?? ''),
            'patient_state' => $this->sanitizeTextValue($formData['patient_state'] ?? ''),
            'patient_zip' => $this->sanitizeTextValue($formData['patient_zip'] ?? ''),
            'patient_full_address' => $this->formatFullAddress($formData),

            // Contact information
            'patient_phone' => $this->formatPhoneNumber($formData['patient_phone'] ?? ''),
            'patient_email' => $this->sanitizeEmail($formData['patient_email'] ?? ''),

            // Provider Information Fields
            'provider_name' => $this->sanitizeTextValue($formData['provider_name'] ?? ''),
            'provider_npi' => $this->sanitizeTextValue($formData['provider_npi'] ?? ''),
            'provider_credentials' => $this->sanitizeTextValue($formData['provider_credentials'] ?? ''),
            'facility_name' => $this->sanitizeTextValue($formData['facility_name'] ?? ''),
            'facility_address' => $this->sanitizeTextValue($formData['facility_address'] ?? ''),

            // Clinical Information Fields with enhanced formatting
            'wound_type' => $this->sanitizeTextValue($formData['wound_type'] ?? ''),
            'wound_location' => $this->sanitizeTextValue($formData['wound_location'] ?? ''),
            'wound_size' => $this->formatWoundSize($formData),
            'wound_size_length' => $this->formatMeasurement($formData['wound_size_length'] ?? ''),
            'wound_size_width' => $this->formatMeasurement($formData['wound_size_width'] ?? ''),
            'wound_size_depth' => $this->formatMeasurement($formData['wound_size_depth'] ?? ''),
            'total_wound_area' => $this->calculateWoundArea($formData),
            'wound_onset_date' => $this->formatDate($formData['wound_onset_date'] ?? ''),
            'failed_conservative_treatment' => $this->formatBoolean($formData['failed_conservative_treatment'] ?? ''),
            'treatment_tried' => $this->sanitizeTextValue($formData['treatment_tried'] ?? ''),
            'current_dressing' => $this->sanitizeTextValue($formData['current_dressing'] ?? ''),
            'expected_service_date' => $this->formatDate($formData['expected_service_date'] ?? ''),

            // Insurance Information Fields with better naming
            'primary_insurance' => $this->sanitizeTextValue($formData['primary_insurance_name'] ?? $formData['primary_insurance'] ?? ''),
            'primary_insurance_name' => $this->sanitizeTextValue($formData['primary_insurance_name'] ?? ''),
            'primary_member_id' => $this->sanitizeTextValue($formData['primary_member_id'] ?? ''),
            'primary_plan_type' => $this->sanitizeTextValue($formData['primary_plan_type'] ?? ''),
            'primary_payer_phone' => $this->formatPhoneNumber($formData['primary_payer_phone'] ?? ''),
            'has_secondary_insurance' => $this->formatBoolean($formData['has_secondary_insurance'] ?? ''),
            'secondary_insurance' => $this->sanitizeTextValue($formData['secondary_insurance_name'] ?? $formData['secondary_insurance'] ?? ''),
            'secondary_insurance_name' => $this->sanitizeTextValue($formData['secondary_insurance_name'] ?? ''),
            'secondary_member_id' => $this->sanitizeTextValue($formData['secondary_member_id'] ?? ''),

            // Product Information Fields with dynamic product handling
            'selected_product_name' => $this->sanitizeTextValue($formData['selected_product_name'] ?? ''),
            'selected_product_code' => $this->sanitizeTextValue($formData['selected_product_code'] ?? ''),
            'selected_product_manufacturer' => $this->sanitizeTextValue($formData['selected_product_manufacturer'] ?? ''),
            'product_quantity' => $this->formatQuantity($formData['product_quantity'] ?? ''),
            'product_size' => $this->sanitizeTextValue($formData['product_size'] ?? ''),
            'manufacturer_name' => $this->sanitizeTextValue($formData['manufacturer_name'] ?? $formData['selected_product_manufacturer'] ?? ''),

            // Multi-product support
            'selected_products_list' => $this->formatSelectedProductsList($formData),
            'total_product_quantity' => $this->calculateTotalQuantity($formData),

            // Shipping Information Fields
            'shipping_same_as_patient' => $this->formatBoolean($formData['shipping_same_as_patient'] ?? ''),
            'shipping_address' => $this->formatShippingAddress($formData),
            'shipping_address_line1' => $this->sanitizeTextValue($formData['shipping_address_line1'] ?? ''),
            'shipping_address_line2' => $this->sanitizeTextValue($formData['shipping_address_line2'] ?? ''),
            'shipping_city' => $this->sanitizeTextValue($formData['shipping_city'] ?? ''),
            'shipping_state' => $this->sanitizeTextValue($formData['shipping_state'] ?? ''),
            'shipping_zip' => $this->sanitizeTextValue($formData['shipping_zip'] ?? ''),
            'shipping_full_address' => $this->formatFullShippingAddress($formData),
            'delivery_notes' => $this->sanitizeTextValue($formData['delivery_notes'] ?? ''),

            // Metadata Fields
            'submission_date' => $this->formatDate(now()->toDateString()),
            'submission_timestamp' => now()->format('Y-m-d H:i:s'),
            'episode_id' => $this->sanitizeTextValue($formData['episode_id'] ?? ''),
            'patient_fhir_id' => $this->sanitizeTextValue($formData['patient_fhir_id'] ?? ''),
            'organization_name' => $this->sanitizeTextValue($formData['organization_name'] ?? 'MSC Wound Care'),

            // Clinical calculations
            'wound_duration_days' => $this->calculateWoundDuration($formData),
            'urgency_level' => $this->determineUrgencyLevel($formData),
        ];

        // Add any additional custom fields from form data
        foreach ($formData as $key => $value) {
            if (!isset($mappedFields[$key]) && !empty($value)) {
                $mappedFields[$key] = $this->sanitizeTextValue($value);
            }
        }

        // Log the mapping for debugging (without PHI)
        Log::info('DocuSeal field mapping completed', [
            'total_fields' => count($mappedFields),
            'has_patient_name' => !empty($mappedFields['patient_first_name']),
            'has_wound_info' => !empty($mappedFields['wound_type']),
            'has_insurance' => !empty($mappedFields['primary_insurance']),
            'has_products' => !empty($mappedFields['selected_product_name'])
        ]);

        return $mappedFields;
    }

    // Helper methods for data transformation and validation

    private function sanitizeTextValue($value): string
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }
        return trim(strip_tags((string)$value));
    }

    private function sanitizeEmail($email): string
    {
        $email = $this->sanitizeTextValue($email);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private function formatDate($date): string
    {
        if (empty($date)) return '';

        try {
            return \Carbon\Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return '';
        }
    }

    private function formatPhoneNumber($phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $this->sanitizeTextValue($phone));
        if (strlen($phone) === 10) {
            return sprintf('(%s) %s-%s', substr($phone, 0, 3), substr($phone, 3, 3), substr($phone, 6));
        }
        return $phone;
    }

    private function formatGender($gender): string
    {
        $gender = strtoupper($this->sanitizeTextValue($gender));
        $genderMap = ['M' => 'Male', 'F' => 'Female', 'MALE' => 'Male', 'FEMALE' => 'Female'];
        return $genderMap[$gender] ?? $gender;
    }

    private function formatBoolean($value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        $value = strtolower($this->sanitizeTextValue($value));
        return in_array($value, ['true', '1', 'yes', 'on']) ? 'Yes' : 'No';
    }

    private function formatAddress($formData): string
    {
        $parts = array_filter([
            $formData['patient_address_line1'] ?? '',
            $formData['patient_address_line2'] ?? ''
        ]);
        return $this->sanitizeTextValue(implode(', ', $parts));
    }

    private function formatFullAddress($formData): string
    {
        $parts = array_filter([
            $this->formatAddress($formData),
            $formData['patient_city'] ?? '',
            $formData['patient_state'] ?? '',
            $formData['patient_zip'] ?? ''
        ]);
        return $this->sanitizeTextValue(implode(', ', $parts));
    }

    private function formatShippingAddress($formData): string
    {
        if ($this->formatBoolean($formData['shipping_same_as_patient'] ?? '') === 'Yes') {
            return $this->formatAddress($formData);
        }

        $parts = array_filter([
            $formData['shipping_address_line1'] ?? '',
            $formData['shipping_address_line2'] ?? ''
        ]);
        return $this->sanitizeTextValue(implode(', ', $parts));
    }

    private function formatFullShippingAddress($formData): string
    {
        if ($this->formatBoolean($formData['shipping_same_as_patient'] ?? '') === 'Yes') {
            return $this->formatFullAddress($formData);
        }

        $parts = array_filter([
            $this->formatShippingAddress($formData),
            $formData['shipping_city'] ?? '',
            $formData['shipping_state'] ?? '',
            $formData['shipping_zip'] ?? ''
        ]);
        return $this->sanitizeTextValue(implode(', ', $parts));
    }

    private function formatWoundSize($formData): string
    {
        $length = $this->formatMeasurement($formData['wound_size_length'] ?? '');
        $width = $this->formatMeasurement($formData['wound_size_width'] ?? '');
        $depth = $this->formatMeasurement($formData['wound_size_depth'] ?? '');

        $parts = array_filter([$length, $width, $depth]);
        return implode(' x ', $parts) . ($parts ? ' cm' : '');
    }

    private function formatMeasurement($value): string
    {
        $value = $this->sanitizeTextValue($value);
        return is_numeric($value) ? number_format((float)$value, 1) : $value;
    }

    private function calculateWoundArea($formData): string
    {
        $length = floatval($formData['wound_size_length'] ?? 0);
        $width = floatval($formData['wound_size_width'] ?? 0);

        if ($length > 0 && $width > 0) {
            return number_format($length * $width, 2) . ' cm²';
        }
        return '';
    }

    private function formatQuantity($quantity): string
    {
        return is_numeric($quantity) ? (string)intval($quantity) : $this->sanitizeTextValue($quantity);
    }

    private function formatSelectedProductsList($formData): string
    {
        if (isset($formData['selected_products']) && is_array($formData['selected_products'])) {
            $products = [];
            foreach ($formData['selected_products'] as $product) {
                $name = $product['product_name'] ?? $product['name'] ?? 'Unknown Product';
                $quantity = $product['quantity'] ?? 1;
                $size = $product['size'] ?? '';

                $productStr = "{$name} (Qty: {$quantity})";
                if (!empty($size)) {
                    $productStr .= " [Size: {$size}]";
                }
                $products[] = $productStr;
            }
            return implode('; ', $products);
        }
        return '';
    }

    private function calculateTotalQuantity($formData): string
    {
        if (isset($formData['selected_products']) && is_array($formData['selected_products'])) {
            $total = 0;
            foreach ($formData['selected_products'] as $product) {
                $total += intval($product['quantity'] ?? 0);
            }
            return (string)$total;
        }
        return $this->formatQuantity($formData['product_quantity'] ?? '1');
    }

    private function calculateWoundDuration($formData): string
    {
        $onsetDate = $formData['wound_onset_date'] ?? '';
        if (empty($onsetDate)) return '';

        try {
            $onset = \Carbon\Carbon::parse($onsetDate);
            $now = \Carbon\Carbon::now();
            $days = $onset->diffInDays($now);
            return (string)$days;
        } catch (\Exception $e) {
            return '';
        }
    }

    private function determineUrgencyLevel($formData): string
    {
        // Simple urgency determination based on wound characteristics
        $urgencyFactors = 0;

        // Check wound duration
        $duration = intval($this->calculateWoundDuration($formData));
        if ($duration > 90) $urgencyFactors++;
        if ($duration > 180) $urgencyFactors++;

        // Check wound size
        $length = floatval($formData['wound_size_length'] ?? 0);
        $width = floatval($formData['wound_size_width'] ?? 0);
        if ($length > 5 || $width > 5) $urgencyFactors++;

        // Check if conservative treatment failed
        if ($this->formatBoolean($formData['failed_conservative_treatment'] ?? '') === 'Yes') {
            $urgencyFactors++;
        }

        if ($urgencyFactors >= 3) return 'High';
        if ($urgencyFactors >= 2) return 'Medium';
        return 'Low';
    }
}

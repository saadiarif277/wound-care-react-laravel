<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\PatientManufacturerIVREpisode;
use App\Models\Episode;
use App\Models\Order\Order;
use App\Models\Order\Product;
use App\Models\Fhir\Facility;
use App\Models\User;
use App\Services\FhirService;
use App\Services\DocusealService;
use App\Services\PayerService;
use App\Mail\ManufacturerOrderEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

final class QuickRequestService
{
    public function __construct(
        private FhirService $fhirClient,
        private DocusealService $docuSealService,
        private PayerService $payerService,
    ) {}

    /**
     * Get form data for Quick Request creation
     */
    public function getFormData(User $user): array
    {
        $user = $this->loadUserWithRelations($user);
        $currentOrg = $user->organizations->first();

        // Determine if we should filter products by provider
        $providerId = null;
        $userRole = $user->getPrimaryRole()?->slug ?? $user->roles->first()?->slug;

        if ($userRole === 'provider') {
            $providerId = $user->id;
        }

        return [
            'facilities' => $this->getFacilitiesForUser($user, $currentOrg),
            'providers' => $this->getProvidersForUser($user, $currentOrg),
            'products' => $userRole === 'office-manager' ? [] : $this->getActiveProducts($providerId),
            'woundTypes' => $this->getWoundTypes(),
            'insuranceCarriers' => $this->getInsuranceCarriers(),
            'diagnosisCodes' => $this->getDiagnosisCodes(),
            'currentUser' => $this->getCurrentUserData($user, $currentOrg),
            'providerProducts' => [],
        ];
    }

    /**
     * Load user with necessary relations
     */
    private function loadUserWithRelations(User $user): User
    {
        return $user->load([
            'roles',
            'providerProfile',
            'providerCredentials',
            'organizations' => fn($q) => $q->where('organization_users.is_active', true),
            'facilities'
        ]);
    }

    /**
     * Get facilities for user
     */
    private function getFacilitiesForUser(User $user, $currentOrg): \Illuminate\Support\Collection
    {
        $userRole = $user->getPrimaryRole()?->slug ?? $user->roles->first()?->slug;

        // Office managers should only see their assigned facility
        if ($userRole === 'office-manager') {
            $userFacilities = $user->facilities->map(function($facility) {
                return [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'address' => $facility->full_address,
                    'address_line1' => $facility->address_line1,
                    'address_line2' => $facility->address_line2,
                    'city' => $facility->city,
                    'state' => $facility->state,
                    'zip_code' => $facility->zip_code,
                    'phone' => $facility->phone,
                    'fax' => $facility->fax,
                    'email' => $facility->email,
                    'npi' => $facility->npi,
                    'group_npi' => $facility->group_npi,
                    'ptan' => $facility->ptan,
                    'tax_id' => $facility->tax_id,
                    'facility_type' => $facility->facility_type,
                    'place_of_service' => $facility->place_of_service,
                    'medicaid_number' => $facility->medicaid_number,
                    'source' => 'user_relationship'
                ];
            });

            return $userFacilities;
        }

        // Providers can select from multiple facilities
        $userFacilities = $user->facilities->map(function($facility) {
            return [
                'id' => $facility->id,
                'name' => $facility->name,
                'address' => $facility->full_address,
                'address_line1' => $facility->address_line1,
                'address_line2' => $facility->address_line2,
                'city' => $facility->city,
                'state' => $facility->state,
                'zip_code' => $facility->zip_code,
                'phone' => $facility->phone,
                'fax' => $facility->fax,
                'email' => $facility->email,
                'npi' => $facility->npi,
                'group_npi' => $facility->group_npi,
                'ptan' => $facility->ptan,
                'tax_id' => $facility->tax_id,
                'facility_type' => $facility->facility_type,
                'place_of_service' => $facility->place_of_service,
                'medicaid_number' => $facility->medicaid_number,
                'source' => 'user_relationship'
            ];
        });

        if ($userFacilities->count() > 0) {
            return $userFacilities;
        }

        return Facility::withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class)
            ->where('active', true)
            ->take(10)
            ->get()
            ->map(function($facility) {
                return [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'address' => $facility->full_address ?? 'No address',
                    'address_line1' => $facility->address_line1,
                    'address_line2' => $facility->address_line2,
                    'city' => $facility->city,
                    'state' => $facility->state,
                    'zip_code' => $facility->zip_code,
                    'phone' => $facility->phone,
                    'fax' => $facility->fax,
                    'email' => $facility->email,
                    'npi' => $facility->npi,
                    'group_npi' => $facility->group_npi,
                    'ptan' => $facility->ptan,
                    'tax_id' => $facility->tax_id,
                    'facility_type' => $facility->facility_type,
                    'place_of_service' => $facility->place_of_service,
                    'medicaid_number' => $facility->medicaid_number,
                    'organization_id' => $facility->organization_id,
                    'source' => 'all_facilities'
                ];
            });
    }

    /**
     * Get providers for user based on role restrictions
     */
    private function getProvidersForUser(User $user, $currentOrg): array
    {
        $providers = [];
        $userRole = $user->getPrimaryRole()?->slug ?? $user->roles->first()?->slug;

        if ($userRole === 'provider') {
            // Providers can only see themselves - they cannot order for other providers
            // Load full provider profile with all fields
            $user->load(['providerProfile', 'providerCredentials']);
            
            $providers[] = [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'credentials' => $user->providerProfile?->credentials ?? $user->providerCredentials->pluck('credential_number')->implode(', ') ?? null,
                'npi' => $user->npi_number ?? $user->providerCredentials->where('credential_type', 'npi_number')->first()?->credential_number ?? null,
                'email' => $user->email,
                'phone' => $user->phone ?? $user->providerProfile?->phone ?? null,
                'specialty' => $user->providerProfile?->specialty ?? null,
                'license_number' => $user->providerProfile?->license_number ?? $user->providerCredentials->where('credential_type', 'license_number')->first()?->credential_number ?? null,
                'license_state' => $user->providerProfile?->license_state ?? null,
                'dea_number' => $user->providerProfile?->dea_number ?? $user->providerCredentials->where('credential_type', 'dea_number')->first()?->credential_number ?? null,
                'ptan' => $user->providerProfile?->ptan ?? $user->providerCredentials->where('credential_type', 'ptan')->first()?->credential_number ?? null,
                'tax_id' => $user->providerProfile?->tax_id ?? null,
                'practice_name' => $user->providerProfile?->practice_name ?? $currentOrg?->name ?? null,
                'medicaid_number' => $user->providerProfile?->medicaid_number ?? $user->providerCredentials->where('credential_type', 'medicaid_number')->first()?->credential_number ?? null,
                'fhir_practitioner_id' => $user->fhir_practitioner_id ?? null,
            ];
        } elseif ($userRole === 'office-manager') {
            // Office managers can order for multiple providers at their facility
            if ($currentOrg) {
                $orgProviders = User::whereHas('organizations', function($q) use ($currentOrg) {
                        $q->where('organizations.id', $currentOrg->id);
                    })
                    ->whereHas('roles', function($q) {
                        $q->where('slug', 'provider');
                    })
                    ->with(['providerProfile', 'providerCredentials'])
                    ->get()
                    ->map(function($provider) use ($currentOrg) {
                        return [
                            'id' => $provider->id,
                            'name' => $provider->first_name . ' ' . $provider->last_name,
                            'first_name' => $provider->first_name,
                            'last_name' => $provider->last_name,
                            'credentials' => $provider->providerProfile?->credentials ?? $provider->provider_credentials ?? null,
                            'npi' => $provider->npi_number,
                            'email' => $provider->email,
                            'phone' => $provider->phone ?? $provider->providerProfile?->phone ?? null,
                            'specialty' => $provider->providerProfile?->specialty ?? null,
                            'license_number' => $provider->providerProfile?->license_number ?? null,
                            'license_state' => $provider->providerProfile?->license_state ?? null,
                            'dea_number' => $provider->providerProfile?->dea_number ?? null,
                            'ptan' => $provider->providerProfile?->ptan ?? null,
                            'tax_id' => $provider->providerProfile?->tax_id ?? null,
                            'practice_name' => $provider->providerProfile?->practice_name ?? $currentOrg?->name ?? null,
                            'medicaid_number' => $provider->providerProfile?->medicaid_number ?? null,
                            'fhir_practitioner_id' => $provider->fhir_practitioner_id ?? null,
                        ];
                    })
                    ->toArray();

                $providers = $orgProviders;
            }
        }

        return $providers;
    }

    /**
     * Get active products
     */
    private function getActiveProducts(?int $providerId = null): \Illuminate\Support\Collection
    {
        $query = Product::where('is_active', true);

        // If a provider ID is specified, filter to only products they're onboarded with
        if ($providerId) {
            $query->whereHas('activeProviders', function($q) use ($providerId) {
                $q->where('users.id', $providerId);
            });
        }

        return $query
            ->with(['activeSizes', 'manufacturer']) // Load both ProductSize and Manufacturer relationships
            ->orderBy('price_per_sq_cm', 'desc') // Sort by highest ASP first
            ->get()
            ->map(function($product) {
                // Get sizes from ProductSize relationship
                $productSizes = $product->activeSizes;

                // Format sizes for frontend
                $availableSizes = [];
                $sizeOptions = [];
                $sizePricing = [];

                foreach ($productSizes as $size) {
                    // Add the display label (e.g., "2x2cm", "4x4cm")
                    $sizeOptions[] = $size->size_label;

                    // Add to size pricing mapping
                    $sizePricing[$size->size_label] = $size->area_cm2;

                    // For backward compatibility, also add numeric sizes
                    $availableSizes[] = $size->area_cm2;
                }

                return [
                    'id' => $product->id,
                    'code' => $product->q_code,
                    'name' => $product->name,
                    'manufacturer' => is_object($product->manufacturer) ? $product->manufacturer->name : $product->manufacturer,
                    'manufacturer_id' => $product->manufacturer_id,
                    'available_sizes' => $availableSizes, // Numeric sizes for backward compatibility
                    'size_options' => $sizeOptions, // Actual size labels like "2x2cm", "4x4cm"
                    'size_pricing' => $sizePricing, // Maps size labels to area in cm²
                    'size_unit' => 'cm', // Default to cm for wound care products
                    'price_per_sq_cm' => $product->price_per_sq_cm ?? 0,
                    'msc_price' => $product->msc_price ?? null,
                    'commission_rate' => $product->commission_rate ?? null,
                    'docuseal_template_id' => $product->manufacturer?->docuseal_order_form_template_id ?? null,
                    'signature_required' => $product->manufacturer?->signature_required ?? false,
                ];
            });
    }

    /**
     * Get wound types
     */
    private function getWoundTypes(): array
    {
        try {
            return DB::table('wound_types')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('display_name', 'code')
                ->toArray();
        } catch (\Exception $e) {
            // Fallback to hardcoded wound types if table doesn't exist
            return [
                'DFU' => 'Diabetic Foot Ulcer',
                'VLU' => 'Venous Leg Ulcer',
                'PU' => 'Pressure Ulcer',
                'SWI' => 'Surgical Wound Infection',
                'TU' => 'Traumatic Ulcer',
                'AU' => 'Arterial Ulcer',
            ];
        }
    }

    /**
     * Get insurance carriers
     */
    private function getInsuranceCarriers(): array
    {
        try {
            return $this->payerService->getAllPayers()
                ->pluck('name')
                ->unique()
                ->values()
                ->toArray();
        } catch (\Exception $e) {
            // Fallback to basic insurance carriers
            return [
                'Medicare',
                'Medicaid',
                'Aetna',
                'Anthem',
                'Blue Cross Blue Shield',
                'Cigna',
                'Humana',
                'UnitedHealthcare',
                'Other',
            ];
        }
    }

    /**
     * Get diagnosis codes
     */
    private function getDiagnosisCodes(): array
    {
        try {
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
        } catch (\Exception $e) {
            // Fallback to basic diagnosis codes
            return [
                'Diabetic' => [
                    ['code' => 'E11.621', 'description' => 'Type 2 diabetes mellitus with foot ulcer'],
                    ['code' => 'E10.621', 'description' => 'Type 1 diabetes mellitus with foot ulcer'],
                ],
                'Venous' => [
                    ['code' => 'I87.2', 'description' => 'Venous insufficiency (chronic) (peripheral)'],
                    ['code' => 'L97.909', 'description' => 'Non-pressure chronic ulcer of unspecified part of unspecified lower leg'],
                ],
                'Pressure' => [
                    ['code' => 'L89.90', 'description' => 'Pressure ulcer of unspecified site, unspecified stage'],
                ],
            ];
        }
    }

    /**
     * Get current user data
     */
    private function getCurrentUserData(User $user, $currentOrg): array
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
     * Get the Docuseal service instance.
     */
    public function getDocusealService(): DocusealService
    {
        return $this->docuSealService;
    }

    /**
     * Start a new quick request episode and create initial order.
     */
    public function startEpisode(array $data): Episode
    {
        // Extract data for FHIR resources
        $patientData = $data['patient'] ?? [];
        $providerData = $data['provider'] ?? [];
        $facilityData = $data['facility'] ?? [];
        $clinicalData = $data['clinical'] ?? [];
        $insuranceData = $data['insurance'] ?? [];
        $productData = $data['product'] ?? [];

        $fhirIds = [];

        // Orchestrate FHIR resource creation as per workflow
        try {
            // 1. Create or get Patient
            $patientResource = $this->createFhirPatient($patientData);
            $fhirIds['patient_id'] = $patientResource['id'] ?? null;

            // 2. Create or get Practitioner
            $practitionerResource = $this->createFhirPractitioner($providerData);
            $fhirIds['practitioner_id'] = $practitionerResource['id'] ?? null;

            // 3. Create Organization
            $organizationResource = $this->createFhirOrganization($facilityData);
            $fhirIds['organization_id'] = $organizationResource['id'] ?? null;

            // 4. Create Condition (diagnosis)
            $conditionResource = $this->createFhirCondition($clinicalData, $fhirIds['patient_id']);
            $fhirIds['condition_id'] = $conditionResource['id'] ?? null;

            // 5. Create EpisodeOfCare
            $episodeOfCareResource = $this->createFhirEpisodeOfCare($fhirIds);
            $fhirIds['episode_of_care_id'] = $episodeOfCareResource['id'] ?? null;

            // 6. Create Coverage (insurance)
            $coverageResource = $this->createFhirCoverage($insuranceData, $fhirIds);
            $fhirIds['coverage_id'] = $coverageResource['id'] ?? null;

            // 7. Create Encounter
            $encounterResource = $this->createFhirEncounter($fhirIds, $clinicalData);
            $fhirIds['encounter_id'] = $encounterResource['id'] ?? null;

            // 8. Create QuestionnaireResponse (assessment)
            $questionnaireResource = $this->createFhirQuestionnaireResponse($clinicalData, $fhirIds);
            $fhirIds['questionnaire_response_id'] = $questionnaireResource['id'] ?? null;

            // 9. Create DeviceRequest (product order)
            $deviceRequestResource = $this->createFhirDeviceRequest($productData, $fhirIds);
            $fhirIds['device_request_id'] = $deviceRequestResource['id'] ?? null;

            // 10. Create Task for internal review
            $taskResource = $this->createFhirTask($fhirIds, 'internal_review');
            $fhirIds['task_id'] = $taskResource['id'] ?? null;

        } catch (\Exception $e) {
            Log::error('FHIR orchestration failed', [
                'error' => $e->getMessage(),
                'step' => $e->getCode(),
                'fhir_ids' => $fhirIds
            ]);
            // Continue with episode creation even if FHIR fails
        }

        // Persist episode with FHIR references
        $episode = Episode::create([
            'patient_id'           => $data['patient_id'] ?? null,
            'patient_fhir_id'      => $fhirIds['patient_id'] ?? $data['patient_fhir_id'] ?? null,
            'patient_display_id'   => $data['patient_display_id'] ?? $this->generatePatientDisplayId($patientData),
            'manufacturer_id'      => $data['manufacturer_id'],
            'status'               => 'draft',
            'metadata'             => array_merge($data, [
                'fhir_ids' => $fhirIds,
                'created_via' => 'quick_request'
            ]),
        ]);

        // Create initial order
        $order = Order::create([
            'episode_id' => $episode->id,
            'type'       => 'initial',
            'details'    => $data['order_details'] ?? [],
        ]);

        // Docuseal PDF generation
        try {
            $manufacturerId = $data['manufacturer_id'];
            $productCode = $data['order_details']['product'] ?? null;

            // Note: DocusealBuilder service not implemented yet
            // For now, use basic data without template lookup
            $dataWithTemplate = $data;
            $submission = $this->docuSealService->createIVRSubmission(
                $dataWithTemplate,
                $episode
            );
            if (!empty($submission['embed_url'])) {
                $episode->update(['docuseal_submission_url' => $submission['embed_url']]);
            }
        } catch (\Exception $e) {
            Log::error('Docuseal PDF generation failed', [
                'error' => $e->getMessage(),
                'episode_id' => $episode->id,
            ]);
        }

        return $episode->load('orders');
    }

    /**
     * Add a follow-up order to an existing episode.
     */
    public function addFollowUp(Episode $episode, array $data): Order
    {
        // FHIR follow-up request
        try {
            $bundle = [
                'resourceType' => 'Bundle',
                'type'         => 'transaction',
                'entry'        => [
                    // Map follow-up device request here...
                ],
            ];
            $this->fhirClient->createBundle($bundle);
        } catch (\Exception $e) {
            Log::error('FHIR follow-up bundle creation failed', ['error' => $e->getMessage()]);
        }

        // Create follow-up order
        $order = Order::create([
            'episode_id'      => $episode->id,
            'parent_order_id' => $data['parent_order_id'],
            'type'            => 'follow_up',
            'details'         => $data['order_details'] ?? [],
        ]);

        return $order;
    }

    /**
     * Approve an episode and send notification.
     */
    public function approve(Episode $episode): void
    {
        // Create manufacturer acceptance Task in FHIR
        try {
            $fhirIds = $episode->metadata['fhir_ids'] ?? [];
            if (!empty($fhirIds['episode_of_care_id'])) {
                $this->createFhirTask($fhirIds, 'manufacturer_acceptance');
            }
        } catch (\Exception $e) {
            Log::error('Failed to create manufacturer task', [
                'error' => $e->getMessage(),
                'episode_id' => $episode->id
            ]);
        }

        // Transition episode status
        $episode->update(['status' => 'manufacturer_review']);

        // Dispatch email to manufacturer
        try {
            $email = $episode->manufacturer->contact_email ?? null;
            if (is_string($email) && !empty($email)) {
                Mail::to([$email])->send(new ManufacturerOrderEmail($episode->toArray()));
            }
        } catch (\Exception $e) {
            Log::error('Error sending approval email', [
                'error' => $e->getMessage(),
                'episode_id' => $episode->id,
            ]);
        }
    }

    /**
     * Create FHIR Patient resource
     */
    private function createFhirPatient(array $patientData): array
    {
        return retry(3, function () use ($patientData) {
            $patient = [
                'resourceType' => 'Patient',
                'name' => [[
                    'use' => 'official',
                    'family' => $patientData['last_name'] ?? '',
                    'given' => [$patientData['first_name'] ?? '']
                ]],
                'gender' => strtolower($patientData['gender'] ?? 'unknown'),
                'birthDate' => $patientData['date_of_birth'] ?? null,
                'telecom' => [
                    [
                        'system' => 'phone',
                        'value' => $patientData['phone'] ?? '',
                        'use' => 'mobile'
                    ],
                    [
                        'system' => 'email',
                        'value' => $patientData['email'] ?? '',
                        'use' => 'home'
                    ]
                ],
                'address' => [[
                    'use' => 'home',
                    'line' => array_filter([
                        $patientData['address_line1'] ?? '',
                        $patientData['address_line2'] ?? ''
                    ]),
                    'city' => $patientData['city'] ?? '',
                    'state' => $patientData['state'] ?? '',
                    'postalCode' => $patientData['zip'] ?? ''
                ]],
                'identifier' => [[
                    'system' => 'http://mscwoundcare.com/patient-id',
                    'value' => $patientData['member_id'] ?? uniqid('PAT')
                ]]
            ];

            return $this->fhirClient->create('Patient', $patient);
        }, 1000);
    }

    /**
     * Create FHIR Practitioner resource
     */
    private function createFhirPractitioner(array $providerData): array
    {
        return retry(3, function () use ($providerData) {
            // First try to search for existing practitioner by NPI
            if (!empty($providerData['npi'])) {
                $search = $this->fhirClient->search('Practitioner', [
                    'identifier' => $providerData['npi']
                ]);

                if (!empty($search['entry'])) {
                    return $search['entry'][0]['resource'];
                }
            }

            // Create new practitioner
            $practitioner = [
                'resourceType' => 'Practitioner',
                'name' => [[
                    'use' => 'official',
                    'text' => $providerData['name'] ?? '',
                    'family' => $providerData['last_name'] ?? '',
                    'given' => [$providerData['first_name'] ?? '']
                ]],
                'identifier' => [[
                    'system' => 'http://hl7.org/fhir/sid/us-npi',
                    'value' => $providerData['npi'] ?? ''
                ]],
                'telecom' => [[
                    'system' => 'email',
                    'value' => $providerData['email'] ?? '',
                    'use' => 'work'
                ]],
                'qualification' => [[
                    'code' => [
                        'text' => $providerData['credentials'] ?? 'MD'
                    ]
                ]]
            ];

            return $this->fhirClient->create('Practitioner', $practitioner);
        }, 1000);
    }

    /**
     * Create FHIR Organization resource
     */
    private function createFhirOrganization(array $facilityData): array
    {
        return retry(3, function () use ($facilityData) {
            $organization = [
                'resourceType' => 'Organization',
                'name' => $facilityData['name'] ?? '',
                'type' => [[
                    'coding' => [[
                        'system' => 'http://terminology.hl7.org/CodeSystem/organization-type',
                        'code' => 'prov',
                        'display' => 'Healthcare Provider'
                    ]]
                ]],
                'telecom' => [[
                    'system' => 'phone',
                    'value' => $facilityData['phone'] ?? '',
                    'use' => 'work'
                ]],
                'address' => [[
                    'use' => 'work',
                    'line' => [$facilityData['address'] ?? ''],
                    'city' => $facilityData['city'] ?? '',
                    'state' => $facilityData['state'] ?? '',
                    'postalCode' => $facilityData['zip'] ?? ''
                ]],
                'identifier' => [[
                    'system' => 'http://hl7.org/fhir/sid/us-npi',
                    'value' => $facilityData['npi'] ?? ''
                ]]
            ];

            return $this->fhirClient->create('Organization', $organization);
        }, 1000);
    }

    /**
     * Create FHIR Condition resource
     */
    private function createFhirCondition(array $clinicalData, ?string $patientId): array
    {
        return retry(3, function () use ($clinicalData, $patientId) {
            $condition = [
                'resourceType' => 'Condition',
                'clinicalStatus' => [
                    'coding' => [[
                        'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                        'code' => 'active'
                    ]]
                ],
                'verificationStatus' => [
                    'coding' => [[
                        'system' => 'http://terminology.hl7.org/CodeSystem/condition-ver-status',
                        'code' => 'confirmed'
                    ]]
                ],
                'code' => [
                    'coding' => [[
                        'system' => 'http://hl7.org/fhir/sid/icd-10',
                        'code' => $clinicalData['diagnosis_code'] ?? '',
                        'display' => $clinicalData['diagnosis_description'] ?? ''
                    ]]
                ],
                'subject' => [
                    'reference' => "Patient/{$patientId}"
                ],
                'onsetDateTime' => $clinicalData['onset_date'] ?? date('Y-m-d'),
                'note' => [[
                    'text' => $clinicalData['clinical_notes'] ?? ''
                ]]
            ];

            return $this->fhirClient->create('Condition', $condition);
        }, 1000);
    }

    /**
     * Create FHIR EpisodeOfCare resource
     */
    private function createFhirEpisodeOfCare(array $fhirIds): array
    {
        return retry(3, function () use ($fhirIds) {
            $episodeOfCare = [
                'resourceType' => 'EpisodeOfCare',
                'status' => 'active',
                'type' => [[
                    'coding' => [[
                        'system' => 'http://snomed.info/sct',
                        'code' => '225358003',
                        'display' => 'Wound care'
                    ]]
                ]],
                'patient' => [
                    'reference' => "Patient/{$fhirIds['patient_id']}"
                ],
                'managingOrganization' => [
                    'reference' => "Organization/{$fhirIds['organization_id']}"
                ],
                'period' => [
                    'start' => date('Y-m-d')
                ],
                'team' => [[
                    'reference' => "CareTeam/wound-care-team",
                    'display' => 'Wound Care Team'
                ]]
            ];

            return $this->fhirClient->create('EpisodeOfCare', $episodeOfCare);
        }, 1000);
    }

    /**
     * Create FHIR Coverage resource
     */
    private function createFhirCoverage(array $insuranceData, array $fhirIds): array
    {
        return retry(3, function () use ($insuranceData, $fhirIds) {
            $coverage = [
                'resourceType' => 'Coverage',
                'status' => 'active',
                'type' => [
                    'coding' => [[
                        'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                        'code' => $insuranceData['type'] ?? 'HIP',
                        'display' => $insuranceData['type_display'] ?? 'health insurance plan policy'
                    ]]
                ],
                'subscriber' => [
                    'reference' => "Patient/{$fhirIds['patient_id']}"
                ],
                'beneficiary' => [
                    'reference' => "Patient/{$fhirIds['patient_id']}"
                ],
                'payor' => [[
                    'display' => $insuranceData['payer_name'] ?? ''
                ]],
                'identifier' => [[
                    'system' => 'http://mscwoundcare.com/insurance-id',
                    'value' => $insuranceData['member_id'] ?? ''
                ]]
            ];

            return $this->fhirClient->create('Coverage', $coverage);
        }, 1000);
    }

    /**
     * Create FHIR Encounter resource
     */
    private function createFhirEncounter(array $fhirIds, array $clinicalData): array
    {
        return retry(3, function () use ($fhirIds, $clinicalData) {
            $encounter = [
                'resourceType' => 'Encounter',
                'status' => 'in-progress',
                'class' => [
                    'system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode',
                    'code' => 'AMB',
                    'display' => 'ambulatory'
                ],
                'type' => [[
                    'coding' => [[
                        'system' => 'http://snomed.info/sct',
                        'code' => '225358003',
                        'display' => 'Wound care'
                    ]]
                ]],
                'subject' => [
                    'reference' => "Patient/{$fhirIds['patient_id']}"
                ],
                'episodeOfCare' => [[
                    'reference' => "EpisodeOfCare/{$fhirIds['episode_of_care_id']}"
                ]],
                'participant' => [[
                    'type' => [[
                        'coding' => [[
                            'system' => 'http://terminology.hl7.org/CodeSystem/v3-ParticipationType',
                            'code' => 'PPRF',
                            'display' => 'primary performer'
                        ]]
                    ]],
                    'individual' => [
                        'reference' => "Practitioner/{$fhirIds['practitioner_id']}"
                    ]
                ]],
                'serviceProvider' => [
                    'reference' => "Organization/{$fhirIds['organization_id']}"
                ]
            ];

            return $this->fhirClient->create('Encounter', $encounter);
        }, 1000);
    }

    /**
     * Create FHIR QuestionnaireResponse resource
     */
    private function createFhirQuestionnaireResponse(array $clinicalData, array $fhirIds): array
    {
        return retry(3, function () use ($clinicalData, $fhirIds) {
            $questionnaireResponse = [
                'resourceType' => 'QuestionnaireResponse',
                'status' => 'completed',
                'subject' => [
                    'reference' => "Patient/{$fhirIds['patient_id']}"
                ],
                'encounter' => [
                    'reference' => "Encounter/{$fhirIds['encounter_id']}"
                ],
                'authored' => date('c'),
                'author' => [
                    'reference' => "Practitioner/{$fhirIds['practitioner_id']}"
                ],
                'item' => [
                    [
                        'linkId' => 'wound-assessment',
                        'text' => 'Wound Assessment',
                        'item' => [
                            [
                                'linkId' => 'wound-type',
                                'text' => 'Wound Type',
                                'answer' => [[
                                    'valueString' => $clinicalData['wound_type'] ?? ''
                                ]]
                            ],
                            [
                                'linkId' => 'wound-location',
                                'text' => 'Wound Location',
                                'answer' => [[
                                    'valueString' => $clinicalData['wound_location'] ?? ''
                                ]]
                            ],
                            [
                                'linkId' => 'wound-size',
                                'text' => 'Wound Size (cm)',
                                'answer' => [[
                                    'valueString' => sprintf('%s x %s x %s',
                                        $clinicalData['wound_length'] ?? '0',
                                        $clinicalData['wound_width'] ?? '0',
                                        $clinicalData['wound_depth'] ?? '0'
                                    )
                                ]]
                            ]
                        ]
                    ]
                ]
            ];

            return $this->fhirClient->create('QuestionnaireResponse', $questionnaireResponse);
        }, 1000);
    }

    /**
     * Create FHIR DeviceRequest resource
     */
    private function createFhirDeviceRequest(array $productData, array $fhirIds): array
    {
        return retry(3, function () use ($productData, $fhirIds) {
            $deviceRequest = [
                'resourceType' => 'DeviceRequest',
                'status' => 'active',
                'intent' => 'order',
                'codeCodeableConcept' => [
                    'coding' => [[
                        'system' => 'http://mscwoundcare.com/product-codes',
                        'code' => $productData['code'] ?? '',
                        'display' => $productData['name'] ?? ''
                    ]]
                ],
                'subject' => [
                    'reference' => "Patient/{$fhirIds['patient_id']}"
                ],
                'encounter' => [
                    'reference' => "Encounter/{$fhirIds['encounter_id']}"
                ],
                'authoredOn' => date('c'),
                'requester' => [
                    'reference' => "Practitioner/{$fhirIds['practitioner_id']}"
                ],
                'parameter' => [
                    [
                        'code' => [
                            'text' => 'Quantity'
                        ],
                        'valueQuantity' => [
                            'value' => $productData['quantity'] ?? 1,
                            'unit' => 'units'
                        ]
                    ],
                    [
                        'code' => [
                            'text' => 'Size'
                        ],
                        'valueCodeableConcept' => [
                            'text' => $productData['size'] ?? 'Standard'
                        ]
                    ]
                ]
            ];

            return $this->fhirClient->create('DeviceRequest', $deviceRequest);
        }, 1000);
    }

    /**
     * Create FHIR Task resource
     */
    private function createFhirTask(array $fhirIds, string $type): array
    {
        return retry(3, function () use ($fhirIds, $type) {
            $taskConfigs = [
                'internal_review' => [
                    'code' => 'approve',
                    'display' => 'Approve order',
                    'description' => 'Review and approve wound care product order',
                    'priority' => 'routine',
                    'performerType' => 'office-manager'
                ],
                'manufacturer_acceptance' => [
                    'code' => 'fulfill',
                    'display' => 'Fulfill order',
                    'description' => 'Process and fulfill wound care product order',
                    'priority' => 'normal',
                    'performerType' => 'manufacturer'
                ]
            ];

            $config = $taskConfigs[$type] ?? $taskConfigs['internal_review'];

            $task = [
                'resourceType' => 'Task',
                'status' => 'requested',
                'intent' => 'order',
                'code' => [
                    'coding' => [[
                        'system' => 'http://hl7.org/fhir/CodeSystem/task-code',
                        'code' => $config['code'],
                        'display' => $config['display']
                    ]]
                ],
                'description' => $config['description'],
                'priority' => $config['priority'],
                'for' => [
                    'reference' => "Patient/{$fhirIds['patient_id']}"
                ],
                'focus' => [
                    'reference' => "EpisodeOfCare/{$fhirIds['episode_of_care_id']}"
                ],
                'authoredOn' => date('c'),
                'requester' => [
                    'reference' => "Practitioner/{$fhirIds['practitioner_id']}"
                ],
                'performerType' => [[
                    'text' => $config['performerType']
                ]]
            ];

            return $this->fhirClient->create('Task', $task);
        }, 1000);
    }

    /**
     * Generate patient display ID
     */
    private function generatePatientDisplayId(array $patientData): string
    {
        $firstName = strtoupper(substr($patientData['first_name'] ?? 'XX', 0, 2));
        $lastName = strtoupper(substr($patientData['last_name'] ?? 'XX', 0, 2));
        $randomNum = str_pad((string) rand(0, 999), 3, '0', STR_PAD_LEFT);

        return $firstName . $lastName . $randomNum;
    }

    /**
     * Generate pre-filled IVR form using Docuseal
     */
    public function generatePrefilledIVR(array $formData, $template): array
    {
        try {
            Log::info('Generating pre-filled IVR with form data', [
                'template_id' => $template->id,
                'template_name' => $template->template_name,
                'form_sections' => array_keys($formData)
            ]);

            // Map form data to Docuseal field format
            $docusealFields = $this->mapFormDataToDocusealFields($formData, $template);

            // Create Docuseal submission with pre-filled data
            $submission = $this->docuSealService->createSubmissionForQuickRequest(
                $template->docuseal_template_id,
                config('docuseal.integration_email', 'limitless@mscwoundcare.com'),
                'provider@example.com', // Default provider email
                'Healthcare Provider', // Default provider name
                $docusealFields
            );

            Log::info('IVR form generated successfully', [
                'submission_id' => $submission['id'] ?? 'unknown',
                'template_name' => $template->template_name
            ]);

            return [
                'submission_url' => $submission['url'] ?? '',
                'submission_id' => $submission['id'] ?? '',
                'template_name' => $template->template_name,
                'mapped_fields_count' => count($docusealFields)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to generate pre-filled IVR', [
                'error' => $e->getMessage(),
                'template_id' => $template->id ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Validate form data and return detailed validation results
     */
    public function validateFormData(array $formData, string $section = 'all'): array
    {
        $validation = [
            'results' => [],
            'errors' => [],
            'warnings' => [],
            'missing_required' => [],
            'suggestions' => [],
            'completeness_percentage' => 0
        ];

        try {
            // Define required fields for each section
            $requiredFields = $this->getRequiredFieldsBySection($section);
            
            // Validate each section
            $sectionsToValidate = $section === 'all' 
                ? ['patient', 'provider', 'insurance', 'clinical'] 
                : [$section];

            $totalFields = 0;
            $completedFields = 0;

            foreach ($sectionsToValidate as $sectionName) {
                $sectionData = $formData[$sectionName] ?? [];
                $sectionRequired = $requiredFields[$sectionName] ?? [];
                
                $sectionValidation = $this->validateSection($sectionName, $sectionData, $sectionRequired);
                
                $validation['results'][$sectionName] = $sectionValidation;
                $validation['errors'] = array_merge($validation['errors'], $sectionValidation['errors']);
                $validation['warnings'] = array_merge($validation['warnings'], $sectionValidation['warnings']);
                $validation['missing_required'] = array_merge($validation['missing_required'], $sectionValidation['missing_required']);
                
                $totalFields += count($sectionRequired);
                $completedFields += count(array_filter($sectionData, fn($value) => !empty($value)));
            }

            // Calculate completeness percentage
            $validation['completeness_percentage'] = $totalFields > 0 
                ? round(($completedFields / $totalFields) * 100, 2) 
                : 0;

            // Add suggestions for improvement
            $validation['suggestions'] = $this->generateValidationSuggestions($formData, $validation);

            Log::info('Form validation completed', [
                'section' => $section,
                'completeness' => $validation['completeness_percentage'],
                'errors_count' => count($validation['errors']),
                'warnings_count' => count($validation['warnings'])
            ]);

        } catch (\Exception $e) {
            Log::error('Form validation failed', [
                'error' => $e->getMessage(),
                'section' => $section,
                'trace' => $e->getTraceAsString()
            ]);
            
            $validation['errors'][] = 'Validation process failed: ' . $e->getMessage();
        }

        return $validation;
    }

    /**
     * Map form data to Docuseal field format
     */
    private function mapFormDataToDocusealFields(array $formData, $template): array
    {
        $docusealFields = [];

        // Patient information mapping
        if (isset($formData['patient'])) {
            $patient = $formData['patient'];
            $docusealFields = array_merge($docusealFields, [
                'patient_name' => ($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''),
                'patient_first_name' => $patient['first_name'] ?? '',
                'patient_last_name' => $patient['last_name'] ?? '',
                'patient_dob' => $patient['date_of_birth'] ?? '',
                'patient_phone' => $patient['phone'] ?? '',
                'patient_address' => $patient['address'] ?? '',
                'patient_city' => $patient['city'] ?? '',
                'patient_state' => $patient['state'] ?? '',
                'patient_zip' => $patient['zip_code'] ?? ''
            ]);
        }

        // Provider information mapping
        if (isset($formData['provider'])) {
            $provider = $formData['provider'];
            $docusealFields = array_merge($docusealFields, [
                'provider_name' => $provider['name'] ?? '',
                'provider_npi' => $provider['npi'] ?? '',
                'provider_phone' => $provider['phone'] ?? '',
                'provider_fax' => $provider['fax'] ?? '',
                'provider_address' => $provider['address'] ?? ''
            ]);
        }

        // Insurance information mapping
        if (isset($formData['insurance'])) {
            $insurance = $formData['insurance'];
            $docusealFields = array_merge($docusealFields, [
                'primary_insurance' => $insurance['primary_insurance_name'] ?? '',
                'primary_member_id' => $insurance['primary_member_id'] ?? '',
                'primary_group_number' => $insurance['primary_group_number'] ?? '',
                'secondary_insurance' => $insurance['secondary_insurance_name'] ?? '',
                'secondary_member_id' => $insurance['secondary_member_id'] ?? ''
            ]);
        }

        // Clinical information mapping
        if (isset($formData['clinical'])) {
            $clinical = $formData['clinical'];
            $docusealFields = array_merge($docusealFields, [
                'primary_diagnosis' => $clinical['primary_diagnosis'] ?? '',
                'diagnosis_description' => $clinical['diagnosis_description'] ?? '',
                'wound_location' => $clinical['wound_location'] ?? '',
                'wound_size' => $clinical['wound_size'] ?? '',
                'wound_type' => $clinical['wound_type'] ?? '',
                'products_requested' => $clinical['products_requested'] ?? ''
            ]);
        }

        // Filter out empty values
        return array_filter($docusealFields, fn($value) => !empty($value));
    }

    /**
     * Get required fields by section
     */
    private function getRequiredFieldsBySection(string $section): array
    {
        $requiredFields = [
            'patient' => [
                'first_name', 'last_name', 'date_of_birth', 'phone'
            ],
            'provider' => [
                'name', 'npi'
            ],
            'insurance' => [
                'primary_insurance_name', 'primary_member_id'
            ],
            'clinical' => [
                'primary_diagnosis', 'wound_location'
            ]
        ];

        return $section === 'all' ? $requiredFields : [$section => $requiredFields[$section] ?? []];
    }

    /**
     * Validate a specific section
     */
    private function validateSection(string $sectionName, array $sectionData, array $requiredFields): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'missing_required' => []
        ];

        // Check required fields
        foreach ($requiredFields as $field) {
            if (empty($sectionData[$field])) {
                $result['missing_required'][] = $field;
                $result['valid'] = false;
            }
        }

        // Section-specific validation
        switch ($sectionName) {
            case 'patient':
                $result = $this->validatePatientSection($sectionData, $result);
                break;
            case 'provider':
                $result = $this->validateProviderSection($sectionData, $result);
                break;
            case 'insurance':
                $result = $this->validateInsuranceSection($sectionData, $result);
                break;
            case 'clinical':
                $result = $this->validateClinicalSection($sectionData, $result);
                break;
        }

        return $result;
    }

    /**
     * Validate patient section
     */
    private function validatePatientSection(array $data, array $result): array
    {
        // Date of birth validation
        if (!empty($data['date_of_birth'])) {
            $dob = \DateTime::createFromFormat('Y-m-d', $data['date_of_birth']);
            if (!$dob || $dob->format('Y-m-d') !== $data['date_of_birth']) {
                $result['errors'][] = 'Invalid date of birth format';
                $result['valid'] = false;
            } elseif ($dob > new \DateTime()) {
                $result['errors'][] = 'Date of birth cannot be in the future';
                $result['valid'] = false;
            }
        }

        // Phone validation
        if (!empty($data['phone'])) {
            $phone = preg_replace('/[^0-9]/', '', $data['phone']);
            if (strlen($phone) !== 10) {
                $result['warnings'][] = 'Phone number should be 10 digits';
            }
        }

        return $result;
    }

    /**
     * Validate provider section
     */
    private function validateProviderSection(array $data, array $result): array
    {
        // NPI validation
        if (!empty($data['npi'])) {
            $npi = preg_replace('/[^0-9]/', '', $data['npi']);
            if (strlen($npi) !== 10) {
                $result['errors'][] = 'NPI must be exactly 10 digits';
                $result['valid'] = false;
            }
        }

        return $result;
    }

    /**
     * Validate insurance section
     */
    private function validateInsuranceSection(array $data, array $result): array
    {
        // Check if secondary insurance is provided without member ID
        if (!empty($data['secondary_insurance_name']) && empty($data['secondary_member_id'])) {
            $result['warnings'][] = 'Secondary insurance name provided but member ID is missing';
        }

        return $result;
    }

    /**
     * Validate clinical section
     */
    private function validateClinicalSection(array $data, array $result): array
    {
        // ICD-10 code validation (basic format check)
        if (!empty($data['primary_diagnosis'])) {
            if (!preg_match('/^[A-Z][0-9]{2}(\.[0-9A-Z]*)?$/', $data['primary_diagnosis'])) {
                $result['warnings'][] = 'Primary diagnosis does not appear to be a valid ICD-10 code format';
            }
        }

        return $result;
    }

    /**
     * Generate validation suggestions
     */
    private function generateValidationSuggestions(array $formData, array $validation): array
    {
        $suggestions = [];

        if ($validation['completeness_percentage'] < 50) {
            $suggestions[] = 'Form is less than 50% complete. Consider filling in more required fields before submission.';
        }

        if (count($validation['missing_required']) > 0) {
            $suggestions[] = 'Complete all required fields: ' . implode(', ', $validation['missing_required']);
        }

        if (count($validation['warnings']) > 0) {
            $suggestions[] = 'Review and address the warnings to improve data quality.';
        }

        // Specific suggestions based on data quality
        if (empty($formData['clinical']['products_requested'] ?? '')) {
            $suggestions[] = 'Specify the wound care products being requested for faster processing.';
        }

        return $suggestions;
    }
}

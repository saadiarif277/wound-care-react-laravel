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
            $providers[] = [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'credentials' => $user->providerProfile?->credentials ?? $user->providerCredentials->pluck('credential_number')->implode(', ') ?? null,
                'npi' => $user->npi_number ?? $user->providerCredentials->where('credential_type', 'npi_number')->first()?->credential_number ?? null,
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
            ->with('activeSizes') // Load the ProductSize relationship
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
                    'manufacturer' => $product->manufacturer,
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
}

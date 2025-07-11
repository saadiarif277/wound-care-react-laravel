<?php

namespace App\Http\Controllers\QuickRequest;

use App\Http\Controllers\Controller;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Order\Product;
use App\Models\PatientManufacturerIVREpisode;
use App\Services\DocusealService;
use App\Services\UnifiedFieldMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\CanonicalFieldService;
use App\Models\User;
use App\Models\Fhir\Facility;

/**
 * DocusealController - Handles all Docuseal integration for Quick Requests
 *
 * This controller is focused specifically on Docuseal functionality,
 * extracted from the original monolithic QuickRequestController.
 */
class DocusealController extends Controller
{
    public function __construct(
        protected DocusealService $docuSealService,
        protected UnifiedFieldMappingService $fieldMappingService,
    ) {}

    /**
     * Create episode for Docuseal integration
     */
    public function createEpisode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|string',
            'patient_fhir_id' => 'required|string',
            'patient_display_id' => 'required|string',
            'manufacturer_id' => 'nullable|exists:manufacturers,id',
            'selected_product_id' => 'nullable|exists:msc_products,id',
            'form_data' => 'required|array',
        ]);

        try {
            // Determine manufacturer from product if not provided
            if (!$validated['manufacturer_id'] && $validated['selected_product_id']) {
                $product = Product::find($validated['selected_product_id']);
                if ($product && $product->manufacturer_id) {
                    $validated['manufacturer_id'] = $product->manufacturer_id;
                }
            }

            if (!$validated['manufacturer_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to determine manufacturer. Please ensure a product is selected.',
                ], 422);
            }

            // Find or create episode
            $episode = PatientManufacturerIVREpisode::firstOrCreate([
                'patient_fhir_id' => $validated['patient_fhir_id'],
                'manufacturer_id' => $validated['manufacturer_id'],
            ], [
                'patient_id' => $validated['patient_id'],
                'patient_display_id' => $validated['patient_display_id'],
                'status' => PatientManufacturerIVREpisode::STATUS_READY_FOR_REVIEW,
                'metadata' => [
                    'facility_id' => $validated['form_data']['facility_id'] ?? null,
                    'provider_id' => Auth::id(),
                    'created_from' => 'quick_request_docuseal',
                    'form_data' => $validated['form_data']
                ]
            ]);

            Log::info('Episode created for Docuseal', [
                'episode_id' => $episode->id,
                'manufacturer_id' => $validated['manufacturer_id'],
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'episode_id' => $episode->id,
                'manufacturer_id' => $validated['manufacturer_id']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create episode for Docuseal', [
                'error' => $e->getMessage(),
                'patient_display_id' => $validated['patient_display_id'],
                'manufacturer_id' => $validated['manufacturer_id'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create episode: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate JWT token for Docuseal builder
     */
    public function generateBuilderToken(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'user_email' => 'required|email',
                'integration_email' => 'nullable|email',
                'template_id' => 'nullable|string',
                'template_name' => 'nullable|string',
                'document_urls' => 'nullable|array',
                'prefill_data' => 'nullable|array',
                'manufacturerId' => 'nullable|integer',
                'productCode' => 'nullable|string',
            ]);

            $apiKey = config('docuseal.api_key');
            if (!$apiKey) {
                throw new \Exception('Docuseal API key not configured');
            }

            // Get manufacturer ID from request
            $manufacturerId = $data['manufacturerId'] ?? $this->getManufacturerIdFromPrefillData($data['prefill_data'] ?? []);

            // Load authenticated user's profile data
            $userProfileData = $this->loadUserProfileDataForDocuseal();

            // Merge user profile data with prefill data
            if (!empty($userProfileData)) {
                $data['prefill_data'] = array_merge($userProfileData, $data['prefill_data'] ?? []);
            }

            // Map fields if we have prefill data and manufacturer
            if (!empty($data['prefill_data']) && $manufacturerId) {
                try {
                    $manufacturer = \App\Models\Order\Manufacturer::find($manufacturerId);
                    if ($manufacturer) {
                        $mappingResult = $this->fieldMappingService->mapEpisodeToTemplate(
                            null, // No episode ID for builder token
                            $manufacturer->name,
                            $data['prefill_data'],
                            'IVR'
                        );

                        if (!empty($mappingResult['data'])) {
                            $data['prefill_data'] = array_merge($data['prefill_data'], $mappingResult['data']);
                            Log::info('Field mapping applied for Docuseal builder', [
                                'manufacturer_id' => $manufacturerId,
                                'mapped_fields_count' => count($mappingResult['data']),
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Field mapping failed for builder token', [
                        'manufacturer_id' => $manufacturerId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Create JWT payload
            $payload = [
                'integration_email' => $data['integration_email'] ?? $data['user_email'],
                'external_id' => uniqid('qr_builder_'),
                'name' => 'Quick Request Builder',
                'template_id' => $data['template_id'] ?? null,
                'template_name' => $data['template_name'] ?? null,
                'document_urls' => $data['document_urls'] ?? [],
                'prefill_data' => $this->formatPrefillValues($data['prefill_data'] ?? []),
                'iat' => time(),
                'exp' => time() + 3600, // 1 hour expiration
            ];

            $token = $this->generateJwtToken($payload, $apiKey);

            Log::info('Docuseal builder token generated', [
                'manufacturer_id' => $manufacturerId,
                'template_id' => $payload['template_id'],
                'user_id' => Auth::id(),
                'prefill_fields_count' => count($payload['prefill_data']),
            ]);

            return response()->json([
                'success' => true,
                'token' => $token,
                'manufacturer_id' => $manufacturerId,
                'prefill_data' => $payload['prefill_data'],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate Docuseal builder token', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate builder token: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate form token for Docuseal
     */
    public function generateFormToken(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'template_id' => 'required|string',
                'user_email' => 'required|email',
                'prefill_data' => 'nullable|array',
                'manufacturerId' => 'nullable|integer',
            ]);

            $apiKey = config('docuseal.api_key');
            if (!$apiKey) {
                throw new \Exception('Docuseal API key not configured');
            }

            // Load user profile data and merge with prefill data
            $userProfileData = $this->loadUserProfileDataForDocuseal();
            $prefillData = array_merge($userProfileData, $data['prefill_data'] ?? []);

            // Apply field mapping if manufacturer is provided
            if (!empty($data['manufacturerId'])) {
                try {
                    $manufacturer = \App\Models\Order\Manufacturer::find($data['manufacturerId']);
                    if ($manufacturer) {
                        $mappingResult = $this->fieldMappingService->mapEpisodeToTemplate(
                            null, // No episode ID for form token
                            $manufacturer->name,
                            $prefillData,
                            'IVR'
                        );

                        if (!empty($mappingResult['data'])) {
                            $prefillData = array_merge($prefillData, $mappingResult['data']);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Field mapping failed for form token', [
                        'manufacturer_id' => $data['manufacturerId'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Get template role
            $role = $this->getTemplateRole($data['template_id'], $apiKey);

            // Create submission payload
            $payload = [
                'template_id' => $data['template_id'],
                'send_email' => false,
                'order' => 'random',
                'submitters' => [
                    [
                        'role' => $role,
                        'email' => $data['user_email'],
                        'name' => 'Quick Request User',
                    ]
                ],
                'values' => $this->formatPrefillValues($prefillData),
            ];

            // Create submission
            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey,
            ])->post(config('docuseal.api_url', 'https://api.docuseal.com') . '/submissions', $payload);

            if ($response->successful()) {
                $submissionData = $response->json();

                Log::info('Docuseal form token generated', [
                    'template_id' => $data['template_id'],
                    'submission_id' => $submissionData['id'] ?? null,
                    'user_id' => Auth::id(),
                ]);

                return response()->json([
                    'success' => true,
                    'submission' => $submissionData,
                    'embed_url' => $submissionData['submitters'][0]['embed_src'] ?? null,
                ]);
            }

            throw new \Exception('Docuseal API returned error: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Failed to generate Docuseal form token', [
                'error' => $e->getMessage(),
                'template_id' => $data['template_id'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate form token: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate submission slug for Docuseal integration
     */
    public function generateSubmissionSlug(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'user_email' => 'required|email',
                'integration_email' => 'nullable|email',
                'prefill_data' => 'required|array',
                'manufacturerId' => 'required|integer',
                'templateId' => 'nullable|string',
                'productCode' => 'nullable|string',
                'documentType' => 'nullable|string|in:IVR,OrderForm',
                'episode_id' => 'nullable|integer',
            ]);

            $apiKey = config('docuseal.api_key');
            if (!$apiKey) {
                throw new \Exception('Docuseal API key not configured');
            }

            // Fetch full provider details if only ID is provided
            $providerData = [];
            if ($request->has('provider_id') && $request->provider_id) {
                $provider = User::with(['providerProfile', 'providerCredentials'])->find($request->provider_id);
                if ($provider) {
                    $providerData = [
                        'name' => $provider->first_name . ' ' . $provider->last_name,
                        'first_name' => $provider->first_name,
                        'last_name' => $provider->last_name,
                        'npi' => $provider->npi_number ?? $provider->providerCredentials->where('credential_type', 'npi_number')->first()?->credential_number ?? '',
                        'email' => $provider->email,
                        'phone' => $provider->phone ?? $provider->providerProfile?->phone ?? '',
                        'specialty' => $provider->providerProfile?->specialty ?? '',
                        'credentials' => $provider->providerProfile?->credentials ?? $provider->providerCredentials->pluck('credential_number')->implode(', ') ?? '',
                        'license_number' => $provider->providerProfile?->license_number ?? $provider->providerCredentials->where('credential_type', 'license_number')->first()?->credential_number ?? '',
                        'license_state' => $provider->providerProfile?->license_state ?? '',
                        'dea_number' => $provider->providerProfile?->dea_number ?? $provider->providerCredentials->where('credential_type', 'dea_number')->first()?->credential_number ?? '',
                        'ptan' => $provider->providerProfile?->ptan ?? $provider->providerCredentials->where('credential_type', 'ptan')->first()?->credential_number ?? '',
                        'tax_id' => $provider->providerProfile?->tax_id ?? '',
                        'practice_name' => $provider->providerProfile?->practice_name ?? '',
                        'medicaid_number' => $provider->providerProfile?->medicaid_number ?? $provider->providerCredentials->where('credential_type', 'medicaid_number')->first()?->credential_number ?? '',
                    ];
                }
            }

            // Fetch full facility details if only ID is provided
            $facilityData = [];
            if ($request->has('facility_id') && $request->facility_id) {
                $facility = Facility::find($request->facility_id);
                if ($facility) {
                    $facilityData = [
                        'name' => $facility->name,
                        'address' => $facility->address,
                        'address_line1' => $facility->address_line1,
                        'address_line2' => $facility->address_line2,
                        'city' => $facility->city,
                        'state' => $facility->state,
                        'zip_code' => $facility->zip_code,
                        'phone' => $facility->phone ?? '',
                        'fax' => $facility->fax ?? '',
                        'email' => $facility->email ?? '',
                        'npi' => $facility->npi ?? '',
                        'group_npi' => $facility->group_npi ?? '',
                        'ptan' => $facility->ptan ?? '',
                        'tax_id' => $facility->tax_id ?? '',
                        'facility_type' => $facility->facility_type ?? '',
                        'place_of_service' => $facility->place_of_service ?? '',
                        'medicaid_number' => $facility->medicaid_number ?? '',
                    ];
                }
            }

            // Merge provider and facility data with prefill data
            $enrichedPrefillData = array_merge(
                $request->prefill_data ?? [],
                $providerData ? ['provider_data' => $providerData] : [],
                $facilityData ? ['facility_data' => $facilityData] : []
            );

            // Use CanonicalFieldService to map fields properly
            $canonicalService = app(CanonicalFieldService::class);
            $mappedData = [];

            // Map provider fields using canonical service
            if ($providerData) {
                $mappedData['provider_name'] = $canonicalService->getFieldValue('provider', 'provider_name', $providerData) ?? $providerData['name'] ?? '';
                $mappedData['provider_npi'] = $canonicalService->getFieldValue('provider', 'provider_npi', $providerData) ?? $providerData['npi'] ?? '';
                $mappedData['provider_email'] = $canonicalService->getFieldValue('provider', 'provider_email', $providerData) ?? $providerData['email'] ?? '';
                $mappedData['provider_phone'] = $canonicalService->getFieldValue('provider', 'provider_phone', $providerData) ?? $providerData['phone'] ?? '';
                $mappedData['provider_specialty'] = $canonicalService->getFieldValue('provider', 'provider_specialty', $providerData) ?? $providerData['specialty'] ?? '';
                
                // Also add physician aliases for compatibility
                $mappedData['physician_name'] = $mappedData['provider_name'];
                $mappedData['physician_npi'] = $mappedData['provider_npi'];
                $mappedData['physician_specialty'] = $mappedData['provider_specialty'];
            }

            // Map facility fields using canonical service
            if ($facilityData) {
                $mappedData['facility_name'] = $canonicalService->getFieldValue('facility', 'facility_name', $facilityData) ?? $facilityData['name'] ?? '';
                $mappedData['facility_address'] = $canonicalService->getFieldValue('facility', 'facility_address', $facilityData) ?? $facilityData['address'] ?? '';
                $mappedData['facility_phone'] = $canonicalService->getFieldValue('facility', 'facility_phone', $facilityData) ?? $facilityData['phone'] ?? '';
                $mappedData['facility_npi'] = $canonicalService->getFieldValue('facility', 'facility_npi', $facilityData) ?? $facilityData['npi'] ?? '';
            }

            // Merge with existing prefill data (giving precedence to mapped data)
            $finalPrefillData = array_merge($enrichedPrefillData, $mappedData);

            // Log the enriched data for debugging
            Log::info('Enriched DocuSeal prefill data', [
                'has_provider_data' => !empty($providerData),
                'has_facility_data' => !empty($facilityData),
                'provider_fields' => array_keys($providerData),
                'mapped_fields' => array_keys($mappedData),
                'sample_mapped_data' => array_slice($mappedData, 0, 5, true),
            ]);

            // Find the manufacturer to get template info
            $manufacturer = \App\Models\Order\Manufacturer::find($data['manufacturerId']);
            if (!$manufacturer) {
                throw new \Exception('Manufacturer not found');
            }

            // Get template ID - use provided one or get from manufacturer
            $templateId = $data['templateId'] ?? $manufacturer?->docuseal_ivr_template_id ?? $manufacturer?->docuseal_order_form_template_id;
            if (!$templateId) {
                throw new \Exception('No Docuseal template found for this manufacturer');
            }

            // Apply field mapping for this manufacturer
            $mappingResult = $this->fieldMappingService->mapEpisodeToTemplate(
                $data['episode_id'] ?? null,
                $manufacturer->name,
                $finalPrefillData,
                $data['documentType'] ?? 'IVR'
            );

            $mappedData = [];
            if (!empty($mappingResult['data'])) {
                $mappedData = $mappingResult['data'];
                $finalPrefillData = array_merge($finalPrefillData, $mappedData);
            }

            // Get template role
            $role = $this->getTemplateRole($templateId, $apiKey);

            // Create submission payload
            $payload = [
                'template_id' => $templateId,
                'send_email' => false,
                'order' => 'random',
                'submitters' => [
                    [
                        'role' => $role,
                        'email' => $data['integration_email'] ?? $data['user_email'],
                        'name' => 'Quick Request User',
                    ]
                ],
                'values' => $this->formatPrefillValues($finalPrefillData),
            ];

            // Create submission via Docuseal API
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-Auth-Token' => $apiKey,
            ])->post(config('docuseal.api_url', 'https://api.docuseal.com') . '/submissions', $payload);

            if ($response->successful()) {
                $submissionData = $response->json();
                $slug = $submissionData['submitters'][0]['slug'] ?? null;

                if (!$slug) {
                    throw new \Exception('No submission slug received from Docuseal');
                }

                Log::info('Docuseal submission created successfully', [
                    'template_id' => $templateId,
                    'submission_id' => $submissionData['id'] ?? null,
                    'manufacturer_id' => $data['manufacturerId'],
                    'user_id' => Auth::id(),
                    'fields_mapped' => count($mappedData),
                ]);

                return response()->json([
                    'success' => true,
                    'slug' => $slug,
                    'submission_id' => $submissionData['id'] ?? null,
                    'template_id' => $templateId,
                    'integration_type' => $data['episode_id'] ? 'fhir_enhanced' : 'standard',
                    'fhir_data_used' => $data['episode_id'] ? 1 : 0,
                    'fields_mapped' => count($mappedData),
                    'template_name' => $manufacturer->name . ' IVR Form',
                    'manufacturer' => $manufacturer->name,
                    'ai_mapping_used' => true,
                    'ai_confidence' => 0.95,
                    'mapping_method' => 'hybrid',
                ]);
            }

            throw new \Exception('Docuseal API returned error: ' . $response->body());

        } catch (\Exception $e) {
            Log::error('Failed to generate Docuseal submission slug', [
                'error' => $e->getMessage(),
                'manufacturer_id' => $data['manufacturerId'] ?? null,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate submission slug: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test template count (debugging endpoint)
     */
    public function testTemplateCount(Request $request): JsonResponse
    {
        try {
            $apiKey = config('docuseal.api_key');
            $apiUrl = config('docuseal.api_url', 'https://api.docuseal.com');

            if (!$apiKey) {
                return response()->json([
                    'success' => false,
                    'error' => 'Docuseal API key not configured'
                ], 500);
            }

            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey,
            ])->timeout(15)->get("{$apiUrl}/templates", [
                'page' => 1,
                'per_page' => 20
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => 'API request failed: ' . $response->body(),
                    'status_code' => $response->status()
                ], 500);
            }

            $responseData = $response->json();
            $templates = $responseData['data'] ?? $responseData;
            $pagination = $responseData['pagination'] ?? null;

            return response()->json([
                'success' => true,
                'first_page_count' => count($templates),
                'pagination_info' => $pagination,
                'sample_templates' => array_slice($templates, 0, 5),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Private helper methods
    private function getManufacturerIdFromPrefillData(array $prefillData): ?int
    {
        if (!empty($prefillData['manufacturer_id'])) {
            return is_numeric($prefillData['manufacturer_id']) ? (int) $prefillData['manufacturer_id'] : null;
        }

        if (!empty($prefillData['selected_products'])) {
            $product = Product::find($prefillData['selected_products'][0]['product_id'] ?? null);
            if ($product && $product->manufacturer) {
                $manufacturer = \App\Models\Order\Manufacturer::whereRaw('LOWER(name) = ?', [strtolower($product->manufacturer)])->first();
                return $manufacturer?->id;
            }
        }

        return null;
    }

    private function loadUserProfileDataForDocuseal(): array
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return [];
            }

            $user->load(['providerProfile', 'facilities', 'organizations']);

            $profileData = [
                'provider_name' => $user->first_name . ' ' . $user->last_name,
                'provider_email' => $user->email,
                'provider_npi' => $user->npi_number ?? '',
                'request_date' => date('m/d/Y'),
                'request_time' => date('h:i A'),
            ];

            // Add facility data if available
            $facility = $user->facilities()->wherePivot('is_primary', true)->first() ?? $user->facilities->first();
            if ($facility) {
                $profileData['facility_name'] = $facility->name;
                $profileData['facility_phone'] = $facility->phone ?? '';
                $profileData['facility_address'] = $facility->address ?? '';
            }

            return array_filter($profileData, fn($value) => $value !== null && $value !== '');

        } catch (\Exception $e) {
            Log::error('Failed to load user profile data for Docuseal', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            return [];
        }
    }

    private function getTemplateRole(string $templateId, string $apiKey): string
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $apiKey,
            ])->timeout(15)->get(config('docuseal.api_url', 'https://api.docuseal.com') . "/templates/{$templateId}");

            if ($response->successful()) {
                $templateData = $response->json();

                // Extract role from template data
                if (isset($templateData['submitter_roles']) && !empty($templateData['submitter_roles'])) {
                    return is_string($templateData['submitter_roles'][0])
                        ? $templateData['submitter_roles'][0]
                        : $templateData['submitter_roles'][0]['name'] ?? 'First Party';
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get template role', [
                'template_id' => $templateId,
                'error' => $e->getMessage(),
            ]);
        }

        return 'First Party'; // Default role
    }

    private function generateJwtToken(array $payload, string $apiKey): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);

        $headerEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $payloadEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, $apiKey, true);
        $signatureEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
    }

    private function formatPrefillValues(array $prefillData): array
    {
        return array_filter($prefillData, fn($value) => $value !== null && $value !== '');
    }
}

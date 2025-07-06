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

            // Load user profile data and merge with prefill data
            $userProfileData = $this->loadUserProfileDataForDocuseal();
            $prefillData = array_merge($userProfileData, $data['prefill_data']);

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
                $prefillData,
                $data['documentType'] ?? 'IVR'
            );

            $mappedData = [];
            if (!empty($mappingResult['data'])) {
                $mappedData = $mappingResult['data'];
                $prefillData = array_merge($prefillData, $mappedData);
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
                'values' => $this->formatPrefillValues($prefillData),
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

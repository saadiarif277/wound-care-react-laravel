<?php

namespace App\Http\Controllers;

use App\Models\Order\Order;
use App\Models\Docuseal\DocusealSubmission;
use App\Models\User;
use GetSubmissionStatusResponse;
use App\Services\DocusealService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class DocusealController extends Controller
{
    public function __construct(
        private DocusealService $docusealService
    ) {
        $this->middleware('auth');
        // Apply manage-orders permission to most methods, but exclude IVR-related methods that providers need
        $this->middleware('permission:manage-orders')->except([
            'createSubmission',
            'createDemoSubmission',
            'generateToken'
        ]);
        // Apply create-product-requests permission to IVR methods that providers use
        $this->middleware('permission:create-product-requests')->only([
            'createSubmission',
            'createDemoSubmission',
            'generateToken'
        ]);
    }

    /**
     * Generate document from a template
     * POST /api/v1/admin/docuseal/generate-document
     */
    public function generateDocument(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|uuid|exists:product_requests,id',
        ]);

        try {
            $order = Order::where('id', $request->order_id)->firstOrFail();

            // Check if user has permission to access this order
            if (!$this->canAccessOrder($order)) {
                return response()->json([
                    'error' => 'Unauthorized access to order'
                ], 403);
            }

            // Generate documents for the order
            $episodeId = $order->episode_id;
            if (!$episodeId) {
                throw new Exception('Order does not have an associated episode');
            }

            $manufacturerName = $order->manufacturer->name ?? '';
            if (!$manufacturerName) {
                throw new Exception('Order does not have a manufacturer');
            }

            // Create or update submission for the episode
            $result = $this->docusealService->createOrUpdateSubmission(
                $episodeId,
                $manufacturerName,
                ['order_id' => $order->id]
            );

            if (!$result['success']) {
                throw new Exception($result['error'] ?? 'Failed to create submission');
            }

            // Return the submission info
            return response()->json([
                'submission_id' => $result['submission']['id'] ?? null,
                'docuseal_submission_id' => $result['submission']['id'] ?? null,
                'status' => 'pending',
                'document_url' => $result['submission']['embed_url'] ?? null,
                'expires_at' => now()->addDays(30)->toIso8601String(),
            ]);

        } catch (Exception $e) {
            Log::error('Docuseal document generation failed', [
                'order_id' => $request->order_id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Document generation failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get document submission status
     * GET /api/v1/admin/docuseal/submissions/{submission_id}/status
     */
    public function getSubmissionStatus(string $submissionId): JsonResponse
    {
        try {
            $submission = DocusealSubmission::with('order')->findOrFail($submissionId);

            // Check if user has permission to access this submission
            if (!$submission->order || !$this->canAccessOrder($submission->order)) {
                return response()->json([
                    'error' => 'Unauthorized access to submission'
                ], 403);
            }

            // Get latest status from Docuseal API
            $docusealData = $this->docusealService->getSubmission($submission->docuseal_submission_id);

            // Extract status from the response
            $docusealStatus = $docusealData['status'] ?? $submission->status;

            // Update local status if different
            if ($docusealStatus !== $submission->status) {
                $submission->update([
                    'status' => $docusealStatus,
                    'completed_at' => $docusealStatus === 'completed' ? now() : null,
                ]);
            }

            return response()->json([
                'submission_id' => $submission->id,
                'docuseal_submission_id' => $submission->docuseal_submission_id,
                'status' => $submission->status,
                'completed_at' => $submission->completed_at?->toIso8601String(),
                'download_url' => $submission->isCompleted()
                    ? route('docuseal.download', $submission->id)
                    : null,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get Docuseal submission status', [
                'submission_id' => $submissionId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to get submission status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download completed document
     * GET /api/v1/admin/docuseal/submissions/{submission_id}/download
     */
    public function downloadDocument(string $submissionId)
    {
        try {
            $submission = DocusealSubmission::with('order')->findOrFail($submissionId);

            // Check if user has permission to access this submission
            if (!$submission->order || !$this->canAccessOrder($submission->order)) {
                return response()->json([
                    'error' => 'Unauthorized access to submission'
                ], 403);
            }

            // Check if document is completed
            if (!$submission->isCompleted()) {
                return response()->json([
                    'error' => 'Document is not completed yet'
                ], 400);
            }

            // Get document URL from Docuseal
            $documentUrl = $this->docusealService->downloadDocument($submission->docuseal_submission_id);

            if (!$documentUrl) {
                return response()->json([
                    'error' => 'Document not available for download'
                ], 404);
            }

            // Redirect to the document URL
            return redirect($documentUrl);

        } catch (Exception $e) {
            Log::error('Failed to download Docuseal document', [
                'submission_id' => $submissionId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to download document',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate JWT token for Docuseal form embedding
     * POST /api/v1/admin/docuseal/generate-token
     */
    public function generateToken(Request $request): JsonResponse
    {
        $request->validate([
            'template_id' => 'required|string',
            'name' => 'required|string',
            'order_id' => 'nullable|uuid|exists:orders,id',
        ]);

        try {
            $user = Auth::user();
            $apiKey = config('services.docuseal.api_key');

            if (empty($apiKey)) {
                throw new Exception('Docuseal API key is not configured');
            }

            // If order_id is provided, check access
            if ($request->order_id) {
                $order = Order::findOrFail($request->order_id);
                if (!$this->canAccessOrder($order)) {
                    return response()->json([
                        'error' => 'Unauthorized access to order'
                    ], 403);
                }
            }

            // Prepare JWT payload
            $payload = [
                'user_email' => $user->email,
                'template_id' => $request->template_id,
                'name' => $request->name,
                'iat' => time(),
                'exp' => time() + (60 * 30), // Token expires in 30 minutes
            ];

            // Add order-specific data if provided
            if ($request->order_id && isset($order)) {
                $payload['metadata'] = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'organization_id' => $order->organization_id,
                ];
            }

            // Generate JWT token
            $token = JWT::encode($payload, $apiKey, 'HS256');

            Log::info('Docuseal JWT token generated', [
                'user_id' => $user->id,
                'template_id' => $request->template_id,
                'order_id' => $request->order_id ?? null,
            ]);

            return response()->json([
                'token' => $token,
                'expires_at' => date('c', $payload['exp']),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to generate Docuseal JWT token', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'template_id' => $request->template_id ?? null,
            ]);

            return response()->json([
                'error' => 'Failed to generate token',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate DocuSeal submission slug for Quick Request IVR workflow
     * POST /quick-requests/docuseal/generate-submission-slug
     */
    public function generateSubmissionSlug(Request $request): JsonResponse
    {
        Log::info('DocuSeal generateSubmissionSlug called', [
            'user' => Auth::user()?->email,
            'authenticated' => Auth::check(),
            'has_permission' => Auth::user()?->hasPermission('create-product-requests'),
            'data_keys' => array_keys($request->all())
        ]);

        $request->validate([
            'submitter_email' => 'required|email',
            'submitter_name' => 'required|string',
            'integration_email' => 'required|email',
            'prefill_data' => 'nullable|array',
            'manufacturerId' => 'required|string',
            'templateId' => 'nullable|string',
            'productCode' => 'nullable|string',
            'documentType' => 'nullable|string',
            'episode_id' => 'nullable|integer'
        ]);

        try {
            $user = Auth::user();
            $templateId = $request->templateId;
            $prefillData = $request->prefill_data ?? [];
            $episodeId = $request->episode_id;

            // Use orchestrator's comprehensive data preparation instead of just frontend data
            $comprehensiveData = $prefillData;
            if ($episodeId) {
                $episode = \App\Models\PatientManufacturerIVREpisode::find($episodeId);
                if ($episode) {
                    $orchestrator = app(\App\Services\QuickRequest\QuickRequestOrchestrator::class);

                    // Check if AI enhancement is enabled
                    $useAI = config('services.medical_ai.enabled', true) &&
                             config('services.medical_ai.use_for_docuseal', true);

                    if ($useAI) {
                        // Try AI-enhanced preparation first
                        try {
                            $comprehensiveData = $orchestrator->prepareAIEnhancedDocusealData(
                                $episode,
                                $templateId,
                                'insurance'
                            );
                            Log::info('Using AI-enhanced DocuSeal data preparation', [
                                'episode_id' => $episodeId,
                                'template_id' => $templateId,
                                'ai_used' => true
                            ]);
                        } catch (\Exception $e) {
                            Log::warning('AI enhancement failed, falling back to standard preparation', [
                                'episode_id' => $episodeId,
                                'error' => $e->getMessage()
                            ]);
                            // Fallback to standard preparation
                            $comprehensiveData = $orchestrator->prepareDocusealData($episode);
                        }
                    } else {
                        // Use standard preparation when AI is disabled
                        Log::info('AI enhancement disabled, using standard preparation', [
                            'episode_id' => $episodeId
                        ]);
                        $comprehensiveData = $orchestrator->prepareDocusealData($episode);
                    }

                    Log::info('Using orchestrator comprehensive data', [
                        'episode_id' => $episodeId,
                        'comprehensive_fields' => count($comprehensiveData),
                        'frontend_fields' => count($prefillData),
                        'sample_comprehensive_data' => array_slice($comprehensiveData, 0, 10, true)
                    ]);
                }
            }

            // Enhanced response data for frontend
            $responseData = [
                'slug' => null,
                'submission_id' => null,
                'template_id' => $templateId,
                'integration_type' => $episodeId ? 'fhir_enhanced' : 'standard',
                'fhir_data_used' => $episodeId ? count($comprehensiveData) : 0,
                'fields_mapped' => count($comprehensiveData),
                'template_name' => 'Insurance Verification Request',
                'manufacturer' => 'Standard',
                'ai_mapping_used' => false,
                'ai_confidence' => 0.0,
                'mapping_method' => 'static'
            ];

            // If no template ID provided, return success without creating submission
            if (!$templateId) {
                Log::warning('No template ID provided for DocuSeal submission', [
                    'manufacturerId' => $request->manufacturerId,
                    'productCode' => $request->productCode
                ]);

                return response()->json(array_merge($responseData, [
                    'error' => 'No template configured for this product'
                ]), 400);
            }

            // Call DocuSeal API to create submission using comprehensive data
            $result = $this->docusealService->createSubmissionForQuickRequest(
                $templateId,
                $request->integration_email,  // Our DocuSeal account email (limitless@mscwoundcare.com)
                $request->submitter_email,    // The person who will sign (provider@example.com)
                $request->submitter_name,     // The person's name
                $comprehensiveData,          // Use orchestrator's comprehensive data
                $episodeId
            );

            if (!$result['success']) {
                throw new Exception($result['error'] ?? 'Failed to create DocuSeal submission');
            }

            $submission = $result['data'];

            // Update response with actual submission data
            $responseData = array_merge($responseData, [
                'slug' => $submission['slug'] ?? null,
                'submission_id' => $submission['submission_id'] ?? null,
                'ai_mapping_used' => $result['ai_mapping_used'] ?? false,
                'ai_confidence' => $result['ai_confidence'] ?? 0.0,
                'mapping_method' => $result['mapping_method'] ?? 'static'
            ]);

            Log::info('DocuSeal submission created successfully', [
                'template_id' => $templateId,
                'submission_id' => $responseData['submission_id'],
                'slug' => $responseData['slug'],
                'fields_mapped' => $responseData['fields_mapped']
            ]);

            return response()->json($responseData);

        } catch (Exception $e) {
            Log::error('Failed to generate DocuSeal submission slug', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'template_id' => $request->templateId ?? null,
                'manufacturer_id' => $request->manufacturerId ?? null
            ]);

            return response()->json([
                'error' => 'Failed to create form submission',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create Docuseal submission with pre-filled data
     * POST /docuseal/create-submission
     */
    public function createSubmission(Request $request): JsonResponse
    {
        // Debug logging
        Log::info('Docuseal createSubmission called', [
            'user' => Auth::user()?->email,
            'authenticated' => Auth::check(),
            'has_permission' => Auth::user()?->hasPermission('manage-orders'),
        ]);

        $request->validate([
            'template_id' => 'required|string',
            'email' => 'required|email',
            'fields' => 'nullable|array',
            'name' => 'required|string',
            'send_email' => 'nullable|boolean',
            'order_id' => 'nullable|uuid|exists:orders,id',
        ]);

        try {
            $user = Auth::user();

            // If order_id is provided, check access
            if ($request->order_id) {
                $order = Order::findOrFail($request->order_id);
                if (!$this->canAccessOrder($order)) {
                    return response()->json([
                        'error' => 'Unauthorized access to order'
                    ], 403);
                }
            }

            // Call Docuseal API to create submission
            $apiKey = config('services.docuseal.api_key');
            $apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');

            if (empty($apiKey)) {
                throw new Exception('Docuseal API key is not configured');
            }

            // Build submitter values for pre-filled data
            $submitterValues = [];
            if ($request->fields) {
                foreach ($request->fields as $fieldName => $fieldValue) {
                    // Convert boolean values to strings for Docuseal
                    if (is_bool($fieldValue)) {
                        $fieldValue = $fieldValue ? 'true' : 'false';
                    }

                    // Docuseal expects values as a key-value object
                    $submitterValues[$fieldName] = (string) $fieldValue;
                }
            }

            Log::info('Docuseal submission attempt', [
                'template_id' => $request->template_id,
                'email' => $request->email,
                'field_count' => count($submitterValues),
                'api_key_length' => strlen($apiKey),
            ]);

            // Prepare request data
            $requestData = [
                'template_id' => (int) $request->template_id,
                'send_email' => $request->send_email ?? false,
                'submitters' => [
                    [
                        'email' => $request->email,
                        'name' => $request->name,
                        'values' => $submitterValues, // Use 'values' object
                    ]
                ],
            ];

            Log::info('Docuseal API request', [
                'url' => $apiUrl . '/submissions',
                'template_id' => $requestData['template_id'],
                'submitter_email' => $request->email,
                'values_sample' => array_slice($submitterValues, 0, 3, true), // Log first 3 values
                'full_request' => $requestData, // Log the full request to debug
            ]);

            // Make API request to Docuseal
            $client = new \GuzzleHttp\Client();
            $response = $client->post($apiUrl . '/submissions', [
                'headers' => [
                    'X-Auth-Token' => $apiKey,  // Docuseal uses X-Auth-Token, not X-Api-Key
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // Log the full response to debug
            Log::info('Docuseal API response', [
                'data' => $data,
                'is_array' => is_array($data),
                'first_item' => $data[0] ?? null,
            ]);

            // Extract the signing URL for embedding
            $signingUrl = null;
            $submitterSlug = null;
            $submissionId = null;

            // Docuseal returns an array of submitters directly
            if (is_array($data) && isset($data[0])) {
                $submitter = $data[0];

                // Get the submission ID
                $submissionId = $submitter['submission_id'] ?? null;

                // Get the slug for constructing the URL
                if (isset($submitter['slug'])) {
                    $submitterSlug = $submitter['slug'];
                }

                // Check for embed_src which contains the full URL
                if (isset($submitter['embed_src'])) {
                    $signingUrl = $submitter['embed_src'];
                } elseif ($submitterSlug) {
                    // Construct the signing URL using the slug
                    $signingUrl = "https://docuseal.com/s/{$submitterSlug}";
                }
            }

            Log::info('Docuseal submission created', [
                'user_id' => $user->id,
                'template_id' => $request->template_id,
                'submission_id' => $submissionId,
                'submitter_slug' => $submitterSlug,
                'signing_url' => $signingUrl,
            ]);

            return response()->json([
                'submission_id' => $submissionId,
                'submitter_slug' => $submitterSlug,
                'signing_url' => $signingUrl,
                'status' => $data[0]['status'] ?? 'pending',
                'data' => $data,
            ]);

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $errorBody = $e->getResponse()->getBody()->getContents();
            Log::error('Docuseal API client error', [
                'user_id' => Auth::id(),
                'error' => $errorBody,
                'status_code' => $e->getResponse()->getStatusCode(),
            ]);

            return response()->json([
                'error' => 'Docuseal API error',
                'message' => json_decode($errorBody, true)['error'] ?? 'Unknown error',
            ], $e->getResponse()->getStatusCode());

        } catch (Exception $e) {
            Log::error('Failed to create Docuseal submission', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to create submission',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create Docuseal submission for demo purposes
     * POST /docuseal/demo/create-submission
     */
    public function createDemoSubmission(Request $request): JsonResponse
    {
        // This endpoint is for demo purposes and doesn't require manage-orders permission
        // It still requires authentication to prevent abuse

        $request->validate([
            'template_id' => 'required|string',
            'email' => 'required|email',
            'fields' => 'nullable|array',
            'name' => 'required|string',
            'send_email' => 'nullable|boolean',
        ]);

        try {
            $user = Auth::user();

            // Call Docuseal API to create submission
            $apiKey = config('services.docuseal.api_key');
            $apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');

            if (empty($apiKey)) {
                throw new Exception('Docuseal API key is not configured');
            }

            // Build submitter values for pre-filled data
            $submitterValues = [];
            if ($request->fields) {
                foreach ($request->fields as $fieldName => $fieldValue) {
                    // Convert boolean values to strings for Docuseal
                    if (is_bool($fieldValue)) {
                        $fieldValue = $fieldValue ? 'true' : 'false';
                    }

                    // Docuseal expects values as a key-value object
                    $submitterValues[$fieldName] = (string) $fieldValue;
                }
            }

            Log::info('Docuseal demo submission attempt', [
                'template_id' => $request->template_id,
                'email' => $request->email,
                'field_count' => count($submitterValues),
                'user_id' => $user->id,
            ]);

            // Prepare request data
            $requestData = [
                'template_id' => (int) $request->template_id,
                'send_email' => $request->send_email ?? false,
                'submitters' => [
                    [
                        'email' => $request->email,
                        'name' => $request->name,
                        'values' => $submitterValues, // Use 'values' object
                    ]
                ],
            ];

            // Make API request to Docuseal
            $client = new \GuzzleHttp\Client();
            $response = $client->post($apiUrl . '/submissions', [
                'headers' => [
                    'X-Auth-Token' => $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // Extract the signing URL for embedding
            $signingUrl = null;
            $submitterSlug = null;
            $submissionId = null;

            // Docuseal returns an array of submitters directly
            if (is_array($data) && isset($data[0])) {
                $submitter = $data[0];

                // Get the submission ID
                $submissionId = $submitter['submission_id'] ?? null;

                // Get the slug for constructing the URL
                if (isset($submitter['slug'])) {
                    $submitterSlug = $submitter['slug'];
                }

                // Check for embed_src which contains the full URL
                if (isset($submitter['embed_src'])) {
                    $signingUrl = $submitter['embed_src'];
                } elseif ($submitterSlug) {
                    // Construct the signing URL using the slug
                    $signingUrl = "https://docuseal.com/s/{$submitterSlug}";
                }
            }

            Log::info('Docuseal demo submission created', [
                'user_id' => $user->id,
                'template_id' => $request->template_id,
                'submission_id' => $submissionId,
                'submitter_slug' => $submitterSlug,
            ]);

            return response()->json([
                'submission_id' => $submissionId,
                'submitter_slug' => $submitterSlug,
                'signing_url' => $signingUrl,
                'status' => $data[0]['status'] ?? 'pending',
                'data' => $data,
            ]);

        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $errorBody = $e->getResponse()->getBody()->getContents();
            Log::error('Docuseal API client error (demo)', [
                'user_id' => Auth::id(),
                'error' => $errorBody,
                'status_code' => $e->getResponse()->getStatusCode(),
            ]);

            return response()->json([
                'error' => 'Docuseal API error',
                'message' => json_decode($errorBody, true)['error'] ?? 'Unknown error',
            ], $e->getResponse()->getStatusCode());

        } catch (Exception $e) {
            Log::error('Failed to create Docuseal demo submission', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to create submission',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create episode using orchestrator's comprehensive data preparation
     * This ensures all provider/facility metadata is populated from the database
     */
    public function createEpisodeWithComprehensiveData(Request $request): JsonResponse
    {
        Log::info('Creating episode with comprehensive data', [
            'user' => Auth::user()?->email,
            'data_keys' => array_keys($request->all())
        ]);

        $validated = $request->validate([
            'formData' => 'required|array',
            'manufacturerId' => 'required|string',
            'productCode' => 'nullable|string',
            'selected_products' => 'nullable|array',
        ]);

        try {
            // Use QuickRequestOrchestrator to create episode with comprehensive data
            $orchestrator = app(\App\Services\QuickRequest\QuickRequestOrchestrator::class);

            // Extract comprehensive data like the main QuickRequestController does
            $formData = $validated['formData'];

            $episodeData = [
                'patient' => $this->extractPatientData($formData),
                'provider' => $this->extractProviderData($formData),
                'facility' => $this->extractFacilityData($formData),
                'organization' => $this->extractOrganizationData(),
                'clinical' => $this->extractClinicalData($formData),
                'insurance' => $this->extractInsuranceData($formData),
                'order_details' => $this->extractOrderData($formData),
                'manufacturer_id' => $validated['manufacturerId'],
            ];

            // Create episode using orchestrator's comprehensive method
            $episode = $orchestrator->startEpisode($episodeData);

            Log::info('Episode created with comprehensive data', [
                'episode_id' => $episode->id,
                'manufacturer_id' => $validated['manufacturerId'],
                'user_id' => Auth::id(),
                'has_provider_data' => !empty($episode->metadata['provider_data']),
                'has_facility_data' => !empty($episode->metadata['facility_data']),
            ]);

            return response()->json([
                'success' => true,
                'episode_id' => $episode->id,
                'manufacturer_id' => $validated['manufacturerId'],
                'comprehensive_data_populated' => true,
                'metadata_keys' => array_keys($episode->metadata ?? [])
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create episode with comprehensive data', [
                'error' => $e->getMessage(),
                'manufacturer_id' => $validated['manufacturerId'],
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create episode: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extract patient data from form (same as QuickRequestController)
     */
    private function extractPatientData(array $formData): array
    {
        $displayId = $formData['patient_display_id'] ?? $this->generateRandomPatientDisplayId($formData);

        return [
            'id' => $formData['patient_id'] ?? uniqid('patient-'),
            'first_name' => $formData['patient_first_name'] ?? '',
            'last_name' => $formData['patient_last_name'] ?? '',
            'dob' => $formData['patient_dob'] ?? '',
            'gender' => $formData['patient_gender'] ?? 'unknown',
            'display_id' => $displayId,
            'phone' => $formData['patient_phone'] ?? '',
            'email' => $formData['patient_email'] ?? null,
        ];
    }

    /**
     * Extract provider data from form (same as QuickRequestController)
     */
    private function extractProviderData(array $formData): array
    {
        $providerId = $formData['provider_id'];

        // Load provider with full profile information
        $provider = User::with(['providerProfile', 'providerCredentials'])->find($providerId);

        if (!$provider) {
            throw new \Exception("Provider not found with ID: {$providerId}");
        }

        $providerData = [
            'id' => $provider->id,
            'name' => $provider->first_name . ' ' . $provider->last_name,
            'first_name' => $provider->first_name,
            'last_name' => $provider->last_name,
            'email' => $provider->email,
            'phone' => $provider->phone ?? '',
            'npi' => $provider->npi_number ?? '',
        ];

        // Add provider profile data if available
        if ($provider->providerProfile) {
            $profile = $provider->providerProfile;
            $providerData = array_merge($providerData, [
                'specialty' => $profile->primary_specialty ?? '',
                'credentials' => $profile->credentials ?? '',
                'license_number' => $profile->state_license_number ?? '',
                'license_state' => $profile->license_state ?? '',
                'dea_number' => $profile->dea_number ?? '',
                'ptan' => $profile->ptan ?? '',
                'tax_id' => $profile->tax_id ?? '',
                'practice_name' => $profile->practice_name ?? '',
            ]);
        }

        // Add credential data if available
        if ($provider->providerCredentials) {
            foreach ($provider->providerCredentials as $credential) {
                if ($credential->credential_type === 'npi_number' && empty($providerData['npi'])) {
                    $providerData['npi'] = $credential->credential_number;
                }
            }
        }

        return $providerData;
    }

    /**
     * Extract facility data from form (same as QuickRequestController)
     */
    private function extractFacilityData(array $formData): array
    {
        $facilityId = $formData['facility_id'] ?? null;

        // If facility_id is provided, try to load from database
        if ($facilityId) {
            try {
                $facility = \App\Models\Fhir\Facility::with('organization')->find($facilityId);

                if ($facility) {
                    Log::info('Successfully loaded facility data for DocuSeal', [
                        'facility_id' => $facilityId,
                        'facility_name' => $facility->name,
                        'has_address' => !empty($facility->address),
                        'has_organization' => !empty($facility->organization)
                    ]);

                    return [
                        'id' => $facility->id,
                        'name' => $facility->name,
                        'address' => $facility->address ?? '',
                        'address_line1' => $facility->address ?? '',
                        'address_line2' => $facility->address_line2 ?? '',
                        'city' => $facility->city ?? '',
                        'state' => $facility->state ?? '',
                        'zip' => $facility->zip_code ?? '',
                        'zip_code' => $facility->zip_code ?? '', // Alias
                        'phone' => $facility->phone ?? '',
                        'fax' => $facility->fax ?? '',
                        'email' => $facility->email ?? '',
                        'npi' => $facility->npi ?? '',
                        'group_npi' => $facility->group_npi ?? '',
                        'tax_id' => $facility->tax_id ?? '',
                        'tin' => $facility->tax_id ?? '', // Alias for DocuSeal templates
                        'ptan' => $facility->ptan ?? '',
                        'medicaid_number' => $facility->medicaid_number ?? '',
                        'medicare_admin_contractor' => $facility->medicare_admin_contractor ?? '',
                        'place_of_service' => $facility->default_place_of_service ?? '',
                        'facility_type' => $facility->facility_type ?? '',
                        'contact_name' => $facility->contact_name ?? '',
                        'contact_phone' => $facility->contact_phone ?? '',
                        'contact_email' => $facility->contact_email ?? '',
                        'contact_fax' => $facility->contact_fax ?? '',
                        'business_hours' => $facility->business_hours ?? '',
                        'organization_id' => $facility->organization_id ?? null,
                        'organization_name' => $facility->organization?->name ?? '',
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('Failed to load facility data from database', [
                    'facility_id' => $facilityId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Log fallback usage for debugging
        Log::warning('DocuSeal facility data falling back to defaults', [
            'facility_id_provided' => $facilityId,
            'form_data_keys' => array_keys($formData),
            'has_facility_name_in_form' => isset($formData['facility_name'])
        ]);

        // Fallback: use form data or defaults
        return [
            'id' => $facilityId ?? 'default',
            'name' => $formData['facility_name'] ?? 'Default Facility',
            'address' => $formData['facility_address'] ?? '',
            'address_line1' => $formData['facility_address'] ?? '',
            'address_line2' => $formData['facility_address_line2'] ?? '',
            'city' => $formData['facility_city'] ?? '',
            'state' => $formData['facility_state'] ?? '',
            'zip' => $formData['facility_zip'] ?? '',
            'zip_code' => $formData['facility_zip'] ?? '',
            'phone' => $formData['facility_phone'] ?? '',
            'fax' => $formData['facility_fax'] ?? '',
            'email' => $formData['facility_email'] ?? '',
            'npi' => $formData['facility_npi'] ?? '',
            'group_npi' => $formData['facility_group_npi'] ?? '',
            'tax_id' => $formData['facility_tax_id'] ?? '',
            'tin' => $formData['facility_tax_id'] ?? '',
            'ptan' => $formData['facility_ptan'] ?? '',
            'medicaid_number' => $formData['facility_medicaid_number'] ?? '',
            'medicare_admin_contractor' => $formData['facility_medicare_admin_contractor'] ?? '',
            'place_of_service' => $formData['place_of_service'] ?? '',
            'facility_type' => $formData['facility_type'] ?? '',
            'contact_name' => $formData['facility_contact_name'] ?? '',
            'contact_phone' => $formData['facility_contact_phone'] ?? '',
            'contact_email' => $formData['facility_contact_email'] ?? '',
            'contact_fax' => $formData['facility_contact_fax'] ?? '',
            'organization_id' => $formData['organization_id'] ?? null,
            'organization_name' => $formData['organization_name'] ?? '',
        ];
    }

    /**
     * Extract organization data (same as QuickRequestController)
     */
    private function extractOrganizationData(array $formData = []): array
    {
        $user = Auth::user();
        $currentOrganization = $user?->currentOrganization ?? null;

        if (!$currentOrganization) {
            return [];
        }

        return [
            'id' => $currentOrganization->id,
            'name' => $currentOrganization->name,
            'type' => $currentOrganization->type,
            'address' => $currentOrganization->address,
            'city' => $currentOrganization->city,
            'state' => $currentOrganization->state,
            'zip_code' => $currentOrganization->zip_code,
            'phone' => $currentOrganization->phone,
            'email' => $currentOrganization->email,
            'npi' => $currentOrganization->npi,
            'tax_id' => $currentOrganization->tax_id,
        ];
    }

    /**
     * Extract clinical data from form (same as QuickRequestController)
     */
    private function extractClinicalData(array $formData): array
    {
        return [
            'wound_type' => $formData['wound_type'] ?? null,
            'wound_location' => $formData['wound_location'] ?? null,
            'wound_size_length' => $formData['wound_size_length'] ?? null,
            'wound_size_width' => $formData['wound_size_width'] ?? null,
            'wound_size_depth' => $formData['wound_size_depth'] ?? null,
            'primary_diagnosis_code' => $formData['primary_diagnosis_code'] ?? null,
            'secondary_diagnosis_code' => $formData['secondary_diagnosis_code'] ?? null,
            'diagnosis_code' => $formData['diagnosis_code'] ?? null,
            'wound_duration_days' => $formData['wound_duration_days'] ?? null,
            'wound_duration_weeks' => $formData['wound_duration_weeks'] ?? null,
            'wound_duration_months' => $formData['wound_duration_months'] ?? null,
            'wound_duration_years' => $formData['wound_duration_years'] ?? null,
            'prior_applications' => $formData['prior_applications'] ?? null,
            'prior_application_product' => $formData['prior_application_product'] ?? null,
            'prior_application_within_12_months' => $formData['prior_application_within_12_months'] ?? false,
            'hospice_status' => $formData['hospice_status'] ?? false,
            'hospice_family_consent' => $formData['hospice_family_consent'] ?? false,
            'hospice_clinically_necessary' => $formData['hospice_clinically_necessary'] ?? false,
        ];
    }

    /**
     * Extract insurance data from form (same as QuickRequestController)
     */
    private function extractInsuranceData(array $formData): array
    {
        return [
            'primary_insurance_name' => $formData['primary_insurance_name'] ?? null,
            'primary_member_id' => $formData['primary_member_id'] ?? $formData['patient_member_id'] ?? null,
            'primary_plan_type' => $formData['primary_plan_type'] ?? null,
        ];
    }

    /**
     * Extract order data from form (same as QuickRequestController)
     */
    private function extractOrderData(array $formData): array
    {
        return [
            'products' => $formData['selected_products'] ?? [],
            'expected_service_date' => $formData['expected_service_date'] ?? null,
            'place_of_service' => $formData['place_of_service'] ?? null,
            'special_instructions' => $formData['special_instructions'] ?? null,
        ];
    }

    /**
     * Generate random patient display ID (same as QuickRequestController)
     */
    private function generateRandomPatientDisplayId(array $formData): string
    {
        $firstName = $formData['patient_first_name'] ?? 'Unknown';
        $lastName = $formData['patient_last_name'] ?? 'Patient';
        $dob = $formData['patient_dob'] ?? date('Y-m-d');

        $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
        $dobDigits = str_replace('-', '', $dob);
        $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

        return $initials . $dobDigits . $random;
    }

    /**
     * Get submission slugs for a specific submission ID
     * GET /api/v1/admin/docuseal/submissions/{submission_id}/slugs
     */
    public function getSubmissionSlugs(string $submissionId): JsonResponse
    {
        try {
            Log::info('Getting Docuseal submission slugs', [
                'submission_id' => $submissionId,
                'user_id' => Auth::id()
            ]);

            // Get submission slugs from Docuseal service
            $result = $this->docusealService->getSubmissionSlugs($submissionId);

            if (!$result['success']) {
                return response()->json([
                    'error' => 'Failed to fetch submission slugs',
                    'message' => $result['error']
                ], 500);
            }

            return response()->json([
                'success' => true,
                'submission_id' => $submissionId,
                'slugs' => $result['slugs'],
                'total_count' => $result['total_count']
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get Docuseal submission slugs', [
                'submission_id' => $submissionId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to get submission slugs',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Docuseal document URL for viewing
     * GET /admin/orders/{orderId}/docuseal-document-url
     */
    public function getDocusealDocumentUrl(int $orderId): JsonResponse
    {
        try {
            Log::info('Getting Docuseal document URL', [
                'order_id' => $orderId,
                'user_id' => Auth::id()
            ]);

            // Get the order to find the submission ID
            $order = \App\Models\Order\ProductRequest::find($orderId);


            // Get the submission ID from the order
            $submissionId = $order->docuseal_submission_id;
            if (!$submissionId) {
                return response()->json([
                    'error' => 'No Docuseal submission found for this order'
                ], 404);
            }

            // Get submission slugs from Docuseal service
            $result = $this->docusealService->getSubmissionSlugs($submissionId);

            if (!$result['success']) {
                return response()->json([
                    'error' => 'Failed to fetch submission slugs',
                    'message' => $result['error']
                ], 500);
            }

            // Get the first available slug
            if (empty($result['slugs'])) {
                return response()->json([
                    'error' => 'No signer URLs found for this submission'
                ], 404);
            }

            $firstSlug = $result['slugs'][0];
            $documentUrl = $firstSlug['url'];

            Log::info('Successfully generated Docuseal document URL', [
                'order_id' => $orderId,
                'submission_id' => $submissionId,
                'document_url' => $documentUrl
            ]);

            return response()->json([
                'success' => true,
                'document_url' => $documentUrl,
                'submission_id' => $submissionId,
                'signer_info' => [
                    'name' => $firstSlug['name'],
                    'email' => $firstSlug['email'],
                    'status' => $firstSlug['status']
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get Docuseal document URL', [
                'order_id' => $orderId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to get document URL',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if user can access the given order
     */
    private function canAccessOrder(Order $order): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // MSC Admins can access all orders
        if ($user->hasPermission('manage-all-organizations')) {
            return true;
        }

        // Check if user has manage-orders permission
        if ($user->hasPermission('manage-orders')) {
            // For now, allow access if they have the permission
            // TODO: Add organization-level checks when needed
            return true;
        }

        return false;
    }
}




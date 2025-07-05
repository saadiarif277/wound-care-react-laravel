<?php

namespace App\Http\Controllers;

use App\Models\Order\Order;
use App\Models\Docuseal\DocusealSubmission;
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
            'order_id' => 'required|uuid|exists:orders,id',
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
            'user_email' => 'required|email',
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

            // Enhanced response data for frontend
            $responseData = [
                'slug' => null,
                'submission_id' => null,
                'template_id' => $templateId,
                'integration_type' => $episodeId ? 'fhir_enhanced' : 'standard',
                'fhir_data_used' => $episodeId ? count($prefillData) : 0,
                'fields_mapped' => count($prefillData),
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

            // Call DocuSeal API to create submission
            $result = $this->docusealService->createSubmissionForQuickRequest(
                $templateId,
                $request->integration_email,
                $request->user_email ?? $request->integration_email,
                $prefillData,
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




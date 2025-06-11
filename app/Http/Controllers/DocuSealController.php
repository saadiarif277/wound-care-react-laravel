<?php

namespace App\Http\Controllers;

use App\Models\Order\Order;
use App\Models\Docuseal\DocusealSubmission;
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
        $this->middleware('permission:manage-orders');
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
            $submissions = $this->docusealService->generateDocumentsForOrder($order);

            if (empty($submissions)) {
                return response()->json([
                    'error' => 'No documents could be generated'
                ], 400);
            }

            // Return the first submission (primary document)
            $submission = $submissions[0];

            return response()->json([
                'submission_id' => $submission->id,
                'docuseal_submission_id' => $submission->docuseal_submission_id,
                'status' => $submission->status,
                'document_url' => $submission->signing_url,
                'expires_at' => now()->addDays(30)->toISOString(),
            ]);

        } catch (Exception $e) {
            Log::error('DocuSeal document generation failed', [
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

            // Get latest status from DocuSeal API
            $docusealStatus = $this->docusealService->getSubmissionStatus($submission->docuseal_submission_id);

            // Update local status if different
            if ($docusealStatus['status'] !== $submission->status) {
                $submission->update([
                    'status' => $docusealStatus['status'],
                    'completed_at' => $docusealStatus['status'] === 'completed' ? now() : null,
                ]);
            }

            return response()->json([
                'submission_id' => $submission->id,
                'docuseal_submission_id' => $submission->docuseal_submission_id,
                'status' => $submission->status,
                'completed_at' => $submission->completed_at?->toISOString(),
                'download_url' => $submission->isCompleted()
                    ? route('docuseal.download', $submission->id)
                    : null,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get DocuSeal submission status', [
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

            // Get document URL from DocuSeal
            $documentUrl = $this->docusealService->downloadDocument($submission->docuseal_submission_id);

            if (!$documentUrl) {
                return response()->json([
                    'error' => 'Document not available for download'
                ], 404);
            }

            // Redirect to the document URL
            return redirect($documentUrl);

        } catch (Exception $e) {
            Log::error('Failed to download DocuSeal document', [
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
     * Generate JWT token for DocuSeal form embedding
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
                throw new Exception('DocuSeal API key is not configured');
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

            Log::info('DocuSeal JWT token generated', [
                'user_id' => $user->id,
                'template_id' => $request->template_id,
                'order_id' => $request->order_id ?? null,
            ]);

            return response()->json([
                'token' => $token,
                'expires_at' => date('c', $payload['exp']),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to generate DocuSeal JWT token', [
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
     * Create DocuSeal submission with pre-filled data
     * POST /docuseal/create-submission
     */
    public function createSubmission(Request $request): JsonResponse
    {
        // Debug logging
        Log::info('DocuSeal createSubmission called', [
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

            // Call DocuSeal API to create submission
            $apiKey = config('services.docuseal.api_key');
            $apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');

            if (empty($apiKey)) {
                throw new Exception('DocuSeal API key is not configured');
            }

            // Build submitter values for pre-filled data
            $submitterValues = [];
            if ($request->fields) {
                foreach ($request->fields as $fieldName => $fieldValue) {
                    // Convert boolean values to strings for DocuSeal
                    if (is_bool($fieldValue)) {
                        $fieldValue = $fieldValue ? 'true' : 'false';
                    }
                    
                    // DocuSeal expects values as a key-value object
                    $submitterValues[$fieldName] = (string) $fieldValue;
                }
            }
            
            Log::info('DocuSeal submission attempt', [
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
            
            Log::info('DocuSeal API request', [
                'url' => $apiUrl . '/submissions',
                'template_id' => $requestData['template_id'],
                'submitter_email' => $request->email,
                'values_sample' => array_slice($submitterValues, 0, 3, true), // Log first 3 values
                'full_request' => $requestData, // Log the full request to debug
            ]);

            // Make API request to DocuSeal
            $client = new \GuzzleHttp\Client();
            $response = $client->post($apiUrl . '/submissions', [
                'headers' => [
                    'X-Auth-Token' => $apiKey,  // DocuSeal uses X-Auth-Token, not X-Api-Key
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // Log the full response to debug
            Log::info('DocuSeal API response', [
                'data' => $data,
                'is_array' => is_array($data),
                'first_item' => $data[0] ?? null,
            ]);

            // Extract the signing URL for embedding
            $signingUrl = null;
            $submitterSlug = null;
            $submissionId = null;
            
            // DocuSeal returns an array of submitters directly
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

            Log::info('DocuSeal submission created', [
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
            Log::error('DocuSeal API client error', [
                'user_id' => Auth::id(),
                'error' => $errorBody,
                'status_code' => $e->getResponse()->getStatusCode(),
            ]);

            return response()->json([
                'error' => 'DocuSeal API error',
                'message' => json_decode($errorBody, true)['error'] ?? 'Unknown error',
            ], $e->getResponse()->getStatusCode());

        } catch (Exception $e) {
            Log::error('Failed to create DocuSeal submission', [
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
     * Create DocuSeal submission for demo purposes
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
            
            // Call DocuSeal API to create submission
            $apiKey = config('services.docuseal.api_key');
            $apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');

            if (empty($apiKey)) {
                throw new Exception('DocuSeal API key is not configured');
            }

            // Build submitter values for pre-filled data
            $submitterValues = [];
            if ($request->fields) {
                foreach ($request->fields as $fieldName => $fieldValue) {
                    // Convert boolean values to strings for DocuSeal
                    if (is_bool($fieldValue)) {
                        $fieldValue = $fieldValue ? 'true' : 'false';
                    }
                    
                    // DocuSeal expects values as a key-value object
                    $submitterValues[$fieldName] = (string) $fieldValue;
                }
            }
            
            Log::info('DocuSeal demo submission attempt', [
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

            // Make API request to DocuSeal
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
            
            // DocuSeal returns an array of submitters directly
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

            Log::info('DocuSeal demo submission created', [
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
            Log::error('DocuSeal API client error (demo)', [
                'user_id' => Auth::id(),
                'error' => $errorBody,
                'status_code' => $e->getResponse()->getStatusCode(),
            ]);

            return response()->json([
                'error' => 'DocuSeal API error',
                'message' => json_decode($errorBody, true)['error'] ?? 'Unknown error',
            ], $e->getResponse()->getStatusCode());

        } catch (Exception $e) {
            Log::error('Failed to create DocuSeal demo submission', [
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
        $user = Auth::user();

        // MSC Admins can access all orders
        if ($user->hasPermission('manage-all-organizations')) {
            return true;
        }

        // Office Managers can only access orders from their facility
        if ($user->hasPermission('manage-orders')) {
            // Security-critical: Ensure user can only access orders from their organization
            return $user->organization_id === $order->organization_id;
        }

        return false;
    }
}




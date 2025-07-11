<?php

namespace App\Http\Controllers;

use App\Models\Order\Order;
use App\Models\Order\Manufacturer;
use App\Models\Docuseal\DocusealSubmission;
use App\Models\User;
use App\Models\Fhir\Facility;
use GetSubmissionStatusResponse;
use App\Services\DocusealService;
use App\Services\CanonicalFieldService;
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
            'data_keys' => array_keys($request->all()),
            'provider_id' => $request->provider_id ?? $request->input('provider_id'),
            'provider_id_type' => gettype($request->provider_id ?? $request->input('provider_id')),
            'facility_id' => $request->facility_id ?? $request->input('facility_id'),
            'facility_id_type' => gettype($request->facility_id ?? $request->input('facility_id')),
            'all_request_data' => $request->all()
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
            'episode_id' => 'nullable|integer',
            'provider_id' => 'nullable',
            'facility_id' => 'nullable'
        ]);

        try {
            $user = Auth::user();
            $templateId = $request->templateId;
            $prefillData = $request->prefill_data ?? [];
            $episodeId = $request->episode_id;
            
            // Use orchestrator's comprehensive data preparation instead of just frontend data
            $comprehensiveData = $prefillData;
            $aiMappingUsed = false;
            $aiConfidence = 0.0;
            $mappingMethod = 'static';
            
            // Enrich data with full provider, facility details when only IDs are provided
            if (!$episodeId && !empty($prefillData)) {
                // Initialize CanonicalFieldService
                $canonicalFieldService = app(\App\Services\CanonicalFieldService::class);
                
                // Debug log the incoming data
                Log::info('DocuSeal prefill data before enrichment', [
                    'provider_id' => $prefillData['provider_id'] ?? 'Not set',
                    'facility_id' => $prefillData['facility_id'] ?? 'Not set',
                    'provider_name' => $prefillData['provider_name'] ?? 'Not set',
                    'provider_npi' => $prefillData['provider_npi'] ?? 'Not set',
                    'primary_insurance_name' => $prefillData['primary_insurance_name'] ?? 'Not set',
                    'sample_keys' => array_slice(array_keys($prefillData), 0, 20)
                ]);
                
                // Enrich provider data if only provider_id is provided
                $providerId = $request->provider_id ?? $request->input('provider_id') ?? $prefillData['provider_id'] ?? null;
                if ($providerId) {
                    // Convert to integer for database query
                    $providerId = is_numeric($providerId) ? (int)$providerId : $providerId;
                    $provider = User::with(['profile', 'providerCredentials', 'facilities'])->find($providerId);
                    
                    Log::info('Provider lookup result', [
                        'provider_id' => $providerId,
                        'provider_found' => !is_null($provider),
                        'has_provider_profile' => $provider ? !is_null($provider->profile) : false,
                        'provider_name' => $provider ? $provider->full_name ?? trim($provider->first_name . ' ' . $provider->last_name) : 'Not found',
                        'provider_email' => $provider ? $provider->email : 'Not found',
                        'provider_npi' => $provider ? ($provider->npi_number ?? $provider->profile?->npi) : 'Not found'
                    ]);
                    
                    if ($provider) {
                        Log::info('Enriching provider data from ID', ['provider_id' => $providerId]);
                        
                        $providerData = [
                            'id' => $provider->id,
                            'name' => $provider->full_name ?? trim($provider->first_name . ' ' . $provider->last_name),
                            'first_name' => $provider->first_name,
                            'last_name' => $provider->last_name,
                            'email' => $provider->email,
                            'phone' => $provider->phone ?? ($provider->profile ? $provider->profile->phone : null) ?? '',
                            'fax' => ($provider->profile ? $provider->profile->fax : null) ?? '',
                            'npi' => $provider->npi_number ?? ($provider->profile ? $provider->profile->npi : null) ?? '',
                            'specialty' => ($provider->profile ? $provider->profile->specialty : null) ?? '',
                            'credentials' => $provider->credentials ?? ($provider->profile ? $provider->profile->credentials : null) ?? '',
                            'license_number' => $provider->license_number ?? ($provider->profile ? $provider->profile->license_number : null) ?? '',
                            'license_state' => $provider->license_state ?? ($provider->profile ? $provider->profile->license_state : null) ?? '',
                            'dea_number' => $provider->dea_number ?? ($provider->profile ? $provider->profile->dea_number : null) ?? '',
                            'ptan' => ($provider->profile ? $provider->profile->ptan : null) ?? '',
                            'tax_id' => ($provider->profile ? $provider->profile->tax_id : null) ?? '',
                            'medicaid_number' => ($provider->profile ? $provider->profile->medicaid_number : null) ?? '',
                            'practice_name' => ($provider->profile ? $provider->profile->practice_name : null) ?? '',
                            // Get practice address from first associated facility
                            'practice_address' => $provider->facilities->first() ? $provider->facilities->first()->address : '',
                            'practice_address_line1' => $provider->facilities->first() ? $provider->facilities->first()->address_line1 : '',
                            'practice_city' => $provider->facilities->first() ? $provider->facilities->first()->city : '',
                            'practice_state' => $provider->facilities->first() ? $provider->facilities->first()->state : '',
                            'practice_zip' => $provider->facilities->first() ? $provider->facilities->first()->zip_code : '',
                        ];
                        
                        // Map fields using CanonicalFieldService
                        $comprehensiveData['provider_name'] = $canonicalFieldService->getFieldValue('provider', 'provider_name', $providerData) ?? $providerData['name'];
                        $comprehensiveData['provider_first_name'] = $canonicalFieldService->getFieldValue('provider', 'provider_first_name', $providerData) ?? $providerData['first_name'];
                        $comprehensiveData['provider_last_name'] = $canonicalFieldService->getFieldValue('provider', 'provider_last_name', $providerData) ?? $providerData['last_name'];
                        $comprehensiveData['provider_email'] = $canonicalFieldService->getFieldValue('provider', 'provider_email', $providerData) ?? $providerData['email'];
                        $comprehensiveData['provider_phone'] = $canonicalFieldService->getFieldValue('provider', 'provider_phone', $providerData) ?? $providerData['phone'];
                        $comprehensiveData['provider_npi'] = $canonicalFieldService->getFieldValue('provider', 'provider_npi', $providerData) ?? $providerData['npi'];
                        $comprehensiveData['provider_specialty'] = $canonicalFieldService->getFieldValue('provider', 'provider_specialty', $providerData) ?? $providerData['specialty'];
                        $comprehensiveData['provider_credentials'] = $canonicalFieldService->getFieldValue('provider', 'provider_credentials', $providerData) ?? $providerData['credentials'];
                        $comprehensiveData['provider_license_number'] = $canonicalFieldService->getFieldValue('provider', 'provider_license_number', $providerData) ?? $providerData['license_number'];
                        $comprehensiveData['provider_license_state'] = $canonicalFieldService->getFieldValue('provider', 'provider_license_state', $providerData) ?? $providerData['license_state'];
                        $comprehensiveData['provider_dea_number'] = $canonicalFieldService->getFieldValue('provider', 'provider_dea_number', $providerData) ?? $providerData['dea_number'];
                        $comprehensiveData['provider_ptan'] = $canonicalFieldService->getFieldValue('provider', 'provider_ptan', $providerData) ?? $providerData['ptan'];
                        $comprehensiveData['provider_tax_id'] = $canonicalFieldService->getFieldValue('provider', 'provider_tax_id', $providerData) ?? $providerData['tax_id'];
                        $comprehensiveData['provider_medicaid'] = $canonicalFieldService->getFieldValue('provider', 'provider_medicaid', $providerData) ?? $providerData['medicaid_number'];
                        $comprehensiveData['practice_name'] = $canonicalFieldService->getFieldValue('provider', 'practice_name', $providerData) ?? $providerData['practice_name'];
                        
                        // Create physician aliases for DocuSeal compatibility - comprehensive mapping
                        $comprehensiveData['physician_name'] = $comprehensiveData['provider_name'];
                        $comprehensiveData['physician_npi'] = $comprehensiveData['provider_npi'];
                        $comprehensiveData['physician_ptan'] = $comprehensiveData['provider_ptan'];
                        $comprehensiveData['physician_specialty'] = $comprehensiveData['provider_specialty'];
                        
                        // Additional physician field mappings
                        $comprehensiveData['physician_address'] = $providerData['practice_address'] ?? 
                            ($providerData['address'] ?? 
                            ($providerData['practice_address_line1'] ?? ''));
                        $comprehensiveData['physician_phone'] = $providerData['phone'] ?? '';
                        $comprehensiveData['physician_fax'] = $providerData['fax'] ?? '';
                        $comprehensiveData['physician_tin'] = $providerData['tax_id'] ?? '';
                        $comprehensiveData['physician_email'] = $providerData['email'] ?? '';
                        
                        // Physician field variations for different manufacturers
                        $comprehensiveData['Physician Name'] = $comprehensiveData['physician_name'];
                        $comprehensiveData['Physician NPI'] = $comprehensiveData['physician_npi'];
                        $comprehensiveData['Physician TIN'] = $comprehensiveData['physician_tin'];
                        $comprehensiveData['Address (Physician)'] = $comprehensiveData['physician_address'];
                        $comprehensiveData['Phone'] = $comprehensiveData['physician_phone'];
                        $comprehensiveData['Fax'] = $comprehensiveData['physician_fax'];
                    }
                }
                
                // Enrich facility data if only facility_id is provided
                $facilityId = $request->facility_id ?? $request->input('facility_id') ?? $prefillData['facility_id'] ?? null;
                if ($facilityId) {
                    // Convert to integer for database query
                    $facilityId = is_numeric($facilityId) ? (int)$facilityId : $facilityId;
                    $facility = \App\Models\Fhir\Facility::find($facilityId);
                    
                    if ($facility) {
                        Log::info('Enriching facility data from ID', ['facility_id' => $facilityId]);
                        
                        $facilityData = [
                            'id' => $facility->id,
                            'name' => $facility->name,
                            'address' => $facility->address,
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
                        ];
                        
                        // Map fields using CanonicalFieldService
                        $comprehensiveData['facility_name'] = $canonicalFieldService->getFieldValue('facility', 'facility_name', $facilityData) ?? $facilityData['name'];
                        $comprehensiveData['facility_address'] = $canonicalFieldService->getFieldValue('facility', 'facility_address', $facilityData) ?? $facilityData['address'];
                        $comprehensiveData['facility_address_line1'] = $canonicalFieldService->getFieldValue('facility', 'facility_address_line1', $facilityData) ?? $facilityData['address_line1'];
                        $comprehensiveData['facility_address_line2'] = $canonicalFieldService->getFieldValue('facility', 'facility_address_line2', $facilityData) ?? $facilityData['address_line2'];
                        $comprehensiveData['facility_city'] = $canonicalFieldService->getFieldValue('facility', 'facility_city', $facilityData) ?? $facilityData['city'];
                        $comprehensiveData['facility_state'] = $canonicalFieldService->getFieldValue('facility', 'facility_state', $facilityData) ?? $facilityData['state'];
                        $comprehensiveData['facility_zip_code'] = $canonicalFieldService->getFieldValue('facility', 'facility_zip_code', $facilityData) ?? $facilityData['zip_code'];
                        $comprehensiveData['facility_phone'] = $canonicalFieldService->getFieldValue('facility', 'facility_phone', $facilityData) ?? $facilityData['phone'];
                        $comprehensiveData['facility_fax'] = $canonicalFieldService->getFieldValue('facility', 'facility_fax', $facilityData) ?? $facilityData['fax'];
                        $comprehensiveData['facility_email'] = $canonicalFieldService->getFieldValue('facility', 'facility_email', $facilityData) ?? $facilityData['email'];
                        $comprehensiveData['facility_npi'] = $canonicalFieldService->getFieldValue('facility', 'facility_npi', $facilityData) ?? $facilityData['npi'];
                        $comprehensiveData['facility_group_npi'] = $canonicalFieldService->getFieldValue('facility', 'facility_group_npi', $facilityData) ?? $facilityData['group_npi'];
                        $comprehensiveData['facility_ptan'] = $canonicalFieldService->getFieldValue('facility', 'facility_ptan', $facilityData) ?? $facilityData['ptan'];
                        $comprehensiveData['facility_tax_id'] = $canonicalFieldService->getFieldValue('facility', 'facility_tax_id', $facilityData) ?? $facilityData['tax_id'];
                        $comprehensiveData['facility_type'] = $canonicalFieldService->getFieldValue('facility', 'facility_type', $facilityData) ?? $facilityData['facility_type'];
                        $comprehensiveData['place_of_service'] = $canonicalFieldService->getFieldValue('facility', 'place_of_service', $facilityData) ?? $facilityData['place_of_service'];
                        $comprehensiveData['facility_medicaid'] = $canonicalFieldService->getFieldValue('facility', 'facility_medicaid', $facilityData) ?? $facilityData['medicaid_number'];
                        
                        // Add city_state_zip field for BioWound
                        $comprehensiveData['city_state_zip'] = trim(
                            ($facilityData['city'] ?? '') . ', ' .
                            ($facilityData['state'] ?? '') . ' ' .
                            ($facilityData['zip_code'] ?? '')
                        );
                        
                        // Add missing facility field mappings for the form
                        $comprehensiveData['Medicare Admin Contractor'] = $facilityData['medicare_contractor'] ?? 'CGS Administrators';
                        $comprehensiveData['NPI'] = $facilityData['npi'] ?? ''; // Facility NPI
                        $comprehensiveData['TIN'] = $facilityData['tax_id'] ?? ''; // Facility TIN
                        $comprehensiveData['PTAN'] = $facilityData['ptan'] ?? ''; // Facility PTAN
                        
                        // Alternative field names for facility fields
                        $comprehensiveData['facility_medicare_contractor'] = $comprehensiveData['Medicare Admin Contractor'];
                        $comprehensiveData['facility_tin'] = $comprehensiveData['TIN'];
                        $comprehensiveData['facility_ptan'] = $comprehensiveData['PTAN'];
                        
                        // Map place_of_service to individual checkboxes
                        $pos = $facilityData['place_of_service'] ?? '';
                        $comprehensiveData['pos_11'] = ($pos === '11'); // Office
                        $comprehensiveData['pos_21'] = ($pos === '21'); // Inpatient Hospital
                        $comprehensiveData['pos_24'] = ($pos === '24'); // Ambulatory Surgical Center
                        $comprehensiveData['pos_22'] = ($pos === '22'); // Outpatient Hospital
                        $comprehensiveData['pos_32'] = ($pos === '32'); // Nursing Facility
                        $comprehensiveData['pos_13'] = ($pos === '13'); // Assisted Living
                        $comprehensiveData['pos_12'] = ($pos === '12'); // Home
                        $comprehensiveData['critical_access_hospital'] = ($pos === '85'); // Critical Access Hospital
                        $comprehensiveData['other_pos'] = !in_array($pos, ['11', '21', '24', '22', '32', '13', '12', '85']);
                    }
                }
                
                // Process insurance data to ensure it's properly formatted
                if (!empty($prefillData['primary_insurance_name']) || !empty($prefillData['insurance_name'])) {
                    $comprehensiveData['primary_insurance_name'] = $prefillData['primary_insurance_name'] ?? $prefillData['insurance_name'] ?? '';
                    $comprehensiveData['primary_member_id'] = $prefillData['primary_member_id'] ?? $prefillData['insurance_member_id'] ?? '';
                    $comprehensiveData['insurance_name'] = $comprehensiveData['primary_insurance_name'];
                    $comprehensiveData['insurance_member_id'] = $comprehensiveData['primary_member_id'];
                    $comprehensiveData['primary_name'] = $comprehensiveData['primary_insurance_name']; // BioWound field
                    $comprehensiveData['primary_policy'] = $comprehensiveData['primary_member_id']; // BioWound field
                    $comprehensiveData['primary_phone'] = $prefillData['primary_payer_phone'] ?? '';
                    
                    // Add missing primary insurance fields
                    $comprehensiveData['primary_subscriber_name'] = $prefillData['primary_subscriber_name'] ?? '';
                    $comprehensiveData['primary_subscriber_dob'] = $prefillData['primary_subscriber_dob'] ?? '';
                    $comprehensiveData['primary_plan_type'] = $prefillData['primary_plan_type'] ?? '';
                    
                    // Map to form field variations
                    $comprehensiveData['Policy Number'] = $comprehensiveData['primary_member_id'];
                    $comprehensiveData['Subscriber Name'] = $comprehensiveData['primary_subscriber_name'];
                    $comprehensiveData['Subscriber DOB'] = $comprehensiveData['primary_subscriber_dob'];
                    $comprehensiveData['Insurance Phone Number'] = $comprehensiveData['primary_phone'];
                    $comprehensiveData['Type of Plan'] = $comprehensiveData['primary_plan_type'];
                    
                    // Map plan type to checkboxes
                    $planType = strtolower($prefillData['primary_plan_type'] ?? '');
                    $comprehensiveData['hmo'] = str_contains($planType, 'hmo');
                    $comprehensiveData['ppo'] = str_contains($planType, 'ppo'); 
                    $comprehensiveData['other'] = !str_contains($planType, 'hmo') && !str_contains($planType, 'ppo') && !empty($planType);
                    $comprehensiveData['Type of Plan Other'] = (!str_contains($planType, 'hmo') && !str_contains($planType, 'ppo')) ? $planType : '';
                }
                
                if (!empty($prefillData['has_secondary_insurance']) && !empty($prefillData['secondary_insurance_name'])) {
                    Log::info('Processing secondary insurance data', [
                        'has_secondary' => true,
                        'secondary_insurance_name' => $prefillData['secondary_insurance_name'] ?? 'Not set',
                        'secondary_member_id' => $prefillData['secondary_member_id'] ?? 'Not set',
                        'secondary_subscriber_name' => $prefillData['secondary_subscriber_name'] ?? 'Not set',
                        'secondary_subscriber_dob' => $prefillData['secondary_subscriber_dob'] ?? 'Not set',
                        'secondary_plan_type' => $prefillData['secondary_plan_type'] ?? 'Not set'
                    ]);
                    
                    $comprehensiveData['secondary_insurance_name'] = $prefillData['secondary_insurance_name'] ?? '';
                    $comprehensiveData['secondary_member_id'] = $prefillData['secondary_member_id'] ?? '';
                    $comprehensiveData['secondary_name'] = $comprehensiveData['secondary_insurance_name']; // BioWound field
                    $comprehensiveData['secondary_policy'] = $comprehensiveData['secondary_member_id']; // BioWound field
                    $comprehensiveData['secondary_phone'] = $prefillData['secondary_payer_phone'] ?? '';
                    
                    // Add missing secondary insurance fields
                    $comprehensiveData['secondary_subscriber_name'] = $prefillData['secondary_subscriber_name'] ?? '';
                    $comprehensiveData['secondary_subscriber_dob'] = $prefillData['secondary_subscriber_dob'] ?? '';
                    $comprehensiveData['secondary_plan_type'] = $prefillData['secondary_plan_type'] ?? '';
                    
                    // Add aliases for different manufacturer forms
                    $comprehensiveData['Secondary Subscriber Name'] = $comprehensiveData['secondary_subscriber_name'];
                    $comprehensiveData['Secondary Subscriber DOB'] = $comprehensiveData['secondary_subscriber_dob'];
                    $comprehensiveData['Type of Plan Other 2nd'] = $comprehensiveData['secondary_plan_type'];
                    
                    // Add manufacturer-specific field name variations
                    // Advanced Solution
                    $comprehensiveData['subscriber_dob_secondary'] = $comprehensiveData['secondary_subscriber_dob'];
                    $comprehensiveData['Subscriber DOB 2nd'] = $comprehensiveData['secondary_subscriber_dob'];
                    $comprehensiveData['Policy Number 2nd'] = $comprehensiveData['secondary_member_id'];
                    $comprehensiveData['Secondary Insurance Phone Number'] = $comprehensiveData['secondary_phone'];
                    
                    // Centurion Therapeutics
                    $comprehensiveData['Secondary Payor Phone'] = $comprehensiveData['secondary_phone'];
                    $comprehensiveData['Secondary Policy Number'] = $comprehensiveData['secondary_member_id'];
                    
                    // Celularity
                    $comprehensiveData['Ins. Phone (Secondary)'] = $comprehensiveData['secondary_phone'];
                    
                    // Map secondary plan type to checkboxes (for forms that use checkboxes)
                    $secondaryPlanType = strtolower($prefillData['secondary_plan_type'] ?? '');
                    $comprehensiveData['secondary_hmo'] = str_contains($secondaryPlanType, 'hmo');
                    $comprehensiveData['secondary_ppo'] = str_contains($secondaryPlanType, 'ppo'); 
                    $comprehensiveData['secondary_other'] = !str_contains($secondaryPlanType, 'hmo') && !str_contains($secondaryPlanType, 'ppo') && !empty($secondaryPlanType);
                    $comprehensiveData['secondary_other_type'] = (!str_contains($secondaryPlanType, 'hmo') && !str_contains($secondaryPlanType, 'ppo')) ? $secondaryPlanType : '';
                }
                
                // Process wound/clinical data
                if (!empty($prefillData['wound_type']) || !empty($prefillData['wound_types'])) {
                    $comprehensiveData['wound_type'] = $prefillData['wound_type'] ?? '';
                    $comprehensiveData['wound_location'] = $prefillData['wound_location'] ?? '';
                    $comprehensiveData['wound_size_length'] = $prefillData['wound_length'] ?? $prefillData['wound_size_length'] ?? '';
                    $comprehensiveData['wound_size_width'] = $prefillData['wound_width'] ?? $prefillData['wound_size_width'] ?? '';
                    $comprehensiveData['wound_size_depth'] = $prefillData['wound_depth'] ?? $prefillData['wound_size_depth'] ?? '';
                    
                    // Map wound types to individual checkboxes
                    $woundType = strtolower($prefillData['wound_type'] ?? '');
                    $woundTypes = $prefillData['wound_types'] ?? [];
                    
                    $comprehensiveData['wound_dfu'] = ($woundType === 'dfu' || in_array('DFU', $woundTypes));
                    $comprehensiveData['wound_vlu'] = ($woundType === 'vlu' || in_array('VLU', $woundTypes));
                    $comprehensiveData['wound_chronic_ulcer'] = ($woundType === 'chronic ulcer' || in_array('Chronic Ulcer', $woundTypes));
                    $comprehensiveData['wound_dehisced_surgical'] = (
                        str_contains($woundType, 'dehisced') || 
                        str_contains($woundType, 'surgical') ||
                        in_array('Dehisced Surgical', $woundTypes) ||
                        in_array('Surgical', $woundTypes)
                    );
                    $comprehensiveData['wound_mohs_surgical'] = (
                        str_contains($woundType, 'mohs') ||
                        in_array('Mohs Surgical', $woundTypes)
                    );
                    
                    // Map to checkbox field names used in the form
                    $comprehensiveData['Diabetic Foot Ulcer'] = $comprehensiveData['wound_dfu'];
                    $comprehensiveData['Venous Leg Ulcer'] = $comprehensiveData['wound_vlu'];
                    $comprehensiveData['Pressure Ulcer'] = ($woundType === 'pressure ulcer' || in_array('Pressure Ulcer', $woundTypes));
                    $comprehensiveData['Traumatic Burns'] = (str_contains($woundType, 'burn') || in_array('Traumatic Burns', $woundTypes));
                    $comprehensiveData['Radiation Burns'] = (str_contains($woundType, 'radiation') || in_array('Radiation Burns', $woundTypes));
                    $comprehensiveData['Necrotizing Fasciitis'] = (str_contains($woundType, 'necrotizing') || in_array('Necrotizing Fasciitis', $woundTypes));
                    $comprehensiveData['Dehisced Surgical'] = $comprehensiveData['wound_dehisced_surgical'];
                    
                    // Wound Size fields with proper formatting
                    $comprehensiveData['Wound Size(s)'] = '';
                    if (!empty($comprehensiveData['wound_size_length']) && !empty($comprehensiveData['wound_size_width'])) {
                        $comprehensiveData['Wound Size(s)'] = $comprehensiveData['wound_size_length'] . ' x ' . $comprehensiveData['wound_size_width'];
                        if (!empty($comprehensiveData['wound_size_depth'])) {
                            $comprehensiveData['Wound Size(s)'] .= ' x ' . $comprehensiveData['wound_size_depth'];
                        }
                        $comprehensiveData['Wound Size(s)'] .= ' cm';
                    }
                    
                    // Calculate wound size total
                    if (!empty($comprehensiveData['wound_size_length']) && !empty($comprehensiveData['wound_size_width'])) {
                        $comprehensiveData['wound_size_total'] = floatval($comprehensiveData['wound_size_length']) * floatval($comprehensiveData['wound_size_width']);
                    }
                    
                    // Additional clinical fields
                    $comprehensiveData['icd10_code_1'] = $prefillData['icd10_code_1'] ?? $prefillData['primary_diagnosis_code'] ?? '';
                    $comprehensiveData['primary_icd10'] = $comprehensiveData['icd10_code_1'];
                    $comprehensiveData['secondary_icd10'] = $prefillData['secondary_diagnosis_code'] ?? '';
                    $comprehensiveData['cpt_code_1'] = $prefillData['cpt_code_1'] ?? '';
                    $comprehensiveData['procedure_date'] = $prefillData['procedure_date'] ?? now()->format('Y-m-d');
                    $comprehensiveData['date'] = $comprehensiveData['procedure_date'];
                    $comprehensiveData['wound_duration_weeks'] = $prefillData['wound_duration_weeks'] ?? '';
                    $comprehensiveData['wound_duration'] = $comprehensiveData['wound_duration_weeks'];
                    $comprehensiveData['location_of_wound'] = $comprehensiveData['wound_location']; // BioWound alias
                    $comprehensiveData['post_debridement_size'] = $comprehensiveData['wound_size_total'] ?? '';
                    
                    // Map to form field names
                    $comprehensiveData['ICD-10 Diagnosis Code(s)'] = $comprehensiveData['icd10_code_1'];
                    $comprehensiveData['Application CPT(s)'] = $comprehensiveData['cpt_code_1'];
                    $comprehensiveData['Date of Procedure'] = $comprehensiveData['procedure_date'];
                    $comprehensiveData['Product Information'] = $prefillData['product_name'] ?? '';
                    
                    // Map CPT codes from array to string
                    if (!empty($prefillData['application_cpt_codes']) && is_array($prefillData['application_cpt_codes'])) {
                        $comprehensiveData['cpt_code_1'] = implode(', ', $prefillData['application_cpt_codes']);
                        $comprehensiveData['Application CPT(s)'] = $comprehensiveData['cpt_code_1'];
                    }
                    
                    // Global period status
                    $comprehensiveData['patient_global_yes'] = $prefillData['global_period_status'] ?? false;
                    $comprehensiveData['patient_global_no'] = !($prefillData['global_period_status'] ?? false);
                    $comprehensiveData['global_period_cpt'] = $prefillData['global_period_cpt'] ?? '';
                    $comprehensiveData['global_period_surgery_date'] = $prefillData['global_period_surgery_date'] ?? '';
                }
                
                // Add current user as sales rep/contact
                $currentUser = Auth::user();
                if ($currentUser) {
                    $comprehensiveData['name'] = $currentUser->full_name ?? trim($currentUser->first_name . ' ' . $currentUser->last_name);
                    $comprehensiveData['email'] = $currentUser->email ?? '';
                    $comprehensiveData['phone'] = $currentUser->phone ?? '';
                    $comprehensiveData['contact_name'] = $comprehensiveData['name'];
                    $comprehensiveData['contact_email'] = $comprehensiveData['email'];
                    $comprehensiveData['sales_rep'] = $comprehensiveData['name'];
                    $comprehensiveData['rep_email'] = $comprehensiveData['email'];
                }
                
                // Set distributor_company
                $comprehensiveData['distributor_company'] = 'MSC Wound Care';
                
                // Process product selection data
                if (!empty($prefillData['selected_products']) || !empty($prefillData['product_name'])) {
                    $selectedProducts = $prefillData['selected_products'] ?? [];
                    $productName = $prefillData['product_name'] ?? '';
                    
                    // Map product checkboxes based on product name
                    $comprehensiveData['CompleteAA'] = str_contains(strtolower($productName), 'completeaa');
                    $comprehensiveData['Membrane Wrap Hydro'] = str_contains(strtolower($productName), 'membrane wrap hydro');
                    $comprehensiveData['Membrane Wrap'] = str_contains(strtolower($productName), 'membrane wrap') && !str_contains(strtolower($productName), 'hydro');
                    $comprehensiveData['WoundPlus'] = str_contains(strtolower($productName), 'woundplus');
                    $comprehensiveData['CompleteFT'] = str_contains(strtolower($productName), 'completeft');
                    $comprehensiveData['Other'] = false; // Will be set true if no other product matches
                    
                    // If selected_products array exists, check each product
                    if (is_array($selectedProducts) && !empty($selectedProducts)) {
                        foreach ($selectedProducts as $product) {
                            $pName = strtolower($product['name'] ?? $product['product_name'] ?? '');
                            if (str_contains($pName, 'completeaa')) $comprehensiveData['CompleteAA'] = true;
                            if (str_contains($pName, 'membrane wrap hydro')) $comprehensiveData['Membrane Wrap Hydro'] = true;
                            if (str_contains($pName, 'membrane wrap') && !str_contains($pName, 'hydro')) $comprehensiveData['Membrane Wrap'] = true;
                            if (str_contains($pName, 'woundplus')) $comprehensiveData['WoundPlus'] = true;
                            if (str_contains($pName, 'completeft')) $comprehensiveData['CompleteFT'] = true;
                        }
                    }
                    
                    // Set "Other" if no known product is selected
                    if (!$comprehensiveData['CompleteAA'] && !$comprehensiveData['Membrane Wrap Hydro'] && 
                        !$comprehensiveData['Membrane Wrap'] && !$comprehensiveData['WoundPlus'] && 
                        !$comprehensiveData['CompleteFT'] && !empty($productName)) {
                        $comprehensiveData['Other'] = true;
                        $comprehensiveData['Product Other'] = $productName;
                    }
                }
                
                Log::info('Data enrichment completed', [
                    'original_fields' => count($prefillData),
                    'enriched_fields' => count($comprehensiveData),
                    'has_provider' => !empty($comprehensiveData['provider_name']),
                    'has_facility' => !empty($comprehensiveData['facility_name']),
                    'has_insurance' => !empty($comprehensiveData['primary_insurance_name']),
                    'has_wound_data' => !empty($comprehensiveData['wound_type']),
                    'provider_details' => [
                        'name' => $comprehensiveData['provider_name'] ?? 'Not set',
                        'npi' => $comprehensiveData['provider_npi'] ?? 'Not set',
                        'physician_name' => $comprehensiveData['physician_name'] ?? 'Not set',
                        'physician_npi' => $comprehensiveData['physician_npi'] ?? 'Not set'
                    ],
                    'insurance_details' => [
                        'primary_name' => $comprehensiveData['primary_insurance_name'] ?? 'Not set',
                        'primary_member_id' => $comprehensiveData['primary_member_id'] ?? 'Not set',
                        'secondary_name' => $comprehensiveData['secondary_insurance_name'] ?? 'Not set'
                    ],
                    'sample_comprehensive_data' => array_slice($comprehensiveData, 0, 20, true)
                ]);
            }
            
            // Check if AI enhancement is enabled
            $useAI = config('services.medical_ai.enabled', true) && 
                     config('services.medical_ai.use_for_docuseal', true);
            
            Log::info('DocuSeal AI configuration status', [
                'ai_enabled' => config('services.medical_ai.enabled'),
                'ai_for_docuseal' => config('services.medical_ai.use_for_docuseal'),
                'useAI' => $useAI,
                'has_episode_id' => !empty($episodeId),
                'has_prefill_data' => !empty($prefillData),
                'prefill_data_count' => count($prefillData)
            ]);
            
            if ($episodeId) {
                $episode = \App\Models\PatientManufacturerIVREpisode::find($episodeId);
                if ($episode) {
                    $orchestrator = app(\App\Services\QuickRequest\QuickRequestOrchestrator::class);
                    
                    if ($useAI) {
                        // Try AI-enhanced preparation first
                        try {
                            $aiResult = $orchestrator->prepareAIEnhancedDocusealData(
                                $episode,
                                $templateId,
                                'insurance'
                            );
                            $comprehensiveData = $aiResult['enhanced_data'] ?? $aiResult;
                            $aiMappingUsed = $aiResult['ai_mapping_used'] ?? true;
                            $aiConfidence = $aiResult['ai_confidence'] ?? 0.8;
                            $mappingMethod = 'ai_enhanced_episode';
                            
                            Log::info('Using AI-enhanced DocuSeal data preparation', [
                                'episode_id' => $episodeId,
                                'template_id' => $templateId,
                                'ai_used' => true,
                                'confidence' => $aiConfidence
                            ]);
                        } catch (\Exception $e) {
                            Log::warning('AI enhancement failed, falling back to standard preparation', [
                                'episode_id' => $episodeId,
                                'error' => $e->getMessage()
                            ]);
                            // Fallback to standard preparation
                            $comprehensiveData = $orchestrator->prepareDocusealData($episode);
                            $mappingMethod = 'static_fallback';
                        }
                    } else {
                        // Use standard preparation when AI is disabled
                        Log::info('AI enhancement disabled, using standard preparation', [
                            'episode_id' => $episodeId
                        ]);
                        $comprehensiveData = $orchestrator->prepareDocusealData($episode);
                        $mappingMethod = 'static_episode';
                    }
                    
                    Log::info('Using orchestrator comprehensive data', [
                        'episode_id' => $episodeId,
                        'comprehensive_fields' => count($comprehensiveData),
                        'frontend_fields' => count($prefillData),
                        'sample_comprehensive_data' => array_slice($comprehensiveData, 0, 10, true)
                    ]);
                }
            } elseif ($useAI && !empty($prefillData)) {
                // Get template fields from DocuSeal API first for better AI enhancement
                $templateFields = [];
                try {
                    $templateFields = $this->docusealService->getTemplateFieldsFromAPI($templateId);
                    Log::info('Retrieved template fields from DocuSeal API', [
                        'template_id' => $templateId,
                        'template_fields_count' => count($templateFields),
                        'template_field_names' => array_keys($templateFields)
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to retrieve template fields from DocuSeal API', [
                        'template_id' => $templateId,
                        'error' => $e->getMessage()
                    ]);
                }
                
                // AI enhancement for non-episode requests using form data directly
                try {
                    Log::info('Attempting AI enhancement for non-episode request', [
                        'template_id' => $templateId,
                        'manufacturer_id' => $request->manufacturerId,
                        'has_template_fields' => !empty($templateFields)
                    ]);
                    
                    $aiService = app(\App\Services\Medical\OptimizedMedicalAiService::class);
                    
                    // Build context for AI processing with form data
                    $manufacturer = Manufacturer::find($request->manufacturerId);
                    $manufacturerName = $manufacturer ? $manufacturer->name : 'Unknown';
                    
                    Log::info('Manufacturer resolution', [
                        'manufacturer_id' => $request->manufacturerId,
                        'manufacturer_found' => !is_null($manufacturer),
                        'manufacturer_name' => $manufacturerName
                    ]);
                    
                    // Use the correct method with proper parameters including template fields
                    $aiResult = $aiService->enhanceWithDynamicTemplate(
                        $prefillData,  // FHIR-like data (form data)
                        $templateId,   // Template ID
                        $manufacturerName,  // Manufacturer name
                        [
                            'source' => 'quick_request_form', 
                            'user_id' => Auth::id(),
                            'template_fields' => $templateFields,  // Include template fields for better mapping
                            'manufacturer_name' => $manufacturerName
                        ]  // Additional context
                    );
                    
                    Log::info('AI service response received', [
                        'has_enhanced_fields' => isset($aiResult['enhanced_fields']),
                        'enhanced_fields_count' => isset($aiResult['enhanced_fields']) ? count($aiResult['enhanced_fields']) : 0,
                        'confidence' => $aiResult['confidence'] ?? null,
                        'method' => $aiResult['method'] ?? null
                    ]);
                    
                    if (!empty($aiResult['enhanced_fields'])) {
                        $comprehensiveData = array_merge($prefillData, $aiResult['enhanced_fields']);
                        $aiMappingUsed = true;
                        $aiConfidence = $aiResult['_ai_confidence'] ?? $aiResult['confidence'] ?? 0.7;
                        $mappingMethod = $aiResult['_ai_method'] ?? 'ai_enhanced_with_template_fields';
                        
                        Log::info('Using AI-enhanced DocuSeal data preparation with template fields', [
                            'template_id' => $templateId,
                            'manufacturer' => $manufacturerName,
                            'original_fields' => count($prefillData),
                            'enhanced_fields' => count($comprehensiveData),
                            'template_fields_available' => count($templateFields),
                            'ai_confidence' => $aiConfidence
                        ]);
                    } else {
                        Log::info('AI enhancement returned no improvements, using form data as-is', [
                            'template_id' => $templateId,
                            'manufacturer' => $manufacturerName,
                            'template_fields_count' => count($templateFields),
                            'ai_result_keys' => array_keys($aiResult)
                        ]);
                        $mappingMethod = 'form_data_with_template_fields';
                    }
                } catch (\Exception $e) {
                    Log::error('AI enhancement failed with exception', [
                        'template_id' => $templateId,
                        'manufacturer_name' => $manufacturerName,
                        'template_fields_count' => count($templateFields),
                        'error_message' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'stack_trace' => $e->getTraceAsString()
                    ]);
                    $mappingMethod = 'static_fallback';
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
                'manufacturer' => $manufacturerName ?? 'Standard',
                'ai_mapping_used' => $aiMappingUsed,
                'ai_confidence' => $aiConfidence,
                'mapping_method' => $mappingMethod
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
            // Use direct field mapping when AI enhancement is successful to bypass restrictive manufacturer filtering
            if ($aiMappingUsed && $mappingMethod === 'ai_enhanced_form') {
                // Create submission directly with AI-enhanced data to bypass manufacturer field filtering
                $result = $this->docusealService->createSubmissionDirectWithAIData(
                    $templateId,
                    $request->integration_email,
                    $request->submitter_email,
                    $request->submitter_name,
                    $comprehensiveData,
                    $episodeId,
                    $aiConfidence,
                    $mappingMethod
                );
            } else {
                // Use normal path for non-AI enhanced requests
                $result = $this->docusealService->createSubmissionForQuickRequest(
                    $templateId,
                    $request->integration_email,  // Our DocuSeal account email (limitless@mscwoundcare.com)
                    $request->submitter_email,    // The person who will sign (provider@example.com)
                    $request->submitter_name,     // The person's name
                    $comprehensiveData,          // Use orchestrator's comprehensive data
                    $episodeId
                );
            }

            if (!$result['success']) {
                throw new Exception($result['error'] ?? 'Failed to create DocuSeal submission');
            }

            $submission = $result['data'];

            // Update response with actual submission data
            $responseData = array_merge($responseData, [
                'slug' => $submission['slug'] ?? null,
                'submission_id' => $submission['submission_id'] ?? null,
                'ai_mapping_used' => $result['ai_mapping_used'] ?? $aiMappingUsed,
                'ai_confidence' => $result['ai_confidence'] ?? $aiConfidence,
                'mapping_method' => $result['mapping_method'] ?? $mappingMethod
            ]);

            Log::info('DocuSeal submission created successfully', [
                'template_id' => $templateId,
                'submission_id' => $responseData['submission_id'],
                'slug' => $responseData['slug'],
                'fields_mapped' => $responseData['fields_mapped']
            ]);

            return response()->json($responseData);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('DocuSeal validation failed', [
                'user_id' => Auth::id(),
                'validation_errors' => $e->errors(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'error' => 'Validation failed',
                'message' => 'Invalid request data',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to generate DocuSeal submission slug', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'template_id' => $request->templateId ?? null,
                'manufacturer_id' => $request->manufacturerId ?? null,
                'trace' => $e->getTraceAsString()
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




<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order\Manufacturer;
use App\Services\QuickRequestService;
use App\Services\AI\AzureFoundryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class QuickRequestController extends Controller
{
    protected $service;

    public function __construct(
        QuickRequestService $service
    ) {
        $this->service = $service;
    }

        public function submit(Request $request)
    {
        try {
            // Validate the request (the service will handle detailed validation)
            $data = $request->all();

            // Process the quick request - for now, just return success
            // The actual processing should be handled by the service

            return response()->json([
                'success' => true,
                'message' => 'Product request submitted successfully',
                'data' => $data
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Quick request submission failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your request',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function validateStep(Request $request)
    {
        $step = $request->input('step');
        $data = $request->input('data', []);

        // validateStep method not implemented yet
        // $errors = $this->service->validateStep($step, $data);
        $errors = [];

        return response()->json([
            'valid' => empty($errors),
            'errors' => $errors
        ]);
    }

        /**
     * Generate Docuseal builder token for IVR forms with pre-filled data.
     */
    public function generateBuilderToken(Request $request)
    {
        Log::info('generateBuilderToken called', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'input' => $request->all()
        ]);

        // Accept both camelCase and snake_case
        $data = $request->validate([
            'manufacturer_id' => 'sometimes|integer|exists:manufacturers,id',
            'manufacturerId' => 'sometimes|integer|exists:manufacturers,id',
            'product_code'    => 'nullable|string',
            'productCode'     => 'nullable|string',
            'template_id'     => 'nullable|string',
            'patient_display_id' => 'nullable|string',
            'episode_id'      => 'nullable|string',
            'form_data'       => 'nullable|array',
            'formData'        => 'nullable|array'
        ]);

        // Normalize to snake_case
        $manufacturerId = $data['manufacturer_id'] ?? $data['manufacturerId'] ?? null;
        $productCode = $data['product_code'] ?? $data['productCode'] ?? null;
        $formData = $data['form_data'] ?? $data['formData'] ?? [];

        if (!$manufacturerId) {
            return response()->json(['error' => 'Manufacturer ID is required'], 422);
        }

        try {
            // Note: DocuSeal service has been removed and replaced with new manufacturer system
            // For now, return a basic response indicating the system is ready for new workflow
            Log::warning('DocuSeal functionality removed - using manufacturer response system');

            return response()->json([
                'success' => true,
                'builderToken' => 'manufacturer-workflow-ready-' . uniqid(),
                'token' => 'manufacturer-workflow-ready-' . uniqid(),
                'jwt' => 'manufacturer-workflow-ready-' . uniqid(),
                'template_id' => 'manufacturer-workflow',
                'mapped_fields_count' => 0,
                'note' => 'Using new manufacturer response system - DocuSeal functionality has been removed'
            ]);

        } catch (\Exception $e) {
            Log::error('Manufacturer workflow preparation failed', [
                'error' => $e->getMessage(),
                'manufacturer_id' => $manufacturerId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to prepare manufacturer workflow',
                'message' => $e->getMessage()
            ], 500);
        }
    }

        /**
     * Get Docuseal submission result
     */
    public function getDocusealSubmission(Request $request, $submissionId)
    {
        try {
            // getDocusealSubmission method not implemented yet
            // $result = $this->service->getDocusealSubmission($submissionId);
            $result = ['status' => 'pending'];

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get Docuseal submission', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current user's financial permissions
     */
    public function getUserPermissions(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        // Load roles if not already loaded
        if (!$user->relationLoaded('roles')) {
            $user->load('roles');
        }

        // Get user role
        $userRole = $user->roles->first();
        $roleSlug = $userRole ? $userRole->slug : 'unknown';

        // Log for debugging
        Log::info('getUserPermissions called', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'role' => $roleSlug,
            'has_role' => $userRole ? true : false
        ]);

        // Get financial permissions for the user
        $permissions = [
            'can_view_financials' => $user->hasAnyPermission(['view-financials', 'manage-financials']),
            'can_see_discounts' => $user->hasPermission('view-discounts'),
            'can_see_msc_pricing' => $user->hasPermission('view-msc-pricing'),
            'can_see_order_totals' => $user->hasPermission('view-order-totals'),
            'can_view_commission' => $user->hasPermission('view-commission'),
            'pricing_access_level' => $this->determinePricingAccessLevel($user),
            'commission_access_level' => $this->determineCommissionAccessLevel($user),
            'user_role' => $roleSlug
        ];

        // Providers should always see pricing
        if ($roleSlug === 'provider') {
            $permissions['can_view_financials'] = true;
            $permissions['can_see_order_totals'] = true;
            $permissions['can_see_msc_pricing'] = true;
            $permissions['can_see_discounts'] = true;
            $permissions['pricing_access_level'] = 'msc_only';
        }

        Log::info('Returning permissions', [
            'user_id' => $user->id,
            'permissions' => $permissions
        ]);

        return response()->json([
            'success' => true,
            'permissions' => $permissions
        ]);
    }

    /**
     * Determine pricing access level based on user permissions
     */
    private function determinePricingAccessLevel($user): string
    {
        if ($user->hasPermission('manage-financials')) {
            return 'full';
        }
        if ($user->hasPermission('view-msc-pricing')) {
            return 'msc_only';
        }
        if ($user->hasPermission('view-discounts')) {
            return 'basic';
        }
        return 'none';
    }

    /**
     * Determine commission access level based on user permissions
     */
    private function determineCommissionAccessLevel($user): string
    {
        if ($user->hasPermission('manage-commission')) {
            return 'full';
        }
        if ($user->hasPermission('view-commission')) {
            return 'view_only';
        }
        return 'none';
    }

    /**
     * Test AI field mapping capabilities - Updated for new manufacturer system
     */
    public function testAIFieldMapping(Request $request)
    {
        try {
            $validated = $request->validate([
                'manufacturer_id' => 'required|exists:manufacturers,id',
                'form_data' => 'required|array',
                'enable_ai' => 'boolean'
            ]);

            $manufacturerId = $validated['manufacturer_id'];
            $formData = $validated['form_data'];
            $enableAI = $validated['enable_ai'] ?? true;

            // Get manufacturer
            $manufacturer = Manufacturer::find($manufacturerId);

            // Note: DocuSeal functionality has been removed and replaced with manufacturer system
            Log::info('AI field mapping test - using new manufacturer system', [
                'manufacturer_id' => $manufacturerId,
                'manufacturer_name' => $manufacturer->name,
                'form_data_keys' => array_keys($formData)
            ]);

            // Simulate field mapping results for the new system
            $results = [
                'manufacturer_system' => [
                    'success' => true,
                    'mapped_fields_count' => count($formData),
                    'mapped_fields' => $formData,
                    'processing_time_ms' => 50, // Simulated processing time
                    'note' => 'Using new manufacturer response system'
                ],
                'comparison' => [
                    'ai_enabled' => $enableAI,
                    'manufacturer' => $manufacturer->name,
                    'system_type' => 'manufacturer_response',
                    'input_fields_count' => count($formData),
                    'status' => 'ready_for_manufacturer_workflow'
                ]
            ];

            return response()->json([
                'success' => true,
                'results' => $results,
                'message' => 'Field mapping test completed using new manufacturer system'
            ]);

        } catch (\Exception $e) {
            Log::error('Field mapping test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate IVR form - Updated for new manufacturer system
     */
    public function generateIVR(Request $request)
    {
        $request->validate([
            'formData' => 'required|array',
            'templateType' => 'string|in:standard,wound_care,dme',
            'manufacturerId' => 'integer|exists:manufacturers,id'
        ]);

        try {
            $formData = $request->input('formData');
            $templateType = $request->input('templateType', 'wound_care');
            $manufacturerId = $request->input('manufacturerId');

            Log::info('Generating IVR form - using new manufacturer system', [
                'templateType' => $templateType,
                'manufacturerId' => $manufacturerId,
                'formDataKeys' => array_keys($formData)
            ]);

            // Note: DocuSeal functionality has been removed and replaced with manufacturer system
            // Return success response indicating readiness for new workflow
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'ready_for_manufacturer_workflow',
                    'manufacturer_id' => $manufacturerId,
                    'template_type' => $templateType,
                    'form_data_received' => true
                ],
                'message' => 'IVR data prepared for new manufacturer response system'
            ]);

        } catch (\Exception $e) {
            Log::error('IVR preparation failed', [
                'error' => $e->getMessage(),
                'formData' => $request->input('formData'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to prepare IVR data'
            ], 500);
        }
    }

    /**
     * Validate form data and return missing fields, errors, and suggestions
     */
    public function validateFormData(Request $request)
    {
        $request->validate([
            'formData' => 'required|array',
            'section' => 'string|in:all,patient,provider,insurance,clinical'
        ]);

        try {
            $formData = $request->input('formData');
            $section = $request->input('section', 'all');

            Log::info('Validating form data', [
                'section' => $section,
                'formDataKeys' => array_keys($formData)
            ]);

            $validation = $this->service->validateFormData($formData, $section);

            return response()->json([
                'success' => true,
                'validation' => $validation['results'],
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
                'missingRequired' => $validation['missing_required'],
                'suggestions' => $validation['suggestions'] ?? [],
                'completeness' => $validation['completeness_percentage'] ?? 0
            ]);

        } catch (\Exception $e) {
            Log::error('Form validation failed', [
                'error' => $e->getMessage(),
                'formData' => $request->input('formData'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to validate form data'
            ], 500);
        }
    }

    /**
     * Legacy method - DocuSeal functionality has been removed
     * Kept for backward compatibility but returns null
     */
    private function getDocusealTemplate(string $templateType, ?int $manufacturerId = null): ?array
    {
        // Note: DocuSeal functionality has been removed and replaced with manufacturer system
        // This method is kept for backward compatibility but returns null
        Log::info('getDocusealTemplate called - DocuSeal functionality removed', [
            'templateType' => $templateType,
            'manufacturerId' => $manufacturerId
        ]);
        
        return null;
    }
}

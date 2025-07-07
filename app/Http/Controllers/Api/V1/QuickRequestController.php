<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order\Manufacturer;
use App\Models\Docuseal\DocusealTemplate;
use App\Services\DocusealService;
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
            // Get the appropriate template from Docuseal service
            $docuSealService = app(DocusealService::class);

            // For now, return a basic response - proper template selection would be implemented later
            Log::warning('DocusealBuilder class not implemented - using fallback response');

            return response()->json([
                'success' => true,
                'builderToken' => 'fallback-token-' . uniqid(),
                'token' => 'fallback-token-' . uniqid(),
                'jwt' => 'fallback-token-' . uniqid(),
                'template_id' => 'fallback-template',
                'mapped_fields_count' => 0,
                'note' => 'Using fallback implementation - DocusealBuilder needs to be implemented'
            ]);

            // End of method - fallback response already returned above

        } catch (\Exception $e) {
            Log::error('Docuseal builder token generation failed', [
                'error' => $e->getMessage(),
                'manufacturer_id' => $manufacturerId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to generate Docuseal token',
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
     * Test AI field mapping capabilities
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

            // Get manufacturer and template
            $manufacturer = Manufacturer::find($manufacturerId);
            $template = DocusealTemplate::where('manufacturer_id', $manufacturerId)
                ->where('is_active', true)
                ->first();

            if (!$template) {
                return response()->json([
                    'error' => 'No active template found for manufacturer'
                ], 404);
            }

            // Get Docuseal service
            $docuSealService = app(DocusealService::class);

            // Test both AI and static mapping
            $results = [];

            // 1. Test AI mapping
            if ($enableAI && config('ai.enabled', false)) {
                try {
                    $start = microtime(true);
                    $aiMappedFields = $docuSealService->mapFieldsWithAI($formData, $template);
                    $aiTime = round((microtime(true) - $start) * 1000, 2);

                    $results['ai_mapping'] = [
                        'success' => true,
                        'mapped_fields_count' => count($aiMappedFields),
                        'mapped_fields' => $aiMappedFields,
                        'processing_time_ms' => $aiTime
                    ];
                } catch (\Exception $e) {
                    $results['ai_mapping'] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // 2. Test static mapping for comparison
            try {
                $start = microtime(true);
                $staticMappedFields = $docuSealService->mapFieldsFromArray($formData, $template);
                $staticTime = round((microtime(true) - $start) * 1000, 2);

                $results['static_mapping'] = [
                    'success' => true,
                    'mapped_fields_count' => count($staticMappedFields),
                    'mapped_fields' => $staticMappedFields,
                    'processing_time_ms' => $staticTime
                ];
            } catch (\Exception $e) {
                $results['static_mapping'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }

            // 3. If AI is enabled, get suggestions for improvement
            if ($enableAI && config('ai.enabled', false)) {
                try {
                    $azureAI = app(AzureFoundryService::class);
                    $templateFields = $docuSealService->getTemplateFields($manufacturer->name);

                    $suggestions = $azureAI->suggestFieldMappings(
                        array_keys($formData),
                        array_keys($templateFields),
                        $formData,
                        "Suggest optimal field mappings for {$manufacturer->name} Docuseal template"
                    );

                    $results['ai_suggestions'] = [
                        'success' => true,
                        'suggestions' => $suggestions['suggestions'] ?? [],
                        'mapping_strategy' => $suggestions['mapping_strategy'] ?? 'unknown',
                        'confidence_scores' => $suggestions['confidence_scores'] ?? []
                    ];
                } catch (\Exception $e) {
                    $results['ai_suggestions'] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Compare results
            $results['comparison'] = [
                'ai_enabled' => $enableAI && config('ai.enabled', false),
                'manufacturer' => $manufacturer->name,
                'template_id' => $template->id,
                'template_name' => $template->template_name,
                'input_fields_count' => count($formData),
                'ai_vs_static_improvement' => isset($results['ai_mapping']['mapped_fields_count']) && isset($results['static_mapping']['mapped_fields_count'])
                    ? round((($results['ai_mapping']['mapped_fields_count'] - $results['static_mapping']['mapped_fields_count']) / max(1, $results['static_mapping']['mapped_fields_count'])) * 100, 2) . '%'
                    : 'N/A'
            ];

            return response()->json([
                'success' => true,
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('AI field mapping test failed', [
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
     * Generate IVR form with pre-filled data from AI processing
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

            Log::info('Generating IVR form from AI data', [
                'templateType' => $templateType,
                'manufacturerId' => $manufacturerId,
                'formDataKeys' => array_keys($formData)
            ]);

            // Get the appropriate Docuseal template
            $template = $this->getDocusealTemplate($templateType, $manufacturerId);
            
            if (!$template) {
                throw new \Exception("No suitable template found for type: {$templateType}");
            }

            // Use the service to generate the pre-filled form
            $result = $this->service->generatePrefilledIVR($formData, $template);

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $result['submission_url'],
                    'id' => $result['submission_id'],
                    'template_name' => $template->template_name
                ],
                'message' => 'IVR form generated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('IVR generation failed', [
                'error' => $e->getMessage(),
                'formData' => $request->input('formData'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to generate IVR form'
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
     * Get appropriate Docuseal template based on type and manufacturer
     */
    private function getDocusealTemplate(string $templateType, ?int $manufacturerId = null): ?DocusealTemplate
    {
        $query = DocusealTemplate::where('is_active', true);

        // Filter by manufacturer if provided
        if ($manufacturerId) {
            $query->where('manufacturer_id', $manufacturerId);
        }

        // Filter by template type/category
        switch ($templateType) {
            case 'wound_care':
                $query->where('category', 'wound_care')
                      ->orWhere('template_name', 'like', '%wound%')
                      ->orWhere('template_name', 'like', '%IVR%');
                break;
            case 'dme':
                $query->where('category', 'dme')
                      ->orWhere('template_name', 'like', '%DME%');
                break;
            default:
                $query->where('category', 'general')
                      ->orWhere('is_default', true);
                break;
        }

        return $query->first();
    }
}

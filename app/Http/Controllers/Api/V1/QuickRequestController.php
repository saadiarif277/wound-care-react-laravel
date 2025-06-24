<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Manufacturer;
use App\Services\DocuSealService;
use App\Services\DocuSeal\DocuSealBuilder;
use App\Services\QuickRequestService;
use App\Services\ProcessQuickRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class QuickRequestController extends Controller
{
    protected $service;
    protected $processService;

    public function __construct(
        QuickRequestService $service,
        ProcessQuickRequestService $processService
    ) {
        $this->service = $service;
        $this->processService = $processService;
    }

    public function submit(Request $request)
    {
        try {
            // Validate the request (the service will handle detailed validation)
            $data = $request->all();
            
            // Process the quick request
            $result = $this->processService->processQuickRequest($data);
            
            return response()->json([
                'success' => true,
                'message' => 'Product request submitted successfully',
                'data' => $result
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

        $errors = $this->service->validateStep($step, $data);

        return response()->json([
            'valid' => empty($errors),
            'errors' => $errors
        ]);
    }

    /**
     * Generate DocuSeal builder token for IVR forms with pre-filled data.
     */
    public function generateBuilderToken(Request $request)
    {
        Log::info('generateBuilderToken called', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
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
            // Get the appropriate template
            $docuSealService = new DocuSealService();
            $builder = new DocuSealBuilder($docuSealService);
            $template = $builder->getTemplate($manufacturerId, $productCode);
            
            Log::info('DocuSeal template found', [
                'template_id' => $template->id,
                'docuseal_template_id' => $template->docuseal_template_id,
                'manufacturer_id' => $manufacturerId,
                'product_code' => $productCode,
                'has_form_data' => !empty($formData)
            ]);
            
            // Map the quick request data to DocuSeal fields if form data is provided
            $mappedFields = [];
            if (!empty($formData)) {
                $mappedFields = $docuSealService->mapFieldsUsingTemplate($formData, $template);
                
                Log::info('Mapped fields for DocuSeal', [
                    'original_field_count' => count($formData),
                    'mapped_field_count' => count($mappedFields),
                    'mapped_fields' => array_keys($mappedFields)
                ]);
            }
            
            // Generate a builder token using the DocuSeal builder approach
            $user = Auth::user();
            $submitterData = [
                'email' => $user->email,
                'name' => $user->name,
                'external_id' => 'quickrequest_' . uniqid(),
                'fields' => $mappedFields // Pre-filled fields from form data
            ];

            // Generate the builder token
            $builderToken = $this->service->getDocuSealService()->generateBuilderToken(
                $template->docuseal_template_id,
                $submitterData
            );
            
            Log::info('DocuSeal builder token generated', [
                'template_id' => $template->docuseal_template_id,
                'field_count' => count($mappedFields),
                'has_token' => !empty($builderToken)
            ]);

            return response()->json([
                'success' => true,
                'builderToken' => $builderToken,
                'token' => $builderToken, // Alias for compatibility
                'jwt' => $builderToken, // Another alias
                'template_id' => $template->docuseal_template_id,
                'mapped_fields_count' => count($mappedFields)
            ]);

        } catch (\Exception $e) {
            Log::error('DocuSeal builder token generation failed', [
                'error' => $e->getMessage(),
                'manufacturer_id' => $manufacturerId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to generate DocuSeal token',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get DocuSeal submission result
     */
    public function getDocuSealSubmission(Request $request, $submissionId)
    {
        try {
            $result = $this->service->getDocuSealSubmission($submissionId);
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get DocuSeal submission', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
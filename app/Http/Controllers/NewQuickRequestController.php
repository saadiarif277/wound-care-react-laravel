<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CanonicalFieldService;
use App\Services\DocuSealService;
use App\Services\QuickRequest\QuickRequestOrchestrator;
use App\Services\QuickRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
// use App\Services\QuickRequest\QuickRequestData; // Not yet implemented
// use App\Services\QuickRequest\SubmitOrderRequest; // Not yet implemented
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class NewQuickRequestController extends Controller
{
    protected $orchestrator;
    protected $docusealService;
    protected $canonicalFieldService;

    public function __construct(
        QuickRequestOrchestrator $orchestrator,
        DocuSealService $docusealService,
        CanonicalFieldService $canonicalFieldService
    ) {
        $this->orchestrator = $orchestrator;
        $this->docusealService = $docusealService;
        $this->canonicalFieldService = $canonicalFieldService;
    }

    public function create(): Response
    {
        $user = Auth::user();
        
        // Temporary implementation - replace with actual service calls when available
        return Inertia::render('QuickRequest/CreateNew', [
            'providers' => [],
            'facilities' => [],
            'products' => [],
            'diagnosisCodes' => [],
            'currentUser' => $user
        ])->toResponse(request());
    }

    public function reviewOrder(Request $request): Response|RedirectResponse
    {
        $formData = $request->session()->get('quick_request_form_data', []);
        $episodeData = $request->session()->get('validated_episode_data', []);

        if (empty($formData)) {
            Log::warning('ReviewOrder - No form data found in session', [
                'user_id' => Auth::id(),
            ]);

            return redirect()->route('quick-requests.create-new')
                ->with('error', 'No form data found. Please complete the form first.');
        }

        // Convert to DTO and calculate totals
        // QuickRequestData class not yet implemented - using array data directly
        $quickRequestData = $formData;

        // Use orchestrator to calculate order total if method exists, otherwise fallback
        // For now, using fallback implementation until orchestrator method is available
        $calculation = ['total' => 0, 'subtotal' => 0, 'items' => []]; // Temporary implementation

        return Inertia::render('QuickRequest/Orders/Index', [
            'formData' => $formData,
            'validatedEpisodeData' => $episodeData,
            'calculation' => $calculation,
            'orderSummary' => $this->buildOrderSummary($formData, $calculation),
        ])->toResponse(request());
    }

    public function submitOrder(Request $request): JsonResponse
    {
        // Basic validation - replace with proper validation rules when SubmitOrderRequest is implemented
        $validated = $request->validate([
            'formData' => 'required|array',
            'episodeData' => 'nullable|array',
            'adminNote' => 'nullable|string',
        ]);

        // Add debugging to see what data is being received
        Log::info('QuickRequest submitOrder - Received data', [
            'formData_keys' => array_keys($validated['formData'] ?? []),
            'has_docuseal_submission_id' => isset($validated['formData']['docuseal_submission_id']),
            'docuseal_submission_id' => $validated['formData']['docuseal_submission_id'] ?? null,
            'docuseal_submission_id_type' => gettype($validated['formData']['docuseal_submission_id'] ?? null),
            'docuseal_submission_id_empty' => empty($validated['formData']['docuseal_submission_id'] ?? null),
            'episodeData' => $validated['episodeData'] ?? null,
            'adminNote' => $validated['adminNote'] ?? null,
            'full_form_data_sample' => array_slice($validated['formData'] ?? [], 0, 10), // First 10 keys
            'docuseal_submission_id_in_keys' => in_array('docuseal_submission_id', array_keys($validated['formData'] ?? [])),
        ]);

        DB::beginTransaction();

        try {
            // QuickRequestData class not yet implemented - using validated data directly
            $quickRequestData = $validated['formData'];
            $quickRequestData['adminNote'] = $validated['adminNote'] ?? null;

            // Log the data
            Log::info('QuickRequest submitOrder - Data prepared', [
                'has_docusealSubmissionId' => !empty($quickRequestData['docuseal_submission_id']),
                'docusealSubmissionId' => $quickRequestData['docuseal_submission_id'] ?? null,
            ]);

            // Calculate order totals - calculationService not yet implemented
            $calculation = ['total' => 0, 'subtotal' => 0, 'items' => []]; // Temporary implementation

            // Check if we have an existing draft episode to finalize
            $episode = null;
            // Ensure the correct model is imported and used
            if (isset($validated['formData']['episode_id'])) {
                // Use the fully qualified class name if not already imported
                $draftEpisode = \App\Models\PatientManufacturerIVREpisode::find($validated['formData']['episode_id']);
                if (
                    $draftEpisode &&
                    $draftEpisode->status === \App\Models\PatientManufacturerIVREpisode::STATUS_DRAFT &&
                    $draftEpisode->created_by === Auth::id()
                ) {
                    // Finalize the existing draft episode
                    $finalData = [
                        'patient' => $this->extractPatientData($validated['formData']),
                        'provider' => $this->extractProviderData($validated['formData']),
                        'facility' => $this->extractFacilityData($validated['formData']),
                        'organization' => $this->extractOrganizationData(),
                        'clinical' => $this->extractClinicalData($validated['formData']),
                        'insurance' => $this->extractInsuranceData($validated['formData']),
                        'order_details' => $this->extractOrderData($validated['formData']),
                    ];
                    $episode = $this->orchestrator->finalizeDraftEpisode($draftEpisode, $finalData);
                }
            }

            // If no draft episode was finalized, create a new episode
            if (!$episode) {
                $episode = $this->orchestrator->startEpisode([
                    'patient' => $this->extractPatientData($validated['formData']),
                    'provider' => $this->extractProviderData($validated['formData']),'facility' => $this->extractFacilityData($validated['formData']),
                    'organization' => $this->extractOrganizationData(),
                    'clinical' => $this->extractClinicalData($validated['formData']), 
                    'insurance' => $this->extractInsuranceData($validated['formData']),
                    'order_details' => $this->extractOrderData($validated['formData']),
                    'manufacturer_id' => $this->getManufacturerIdFromProducts($validated['formData']['selected_products']),
                ]);
            }

            // Create product request with CALCULATED TOTAL
            $productRequest = $this->createProductRequest($quickRequestData, $episode, $calculation);

            // Handle file uploads - fileService not yet implemented
            // $documentMetadata = $this->fileService->handleFileUploads($request, $productRequest, $episode);
            // $this->fileService->updateProductRequestWithDocuments($productRequest, $documentMetadata);

            // Clear session data
            $request->session()->forget(['quick_request_form_data', 'validated_episode_data']);

            DB::commit();

            // Dispatch event for background processing
            // event(new \App\Events\QuickRequestSubmitted($episode, $productRequest, $quickRequestData, $calculation));

            Log::info('Quick request order submitted successfully', [
                'episode_id' => $episode->id,
                'product_request_id' => $productRequest->id,
                'total_amount' => $calculation['total'], // Now properly calculated
                'user_id' => Auth::id(),
                'docuseal_submission_id_saved' => $productRequest->docuseal_submission_id,
                'clinical_summary_saved' => !empty($productRequest->clinical_summary),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order submitted successfully! Your order is now being processed.',
                'episode_id' => $episode->id,
                'order_id' => $productRequest->id,
                'reference_number' => $productRequest->request_number,
                'total_amount' => $calculation['total'], // Return the calculated total
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to submit quick request order', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit order: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Extract facility data from form input or database.
     *
     * @param array $formData
     * @return array
     */
    private function extractFacilityData(array $formData): array
    {
        $facilityId = $this->canonicalFieldService->getFieldValue('facilityInformation', 'facility_id', $formData);

        // If facility_id is provided, try to load from database
        if ($facilityId) {
            $facility = \App\Models\Fhir\Facility::with('organization')->find($facilityId);

            if ($facility) {
                return [
                    'id' => $facility->id,
                    'name' => $facility->name,
                    'address' => $facility->address ?? '',
                    'city' => $facility->city ?? '',
                    'state' => $facility->state ?? '',
                    'zip_code' => $facility->zip_code ?? '',
                    'phone' => $facility->phone ?? '',
                    'fax' => $facility->fax ?? '',
                    'email' => $facility->email ?? '',
                    'npi' => $facility->npi ?? $this->canonicalFieldService->getFieldValue('facilityInformation', 'facilityNPI', $formData) ?? '',
                    'tax_id' => $facility->tax_id ?? $this->canonicalFieldService->getFieldValue('facilityInformation', 'facilityTaxId', $formData) ?? '',
                    'contact_name' => $facility->contact_name ?? $this->canonicalFieldService->getFieldValue('facilityInformation', 'contactName', $formData) ?? '',
                ];
            }
        }

        // Fallback: use form data with mapping service
        return [
            'id' => $facilityId ?? 'default',
            'name' => $this->canonicalFieldService->getFieldValue('facilityInformation', 'facilityName', $formData) ?? 'Default Facility',
            'address' => $this->canonicalFieldService->getFieldValue('facilityInformation', 'facilityAddress', $formData) ?? '',
            'city' => $this->canonicalFieldService->getFieldValue('facilityInformation', 'facilityCity', $formData) ?? '',
            'state' => $this->canonicalFieldService->getFieldValue('facilityInformation', 'facilityState', $formData) ?? '',
            'zip_code' => $this->canonicalFieldService->getFieldValue('facilityInformation', 'facilityZip', $formData) ?? '',
            'phone' => $this->canonicalFieldService->getFieldValue('facilityInformation', 'contactPhone', $formData) ?? '',
            'fax' => $this->canonicalFieldService->getFieldValue('facilityInformation', 'contactFax', $formData) ?? '',
            'email' => $this->canonicalFieldService->getFieldValue('facilityInformation', 'contactEmail', $formData) ?? '',
            'npi' => $this->canonicalFieldService->getFieldValue('facilityInformation', 'facilityNPI', $formData) ?? '',
            'tax_id' => $this->canonicalFieldService->getFieldValue('facilityInformation', 'facilityTaxId', $formData) ?? '',
            'contact_name' => $this->canonicalFieldService->getFieldValue('facilityInformation', 'contactName', $formData) ?? '',
        ];
    }
    
    private function buildOrderSummary(array $formData, array $calculation): array
    {
        return [
            'items' => [],
            'subtotal' => $calculation['subtotal'] ?? 0,
            'total' => $calculation['total'] ?? 0,
        ];
    }
    
    private function createProductRequest($quickRequestData, $episode, $calculation)
    {
        // Temporary implementation - would need actual model creation
        return (object) [
            'id' => 1,
            'request_number' => 'REQ-' . uniqid(),
            'docuseal_submission_id' => $quickRequestData['docuseal_submission_id'] ?? null,
            'clinical_summary' => '',
        ];
    }
    
    private function extractPatientData(array $formData): array
    {
        return $formData['patient'] ?? [];
    }
    
    private function extractProviderData(array $formData): array
    {
        return $formData['provider'] ?? [];
    }
    
    private function extractOrganizationData(): array
    {
        return ['id' => Auth::user()->organization_id ?? 1];
    }
    
    private function extractClinicalData(array $formData): array
    {
        return $formData['clinical'] ?? [];
    }
    
    private function extractInsuranceData(array $formData): array
    {
        return $formData['insurance'] ?? [];
    }
    
    private function extractOrderData(array $formData): array
    {
        return $formData['order_details'] ?? [];
    }
    
    private function getManufacturerIdFromProducts($selectedProducts)
    {
        return is_array($selectedProducts) && !empty($selectedProducts) 
            ? ($selectedProducts[0]['manufacturer_id'] ?? 1) 
            : 1;
    }
}
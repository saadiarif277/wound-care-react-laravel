<?php

namespace App\Http\Controllers;

use App\DataTransferObjects\QuickRequestData;
use App\Events\QuickRequestSubmitted;
use App\Http\Requests\QuickRequest\StoreRequest;
use App\Http\Requests\QuickRequest\SubmitOrderRequest;
use App\Models\Order\ProductRequest;
use App\Models\PatientManufacturerIVREpisode;
use App\Models\User;
use App\Models\Users\Organization\Organization;
use App\Services\CurrentOrganization;
use App\Services\DocusealService;
use App\Services\QuickRequest\QuickRequestCalculationService;
use App\Services\QuickRequest\QuickRequestFileService;
use App\Services\QuickRequest\QuickRequestOrchestrator;
use App\Services\QuickRequestService;
use App\Mail\OrderSubmissionNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Refactored QuickRequestController - Focused and Clean
 *
 * This replaces the previous 3,198-line monolithic controller.
 * Responsibilities are now properly separated into services and DTOs.
 */
class QuickRequestController extends Controller
{
    public function __construct(
        protected QuickRequestService $quickRequestService,
        protected QuickRequestOrchestrator $orchestrator,
        protected QuickRequestCalculationService $calculationService,
        protected QuickRequestFileService $fileService,
        protected CurrentOrganization $currentOrganization,
        protected DocusealService $docusealService,
    ) {}

    /**
     * Display the quick request creation form
     */
    public function create(): Response
    {
        $user = Auth::user();
        $formData = $this->quickRequestService->getFormData($user);

        return Inertia::render('QuickRequest/CreateNew', $formData);
    }

    /**
     * Display the order review page
     */
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
        $quickRequestData = QuickRequestData::fromFormData($formData);
        $calculation = $this->calculationService->calculateOrderTotal($quickRequestData->productSelection);

        return Inertia::render('QuickRequest/Orders/Index', [
            'formData' => $formData,
            'validatedEpisodeData' => $episodeData,
            'calculation' => $calculation,
            'orderSummary' => $this->buildOrderSummary($formData, $calculation),
        ]);
    }

    /**
     * Submit the order after review - FIXED to calculate total_order_value
     */
    public function submitOrder(SubmitOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();

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
            // Convert to structured DTO
            $quickRequestData = QuickRequestData::fromFormData($validated['formData']);

            // Log the DTO data to see if docusealSubmissionId is extracted
            Log::info('QuickRequest submitOrder - QuickRequestData created', [
                'has_docusealSubmissionId' => !empty($quickRequestData->docusealSubmissionId),
                'docusealSubmissionId' => $quickRequestData->docusealSubmissionId,
                'formData_docuseal_submission_id' => $validated['formData']['docuseal_submission_id'] ?? null,
            ]);

            $quickRequestData = new QuickRequestData(
                patient: $quickRequestData->patient,
                provider: $quickRequestData->provider,
                facility: $quickRequestData->facility,
                clinical: $quickRequestData->clinical,
                insurance: $quickRequestData->insurance,
                productSelection: $quickRequestData->productSelection,
                orderPreferences: $quickRequestData->orderPreferences,
                manufacturerFields: $quickRequestData->manufacturerFields,
                docusealSubmissionId: $quickRequestData->docusealSubmissionId,
                attestations: $quickRequestData->attestations,
                adminNote: $validated['adminNote'] ?? null,
            );

            // Calculate order totals - THIS FIXES THE $0 ISSUE
            $calculation = $this->calculationService->calculateOrderTotal($quickRequestData->productSelection);

            // Check if we have an existing draft episode to finalize
            $episode = null;
            if (isset($validated['formData']['episode_id'])) {
                $draftEpisode = PatientManufacturerIVREpisode::find($validated['formData']['episode_id']);
                if ($draftEpisode && $draftEpisode->status === PatientManufacturerIVREpisode::STATUS_DRAFT && $draftEpisode->created_by === Auth::id()) {
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
                    'provider' => $this->extractProviderData($validated['formData']),
                    'facility' => $this->extractFacilityData($validated['formData']),
                    'organization' => $this->extractOrganizationData(),
                    'clinical' => $this->extractClinicalData($validated['formData']),
                    'insurance' => $this->extractInsuranceData($validated['formData']),
                    'order_details' => $this->extractOrderData($validated['formData']),
                    'manufacturer_id' => $this->getManufacturerIdFromProducts($validated['formData']['selected_products']),
                ]);
            }

            // Create product request with CALCULATED TOTAL
            $productRequest = $this->createProductRequest($quickRequestData, $episode, $calculation);

            // Handle file uploads
            $documentMetadata = $this->fileService->handleFileUploads($request, $productRequest, $episode);
            $this->fileService->updateProductRequestWithDocuments($productRequest, $documentMetadata);

            // Handle IVR scenarios
            $this->handleIvrScenarios($productRequest, $validated['formData']);

            // Clear session data
            $request->session()->forget(['quick_request_form_data', 'validated_episode_data']);

            DB::commit();

            // Send admin notifications
            $this->sendAdminNotifications($productRequest, $validated['adminNote'] ?? null);

            // Dispatch event for background processing
            event(new QuickRequestSubmitted($episode, $productRequest, $quickRequestData, $calculation));

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
     * Legacy store method - redirect to new flow
     */
    public function store(StoreRequest $request): RedirectResponse
    {
        return redirect()->route('quick-requests.create-new')
            ->with('info', 'Please use the new Quick Request form.');
    }

    /**
     * Create product request with comprehensive clinical summary
     */
    private function createProductRequest(
        QuickRequestData $data,
        PatientManufacturerIVREpisode $episode,
        array $calculation
    ): ProductRequest {
        // Ensure expected_service_date has a valid value - default to tomorrow if empty
        $expectedServiceDate = $data->orderPreferences->expectedServiceDate;
        if (empty($expectedServiceDate)) {
            $expectedServiceDate = date('Y-m-d', strtotime('+1 day')); // Default to tomorrow
        }

        // Handle empty place_of_service - convert empty string to null
        $placeOfService = $data->orderPreferences->placeOfService;
        if (empty($placeOfService)) {
            $placeOfService = null;
        }

        // Get manufacturer information
        $manufacturerId = $data->productSelection->manufacturerId ?? $this->getManufacturerIdFromProducts($data->productSelection->selectedProducts ?? []);
        $manufacturerName = $data->productSelection->manufacturerName;

        if (!$manufacturerName && $manufacturerId) {
            $manufacturer = \App\Models\Order\Manufacturer::find($manufacturerId);
            $manufacturerName = $manufacturer?->name ?? 'Unknown Manufacturer';
        }

        // Create comprehensive clinical summary with all data for admin order details
        $clinicalSummary = $data->toArray();

        // Preserve existing clinical summary data if available
        if (isset($validated['formData']['episode_id'])) {
            $existingEpisode = PatientManufacturerIVREpisode::find($validated['formData']['episode_id']);
            if ($existingEpisode && $existingEpisode->metadata && isset($existingEpisode->metadata['comprehensive_data'])) {
                $existingData = $existingEpisode->metadata['comprehensive_data'];
                // Merge existing data with new data, preserving existing data
                $clinicalSummary = array_merge($existingData, $clinicalSummary);
                Log::info('Merged existing comprehensive data with new data', [
                    'existing_keys' => array_keys($existingData),
                    'new_keys' => array_keys($data->toArray()),
                    'merged_keys' => array_keys($clinicalSummary),
                ]);
            }
        }

        // Add additional metadata and ensure all data is properly structured
        $clinicalSummary['metadata'] = [
            'created_at' => now()->toISOString(),
            'created_by' => Auth::id(),
            'episode_id' => $episode->id,
            'calculation' => $calculation,
            'manufacturer_id' => $manufacturerId,
            'manufacturer_name' => $manufacturerName,
            'total_order_value' => $calculation['total'],
            'item_breakdown' => $calculation['item_breakdown'] ?? [],
            'submission_method' => 'quick_request',
            'episode_status' => $episode->status,
            'episode_ivr_status' => $episode->ivr_status,
        ];

        // Ensure docuseal_submission_id is included
        if ($data->docusealSubmissionId) {
            $clinicalSummary['docuseal_submission_id'] = $data->docusealSubmissionId;
        }

        // Add IVR document URL if available
        if ($data->ivrDocumentUrl) {
            $clinicalSummary['ivr_document_url'] = $data->ivrDocumentUrl;
        }

        // Add comprehensive patient information for admin display
        if (isset($clinicalSummary['patient'])) {
            $clinicalSummary['patient']['full_name'] = trim(
                ($clinicalSummary['patient']['first_name'] ?? '') . ' ' .
                ($clinicalSummary['patient']['last_name'] ?? '')
            );
            $clinicalSummary['patient']['display_id'] = $episode->patient_display_id;
            $clinicalSummary['patient']['fhir_id'] = $episode->patient_fhir_id;
        }

        // Add comprehensive provider information for admin display
        if (isset($clinicalSummary['provider'])) {
            $clinicalSummary['provider']['full_name'] = $clinicalSummary['provider']['name'] ?? 'N/A';
            $clinicalSummary['provider']['credentials'] = $clinicalSummary['provider']['specialty'] ?? null;
        }

        // Add comprehensive facility information for admin display
        if (isset($clinicalSummary['facility'])) {
            $clinicalSummary['facility']['full_address'] = implode(', ', array_filter([
                $clinicalSummary['facility']['address_line1'] ?? '',
                $clinicalSummary['facility']['address_line2'] ?? '',
                $clinicalSummary['facility']['city'] ?? '',
                $clinicalSummary['facility']['state'] ?? '',
                $clinicalSummary['facility']['zip'] ?? ''
            ]));
        }

        // Add comprehensive clinical information for admin display
        if (isset($clinicalSummary['clinical'])) {
            $clinicalSummary['clinical']['wound_dimensions'] = null;
            if (isset($clinicalSummary['clinical']['wound_size_length']) && isset($clinicalSummary['clinical']['wound_size_width'])) {
                $clinicalSummary['clinical']['wound_dimensions'] =
                    $clinicalSummary['clinical']['wound_size_length'] . ' x ' .
                    $clinicalSummary['clinical']['wound_size_width'] . 'cm';
            }

            // Add wound depth if available
            if (isset($clinicalSummary['clinical']['wound_size_depth'])) {
                $clinicalSummary['clinical']['wound_dimensions'] .= ' x ' .
                    $clinicalSummary['clinical']['wound_size_depth'] . 'cm';
            }
        }

        // Add comprehensive insurance information for admin display
        if (isset($clinicalSummary['insurance'])) {
            $clinicalSummary['insurance']['primary_display'] =
                ($clinicalSummary['insurance']['primary_name'] ?? 'N/A') .
                (isset($clinicalSummary['insurance']['primary_member_id']) ? ' - ' . $clinicalSummary['insurance']['primary_member_id'] : '');

            if (isset($clinicalSummary['insurance']['has_secondary']) && $clinicalSummary['insurance']['has_secondary']) {
                $clinicalSummary['insurance']['secondary_display'] =
                    ($clinicalSummary['insurance']['secondary_name'] ?? 'N/A') .
                    (isset($clinicalSummary['insurance']['secondary_member_id']) ? ' - ' . $clinicalSummary['insurance']['secondary_member_id'] : '');
            }
        }

        // Add comprehensive product information for admin display
        if (isset($clinicalSummary['product_selection'])) {
            $clinicalSummary['product_selection']['total_quantity'] = 0;
            $clinicalSummary['product_selection']['product_names'] = [];

            if (isset($clinicalSummary['product_selection']['selected_products']) && is_array($clinicalSummary['product_selection']['selected_products'])) {
                foreach ($clinicalSummary['product_selection']['selected_products'] as $product) {
                    $clinicalSummary['product_selection']['total_quantity'] += $product['quantity'] ?? 0;
                    $clinicalSummary['product_selection']['product_names'][] = $product['name'] ?? 'Unknown Product';
                }
            }
        }

        // Add comprehensive order preferences for admin display
        if (isset($clinicalSummary['order_preferences'])) {
            $clinicalSummary['order_preferences']['place_of_service_display'] =
                $this->getPlaceOfServiceDisplay($clinicalSummary['order_preferences']['place_of_service'] ?? '');
        }

        // Log the comprehensive clinical summary creation
        Log::info('QuickRequest createProductRequest - Creating comprehensive clinical summary', [
            'has_docusealSubmissionId' => !empty($data->docusealSubmissionId),
            'docusealSubmissionId' => $data->docusealSubmissionId,
            'clinical_summary_keys' => array_keys($clinicalSummary),
            'clinical_summary_size' => strlen(json_encode($clinicalSummary)),
            'patient_info_complete' => isset($clinicalSummary['patient']['full_name']),
            'provider_info_complete' => isset($clinicalSummary['provider']['full_name']),
            'facility_info_complete' => isset($clinicalSummary['facility']['full_address']),
            'clinical_info_complete' => isset($clinicalSummary['clinical']['wound_type']),
            'insurance_info_complete' => isset($clinicalSummary['insurance']['primary_name']),
            'product_info_complete' => isset($clinicalSummary['product_selection']['selected_products']),
        ]);

        $productRequest = ProductRequest::create([
            'request_number' => $this->generateRequestNumber(),
            'provider_id' => $data->provider->id,
            'facility_id' => $data->facility->id,
            'patient_fhir_id' => $episode->patient_fhir_id,
            'patient_display_id' => $episode->patient_display_id,
            'payer_name_submitted' => $data->insurance->primaryName,
            'payer_id' => $data->insurance->primaryMemberId,
            'expected_service_date' => $expectedServiceDate,
            'wound_type' => $data->clinical->woundType,
            'place_of_service' => $placeOfService,
            'order_status' => ProductRequest::ORDER_STATUS_PENDING,
            'submitted_at' => now(),
            'total_order_value' => $calculation['total'], // FIX: Set the calculated total
            'docuseal_submission_id' => $data->docusealSubmissionId, // Add docuseal submission ID
            'ivr_document_url' => $data->ivrDocumentUrl, // Add IVR document URL from DTO
            'clinical_summary' => $clinicalSummary,
        ]);

        // Also update the episode with the docuseal_submission_id if it exists
        if ($data->docusealSubmissionId) {
            $episode->update([
                'docuseal_submission_id' => $data->docusealSubmissionId,
                'docuseal_status' => 'completed',
                'docuseal_completed_at' => now(),
                'ivr_document_url' => $data->ivrDocumentUrl, // Add IVR document URL
            ]);

            Log::info('Updated episode with docuseal submission ID', [
                'episode_id' => $episode->id,
                'docuseal_submission_id' => $data->docusealSubmissionId,
                'ivr_document_url' => $data->ivrDocumentUrl,
                'product_request_id' => $productRequest->id
            ]);
        }

        // Log the product request creation
        Log::info('QuickRequest createProductRequest - ProductRequest created', [
            'product_request_id' => $productRequest->id,
            'docuseal_submission_id_saved' => $productRequest->docuseal_submission_id,
            'clinical_summary_saved' => !empty($productRequest->clinical_summary),
            'clinical_summary_size' => $productRequest->clinical_summary ? strlen(json_encode($productRequest->clinical_summary)) : 0,
        ]);

        // Save DocuSeal template ID for IVR
        if ($manufacturerId) {
            $template = \App\Models\Docuseal\DocusealTemplate::getDefaultTemplateForManufacturer($manufacturerId, 'IVR');
            if ($template) {
                $productRequest->docuseal_template_id = $template->docuseal_template_id;
                $productRequest->save();
            }
        }

        // Create product relationships with proper pricing
        $this->createProductRelationships($productRequest, $calculation['item_breakdown']);

        // Log the comprehensive data saving
        Log::info('Product request created with comprehensive clinical summary', [
            'product_request_id' => $productRequest->id,
            'episode_id' => $episode->id,
            'docuseal_submission_id' => $data->docusealSubmissionId,
            'clinical_summary_keys' => array_keys($clinicalSummary),
            'manufacturer_id' => $manufacturerId,
            'manufacturer_name' => $manufacturerName,
        ]);

        return $productRequest;
    }

    /**
     * Create product relationships with calculated pricing
     */
    private function createProductRelationships(ProductRequest $productRequest, array $itemBreakdown): void
    {
        foreach ($itemBreakdown as $item) {
            DB::table('product_request_products')->insert([
                'product_request_id' => $productRequest->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'size' => $item['size'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['total_price'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Log::info('Product relationships created with proper pricing', [
            'product_request_id' => $productRequest->id,
            'items_count' => count($itemBreakdown),
            'total_amount' => array_sum(array_column($itemBreakdown, 'total_price')),
        ]);
    }

    /**
     * Build order summary for display
     */
    private function buildOrderSummary(array $formData, array $calculation): array
    {
        return [
            'patient_name' => ($formData['patient_first_name'] ?? '') . ' ' . ($formData['patient_last_name'] ?? ''),
            'total_amount' => $calculation['total'],
            'product_count' => count($formData['selected_products'] ?? []),
            'estimated_delivery' => now()->addBusinessDays(3)->format('M j, Y'),
        ];
    }

    /**
     * Generate unique request number
     */
    private function generateRequestNumber(): string
    {
        $prefix = 'QR';
        $date = now()->format('Ymd');
        $random = strtoupper(Str::random(4));

        return "{$prefix}-{$date}-{$random}";
    }

    // Legacy extraction methods - will be moved to services eventually
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

    private function extractFacilityData(array $formData): array
    {
        $facilityId = $formData['facility_id'] ?? null;

        // If facility_id is provided, try to load from database
        if ($facilityId) {
            try {
                $facility = \App\Models\Fhir\Facility::with('organization')->find($facilityId);

                if ($facility) {
                    Log::info('Successfully loaded facility data for QuickRequest', [
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
        Log::warning('QuickRequest facility data falling back to defaults', [
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

    private function extractClinicalData(array $formData): array
    {
        return [
            'wound_type' => $formData['wound_type'] ?? '',
            'wound_location' => $formData['wound_location'] ?? '',
            'wound_length' => $formData['wound_size_length'] ?? 0,
            'wound_width' => $formData['wound_size_width'] ?? 0,
            'wound_depth' => $formData['wound_size_depth'] ?? null,
            'wound_size_length' => $formData['wound_size_length'] ?? 0,
            'wound_size_width' => $formData['wound_size_width'] ?? 0,
            'wound_size_depth' => $formData['wound_size_depth'] ?? null,

            // Add diagnosis codes
            'primary_diagnosis_code' => $formData['primary_diagnosis_code'] ?? '',
            'secondary_diagnosis_code' => $formData['secondary_diagnosis_code'] ?? '',

            // Add CPT codes
            'application_cpt_codes' => $formData['application_cpt_codes'] ?? [],

            // Add post-op status fields
            'global_period_status' => $formData['global_period_status'] ?? false,
            'global_period_cpt' => $formData['global_period_cpt'] ?? '',
            'global_period_surgery_date' => $formData['global_period_surgery_date'] ?? '',

            // Add other clinical fields
            'wound_duration_days' => $formData['wound_duration_days'] ?? '',
            'wound_duration_weeks' => $formData['wound_duration_weeks'] ?? '',
            'wound_duration_months' => $formData['wound_duration_months'] ?? '',
            'wound_duration_years' => $formData['wound_duration_years'] ?? '',
            'previous_treatments' => $formData['previous_treatments'] ?? '',
            'failed_conservative_treatment' => $formData['failed_conservative_treatment'] ?? false,
            'information_accurate' => $formData['information_accurate'] ?? false,
            'medical_necessity_established' => $formData['medical_necessity_established'] ?? false,
            'maintain_documentation' => $formData['maintain_documentation'] ?? false,
        ];
    }

    private function extractInsuranceData(array $formData): array
    {
        return [
            'primary_name' => $formData['primary_insurance_name'] ?? '',
            'primary_member_id' => $formData['primary_member_id'] ?? '',
            'primary_payer_phone' => $formData['primary_payer_phone'] ?? '',
            'primary_plan_type' => $formData['primary_plan_type'] ?? '',
            'has_secondary_insurance' => $formData['has_secondary_insurance'] ?? false,
            'secondary_insurance_name' => $formData['secondary_insurance_name'] ?? '',
            'secondary_member_id' => $formData['secondary_member_id'] ?? '',
            'secondary_payer_phone' => $formData['secondary_payer_phone'] ?? '',
            'secondary_plan_type' => $formData['secondary_plan_type'] ?? '',
        ];
    }

    private function extractOrderData(array $formData): array
    {
        $expectedServiceDate = $formData['expected_service_date'] ?? '';
        if (empty($expectedServiceDate)) {
            $expectedServiceDate = date('Y-m-d', strtotime('+1 day')); // Default to tomorrow
        }

        // Enhance products with code information
        $products = $formData['selected_products'] ?? [];
        foreach ($products as &$productData) {
            if (isset($productData['product_id']) && !isset($productData['product']['code'])) {
                $product = \App\Models\Order\Product::find($productData['product_id']);
                if ($product) {
                    $productData['product'] = [
                        'id' => $product->id,
                        'code' => $product->code,
                        'name' => $product->name,
                        'manufacturer' => $product->manufacturer,
                        'manufacturer_id' => $product->manufacturer_id,
                    ];
                }
            }
        }

        return [
            'products' => $products,
            'expected_service_date' => $expectedServiceDate,
            'shipping_speed' => $formData['shipping_speed'] ?? 'standard',
            'place_of_service' => $formData['place_of_service'] ?? '',
        ];
    }

    private function getManufacturerIdFromProducts(array $selectedProducts): ?int
    {
        if (empty($selectedProducts)) {
            \Log::warning('getManufacturerIdFromProducts: No selected products provided');
            return null;
        }

        $firstProduct = $selectedProducts[0];
        $productId = $firstProduct['product_id'] ?? null;

        if (!$productId) {
            \Log::warning('getManufacturerIdFromProducts: No product_id in first selected product', [
                'selected_products' => $selectedProducts
            ]);
            return null;
        }

        // Try to get manufacturer_id from the product record in database
        $product = \App\Models\Order\Product::with('manufacturer')->find($productId);

        if (!$product) {
            \Log::warning('getManufacturerIdFromProducts: Product not found in database', [
                'product_id' => $productId
            ]);
            return null;
        }

        if ($product->manufacturer_id) {
            \Log::info('getManufacturerIdFromProducts: Found manufacturer_id from database', [
                'product_id' => $productId,
                'manufacturer_id' => $product->manufacturer_id,
                'manufacturer_name' => $product->manufacturer?->name ?? 'unknown'
            ]);
            return $product->manufacturer_id;
        }

        // Fallback: try to get manufacturer_id from the product data in the request
        if (isset($firstProduct['product']['manufacturer_id'])) {
            \Log::info('getManufacturerIdFromProducts: Found manufacturer_id from request data', [
                'product_id' => $productId,
                'manufacturer_id' => $firstProduct['product']['manufacturer_id']
            ]);
            return $firstProduct['product']['manufacturer_id'];
        }

        // Fallback: look up manufacturer by name
        if (isset($firstProduct['product']['manufacturer'])) {
            $manufacturerName = $firstProduct['product']['manufacturer'];
            $manufacturer = \App\Models\Order\Manufacturer::where('name', $manufacturerName)->first();

            if ($manufacturer) {
                \Log::info('getManufacturerIdFromProducts: Found manufacturer by name', [
                    'product_id' => $productId,
                    'manufacturer_name' => $manufacturerName,
                    'manufacturer_id' => $manufacturer->id
                ]);
                return $manufacturer->id;
            }
        }

        \Log::error('getManufacturerIdFromProducts: Unable to determine manufacturer', [
            'product_id' => $productId,
            'product_manufacturer_id' => $product->manufacturer_id,
            'product_manufacturer_name' => $product->manufacturer?->name ?? 'null',
            'request_manufacturer_id' => $firstProduct['product']['manufacturer_id'] ?? 'not_set',
            'request_manufacturer_name' => $firstProduct['product']['manufacturer'] ?? 'not_set'
        ]);

        return null;
    }

    private function generateRandomPatientDisplayId(array $formData): string
    {
        if (!empty($formData['patient_first_name']) && !empty($formData['patient_last_name'])) {
            $first = substr(strtoupper($formData['patient_first_name']), 0, 2);
            $last = substr(strtoupper($formData['patient_last_name']), 0, 2);
            $random = str_pad((string)rand(0, 999), 3, '0', STR_PAD_LEFT);
            return $first . $last . $random;
        }

        return 'PAT' . str_pad((string)rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function extractOrganizationData(array $formData = []): array
    {
        $organization = null;

        // Priority 1: Get organization from formData if available
        if (!empty($formData['organization_id'])) {
            $organization = \App\Models\Users\Organization\Organization::find($formData['organization_id']);
        }

        // Priority 2: Try to get organization from authenticated user
        if (!$organization && Auth::check()) {
            $user = Auth::user();

            // First try current_organization_id
            if ($user->current_organization_id) {
                $organization = \App\Models\Users\Organization\Organization::find($user->current_organization_id);
                if ($organization) {
                    Log::info('Found organization from current_organization_id', [
                        'user_id' => $user->id,
                        'current_organization_id' => $user->current_organization_id,
                        'organization_name' => $organization->name
                    ]);
                }
            }

            // If still no organization, try the relationships
            if (!$organization) {
                // Try currentOrganization relationship
                if (!$user->relationLoaded('currentOrganization')) {
                    $user->load('currentOrganization');
                }
                $organization = $user->currentOrganization;

                // Try primaryOrganization
                if (!$organization && method_exists($user, 'primaryOrganization')) {
                    $organization = $user->primaryOrganization();
                }

                // Try first active organization
                if (!$organization && method_exists($user, 'activeOrganizations')) {
                    $organization = $user->activeOrganizations()->first();
                }
            }
        }

        // Priority 3: Fallback to CurrentOrganization service
        if (!$organization) {
            $organization = $this->currentOrganization->getOrganization();
        }

        // Priority 4: If still no organization, try to find the first active organization (for providers with single org)
        if (!$organization && Auth::check()) {
            $user = Auth::user();
            // For providers, they might have access to facilities which belong to organizations
            if ($user->hasRole('provider') && $user->facilities()->exists()) {
                $facility = $user->facilities()->with('organization')->first();
                if ($facility && $facility->organization) {
                    $organization = $facility->organization;
                }
            }
        }

        if (!$organization) {
            Log::error('No organization found for draft episode creation', [
                'user_id' => Auth::id(),
                'form_data_has_org_id' => !empty($formData['organization_id']),
                'form_data_org_id' => $formData['organization_id'] ?? null,
                'current_org_service_has_org' => $this->currentOrganization->hasOrganization(),
                'current_org_service_id' => $this->currentOrganization->getId(),
            ]);

            throw new \Exception("No current organization found. Please ensure you are associated with an organization to create requests.");
        }

        return [
            'id' => $organization->id,
            'name' => $organization->name,
            'tax_id' => $organization->tax_id ?? '',
            'type' => $organization->type ?? '',
            'address' => $organization->address ?? '',
            'city' => $organization->city ?? '',
            'state' => $organization->region ?? '',
            'zip_code' => $organization->postal_code ?? '',
            'phone' => $organization->phone ?? '',
            'email' => $organization->email ?? '',
            'status' => $organization->status ?? '',
        ];
    }

    /**
     * Create IVR submission using orchestrator's comprehensive data
     */
    public function createIvrSubmission(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'form_data' => 'required|array',
            'template_id' => 'nullable|string',
        ]);

        try {
            // Extract form data
            $formData = $validated['form_data'];
            $templateId = $validated['template_id'];

            // Create or get draft episode from form data
            $episode = null;
            if (!empty($formData['episode_id'])) {
                $episode = PatientManufacturerIVREpisode::find($formData['episode_id']);
            }

            // Create draft episode if not exists
            if (!$episode) {
                $episode = $this->orchestrator->createDraftEpisode([
                    'patient' => $this->extractPatientData($formData),
                    'provider' => $this->extractProviderData($formData),
                    'facility' => $this->extractFacilityData($formData),
                    'organization' => $this->extractOrganizationData(),
                    'clinical' => $this->extractClinicalData($formData),
                    'insurance' => $this->extractInsuranceData($formData),
                    'order_details' => $this->extractOrderData($formData),
                    'manufacturer_id' => $this->getManufacturerIdFromProducts($formData['selected_products'] ?? []),
                ]);
            }

            // Check if user has permission to access this episode
            if ((int)$episode->created_by !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to episode'
                ], 403);
            }

            // Get comprehensive data from orchestrator with FHIR integration
            $comprehensiveData = $this->orchestrator->prepareDocusealData($episode);

            // Determine manufacturer from products
            $manufacturerName = $this->getManufacturerNameFromProducts($formData['selected_products'] ?? []);

            // Use DocuSeal service to create submission
            $result = $this->docusealService->createOrUpdateSubmission(
                $episode->id,
                $manufacturerName,
                $comprehensiveData,
                $templateId
            );

            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'Failed to create IVR submission');
            }

            Log::info('IVR submission created successfully using orchestrator data', [
                'episode_id' => $episode->id,
                'submission_id' => $result['submission']['id'] ?? null,
                'user_id' => Auth::id(),
                'manufacturer' => $manufacturerName,
                'data_fields_count' => count($comprehensiveData),
                'fhir_integration' => !empty($episode->fhir_ids)
            ]);

            return response()->json([
                'success' => true,
                'episode_id' => $episode->id,
                'submission_id' => $result['submission']['id'] ?? null,
                'slug' => $result['submission']['slug'] ?? null,
                'embed_url' => $result['submission']['embed_url'] ?? null,
                'status' => $result['submission']['status'] ?? 'pending',
                'manufacturer' => $manufacturerName,
                'mapped_fields_count' => count($comprehensiveData),
                'fhir_data_used' => !empty($episode->fhir_ids),
                'completeness_percentage' => $this->calculateFieldCompleteness($comprehensiveData),
                'message' => 'IVR submission created successfully with comprehensive FHIR data'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create IVR submission using orchestrator data', [
                'form_data_keys' => array_keys($formData ?? []),
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create IVR submission: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a draft episode for IVR generation before final submission
     */
    public function createDraftEpisode(Request $request): JsonResponse
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'User authentication required to create draft episode',
                'requires_auth' => true
            ], 401);
        }

        $validated = $request->validate([
            'form_data' => 'required|array',
            'manufacturer_name' => 'required|string'
        ]);

        try {
            // Extract data from form
            $formData = $validated['form_data'];

            // Get manufacturer ID
            $manufacturerId = $this->getManufacturerIdFromProducts($formData['selected_products'] ?? []);
            if (!$manufacturerId) {
                throw new \Exception('Unable to determine manufacturer from selected products');
            }

            // Prepare data for draft episode creation
            $episodeData = [
                'patient' => $this->extractPatientData($formData),
                'provider' => $this->extractProviderData($formData),
                'facility' => $this->extractFacilityData($formData),
                'organization' => $this->extractOrganizationData($formData),
                'clinical' => $this->extractClinicalData($formData),
                'insurance' => $this->extractInsuranceData($formData),
                'order_details' => $this->extractOrderData($formData),
                'manufacturer_id' => $manufacturerId,
                'request_type' => $formData['request_type'] ?? 'new_request', // Add request_type
            ];

            // Create draft episode (no FHIR resources created yet)
            $episode = $this->orchestrator->createDraftEpisode($episodeData);

            Log::info('Draft episode created for IVR generation', [
                'episode_id' => $episode->id,
                'user_id' => Auth::id(),
                'manufacturer' => $validated['manufacturer_name']
            ]);

            return response()->json([
                'success' => true,
                'episode_id' => $episode->id,
                'status' => $episode->status,
                'message' => 'Draft episode created successfully for IVR generation'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create draft episode for IVR', [
                'user_id' => Auth::id(),
                'manufacturer' => $validated['manufacturer_name'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create draft episode: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save comprehensive data incrementally during quick request flow
     */
    public function saveComprehensiveData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'episode_id' => 'required|string',
            'step_data' => 'required|array',
            'step_number' => 'required|integer|min:0|max:7',
            'comprehensive_data' => 'required|array',
        ]);

        try {
            // Find or create episode
            $episode = PatientManufacturerIVREpisode::where('id', $validated['episode_id'])
                ->where('created_by', Auth::id())
                ->first();

            if (!$episode) {
                // Create new episode if it doesn't exist
                $episode = PatientManufacturerIVREpisode::create([
                    'id' => $validated['episode_id'],
                    'created_by' => Auth::id(),
                    'status' => PatientManufacturerIVREpisode::STATUS_DRAFT,
                    'ivr_status' => 'pending',
                    'metadata' => [
                        'comprehensive_data' => $validated['comprehensive_data'],
                        'last_step_completed' => $validated['step_number'],
                        'last_updated' => now()->toISOString(),
                    ]
                ]);
            } else {
                // Update existing episode with new comprehensive data
                $existingMetadata = $episode->metadata ?? [];
                $existingComprehensiveData = $existingMetadata['comprehensive_data'] ?? [];
                
                // Merge existing comprehensive data with new data, preserving existing data
                $mergedComprehensiveData = array_merge($existingComprehensiveData, $validated['comprehensive_data']);
                
                $existingMetadata['comprehensive_data'] = $mergedComprehensiveData;
                $existingMetadata['last_step_completed'] = $validated['step_number'];
                $existingMetadata['last_updated'] = now()->toISOString();

                $episode->update([
                    'metadata' => $existingMetadata
                ]);

                Log::info('Updated existing episode with merged comprehensive data', [
                    'episode_id' => $episode->id,
                    'existing_data_keys' => array_keys($existingComprehensiveData),
                    'new_data_keys' => array_keys($validated['comprehensive_data']),
                    'merged_data_keys' => array_keys($mergedComprehensiveData),
                ]);
            }

            // Log the comprehensive data saving
            Log::info('QuickRequest saveComprehensiveData - Data saved incrementally', [
                'episode_id' => $episode->id,
                'step_number' => $validated['step_number'],
                'comprehensive_data_keys' => array_keys($validated['comprehensive_data']),
                'comprehensive_data_size' => strlen(json_encode($validated['comprehensive_data'])),
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comprehensive data saved successfully',
                'episode_id' => $episode->id,
                'step_completed' => $validated['step_number'],
                'data_saved_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('QuickRequest saveComprehensiveData - Error saving data', [
                'error' => $e->getMessage(),
                'episode_id' => $validated['episode_id'] ?? null,
                'step_number' => $validated['step_number'] ?? null,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save comprehensive data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get manufacturer name from products
     */
    private function getManufacturerNameFromProducts(array $products): string
    {
        if (empty($products)) {
            return 'Unknown';
        }

        $firstProduct = $products[0];
        return $firstProduct['product']['manufacturer'] ?? 'Unknown';
    }

    /**
     * Calculate field completeness percentage
     */
    private function calculateFieldCompleteness(array $data): int
    {
        $totalFields = count($data);
        $completedFields = 0;

        foreach ($data as $value) {
            if (!empty($value) && $value !== '' && $value !== null) {
                $completedFields++;
            }
        }

        return $totalFields > 0 ? round(($completedFields / $totalFields) * 100) : 0;
    }

    /**
     * Get display text for place of service code
     */
    private function getPlaceOfServiceDisplay(?string $placeOfService): string
    {
        if (empty($placeOfService)) {
            return 'Not specified';
        }

        $placeOfServiceOptions = [
            '11' => 'Office',
            '12' => 'Home',
            '32' => 'Nursing Home',
            '31' => 'Skilled Nursing',
        ];

        return $placeOfServiceOptions[$placeOfService] ?? "Code: $placeOfService";
    }

    /**
     * Handle IVR scenarios based on PRD requirements
     */
    private function handleIvrScenarios(ProductRequest $productRequest, array $formData): void
    {
        // Determine if IVR is required based on product/manufacturer configuration
        $isIvrRequired = $this->determineIvrRequirement($formData);
        $bypassReason = $formData['ivr_bypass_reason'] ?? null;

        if (!$isIvrRequired) {
            // Scenario 2: IVR not required
            $productRequest->setIvrNotRequired($bypassReason);

            // Set order status to allow immediate order form completion
            $productRequest->update([
                'order_status' => 'pending',
                'ivr_status' => 'n/a'
            ]);

            Log::info('IVR not required for order', [
                'order_id' => $productRequest->id,
                'bypass_reason' => $bypassReason
            ]);
        } else {
            // Scenario 1: IVR required
            $productRequest->update([
                'order_status' => 'pending_ivr',
                'ivr_status' => 'pending'
            ]);

            Log::info('IVR required for order', [
                'order_id' => $productRequest->id
            ]);
        }
    }

    /**
     * Determine if IVR is required based on product/manufacturer configuration
     */
    private function determineIvrRequirement(array $formData): bool
    {
        // Check if IVR was explicitly bypassed
        if (isset($formData['ivr_bypass_reason']) && !empty($formData['ivr_bypass_reason'])) {
            return false;
        }

        // Check if IVR was explicitly marked as not required
        if (isset($formData['ivr_required']) && $formData['ivr_required'] === false) {
            return false;
        }

        // Check if selected products require IVR based on manufacturer configuration
        if (isset($formData['selected_products']) && is_array($formData['selected_products'])) {
            foreach ($formData['selected_products'] as $productData) {
                $productId = $productData['product_id'] ?? null;
                if ($productId) {
                    $product = \App\Models\Order\Product::with('manufacturer')->find($productId);
                    if ($product && $product->manufacturer && is_object($product->manufacturer)) {
                        // Check manufacturer configuration for IVR requirement
                        $manufacturerSlug = $product->manufacturer->slug ?? null;
                        if ($manufacturerSlug) {
                            $manufacturerConfig = config("manufacturers.{$manufacturerSlug}");
                            if ($manufacturerConfig && isset($manufacturerConfig['ivr_required'])) {
                                return $manufacturerConfig['ivr_required'];
                            }
                        }
                    }
                }
            }
        }

        // Check if DocuSeal submission indicates IVR was completed
        if (isset($formData['docuseal_submission_id']) && $formData['docuseal_submission_id'] === 'NO_IVR_REQUIRED') {
            return false;
        }

        // Default: IVR is required for all orders
        return true;
    }

    /**
     * Send admin notifications for new order submissions
     */
    private function sendAdminNotifications(ProductRequest $productRequest, ?string $adminNote): void
    {
        try {
            // Get all admin users
            $adminUsers = User::whereHas('roles', function($query) {
                $query->where('slug', 'msc-admin');
            })->get();

            $submitter = Auth::user();

            foreach ($adminUsers as $admin) {
                Mail::to($admin->email)->queue(new OrderSubmissionNotification(
                    $productRequest,
                    $submitter,
                    $adminNote
                ));
            }

            Log::info('Admin notifications sent for order submission', [
                'order_id' => $productRequest->id,
                'admin_count' => $adminUsers->count(),
                'has_admin_note' => !empty($adminNote)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send admin notifications', [
                'order_id' => $productRequest->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}

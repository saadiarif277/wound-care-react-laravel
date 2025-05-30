<?php

namespace App\Http\Controllers\Api\V1\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\SkinSubstituteChecklistRequest;
use App\Services\HealthData\DTO\SkinSubstituteChecklistInput;
use App\Services\HealthData\Services\Fhir\SkinSubstituteChecklistService;
use App\Services\HealthData\Services\ChecklistValidationService as HealthDataChecklistValidationService; // Alias to avoid name collision if another service is named the same
use App\Models\Order\Order; // Adjusted as per user's manual change, assuming this is the correct path
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

class ChecklistController extends Controller
{
    protected SkinSubstituteChecklistService $fhirChecklistService;
    protected HealthDataChecklistValidationService $checklistValidationService;

    public function __construct(
        SkinSubstituteChecklistService $fhirChecklistService,
        HealthDataChecklistValidationService $checklistValidationService
    ) {
        $this->fhirChecklistService = $fhirChecklistService;
        $this->checklistValidationService = $checklistValidationService;
    }

    /**
     * Store a newly created skin substitute checklist for an order.
     *
     * @param SkinSubstituteChecklistRequest $request
     * @param string $orderId
     * @return JsonResponse
     */
    public function store(SkinSubstituteChecklistRequest $request, string $orderId): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $checklistDto = SkinSubstituteChecklistInput::fromArray($validatedData);

            $order = \App\Models\Order\Order::find($orderId); // Using fully qualified class name as per user's change
            if (!$order) {
                return response()->json(['message' => 'Order not found.'], 404);
            }

            // Perform MAC validation using the HealthData ChecklistValidationService
            $validationResult = $this->checklistValidationService->validateSkinSubstituteChecklist($checklistDto);

            if (!$validationResult->isValid) {
                Log::warning('Checklist validation failed for order: ' . $orderId, $validationResult->toArray());
                return response()->json([
                    'message' => 'Checklist validation failed.',
                    'errors' => $validationResult->errors,
                    'warnings' => $validationResult->warnings,
                    'missingFields' => $validationResult->missingFields,
                    'macComplianceScore' => $validationResult->macComplianceScore,
                ], 422);
            }
            Log::info('Checklist MAC validation passed for order: ' . $orderId, $validationResult->toArray());


            // --- Retrieve necessary FHIR IDs ---
            $patientFhirId = $order->patient_fhir_id;
            if (!$patientFhirId) {
                Log::error('Patient FHIR ID is missing for order: ' . $orderId);
                return response()->json(['message' => 'Patient FHIR ID is missing for the order. Checklist cannot be processed.'], 422);
            }

            $organizationFhirId = null;
            if ($order->facility && $order->facility->organization && $order->facility->organization->fhir_id) {
                $organizationFhirId = $order->facility->organization->fhir_id;
            }
            if (!$organizationFhirId) {
                Log::error('Organization FHIR ID is missing for order: ' . $orderId . '. Could not retrieve from facility\'s organization or fhir_id missing on Organization model.');
                return response()->json(['message' => 'Organization FHIR ID for the facility is missing. Checklist cannot be processed.'], 422);
            }

            $practitionerFhirId = null;
            if ($order->provider && $order->provider->practitioner_fhir_id) {
                $practitionerFhirId = $order->provider->practitioner_fhir_id;
            }
            if (!$practitionerFhirId) {
                 $authenticatedUser = $request->user();
                 if ($authenticatedUser && $authenticatedUser instanceof User && $authenticatedUser->practitioner_fhir_id) {
                    $practitionerFhirId = $authenticatedUser->practitioner_fhir_id;
                    Log::info('Practitioner FHIR ID for checklist on order ' . $orderId . ' taken from authenticated user.');
                 } else {
                    Log::error('Practitioner FHIR ID is missing for order: ' . $orderId . '. Could not retrieve from order\'s provider or authenticated user.');
                    return response()->json(['message' => 'Practitioner FHIR ID for the provider is missing. Checklist cannot be processed.'], 422);
                 }
            }
            // --- End FHIR ID Retrieval ---

            $fhirBundle = $this->fhirChecklistService->createPreApplicationAssessment(
                $checklistDto,
                $patientFhirId,
                $practitionerFhirId, 
                $organizationFhirId  
            );

            $fhirBundleId = $fhirBundle->getId() ? $fhirBundle->getId()->getValue() : null;
            Log::info('FHIR Bundle created for checklist, ID: ' . $fhirBundleId, ['order_id' => $orderId]);

            if ($fhirBundleId) {
                $order->azure_order_checklist_fhir_id = $fhirBundleId;
                $order->checklist_status = 'submitted_to_fhir'; 
                // Potentially update MAC validation status and score on the order
                $order->mac_validation_status = $validationResult->isValid ? 'passed' : 'failed';
                if (isset($validationResult->macComplianceScore)) {
                    // Assuming a field like mac_compliance_score exists on the Order model
                    // $order->mac_compliance_score = $validationResult->macComplianceScore;
                }
                $order->save();
            } else {
                 Log::error('FHIR Bundle ID not found after submission for order: ' . $orderId);
            }

            return response()->json([
                'message' => 'Checklist submitted successfully.',
                'order_id' => $orderId,
                'fhir_bundle_id' => $fhirBundleId,
                'checklist_status' => $order->checklist_status ?? null,
                'mac_validation' => $validationResult->toArray() // Include validation results in success response
            ], 201);

        } catch (Exception $e) {
            Log::error('Error processing checklist for order ' . $orderId . ': ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => 'An error occurred while processing the checklist: ' . $e->getMessage()], 500);
        }
    }
} 
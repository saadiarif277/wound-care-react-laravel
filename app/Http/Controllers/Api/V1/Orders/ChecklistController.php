<?php

namespace App\Http\Controllers\Api\V1\Orders; // Corrected namespace to match typical structure

use App\Http\Controllers\Controller;
use App\Http\Requests\SkinSubstituteChecklistRequest; // Will be created
use App\Services\HealthData\DTO\SkinSubstituteChecklistInput;
use App\Services\HealthData\Services\Fhir\SkinSubstituteChecklistService;
use App\Services\HealthData\Services\ChecklistValidationService; // PHP version of validation
use App\Models\Order\Order; // Correct model path
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB; // For transactions
use Illuminate\Support\Facades\Log; // For logging
use Illuminate\Http\Request; // For accessing user

class ChecklistController extends Controller
{
    public function __construct(
        private SkinSubstituteChecklistService $checklistService,
        private ChecklistValidationService $validationService // PHP service
    ) {}

    /**
     * Submit skin substitute checklist for an order.
     */
    public function store(SkinSubstituteChecklistRequest $request, string $orderId): JsonResponse
    {
        try {
            $order = Order::findOrFail($orderId);
            
            // Authorization: Ensure the authenticated user can update this order
            $user = $request->user();
            if (!$user || ($user && !$user->can('update', $order))) { // Check if user exists before calling can()
                 return response()->json(['error' => 'Forbidden. You do not have permission to update this order.'], 403);
            }
            
            // Data is already validated by SkinSubstituteChecklistRequest
            // and available via $request->validated()
            // Convert to DTO
            $checklistDataDto = SkinSubstituteChecklistInput::fromArray($request->validated());
            
            // Perform business logic / MAC validation using the PHP service
            $validationResult = $this->validationService->validateSkinSubstituteChecklist($checklistDataDto);
            
            if (!$validationResult->isValid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Checklist validation failed.',
                    'errors' => $validationResult->errors,
                    'warnings' => $validationResult->warnings,
                    'missingFields' => $validationResult->missingFields,
                    'macComplianceScore' => $validationResult->macComplianceScore,
                ], 400); // Bad Request
            }

            // At this point, patientFhirId should be available, e.g., from the Order model or request context
            // For example, if order has a direct link to patient\'s FHIR ID:
            $patientFhirId = $order->patient_fhir_id; 
            if (!$patientFhirId) {
                // Or attempt to get it from the user/provider context if appropriate
                // This logic depends on how patient FHIR IDs are managed and associated with orders/users.
                // For MVP, let's assume it\'s on the order or passed in request if not directly on order.
                Log::error("Patient FHIR ID not found for order {$orderId}");
                return response()->json(['error' => 'Patient FHIR ID missing for the order.'], 400);
            }

            // Assuming providerId and facilityId come from the authenticated user or order context
            // These are MSC internal system IDs, not necessarily FHIR IDs for Practitioner/Organization yet.
            $providerId = $request->user()->provider_id ?? $order->provider_id ?? 'unknown-provider'; 
            $facilityId = $request->user()->facility_id ?? $order->facility_id ?? 'unknown-facility';

            DB::beginTransaction();
            
            $azureOrderChecklistFhirId = $this->checklistService->createPreApplicationAssessment(
                $checklistDataDto,
                $patientFhirId, 
                (string)$providerId, // Ensure string type for service
                (string)$facilityId  // Ensure string type for service
            );
            
            $order->update([
                'azure_order_checklist_fhir_id' => $azureOrderChecklistFhirId,
                'order_status' => 'PendingMACValidation', // Example status update
                'updated_at' => now()
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Checklist submitted successfully and FHIR resources created.',
                'azure_order_checklist_fhir_id' => $azureOrderChecklistFhirId,
                'validation_results' => $validationResult->toArray() // Send back full validation result
            ], 201); // 201 Created (or 200 OK if just an update)
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            Log::error("Order not found for checklist submission: {$orderId}", ['exception' => $e]);
            return response()->json(['error' => 'Order not found.'], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            DB::rollBack();
            Log::warning("Unauthorized checklist submission attempt for order {$orderId}", ['user_id' => auth()->id(), 'exception' => $e]);
            return response()->json(['error' => $e->getMessage() ?: 'Forbidden.'], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack(); // Should not happen if SkinSubstituteChecklistRequest handles it, but as a safeguard.
            Log::warning("ValidationException during checklist submission for order {$orderId}", ['errors' => $e->errors(), 'exception' => $e]);
            return response()->json(['error' => 'Validation failed.', 'details' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error submitting checklist for order {$orderId}", ['exception' => $e]);
            return response()->json([
                'error' => 'Failed to submit checklist.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
} 
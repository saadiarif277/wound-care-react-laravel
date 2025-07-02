<?php

namespace App\Services;

use App\Models\QuickRequestSubmission;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class QuickRequestSubmissionService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Save Quick Request form data to the submissions table
     */
    public function saveSubmission(array $formData, ?string $orderNumber = null): QuickRequestSubmission
    {
        try {
            // Extract product information
            $selectedProducts = $formData['selected_products'] ?? [];
            $firstProduct = $selectedProducts[0] ?? [];

            // Calculate total bill
            $totalBill = 0;
            $productNames = [];
            $productSizes = [];
            $totalQuantity = 0;

            foreach ($selectedProducts as $product) {
                $price = floatval($product['product']['price'] ?? 0);
                $quantity = intval($product['quantity'] ?? 1);
                $totalBill += $price * $quantity;
                $totalQuantity += $quantity;
                $productNames[] = $product['product']['name'] ?? 'Unknown Product';
                $productSizes[] = $product['size'] ?? 'Standard';
            }

            $submissionData = [
                // Patient Info
                'patient_first_name' => $formData['patient_first_name'] ?? null,
                'patient_last_name' => $formData['patient_last_name'] ?? null,
                'patient_dob' => $formData['patient_dob'] ?? null,
                'patient_gender' => $formData['patient_gender'] ?? null,
                'patient_phone' => $formData['patient_phone'] ?? null,
                'patient_email' => $formData['patient_email'] ?? null,
                'patient_address' => $formData['patient_address_line1'] ?? null,
                'patient_city' => $formData['patient_city'] ?? null,
                'patient_state' => $formData['patient_state'] ?? null,
                'patient_zip' => $formData['patient_zip'] ?? null,

                // Insurance Info
                'primary_insurance_name' => $formData['primary_insurance_name'] ?? null,
                'primary_plan_type' => $formData['primary_plan_type'] ?? null,
                'primary_member_id' => $formData['primary_member_id'] ?? null,
                'has_secondary_insurance' => !empty($formData['secondary_insurance_name']),
                'secondary_insurance_name' => $formData['secondary_insurance_name'] ?? null,
                'secondary_plan_type' => $formData['secondary_plan_type'] ?? null,
                'secondary_member_id' => $formData['secondary_member_id'] ?? null,
                'insurance_card_uploaded' => !empty($formData['insurance_card_front']),

                // Provider/Facility Info
                'provider_name' => $formData['provider_name'] ?? null,
                'provider_npi' => $formData['provider_npi'] ?? null,
                'facility_name' => $formData['facility_name'] ?? null,
                'facility_address' => $formData['facility_address'] ?? $formData['service_address'] ?? null,
                'organization_name' => $formData['organization_name'] ?? null,

                // Clinical Info
                'wound_type' => $formData['wound_type'] ?? null,
                'wound_location' => $formData['wound_location'] ?? null,
                'wound_size_length' => $formData['wound_size_length'] ?? null,
                'wound_size_width' => $formData['wound_size_width'] ?? null,
                'wound_size_depth' => $formData['wound_size_depth'] ?? null,
                'diagnosis_codes' => $formData['diagnosis_codes'] ?? [],
                'icd10_codes' => $formData['icd10_codes'] ?? [],
                'procedure_info' => $formData['procedure_info'] ?? null,
                'prior_applications' => intval($formData['prior_applications'] ?? 0),
                'anticipated_applications' => intval($formData['anticipated_applications'] ?? 0),
                'clinical_facility_info' => $formData['facility_name'] ?? null,

                // Product Info
                'product_name' => implode(', ', $productNames),
                'product_sizes' => $productSizes,
                'product_quantity' => $totalQuantity,
                'asp_price' => floatval($firstProduct['product']['price'] ?? 0),
                'discounted_price' => floatval($firstProduct['product']['discounted_price'] ?? $firstProduct['product']['price'] ?? 0),
                'coverage_warnings' => $formData['coverage_warnings'] ?? [],

                // IVR & Order Form Status (will be updated later)
                'ivr_status' => null,
                'ivr_submission_date' => null,
                'ivr_document_link' => null,
                'order_form_status' => null,
                'order_form_submission_date' => null,
                'order_form_document_link' => null,

                // Order Meta
                'order_number' => $orderNumber,
                'order_status' => 'draft',
                'created_by' => auth()->user()->name ?? 'System',
                'total_bill' => $totalBill,
            ];

            $submission = QuickRequestSubmission::create($submissionData);

            Log::info('Quick Request submission saved', [
                'submission_id' => $submission->id,
                'order_number' => $orderNumber,
                'patient_name' => $submission->patient_full_name,
            ]);

            return $submission;

        } catch (\Exception $e) {
            Log::error('Failed to save Quick Request submission', [
                'error' => $e->getMessage(),
                'form_data_keys' => array_keys($formData),
            ]);
            throw $e;
        }
    }

    /**
     * Update IVR status for a submission
     */
    public function updateIvrStatus(int $submissionId, string $status, ?string $documentLink = null): bool
    {
        try {
            $submission = QuickRequestSubmission::findOrFail($submissionId);

            $updateData = [
                'ivr_status' => $status,
                'ivr_submission_date' => $status === 'completed' ? now() : null,
                'ivr_document_link' => $documentLink,
            ];

            $submission->update($updateData);

            Log::info('IVR status updated for submission', [
                'submission_id' => $submissionId,
                'status' => $status,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to update IVR status', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Update order form status for a submission
     */
    public function updateOrderFormStatus(int $submissionId, string $status, ?string $documentLink = null): bool
    {
        try {
            $submission = QuickRequestSubmission::findOrFail($submissionId);

            $updateData = [
                'order_form_status' => $status,
                'order_form_submission_date' => $status === 'completed' ? now() : null,
                'order_form_document_link' => $documentLink,
            ];

            $submission->update($updateData);

            Log::info('Order form status updated for submission', [
                'submission_id' => $submissionId,
                'status' => $status,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to update order form status', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get submission by ID for review
     */
    public function getSubmissionForReview(int $submissionId): ?QuickRequestSubmission
    {
        return QuickRequestSubmission::find($submissionId);
    }

    /**
     * Get all submissions for admin review
     */
    public function getAllSubmissions(int $perPage = 25)
    {
        return QuickRequestSubmission::orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get submissions by user
     */
    public function getSubmissionsByUser(int $userId, int $perPage = 25)
    {
        return QuickRequestSubmission::where('created_by', auth()->user()->name)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Search submissions
     */
    public function searchSubmissions(string $search, int $perPage = 25)
    {
        return QuickRequestSubmission::where(function ($query) use ($search) {
            $query->where('patient_first_name', 'like', "%{$search}%")
                  ->orWhere('patient_last_name', 'like', "%{$search}%")
                  ->orWhere('order_number', 'like', "%{$search}%")
                  ->orWhere('primary_insurance_name', 'like', "%{$search}%");
        })
        ->orderBy('created_at', 'desc')
        ->paginate($perPage);
    }

    /**
     * Get submission statistics
     */
    public function getSubmissionStats(): array
    {
        $total = QuickRequestSubmission::count();
        $complete = QuickRequestSubmission::complete()->count();
        $withIvr = QuickRequestSubmission::withIvrCompleted()->count();
        $withOrderForm = QuickRequestSubmission::withOrderFormCompleted()->count();

        return [
            'total' => $total,
            'complete' => $complete,
            'with_ivr' => $withIvr,
            'with_order_form' => $withOrderForm,
            'completion_rate' => $total > 0 ? round(($complete / $total) * 100, 2) : 0,
        ];
    }
}

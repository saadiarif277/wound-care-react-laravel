<?php

namespace App\Http\Controllers\Traits;

use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\Product;
use App\Models\Order\Manufacturer;
use App\Services\DocuSealService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait QuickRequestDocuSealIntegration
{
    /**
     * After patient info is collected, create episode and prepare DocuSeal
     */
    public function prepareDocuSealIVR(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|string',
            'patient_fhir_id' => 'required|string',
            'patient_display_id' => 'required|string',
            'selected_product_id' => 'required|exists:products,id',
            'facility_id' => 'required|exists:facilities,id',
            // All the collected form data
            'form_data' => 'required|array',
        ]);

        DB::beginTransaction();
        
        try {
            // Get product and manufacturer
            $product = Product::with('manufacturer')->find($validated['selected_product_id']);
            $manufacturerId = $product->manufacturer_id;
            
            // Create or find episode
            $episode = PatientManufacturerIVREpisode::firstOrCreate([
                'patient_fhir_id' => $validated['patient_fhir_id'],
                'manufacturer_id' => $manufacturerId,
                'status' => '!=' . PatientManufacturerIVREpisode::STATUS_COMPLETED,
            ], [
                'patient_display_id' => $validated['patient_display_id'],
                'status' => PatientManufacturerIVREpisode::STATUS_DRAFT,
                'metadata' => [
                    'facility_id' => $validated['facility_id'],
                    'provider_id' => auth()->id(),
                    'created_from' => 'quick_request',
                    'product_id' => $product->id,
                ]
            ]);
            
            // Prepare data for DocuSeal
            $docusealData = $this->prepareDocuSealData($validated['form_data'], $episode);
            
            // Create DocuSeal submission
            $docusealService = new DocuSealService();
            $result = $docusealService->createIVRSubmission($docusealData, $episode);
            
            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'Failed to create DocuSeal submission');
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'episode_id' => $episode->id,
                'docuseal_url' => $result['embed_url'],
                'docuseal_submission_id' => $result['submission_id'],
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('DocuSeal preparation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to prepare IVR form: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Prepare data for DocuSeal submission using universal mapping
     */
    protected function prepareDocuSealData(array $formData, PatientManufacturerIVREpisode $episode): array
    {
        // Get organization, facility, and provider data
        $facility = \App\Models\Fhir\Facility::find($episode->metadata['facility_id']);
        $provider = \App\Models\User::find($episode->metadata['provider_id']);
        $organization = $facility->organization ?? null;
        
        // Structure data according to universal IVR schema
        return [
            'requestInfo' => [
                'requestType' => $formData['request_type'] ?? 'new_request',
                'requestDate' => now()->format('Y-m-d'),
                'salesRepName' => $formData['sales_rep_name'] ?? auth()->user()->name,
                'salesRepEmail' => $formData['sales_rep_email'] ?? auth()->user()->email,
            ],
            
            'patientInfo' => [
                'patientName' => $formData['patient_first_name'] . ' ' . $formData['patient_last_name'],
                'patientDOB' => $formData['patient_dob'],
                'patientGender' => $formData['patient_gender'] ?? null,
                'patientAddressLine1' => $formData['patient_address_line1'] ?? null,
                'patientAddressLine2' => $formData['patient_address_line2'] ?? null,
                'patientCity' => $formData['patient_city'] ?? null,
                'patientState' => $formData['patient_state'] ?? null,
                'patientZipCode' => $formData['patient_zip'] ?? null,
                'patientPhone' => $formData['patient_phone'] ?? null,
                'patientFaxEmail' => $formData['patient_email'] ?? null,
                'patientCaregiverInfo' => $formData['caregiver_name'] ?? null,
                'patientContactPermission' => $formData['patient_contact_permission'] ?? false,
            ],            
            'insuranceInfo' => [
                'primaryInsurance' => [
                    'primaryInsuranceName' => $formData['primary_insurance_name'],
                    'primaryPolicyNumber' => $formData['primary_member_id'],
                    'primarySubscriberName' => $formData['primary_subscriber_name'] ?? null,
                    'primarySubscriberDOB' => $formData['primary_subscriber_dob'] ?? null,
                    'primaryPayerPhone' => $formData['primary_payer_phone'] ?? null,
                    'primaryPlanType' => $formData['primary_plan_type'] ?? null,
                    'primaryNetworkStatus' => $formData['primary_network_status'] ?? 'unknown',
                ],
                'secondaryInsurance' => [
                    'secondaryInsuranceName' => $formData['secondary_insurance_name'] ?? null,
                    'secondaryPolicyNumber' => $formData['secondary_member_id'] ?? null,
                    'secondarySubscriberName' => $formData['secondary_subscriber_name'] ?? null,
                    'secondarySubscriberDOB' => $formData['secondary_subscriber_dob'] ?? null,
                    'secondaryPayerPhone' => $formData['secondary_payer_phone'] ?? null,
                    'secondaryPlanType' => $formData['secondary_plan_type'] ?? null,
                    'secondaryNetworkStatus' => $formData['secondary_network_status'] ?? null,
                ],
                'authorizationPermission' => $formData['prior_auth_permission'] ?? false,
                'requestPriorAuthAssistance' => $formData['request_prior_auth_assistance'] ?? false,
                'cardsAttached' => $formData['insurance_cards_attached'] ?? false,
            ],
            
            'physicianInfo' => [
                'physicianName' => $provider->full_name ?? $provider->name,
                'physicianSpecialty' => $provider->providerProfile->specialty ?? null,
                'physicianNPI' => $provider->providerProfile->npi ?? null,
                'physicianTaxID' => $provider->providerProfile->tax_id ?? null,
                'physicianPTAN' => $provider->providerProfile->ptan ?? null,
                'physicianMedicaidNumber' => $provider->providerProfile->medicaid_number ?? null,
                'physicianPhone' => $provider->providerProfile->phone ?? null,
                'physicianFax' => $provider->providerProfile->fax ?? null,
            ],            
            'facilityInfo' => [
                'facilityName' => $facility->name ?? null,
                'facilityAddressLine1' => $facility->address_line1 ?? null,
                'facilityAddressLine2' => $facility->address_line2 ?? null,
                'facilityCity' => $facility->city ?? null,
                'facilityState' => $facility->state ?? null,
                'facilityZipCode' => $facility->zip ?? null,
                'facilityNPI' => $facility->npi ?? null,
                'facilityTaxID' => $facility->tax_id ?? $organization->tax_id ?? null,
                'facilityPTAN' => $facility->ptan ?? null,
                'facilityContactName' => $facility->contact_name ?? null,
                'facilityContactPhone' => $facility->contact_phone ?? null,
                'facilityContactFax' => $facility->contact_fax ?? null,
                'facilityContactEmail' => $facility->contact_email ?? null,
                'managementCompany' => $organization->name ?? null,
            ],
            
            'placeOfService' => [
                'placeOfService' => $formData['place_of_service'] ?? '11',
                'snfStatus' => $formData['snf_status'] ?? false,
                'snfDays' => $formData['snf_days'] ?? null,
                'snfOver100Days' => $formData['snf_over_100_days'] ?? false,
                'hospiceStatus' => $formData['hospice_status'] ?? false,
                'partAStatus' => $formData['part_a_status'] ?? false,
            ],
            
            'woundInfo' => [
                'woundType' => $formData['wound_types'] ?? [],
                'woundOtherSpecify' => $formData['wound_other_specify'] ?? null,
                'woundLocation' => $formData['wound_location'] ?? null,
                'woundLocationDetails' => $formData['wound_location_details'] ?? null,
                'woundSizeLength' => $formData['wound_size_length'] ?? null,
                'woundSizeWidth' => $formData['wound_size_width'] ?? null,
                'woundSizeDepth' => $formData['wound_size_depth'] ?? null,
                'woundSizeTotal' => ($formData['wound_size_length'] ?? 0) * ($formData['wound_size_width'] ?? 0),
                'woundDuration' => $formData['wound_duration'] ?? null,
                'previousTreatments' => $formData['previous_treatments'] ?? null,
            ],            
            'procedureInfo' => [
                'globalPeriodStatus' => $formData['global_period_status'] ?? false,
                'globalPeriodCptCodes' => $formData['global_period_cpt'] ?? null,
                'globalPeriodSurgeryDate' => $formData['global_period_surgery_date'] ?? null,
                'anticipatedTreatmentDate' => $formData['expected_service_date'] ?? null,
                'anticipatedApplications' => $formData['anticipated_applications'] ?? null,
                'applicationCptCodes' => $formData['application_cpt_codes'] ?? [],
                'diagnosisCodes' => $formData['diagnosis_codes'] ?? null,
                'primaryDiagnosisCode' => $formData['yellow_diagnosis_code'] ?? null,
                'secondaryDiagnosisCodes' => $formData['orange_diagnosis_code'] ?? null,
                'comorbidities' => $formData['comorbidities'] ?? null,
            ],
            
            'productInfo' => [
                'selectedProducts' => array_map(function($product) {
                    return $product['product_code'] ?? $product['product_name'] ?? 'Unknown';
                }, $formData['selected_products'] ?? []),
                'productSizes' => array_map(function($product) {
                    return $product['size'] ?? null;
                }, $formData['selected_products'] ?? []),
                'graftSizeRequested' => $formData['graft_size_requested'] ?? null,
            ],
            
            'product_name' => $formData['selected_products'][0]['product_name'] ?? 'Unknown Product',
            
            // Add manufacturer-specific fields if any
            'manufacturer_fields' => $formData['manufacturer_fields'] ?? [],
        ];
    }
    
    /**
     * Update episode after DocuSeal webhook
     */
    public function handleDocuSealWebhook(Request $request)
    {
        Log::info('DocuSeal webhook received', $request->all());
        
        $eventType = $request->input('event_type');
        $submissionId = $request->input('data.id');
        
        if ($eventType === 'submission.completed' && $submissionId) {
            $episode = PatientManufacturerIVREpisode::where('docuseal_submission_id', $submissionId)->first();
            
            if ($episode) {
                $episode->update([
                    'status' => PatientManufacturerIVREpisode::STATUS_IVR_SENT,
                    'ivr_status' => PatientManufacturerIVREpisode::IVR_STATUS_PROVIDER_COMPLETED,
                    'verification_date' => now(),
                    'expiration_date' => now()->addDays(180), // 6 months validity
                    'metadata' => array_merge($episode->metadata ?? [], [
                        'docuseal_completed_at' => now()->toIso8601String(),
                        'docuseal_data' => $request->input('data.submitters.0.values', []),
                        'signed_document_url' => $request->input('data.submitters.0.documents.0.url'),
                    ])
                ]);
                
                Log::info('Episode updated from DocuSeal webhook', ['episode_id' => $episode->id]);
            }
        }
        
        return response()->json(['status' => 'ok']);
    }
}
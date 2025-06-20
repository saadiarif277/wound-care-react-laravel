<?php

namespace App\Http\Controllers\Traits;

use App\Models\PatientManufacturerIVREpisode;
use App\Models\Order\Product;
use App\Models\Order\Manufacturer;
use App\Services\DocuSealService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
                'status' => 'draft',
                'metadata' => [
                    'facility_id' => $validated['facility_id'],
                    'provider_id' => Auth::id(),
                    'created_from' => 'quick_request',
                    'product_id' => $product->id,
                    'selected_products' => $validated['form_data']['selected_products'] ?? [],
                    'form_data' => $validated['form_data'], // Store complete form data for future use
                ]
            ]);
            
            // Prepare data for DocuSeal
            $docusealData = $this->prepareDocuSealData($validated['form_data'], $episode, $product);
            
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
    protected function prepareDocuSealData(array $formData, PatientManufacturerIVREpisode $episode, Product $product): array
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
                'salesRepName' => $formData['sales_rep_name'] ?? \Illuminate\Support\Facades\Auth::user()->name,
                'salesRepEmail' => $formData['sales_rep_email'] ?? \Illuminate\Support\Facades\Auth::user()->email,
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
            'productInfo' => [
                'productName' => $product->name,
                'productNDC' => $product->ndc,
                'productUPC' => $product->upc,
                'productLotNumber' => $formData['product_lot_number'] ?? null,
                'productExpirationDate' => $formData['product_expiration_date'] ?? null,
                'productQuantity' => $formData['product_quantity'] ?? 1,
                'productFrequency' => $formData['product_frequency'] ?? null,
                'productRoute' => $formData['product_route'] ?? null,
                'productSite' => $formData['product_site'] ?? null,
                'productInstructions' => $formData['product_instructions'] ?? null,
            ],
            'episodeInfo' => [
                'episodeId' => $episode->id,
                'episodeStatus' => $episode->status,
                'episodeCreatedDate' => $episode->created_at->format('Y-m-d'),
                'episodeModifiedDate' => $episode->updated_at->format('Y-m-d'),
                'episodeCompletionDate' => $episode->completed_at ? $episode->completed_at->format('Y-m-d') : null,
            ],
            'facilityInfo' => [
                'facilityName' => $facility->name,
                'facilityPhone' => $facility->phone,
                'facilityFax' => $facility->fax,
                'facilityAddressLine1' => $facility->address_line1,
                'facilityAddressLine2' => $facility->address_line2,
                'facilityCity' => $facility->city,
                'facilityState' => $facility->state,
                'facilityZipCode' => $facility->zip,
                'facilityCountry' => $facility->country,
            ],
            'providerInfo' => [
                'providerName' => $provider->name,
                'providerNPI' => $provider->npi,
                'providerPhone' => $provider->phone,
                'providerEmail' => $provider->email,
                'providerSpecialty' => $provider->specialty,
                'providerAddressLine1' => $provider->address_line1,
                'providerAddressLine2' => $provider->address_line2,
                'providerCity' => $provider->city,
                'providerState' => $provider->state,
                'providerZipCode' => $provider->zip,
                'providerCountry' => $provider->country,
            ],
            'organizationInfo' => $organization ? [
                'organizationName' => $organization->name,
                'organizationNPI' => $organization->npi,
                'organizationPhone' => $organization->phone,
                'organizationEmail' => $organization->email,
                'organizationAddressLine1' => $organization->address_line1,
                'organizationAddressLine2' => $organization->address_line2,
                'organizationCity' => $organization->city,
                'organizationState' => $organization->state,
                'organizationZipCode' => $organization->zip,
                'organizationCountry' => $organization->country,
            ] : null,
        ];
    }
}
<?php

namespace App\Services;

use App\Models\Order\ProductRequest;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Docuseal\DocusealSubmission;
use App\Models\Docuseal\DocusealFolder;
use App\Models\Order\Manufacturer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class IvrDocusealService
{
    private DocusealService $docusealService;
    private FhirService $fhirService;
    private IvrFieldMappingService $fieldMappingService;

    public function __construct(
        DocusealService $docusealService, 
        FhirService $fhirService,
        IvrFieldMappingService $fieldMappingService
    ) {
        $this->docusealService = $docusealService;
        $this->fhirService = $fhirService;
        $this->fieldMappingService = $fieldMappingService;
    }

    /**
     * Generate IVR document for a product request
     */
    public function generateIvr(ProductRequest $productRequest): DocusealSubmission
    {
        try {
            // Validate order status
            if ($productRequest->order_status !== 'pending_ivr') {
                throw new Exception('Product request must be in pending_ivr status to generate IVR');
            }

            // Get manufacturer from first product
            $product = $productRequest->products()->first();
            if (!$product) {
                throw new Exception('No products found in this request');
            }

            $manufacturer = Manufacturer::where('name', $product->manufacturer)->first();
            if (!$manufacturer) {
                throw new Exception("Manufacturer not found: {$product->manufacturer}");
            }

            // Get manufacturer key for mapping
            $manufacturerKey = $this->getManufacturerKey($manufacturer->name);
            
            // Get DocuSeal configuration for manufacturer
            $docusealConfig = $this->fieldMappingService->getDocuSealConfig($manufacturerKey);
            
            if (!$docusealConfig['template_id']) {
                throw new Exception("No DocuSeal template configured for manufacturer: {$manufacturer->name}");
            }

            // Map product request data to IVR fields using the field mapping service
            $mappedFields = $this->fieldMappingService->mapProductRequestToIvrFields(
                $productRequest,
                $manufacturerKey
            );

            // Validate required fields
            $errors = $this->fieldMappingService->validateMapping($manufacturerKey, $mappedFields);
            if (!empty($errors)) {
                Log::warning('Missing required IVR fields', [
                    'product_request_id' => $productRequest->id,
                    'manufacturer' => $manufacturer->name,
                    'errors' => $errors
                ]);
            }

            // Prepare submission data - No email sending, just generate the document
            $submissionData = [
                'template_id' => (int) $docusealConfig['template_id'],
                'send_email' => false, // Don't send for signature, just generate
                'submitters' => [
                    [
                        'email' => 'noreply@mscwound.com', // Placeholder since no signature needed
                        'name' => 'MSC Admin',
                        'values' => $mappedFields, // Use mapped fields
                    ]
                ],
            ];
            
            // Add folder if available
            if ($docusealConfig['folder_id']) {
                $submissionData['folder'] = $docusealConfig['folder_id'];
            }

            // Create submission via API
            $response = $this->createDocusealSubmission($submissionData);

            // Get submission details from response
            $submissionData = is_array($response) && isset($response[0]) ? $response[0] : $response;
            
            // Create local submission record
            $submission = DocusealSubmission::create([
                'order_id' => $productRequest->id, // Using product_request_id as order_id
                'docuseal_submission_id' => $submissionData['submission_id'] ?? $submissionData['id'] ?? null,
                'docuseal_template_id' => $docusealConfig['template_id'],
                'document_type' => 'IVR',
                'status' => 'completed', // Mark as completed since no signature needed
                'folder_id' => null, // Update if we start tracking folders locally
                'document_url' => $submissionData['documents'][0]['url'] ?? null,
                'metadata' => [
                    'manufacturer_id' => $manufacturer->id,
                    'manufacturer_name' => $manufacturer->name,
                    'template_name' => $docusealConfig['name'],
                    'generated_by' => auth()->user() ? auth()->user()->full_name : 'System',
                    'mapped_fields' => $mappedFields,
                ],
            ]);

            // Update product request with IVR info
            $productRequest->update([
                'docuseal_submission_id' => $submissionData['submission_id'] ?? $submissionData['id'] ?? null,
                'docuseal_template_id' => $docusealConfig['template_id'],
                'ivr_sent_at' => now(),
                'ivr_document_url' => $submissionData['documents'][0]['url'] ?? null,
                'order_status' => 'ivr_sent', // Ready for admin review and send to manufacturer
            ]);

            // Generate order number if not already present
            if (!$productRequest->order_number) {
                $productRequest->update([
                    'order_number' => $productRequest->generateOrderNumber()
                ]);
            }

            Log::info('IVR generated successfully for admin review', [
                'product_request_id' => $productRequest->id,
                'submission_id' => $submission->id,
                'manufacturer' => $manufacturer->name,
            ]);

            return $submission;

        } catch (Exception $e) {
            Log::error('Failed to generate IVR', [
                'product_request_id' => $productRequest->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Mark IVR as sent to manufacturer
     */
    public function markIvrSentToManufacturer(ProductRequest $productRequest, int $userId): void
    {
        try {
            if ($productRequest->order_status !== 'ivr_sent') {
                throw new Exception('Product request must be in ivr_sent status to mark as sent to manufacturer');
            }

            $productRequest->update([
                'manufacturer_sent_at' => now(),
                'manufacturer_sent_by' => $userId,
            ]);

            Log::info('IVR marked as sent to manufacturer', [
                'product_request_id' => $productRequest->id,
                'user_id' => $userId,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to mark IVR as sent to manufacturer', [
                'product_request_id' => $productRequest->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Skip IVR requirement with justification
     */
    public function skipIvr(ProductRequest $productRequest, string $reason, int $userId): void
    {
        try {
            if ($productRequest->order_status !== 'pending_ivr') {
                throw new Exception('Product request must be in pending_ivr status to skip IVR');
            }

            $productRequest->update([
                'ivr_required' => false,
                'ivr_bypass_reason' => $reason,
                'ivr_bypassed_at' => now(),
                'ivr_bypassed_by' => $userId,
                'order_status' => 'ivr_confirmed', // Move directly to IVR confirmed
            ]);

            // Generate order number if not present
            if (!$productRequest->order_number) {
                $productRequest->update([
                    'order_number' => $productRequest->generateOrderNumber()
                ]);
            }

            Log::info('IVR bypassed', [
                'product_request_id' => $productRequest->id,
                'reason' => $reason,
                'user_id' => $userId,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to skip IVR', [
                'product_request_id' => $productRequest->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get patient data from FHIR
     */
    private function getPatientDataFromFhir(ProductRequest $productRequest): array
    {
        try {
            // Get patient data from FHIR using patient FHIR ID
            $patient = $this->fhirService->getPatient($productRequest->patient_fhir_id);
            
            return [
                'patient_name' => $patient->name[0]->given[0] . ' ' . $patient->name[0]->family ?? 'Unknown',
                'patient_dob' => $patient->birthDate ?? '',
                'patient_gender' => $patient->gender ?? '',
                'patient_address' => $this->formatAddress($patient->address[0] ?? null),
                'patient_phone' => $patient->telecom[0]->value ?? '',
                'patient_identifier' => $productRequest->patient_display_id,
            ];
        } catch (Exception $e) {
            Log::warning('Failed to get patient data from FHIR', [
                'product_request_id' => $productRequest->id,
                'patient_fhir_id' => $productRequest->patient_fhir_id,
                'error' => $e->getMessage()
            ]);

            // Return basic data if FHIR fails
            return [
                'patient_name' => 'Patient ' . $productRequest->patient_display_id,
                'patient_dob' => '',
                'patient_gender' => '',
                'patient_address' => '',
                'patient_phone' => '',
                'patient_identifier' => $productRequest->patient_display_id,
            ];
        }
    }

    /**
     * Map IVR fields for DocuSeal according to ACZ IVR schema
     */
    private function mapIvrFields(ProductRequest $productRequest, array $patientData): array
    {
        $product = $productRequest->products()->first();
        $clinicalSummary = $productRequest->clinical_summary ?? [];
        $provider = $productRequest->provider;
        $facility = $productRequest->facility;
        $providerProfile = $provider->providerProfile;

        // Map place of service code
        $posMapping = [
            '11' => 'POS11',
            '22' => 'POS22',
            '24' => 'POS24',
            '12' => 'POS12',
            '32' => 'POS32',
        ];
        $placeOfService = $posMapping[$productRequest->place_of_service] ?? 'Other';

        return [
            // Treating Physician/Facility Information
            [
                'name' => 'treatingPhysicianFacility.npi',
                'default_value' => $provider->npi_number ?? ''
            ],
            [
                'name' => 'treatingPhysicianFacility.taxId',
                'default_value' => $facility->tax_id ?? ''
            ],
            [
                'name' => 'treatingPhysicianFacility.ptan',
                'default_value' => $facility->ptan ?? $providerProfile->ptan ?? ''
            ],
            [
                'name' => 'treatingPhysicianFacility.medicaidNumber',
                'default_value' => $providerProfile->medicaid_number ?? ''
            ],
            [
                'name' => 'treatingPhysicianFacility.phone',
                'default_value' => $facility->phone ?? ''
            ],
            [
                'name' => 'treatingPhysicianFacility.fax',
                'default_value' => $facility->fax ?? ''
            ],
            [
                'name' => 'treatingPhysicianFacility.managementCompany',
                'default_value' => $facility->management_company ?? ''
            ],
            [
                'name' => 'treatingPhysicianFacility.physicianName',
                'default_value' => $provider->full_name
            ],
            [
                'name' => 'treatingPhysicianFacility.facilityName',
                'default_value' => $facility->name
            ],

            // Patient Demographics & Insurance
            [
                'name' => 'patientDemographicInsurance.placeOfService',
                'default_value' => $placeOfService
            ],
            [
                'name' => 'patientDemographicInsurance.placeOfServiceOther',
                'default_value' => $placeOfService === 'Other' ? $productRequest->place_of_service_display : ''
            ],
            [
                'name' => 'patientDemographicInsurance.insurancePrimary.name',
                'default_value' => $productRequest->payer_name_submitted ?? ''
            ],
            [
                'name' => 'patientDemographicInsurance.insurancePrimary.policyNumber',
                'default_value' => $productRequest->payer_id ?? ''
            ],
            [
                'name' => 'patientDemographicInsurance.insurancePrimary.payerPhone',
                'default_value' => '' // Would need to lookup from payer database
            ],
            [
                'name' => 'patientDemographicInsurance.insurancePrimary.providerStatus',
                'default_value' => 'IN-NETWORK' // Default assumption
            ],
            [
                'name' => 'patientDemographicInsurance.permissionForPriorAuth',
                'default_value' => true
            ],
            [
                'name' => 'patientDemographicInsurance.inHospice',
                'default_value' => false
            ],
            [
                'name' => 'patientDemographicInsurance.underPartAStay',
                'default_value' => false
            ],
            [
                'name' => 'patientDemographicInsurance.underGlobalSurgicalPeriod',
                'default_value' => false
            ],

            // Wound Information
            [
                'name' => 'woundInformation.locationOfWound',
                'default_value' => $clinicalSummary['woundDetails']['location'] ?? ''
            ],
            [
                'name' => 'woundInformation.icd10Codes',
                'default_value' => $this->getICD10CodesForWoundType($productRequest->wound_type)
            ],
            [
                'name' => 'woundInformation.totalWoundSizeOrMedicalHistory',
                'default_value' => $clinicalSummary['woundDetails']['size'] ?? ''
            ],

            // Product Information
            [
                'name' => 'productInformation.productName',
                'default_value' => $product->name ?? ''
            ],
            [
                'name' => 'productInformation.productCode',
                'default_value' => $product->sku ?? ''
            ],

            // Representative Information
            [
                'name' => 'representative.name',
                'default_value' => $productRequest->sales_rep_name ?? 'MSC Representative'
            ],
            [
                'name' => 'representative.isoIfApplicable',
                'default_value' => ''
            ],

            // Physician Information
            [
                'name' => 'physician.name',
                'default_value' => $provider->full_name
            ],
            [
                'name' => 'physician.specialty',
                'default_value' => $providerProfile->specialty ?? 'Wound Care'
            ],

            // Facility Information
            [
                'name' => 'facility.name',
                'default_value' => $facility->name
            ],
            [
                'name' => 'facility.address',
                'default_value' => $facility->address ?? ''
            ],
            [
                'name' => 'facility.cityStateZip',
                'default_value' => "{$facility->city}, {$facility->state} {$facility->zip_code}"
            ],
            [
                'name' => 'facility.contactName',
                'default_value' => $facility->contact_name ?? ''
            ],
            [
                'name' => 'facility.contactPhoneEmail',
                'default_value' => "{$facility->phone} / {$facility->email}"
            ],

            // Patient Information
            [
                'name' => 'patient.name',
                'default_value' => $patientData['patient_name']
            ],
            [
                'name' => 'patient.dob',
                'default_value' => $patientData['patient_dob']
            ],
            [
                'name' => 'patient.address',
                'default_value' => $patientData['patient_address']
            ],
            [
                'name' => 'patient.cityStateZip',
                'default_value' => '' // Would be parsed from patient address
            ],
            [
                'name' => 'patient.phone',
                'default_value' => $patientData['patient_phone']
            ],

            // Service Information
            [
                'name' => 'servicedBy',
                'default_value' => 'MSC Wound Portal'
            ],
        ];
    }

    /**
     * Get ICD-10 codes based on wound type
     */
    private function getICD10CodesForWoundType(string $woundType): array
    {
        $icd10Mapping = [
            'DFU' => ['L97.419', 'E11.621'], // Diabetic foot ulcer
            'VLU' => ['I87.33', 'L97.929'],  // Venous leg ulcer
            'PU' => ['L89.95'],               // Pressure ulcer
            'SURGICAL' => ['T81.31XA'],       // Surgical wound
        ];

        return $icd10Mapping[$woundType] ?? [];
    }

    /**
     * Create DocuSeal submission via API
     */
    private function createDocusealSubmission(array $data): array
    {
        $apiKey = config('services.docuseal.api_key');
        $apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');

        $response = Http::withHeaders([
            'X-Auth-Token' => $apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$apiUrl}/submissions", $data);

        if (!$response->successful()) {
            throw new Exception('DocuSeal API error: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Format address for display
     */
    private function formatAddress($address): string
    {
        if (!$address) {
            return '';
        }

        $parts = [];
        if (!empty($address->line)) {
            $parts = array_merge($parts, $address->line);
        }
        if (!empty($address->city)) {
            $parts[] = $address->city;
        }
        if (!empty($address->state)) {
            $parts[] = $address->state;
        }
        if (!empty($address->postalCode)) {
            $parts[] = $address->postalCode;
        }

        return implode(', ', $parts);
    }

    /**
     * Format facility address
     */
    private function formatFacilityAddress($facility): string
    {
        $parts = [];
        if ($facility->address) {
            $parts[] = $facility->address;
        }
        if ($facility->city && $facility->state && $facility->zip_code) {
            $parts[] = "{$facility->city}, {$facility->state} {$facility->zip_code}";
        }

        return implode(', ', $parts);
    }

    /**
     * Get manufacturer key for field mapping
     */
    private function getManufacturerKey(string $manufacturerName): string
    {
        // Map manufacturer names to configuration keys
        $manufacturerMap = [
            'ACZ Distribution' => 'ACZ_Distribution',
            'Advanced Health' => 'Advanced_Health',
            'Advanced Health Solutions' => 'Advanced_Health',
            'Amnio AMP' => 'Amnio_Amp',
            'MedLife Solutions' => 'Amnio_Amp',
            'AmnioBand' => 'AmnioBand',
            'Centurion' => 'AmnioBand',
            'Centurion Therapeutics' => 'AmnioBand',
            'BioWerX' => 'BioWerX',
            'BioWound' => 'BioWound',
            'BioWound Solutions' => 'BioWound',
            'Extremity Care' => 'Extremity_Care',
            'SKYE' => 'SKYE',
            'SKYE Biologics' => 'SKYE',
            'Total Ancillary' => 'Total_Ancillary',
        ];

        return $manufacturerMap[$manufacturerName] ?? str_replace(' ', '_', $manufacturerName);
    }
}
<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class DocuSealFieldMapper
{
    /**
     * Turn an associative map into DocuSeal's expected [ {name, default_value}, … ].
     *
     * @param array<string,mixed> $mappedFields  e.g. ['amnio_amp_size'=>'4x4', …]
     * @return array<array{name:string,default_value:mixed}>
     */
    public static function toDocuSealFields(array $mappedFields): array
    {
        return array_map(
            function(string $name) use ($mappedFields) {
                return [
                    'name'  => $name,
                    'default_value' => $mappedFields[$name],
                ];
            },
            array_keys($mappedFields)
        );
    }

    /**
     * Load a mapping file, extract the "canonicalFields" under a given section,
     * and format for DocuSeal.
     *
     * @param string   $jsonPath  Path to your JSON mapping (e.g. insurance_form_mappings.json)
     * @param string[] $path      The nested keys to "canonicalFields" (e.g. ['insuranceInformation','primaryInsurance'])
     * @param string   $formId    The form key you're targeting (e.g. 'form2_IVR' or 'form5_AdvancedSolution')
     * @return array<array{name:string,value:mixed}>
     */
    public static function mapJsonToDocuSealFields(string $jsonPath, array $path, string $formId): array
    {
        if (!File::exists($jsonPath)) {
            Log::warning("Mapping file not found: {$jsonPath}");
            return [];
        }

        $data = json_decode(File::get($jsonPath), true);
        
        // drill into the nested section
        $node = $data;
        foreach ($path as $key) {
            if (!isset($node[$key])) {
                Log::warning("Path '" . implode('.', $path) . "' not found in {$jsonPath}");
                return [];
            }
            $node = $node[$key];
        }
        
        // node now contains ['canonicalFields' => […]]
        $assoc = [];
        if (isset($node['canonicalFields'])) {
            foreach ($node['canonicalFields'] as $fieldKey => $meta) {
                // pick the label for this form (or null if unmapped)
                $assoc[$fieldKey] = $meta['formMappings'][$formId] ?? null;
            }
        }
        
        // reusable converter from associative → indexed [{name, value},…]
        return self::toDocuSealFields($assoc);
    }

    /**
     * Get the correct form ID for a manufacturer
     */
    public static function getFormIdForManufacturer(string $manufacturerName): string
    {
        $manufacturerFormMap = [
            'ACZ' => 'form1_ACZ',
            'IVR' => 'form2_IVR',
            'Centurion' => 'form3_Centurion',
            'BioWound' => 'form4_BioWound',
            'Advanced Solution' => 'form5_AdvancedSolution',
            'ImbedBio' => 'form6_ImbedBio',
            'Extremity Care FT' => 'form7_ExtremityCare_FT',
            'Extremity Care RO' => 'form8_ExtremityCare_RO',
            'MedLife' => 'form2_IVR', // MedLife uses IVR form format
            'MedLife Solutions' => 'form2_IVR',
        ];

        return $manufacturerFormMap[$manufacturerName] ?? 'form2_IVR';
    }

    /**
     * Map fields for a specific manufacturer using the JSON mapping files
     */
    public static function mapFieldsForManufacturer(array $inputData, string $manufacturerName): array
    {
        $formId = self::getFormIdForManufacturer($manufacturerName);
        $basePath = base_path('docs/mapping-final');
        
        $mappedFields = [];

        // Load all canonical field mappings
        $canonicalMappings = [];
        
        // Load insurance fields JSON
        $insuranceData = json_decode(File::get("{$basePath}/insurance_form_mappings.json"), true);
        
        // Load ALL sections from insurance form mappings
        // 1. Primary Insurance fields
        if (isset($insuranceData['insuranceInformation']['primaryInsurance']['canonicalFields'])) {
            foreach ($insuranceData['insuranceInformation']['primaryInsurance']['canonicalFields'] as $canonicalName => $fieldInfo) {
                if (isset($fieldInfo['formMappings'][$formId])) {
                    $canonicalMappings[$canonicalName] = $fieldInfo['formMappings'][$formId];
                }
            }
        }
        
        // 2. Secondary Insurance fields
        if (isset($insuranceData['insuranceInformation']['secondaryInsurance']['canonicalFields'])) {
            foreach ($insuranceData['insuranceInformation']['secondaryInsurance']['canonicalFields'] as $canonicalName => $fieldInfo) {
                if (isset($fieldInfo['formMappings'][$formId])) {
                    $canonicalMappings[$canonicalName] = $fieldInfo['formMappings'][$formId];
                }
            }
        }
        
        // 3. Physician Information fields
        if (isset($insuranceData['standardFieldMappings']['physicianInformation']['canonicalFields'])) {
            foreach ($insuranceData['standardFieldMappings']['physicianInformation']['canonicalFields'] as $canonicalName => $fieldInfo) {
                if (isset($fieldInfo['formMappings'][$formId])) {
                    $canonicalMappings[$canonicalName] = $fieldInfo['formMappings'][$formId];
                }
            }
        }
        
        // 4. Patient Information fields
        if (isset($insuranceData['standardFieldMappings']['patientInformation']['canonicalFields'])) {
            foreach ($insuranceData['standardFieldMappings']['patientInformation']['canonicalFields'] as $canonicalName => $fieldInfo) {
                if (isset($fieldInfo['formMappings'][$formId])) {
                    $canonicalMappings[$canonicalName] = $fieldInfo['formMappings'][$formId];
                }
            }
        }
        
        // 5. Facility Information fields
        if (isset($insuranceData['standardFieldMappings']['facilityInformation']['canonicalFields'])) {
            foreach ($insuranceData['standardFieldMappings']['facilityInformation']['canonicalFields'] as $canonicalName => $fieldInfo) {
                if (isset($fieldInfo['formMappings'][$formId])) {
                    $canonicalMappings[$canonicalName] = $fieldInfo['formMappings'][$formId];
                }
            }
        }
        
        // Load order form mappings JSON
        $orderData = json_decode(File::get("{$basePath}/order-form-mappings.json"), true);
        
        // 6. Order Information fields
        if (isset($orderData['orderFormFieldMappings']['standardFields']['orderInformation']['canonicalFields'])) {
            foreach ($orderData['orderFormFieldMappings']['standardFields']['orderInformation']['canonicalFields'] as $canonicalName => $fieldInfo) {
                if (isset($fieldInfo['formMappings'][$formId])) {
                    $canonicalMappings[$canonicalName] = $fieldInfo['formMappings'][$formId];
                }
            }
        }
        
        // 7. Shipping Information fields
        if (isset($orderData['orderFormFieldMappings']['standardFields']['shippingInformation']['canonicalFields'])) {
            foreach ($orderData['orderFormFieldMappings']['standardFields']['shippingInformation']['canonicalFields'] as $canonicalName => $fieldInfo) {
                if (isset($fieldInfo['formMappings'][$formId])) {
                    $canonicalMappings[$canonicalName] = $fieldInfo['formMappings'][$formId];
                }
            }
        }
        
        // 8. Provider Information fields (from order forms)
        if (isset($orderData['orderFormFieldMappings']['standardFields']['providerInformation']['canonicalFields'])) {
            foreach ($orderData['orderFormFieldMappings']['standardFields']['providerInformation']['canonicalFields'] as $canonicalName => $fieldInfo) {
                if (isset($fieldInfo['formMappings'][$formId])) {
                    $canonicalMappings[$canonicalName] = $fieldInfo['formMappings'][$formId];
                }
            }
        }
        
        // 9. Product Information fields
        if (isset($orderData['orderFormFieldMappings']['standardFields']['productInformation']['canonicalFields'])) {
            foreach ($orderData['orderFormFieldMappings']['standardFields']['productInformation']['canonicalFields'] as $canonicalName => $fieldInfo) {
                if (isset($fieldInfo['formMappings'][$formId])) {
                    $canonicalMappings[$canonicalName] = $fieldInfo['formMappings'][$formId];
                }
            }
        }
        
        // 10. Additional Information fields (contact, clinical notes, etc.)
        if (isset($orderData['orderFormFieldMappings']['standardFields']['additionalInformation']['canonicalFields'])) {
            foreach ($orderData['orderFormFieldMappings']['standardFields']['additionalInformation']['canonicalFields'] as $canonicalName => $fieldInfo) {
                if (isset($fieldInfo['formMappings'][$formId])) {
                    $canonicalMappings[$canonicalName] = $fieldInfo['formMappings'][$formId];
                }
            }
        }
        
        // Common field name mappings from our app field names to canonical names
        $appToCanonicalMappings = [
            // Provider/Physician fields
            'provider_npi' => 'physicianNPI',
            'provider_name' => 'physicianName',
            'provider_tax_id' => 'taxId',
            'provider_credentials' => 'physicianCredentials',
            'provider_email' => 'physicianEmail',
            'practice_name' => 'practiceName',
            'practice_npi' => 'practiceNPI',
            'practice_ptan' => 'practicePTAN',
            'ptanNumber' => 'ptanNumber',
            'medicaidNumber' => 'medicaidNumber',
            
            // Patient fields
            'patient_name' => 'patientName',
            'patient_first_name' => 'patientFirstName',
            'patient_last_name' => 'patientLastName',
            'patient_dob' => 'patientDOB',
            'patient_member_id' => 'patientMemberID',
            'patient_phone' => 'patientPhone',
            'patient_email' => 'patientEmail',
            'patient_gender' => 'patientGender',
            'patient_address_line1' => 'patientAddressLine1',
            'patient_address_line2' => 'patientAddressLine2',
            'patient_city' => 'patientCity',
            'patient_state' => 'patientState',
            'patient_zip' => 'patientZip',
            
            // Insurance fields
            'primary_insurance_name' => 'insuranceName',
            'primary_member_id' => 'policyNumber',
            'primary_plan_type' => 'primaryPlanType',
            'group_number' => 'primaryGroupNumber',
            'payer_phone' => 'primaryPhoneNumber',
            'primary_payer_phone' => 'primaryPhoneNumber',
            'secondary_insurance_name' => 'secondaryInsuranceName',
            'secondary_member_id' => 'secondaryPolicyNumber',
            'insurance_name' => 'insuranceName',
            'member_id' => 'policyNumber',
            'primaryInsuranceName' => 'insuranceName',
            'primaryPolicyNumber' => 'policyNumber',
            'primaryGroupNumber' => 'primaryGroupNumber',
            'primaryPhoneNumber' => 'primaryPhoneNumber',
            'secondary_payer_phone' => 'secondaryPhoneNumber',
            'secondaryPolicyNumber' => 'secondaryPolicyNumber',
            'secondaryGroupNumber' => 'secondaryGroupNumber',
            
            // Facility fields
            'facility_name' => 'facilityName',
            'facility_address' => 'facilityAddress',
            'facility_city' => 'facilityCity',
            'facility_state' => 'facilityState',
            'facility_zip' => 'facilityZip',
            'facility_phone' => 'facilityPhone',
            'facility_fax' => 'facilityFax',
            'facilityName' => 'facilityName',
            'facilityAddress' => 'facilityAddress',
            'facilityCity' => 'facilityCity',
            'facilityState' => 'facilityState',
            'facilityZip' => 'facilityZip',
            'facilityPhone' => 'facilityPhone',
            'facilityFax' => 'facilityFax',
            
            // Clinical/Procedure fields
            'wound_location' => 'woundLocation',
            'wound_type' => 'woundType',
            'wound_size_length' => 'woundLength',
            'wound_size_width' => 'woundWidth',
            'wound_size_depth' => 'woundDepth',
            'total_wound_size' => 'totalWoundSize',
            'wound_duration' => 'woundDuration',
            'primary_diagnosis_code' => 'primaryDiagnosisCode',
            'secondary_diagnosis_code' => 'secondaryDiagnosisCode',
            'diagnosis_code' => 'diagnosisCode',
            'service_date' => 'serviceDate',
            'expected_service_date' => 'procedureDate',
            'procedure_date' => 'procedureDate',
            'procedureDate' => 'procedureDate',
            'wound_dimensions' => 'woundSize',
            'size_of_graft_requested' => 'woundSize',
            'icd10_codes' => 'diagnosisCode',
            'diagnosis_codes_display' => 'diagnosisCode',
            'wound_location_details' => 'woundLocation',
            'wound_duration_days' => 'woundDurationDays',
            'wound_duration_weeks' => 'woundDurationWeeks',
            'wound_duration_months' => 'woundDurationMonths',
            'wound_duration_years' => 'woundDurationYears',
            'application_cpt_codes' => 'procedureCodes',
            'prior_applications' => 'priorApplications',
            'prior_application_product' => 'priorProductUsed',
            'anticipated_applications' => 'anticipatedApplications',
            
            // Place of service
            'place_of_service' => 'placeOfService',
            'placeOfService' => 'placeOfService',
            
            // Product fields
            'product_name' => 'productName',
            'product_code' => 'productCode',
            'product_manufacturer' => 'manufacturerName',
            'product_details_text' => 'productDetails',
            'productName' => 'productName',
            'productCode' => 'productCode',
            'manufacturerName' => 'manufacturerName',
            
            // Contact fields
            'sales_rep_name' => 'salesRepName',
            'office_contact_name' => 'contactName',
            'office_contact_email' => 'contactEmail',
            'contact_name' => 'contactName',
            'contact_email' => 'contactEmail',
            'contact_phone' => 'contactPhone',
            'contactName' => 'contactName',
            'contactEmail' => 'contactEmail',
            'contactPhone' => 'contactPhone',
            
            // Additional mappings for compatibility
            'physician_name' => 'physicianName',
            'physician_npi' => 'physicianNPI',
            'patient_display_id' => 'patientMemberID',
            'insurance_member_id' => 'primaryPolicyNumber',
            'physicianName' => 'physicianName',
            'physicianNPI' => 'physicianNPI',
            'patientName' => 'patientName',
            'patientDOB' => 'patientDOB',
            'patientPhone' => 'patientPhone',
            'patientMemberID' => 'patientMemberID',
            
            // CPT codes
            'cpt_codes' => 'cptCodes',
            'prior_application_cpt_codes' => 'previousCPTCodes',
            'if_yes_please_list_cpt_codes_of_previous_surgery' => 'previousCPTCodes',
            
            // Additional clinical fields
            'hospice_status' => 'hospiceStatus',
            'part_a_status' => 'partAStatus',
            'global_period_status' => 'globalPeriodStatus',
            'medicare_part_b_authorized' => 'medicarePartBAuthorized',
            'prior_application_within_12_months' => 'priorApplicationWithin12Months',
            
            // Order/Request fields
            'request_date' => 'orderDate',
            'order_date' => 'orderDate',
            'delivery_date' => 'deliveryDate',
            'shipping_speed' => 'shippingMethod',
            
            // Authorization fields
            'prior_auth_permission' => 'priorAuthRequired',
            'failed_conservative_treatment' => 'conservativeTreatmentFailed',
            'information_accurate' => 'informationAccurate',
            'medical_necessity_established' => 'medicalNecessityEstablished',
            'maintain_documentation' => 'maintainDocumentation',
            'authorize_prior_auth' => 'authorizePriorAuth',
            
            // Organization fields
            'organization_name' => 'organizationName',
            'organization_phone' => 'organizationPhone',
        ];
        
        // Log input data for debugging
        Log::info('DocuSeal field mapping input data', [
            'manufacturer' => $manufacturerName,
            'form_id' => $formId,
            'input_field_count' => count($inputData),
            'input_fields' => array_keys($inputData),
            'sample_data' => array_slice($inputData, 0, 5, true)
        ]);
        
        // Log canonical mappings found
        Log::info('DocuSeal canonical mappings loaded', [
            'canonical_field_count' => count($canonicalMappings),
            'canonical_fields' => array_slice($canonicalMappings, 0, 10, true),
            'facility_mapping' => $canonicalMappings['facilityName'] ?? 'NOT FOUND',
            'physician_npi_mapping' => $canonicalMappings['physicianNPI'] ?? 'NOT FOUND'
        ]);
        
        // Map input data to DocuSeal fields
        $skippedFields = [];
        $processedGender = false; // Track if we've already processed gender
        
        foreach ($inputData as $appFieldName => $value) {
            // Skip empty values
            if ($value === null || $value === '') {
                continue;
            }
            
            // Get the canonical field name
            $canonicalName = $appToCanonicalMappings[$appFieldName] ?? null;
            
            // If no canonical mapping exists, check if the field is already a canonical name
            if (!$canonicalName && array_search($appFieldName, $appToCanonicalMappings) === false) {
                // Skip unknown fields to avoid sending them to DocuSeal
                $skippedFields[] = $appFieldName;
                continue;
            }
            
            // Use the app field name as canonical if it's already a canonical field
            if (!$canonicalName) {
                $canonicalName = $appFieldName;
            }
            
            // Skip gender field if it maps to "Male Female" - we'll handle it separately
            if ($canonicalName === 'patientGender' && isset($canonicalMappings[$canonicalName]) && 
                $canonicalMappings[$canonicalName] === 'Male Female') {
                Log::info('Skipping gender field for special handling', [
                    'app_field' => $appFieldName,
                    'canonical_name' => $canonicalName,
                    'mapped_value' => $canonicalMappings[$canonicalName],
                    'patient_gender' => $value
                ]);
                $processedGender = true;
                continue;
            }
            
            // If we have a DocuSeal field name for this canonical field, use it
            if (isset($canonicalMappings[$canonicalName])) {
                $docuSealFieldName = $canonicalMappings[$canonicalName];
                
                // Apply any necessary transformations
                $transformedValue = $value;
                
                // Format dates if needed
                if (in_array($canonicalName, ['patientDOB', 'procedureDate', 'serviceDate']) && $value) {
                    try {
                        $date = new \DateTime($value);
                        $transformedValue = $date->format('m/d/Y');
                    } catch (\Exception $e) {
                        // Keep original value if date parsing fails
                    }
                }
                
                // Format phone numbers
                if (in_array($canonicalName, ['patientPhone', 'contactPhone', 'facilityPhone']) && $value) {
                    // Remove non-numeric characters and format
                    $phone = preg_replace('/[^0-9]/', '', $value);
                    if (strlen($phone) == 10) {
                        $transformedValue = sprintf('(%s) %s-%s', 
                            substr($phone, 0, 3), 
                            substr($phone, 3, 3), 
                            substr($phone, 6)
                        );
                    }
                }
                
                $mappedFields[] = [
                    'name' => $docuSealFieldName,
                    'default_value' => (string)$transformedValue
                ];
                
                Log::debug('Field mapped successfully', [
                    'app_field' => $appFieldName,
                    'canonical_name' => $canonicalName,
                    'docuseal_field' => $docuSealFieldName,
                    'value' => substr((string)$transformedValue, 0, 50) // Log first 50 chars
                ]);
            } else {
                $skippedFields[] = $appFieldName;
            }
        }
        
        if (!empty($skippedFields)) {
            Log::warning('Fields skipped during DocuSeal mapping', [
                'manufacturer' => $manufacturerName,
                'form_id' => $formId,
                'skipped_count' => count($skippedFields),
                'skipped_fields' => $skippedFields
            ]);
        }

        // Special handling for gender checkboxes only if:
        // 1. We have gender data
        // 2. The form expected "Male Female" field (processedGender = true)
        // 3. The form is known to have separate Male/Female checkboxes
        if (($processedGender || isset($inputData['patient_gender'])) && !empty($inputData['patient_gender'])) {
            $gender = strtolower($inputData['patient_gender']);
            
            // Only add gender checkboxes for forms that we know have them
            // Based on the mapping file, form2_IVR maps gender to "Male Female" which doesn't exist
            // So we need to skip gender for IVR forms entirely
            if ($formId === 'form2_IVR') {
                Log::info('Skipping gender checkboxes for IVR form - template does not have Male/Female fields', [
                    'form_id' => $formId,
                    'gender_value' => $inputData['patient_gender']
                ]);
            } else {
                // For other forms that might have actual Male/Female checkbox fields
                Log::debug('Processing gender field', [
                    'original_value' => $inputData['patient_gender'],
                    'normalized_value' => $gender,
                    'form_id' => $formId
                ]);
                
                // Only add if we're sure the form has these fields
                // This is a conservative approach - we'd need to check template fields to be sure
            }
        }
        
        // Special handling for place of service checkboxes
        if (isset($inputData['place_of_service']) && !empty($inputData['place_of_service'])) {
            // Check if this form uses individual checkboxes for place of service
            // For now, skip for IVR forms as they may not have these fields
            if ($formId === 'form2_IVR') {
                Log::info('Skipping place of service checkboxes for IVR form', [
                    'form_id' => $formId,
                    'place_of_service' => $inputData['place_of_service']
                ]);
            } else {
                $placeOfService = strtolower($inputData['place_of_service']);
                $places = ['Office', 'Home', 'Assisted Living'];
                
                // Only add if we know the form has these checkbox fields
                Log::debug('Processing place of service for non-IVR form', [
                    'form_id' => $formId,
                    'place_of_service' => $placeOfService
                ]);
            }
        }
        
        // Special handling for MedLife specific fields
        if (in_array($manufacturerName, ['MedLife', 'MedLife Solutions'])) {
            // Add amnio_amp_size if present
            if (isset($inputData['amnio_amp_size'])) {
                $mappedFields[] = [
                    'name' => 'amnio_amp_size',
                    'default_value' => $inputData['amnio_amp_size']
                ];
            }
        }

        // Log all field names to debug the "Male Female" issue
        $fieldNames = array_column($mappedFields, 'name');
        Log::info('DocuSeal field mapping complete', [
            'manufacturer' => $manufacturerName,
            'form_id' => $formId,
            'input_fields' => count($inputData),
            'mapped_fields' => count($mappedFields),
            'all_field_names' => $fieldNames,
            'sample_mappings' => array_slice($mappedFields, 0, 5),
            'has_male_female' => in_array('Male Female', $fieldNames),
            'has_male' => in_array('Male', $fieldNames),
            'has_female' => in_array('Female', $fieldNames)
        ]);

        return $mappedFields;
    }
}
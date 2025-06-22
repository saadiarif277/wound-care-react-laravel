<?php

namespace App\Services;

use App\Services\FhirService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class FhirToIvrFieldExtractor
{
    protected $fhirService;
    protected $fhirResources = [];
    
    public function __construct(FhirService $fhirService)
    {
        $this->fhirService = $fhirService;
    }
    
    /**
     * Extract IVR field data from FHIR resources for a specific manufacturer
     */
    public function extractForManufacturer(array $context, string $manufacturerKey): array
    {
        // Load all relevant FHIR resources
        $this->loadFhirResources($context);
        
        // Get manufacturer's IVR template from database
        $manufacturer = \App\Models\Order\Manufacturer::where('name', $manufacturerKey)->first();
        if (!$manufacturer) {
            throw new \Exception("Manufacturer not found: {$manufacturerKey}");
        }
        
        $template = $manufacturer->ivrTemplate();
        if (!$template) {
            throw new \Exception("No IVR template found for manufacturer: {$manufacturerKey}");
        }
        
        $fieldMappings = $template->field_mappings ?? [];
        if (empty($fieldMappings)) {
            throw new \Exception("No field mappings found for manufacturer template: {$manufacturerKey}");
        }
        
        // Extract data for each mapped field
        $extractedData = [];
        foreach ($fieldMappings as $ivrFieldName => $mappingConfig) {
            $systemFieldName = $mappingConfig['system_field'] ?? $mappingConfig['local_field'] ?? null;
            if ($systemFieldName) {
                $extractedData[$ivrFieldName] = $this->extractFieldValue($systemFieldName);
            }
        }
        
        // Apply manufacturer-specific post-processing
        $extractedData = $this->applyManufacturerRules($extractedData, $manufacturerKey);
        
        return $extractedData;
    }
    
    /**
     * Load all FHIR resources needed for extraction
     */
    protected function loadFhirResources(array $context): void
    {
        // Patient resource
        if (isset($context['patient_id'])) {
            $this->fhirResources['patient'] = $this->fhirService->getPatient($context['patient_id']);
        }
        
        // Coverage resources (insurance)
        if (isset($context['patient_id'])) {
            $coverages = $this->fhirService->searchCoverage([
                'patient' => $context['patient_id'],
                'status' => 'active'
            ]);
            
            // Separate primary and secondary
            foreach ($coverages as $coverage) {
                if ($this->isPrimaryCoverage($coverage)) {
                    $this->fhirResources['primary_coverage'] = $coverage;
                } else {
                    $this->fhirResources['secondary_coverage'] = $coverage;
                }
            }
        }
        
        // Practitioner (provider)
        if (isset($context['practitioner_id'])) {
            $this->fhirResources['practitioner'] = $this->fhirService->getPractitioner($context['practitioner_id']);
        }
        
        // Organization (facility)
        if (isset($context['organization_id'])) {
            $this->fhirResources['organization'] = $this->fhirService->getOrganization($context['organization_id']);
        }
        
        // QuestionnaireResponse (clinical assessment)
        if (isset($context['questionnaire_response_id'])) {
            $this->fhirResources['questionnaire_response'] = $this->fhirService->getQuestionnaireResponse(
                $context['questionnaire_response_id']
            );
        }
        
        // DeviceRequest (order details)
        if (isset($context['device_request_id'])) {
            $this->fhirResources['device_request'] = $this->fhirService->getDeviceRequest(
                $context['device_request_id']
            );
        }
        
        // EpisodeOfCare (for existing IVR tracking)
        // Check for FHIR EpisodeOfCare ID first, then fall back to local episode_id
        if (isset($context['episode_of_care_id'])) {
            $this->fhirResources['episode'] = $this->fhirService->getEpisodeOfCare($context['episode_of_care_id']);
        } elseif (isset($context['episode_id'])) {
            $this->fhirResources['episode'] = $this->fhirService->getEpisodeOfCare($context['episode_id']);
        }
        
        // Additional context data (non-FHIR)
        if (isset($context['sales_rep'])) {
            $this->fhirResources['sales_rep'] = $context['sales_rep'];
        }
        
        if (isset($context['selected_products'])) {
            $this->fhirResources['selected_products'] = $context['selected_products'];
        }
    }
    
    /**
     * Extract a specific field value from FHIR resources
     */
    protected function extractFieldValue(string $fieldName): ?string
    {
        switch ($fieldName) {
            // Patient Demographics
            case 'patient_name':
                return $this->extractPatientName();
                
            case 'patient_dob':
                return $this->extractPatientDob();
                
            case 'patient_address':
                return $this->extractPatientAddress();
                
            case 'patient_city':
                return $this->extractPatientCity();
                
            case 'patient_state':
                return $this->extractPatientState();
                
            case 'patient_zip':
                return $this->extractPatientZip();
                
            case 'patient_phone':
                return $this->extractPatientPhone();
                
            case 'patient_gender':
                return $this->extractPatientGender();
                
            // Insurance Information
            case 'primary_insurance_name':
                return $this->extractInsuranceName('primary');
                
            case 'primary_policy_number':
            case 'primary_member_id':
                return $this->extractPolicyNumber('primary');
                
            case 'primary_payer_phone':
                return $this->extractPayerPhone('primary');
                
            case 'primary_subscriber_name':
                return $this->extractSubscriberName('primary');
                
            case 'primary_subscriber_dob':
                return $this->extractSubscriberDob('primary');
                
            case 'primary_plan_type':
                return $this->extractPlanType('primary');
                
            case 'secondary_insurance_name':
                return $this->extractInsuranceName('secondary');
                
            case 'secondary_policy_number':
            case 'secondary_member_id':
                return $this->extractPolicyNumber('secondary');
                
            // Provider Information
            case 'provider_name':
            case 'physician_name':
                return $this->extractProviderName();
                
            case 'provider_npi':
            case 'physician_npi':
                return $this->extractProviderNpi();
                
            case 'provider_tax_id':
            case 'physician_tax_id':
                return $this->extractProviderTaxId();
                
            case 'provider_ptan':
            case 'physician_ptan':
                return $this->extractProviderPtan();
                
            case 'provider_specialty':
            case 'physician_specialty':
                return $this->extractProviderSpecialty();
                
            case 'provider_phone':
            case 'physician_phone':
                return $this->extractProviderPhone();
                
            case 'provider_fax':
            case 'physician_fax':
                return $this->extractProviderFax();
                
            case 'provider_medicaid_number':
                return $this->extractProviderMedicaidNumber();
                
            // Facility Information
            case 'facility_name':
                return $this->extractFacilityName();
                
            case 'facility_address':
                return $this->extractFacilityAddress();
                
            case 'facility_city':
                return $this->extractFacilityCity();
                
            case 'facility_state':
                return $this->extractFacilityState();
                
            case 'facility_zip':
                return $this->extractFacilityZip();
                
            case 'facility_npi':
                return $this->extractFacilityNpi();
                
            case 'facility_tax_id':
                return $this->extractFacilityTaxId();
                
            case 'facility_ptan':
                return $this->extractFacilityPtan();
                
            case 'facility_contact_name':
                return $this->extractFacilityContactName();
                
            case 'facility_contact_phone':
                return $this->extractFacilityContactPhone();
                
            case 'facility_contact_fax':
                return $this->extractFacilityContactFax();
                
            case 'facility_contact_email':
                return $this->extractFacilityContactEmail();
                
            // Clinical Information
            case 'wound_type':
                return $this->extractWoundType();
                
            case 'wound_location':
            case 'wound_location_details':
                return $this->extractWoundLocation();
                
            case 'wound_size_length':
                return $this->extractWoundSizeLength();
                
            case 'wound_size_width':
                return $this->extractWoundSizeWidth();
                
            case 'wound_size_total':
                return $this->extractWoundSizeTotal();
                
            case 'wound_duration':
                return $this->extractWoundDuration();
                
            case 'diagnosis_codes':
            case 'icd_10_codes':
                return $this->extractDiagnosisCodes();
                
            case 'primary_diagnosis_code':
                return $this->extractPrimaryDiagnosisCode();
                
            case 'secondary_diagnosis_codes':
                return $this->extractSecondaryDiagnosisCodes();
                
            case 'application_cpt_codes':
            case 'cpt_codes':
                return $this->extractCptCodes();
                
            case 'place_of_service':
                return $this->extractPlaceOfService();
                
            case 'snf_status':
                return $this->extractSnfStatus();
                
            case 'snf_days':
                return $this->extractSnfDays();
                
            case 'hospice_status':
                return $this->extractHospiceStatus();
                
            case 'part_a_status':
                return $this->extractPartAStatus();
                
            case 'global_period_status':
                return $this->extractGlobalPeriodStatus();
                
            case 'global_period_cpt_codes':
                return $this->extractGlobalPeriodCptCodes();
                
            case 'global_period_surgery_date':
                return $this->extractGlobalPeriodSurgeryDate();
                
            case 'previous_treatments':
                return $this->extractPreviousTreatments();
                
            case 'comorbidities':
                return $this->extractComorbidities();
                
            // Product Information
            case 'selected_products':
            case 'product':
                return $this->extractSelectedProducts();
                
            case 'product_sizes':
            case 'graft_size_requested':
                return $this->extractProductSizes();
                
            case 'anticipated_treatment_date':
            case 'procedure_date':
                return $this->extractAnticipatedTreatmentDate();
                
            case 'anticipated_applications':
            case 'number_of_applications':
                return $this->extractAnticipatedApplications();
                
            // Authorization
            case 'authorization_permission':
            case 'prior_auth_permission':
            case 'request_prior_auth_assistance':
                return $this->extractPriorAuthPermission();
                
            // Sales/Admin
            case 'sales_rep_name':
                return $this->extractSalesRepName();
                
            case 'sales_rep_email':
                return $this->extractSalesRepEmail();
                
            case 'additional_notification_emails':
                return $this->extractAdditionalEmails();
                
            // Request Type
            case 'request_type':
                return $this->extractRequestType();
                
            // Signature fields
            case 'signature':
            case 'physician_signature':
            case 'provider_signature':
                return $this->extractSignature();
                
            case 'signature_date':
                return $this->extractSignatureDate();
                
            default:
                // Log unmapped field for debugging
                Log::warning("Unmapped IVR field: {$fieldName}");
                return null;
        }
    }
    
    // Patient extraction methods
    protected function extractPatientName(): ?string
    {
        $patient = $this->fhirResources['patient'] ?? null;
        if (!$patient) return null;
        
        $name = $patient['name'][0] ?? null;
        if (!$name) return null;
        
        $given = implode(' ', $name['given'] ?? []);
        $family = $name['family'] ?? '';
        
        return trim("{$given} {$family}");
    }
    
    protected function extractPatientDob(): ?string
    {
        $patient = $this->fhirResources['patient'] ?? null;
        if (!$patient || !isset($patient['birthDate'])) return null;
        
        // Format date as MM/DD/YYYY for US forms
        return Carbon::parse($patient['birthDate'])->format('m/d/Y');
    }
    
    protected function extractPatientAddress(): ?string
    {
        $patient = $this->fhirResources['patient'] ?? null;
        if (!$patient || !isset($patient['address'][0])) return null;
        
        $address = $patient['address'][0];
        $lines = $address['line'] ?? [];
        
        return implode(', ', $lines);
    }
    
    protected function extractPatientCity(): ?string
    {
        $patient = $this->fhirResources['patient'] ?? null;
        return $patient['address'][0]['city'] ?? null;
    }
    
    protected function extractPatientState(): ?string
    {
        $patient = $this->fhirResources['patient'] ?? null;
        return $patient['address'][0]['state'] ?? null;
    }
    
    protected function extractPatientZip(): ?string
    {
        $patient = $this->fhirResources['patient'] ?? null;
        return $patient['address'][0]['postalCode'] ?? null;
    }
    
    protected function extractPatientPhone(): ?string
    {
        $patient = $this->fhirResources['patient'] ?? null;
        if (!$patient || !isset($patient['telecom'])) return null;
        
        foreach ($patient['telecom'] as $telecom) {
            if ($telecom['system'] === 'phone') {
                return $this->formatPhoneNumber($telecom['value']);
            }
        }
        
        return null;
    }
    
    protected function extractProviderFax(): ?string
    {
        $practitioner = $this->fhirResources['practitioner'] ?? null;
        if (!$practitioner || !isset($practitioner['telecom'])) return null;
        
        foreach ($practitioner['telecom'] as $telecom) {
            if ($telecom['system'] === 'fax') {
                return $this->formatPhoneNumber($telecom['value']);
            }
        }
        
        return null;
    }
    
    // Facility extraction methods
    protected function extractFacilityName(): ?string
    {
        $org = $this->fhirResources['organization'] ?? null;
        return $org['name'] ?? null;
    }
    
    protected function extractFacilityAddress(): ?string
    {
        $org = $this->fhirResources['organization'] ?? null;
        if (!$org || !isset($org['address'][0])) return null;
        
        $address = $org['address'][0];
        $lines = $address['line'] ?? [];
        
        return implode(', ', $lines);
    }
    
    protected function extractFacilityCity(): ?string
    {
        $org = $this->fhirResources['organization'] ?? null;
        return $org['address'][0]['city'] ?? null;
    }
    
    protected function extractFacilityState(): ?string
    {
        $org = $this->fhirResources['organization'] ?? null;
        return $org['address'][0]['state'] ?? null;
    }
    
    protected function extractFacilityZip(): ?string
    {
        $org = $this->fhirResources['organization'] ?? null;
        return $org['address'][0]['postalCode'] ?? null;
    }
    
    protected function extractFacilityNpi(): ?string
    {
        $org = $this->fhirResources['organization'] ?? null;
        if (!$org || !isset($org['identifier'])) return null;
        
        foreach ($org['identifier'] as $identifier) {
            if ($identifier['system'] === 'http://hl7.org/fhir/sid/us-npi') {
                return $identifier['value'];
            }
        }
        
        return null;
    }
    
    protected function extractFacilityTaxId(): ?string
    {
        return $this->extractIdentifier($this->fhirResources['organization'], 'tax-id');
    }
    
    protected function extractFacilityPtan(): ?string
    {
        return $this->extractIdentifier($this->fhirResources['organization'], 'ptan');
    }
    
    protected function extractFacilityContactName(): ?string
    {
        $org = $this->fhirResources['organization'] ?? null;
        if (!$org || !isset($org['contact'][0])) return null;
        
        return $org['contact'][0]['name']['text'] ?? null;
    }
    
    protected function extractFacilityContactPhone(): ?string
    {
        $org = $this->fhirResources['organization'] ?? null;
        if (!$org || !isset($org['contact'][0]['telecom'])) return null;
        
        foreach ($org['contact'][0]['telecom'] as $telecom) {
            if ($telecom['system'] === 'phone') {
                return $this->formatPhoneNumber($telecom['value']);
            }
        }
        
        return null;
    }
    
    protected function extractFacilityContactFax(): ?string
    {
        $org = $this->fhirResources['organization'] ?? null;
        if (!$org || !isset($org['contact'][0]['telecom'])) return null;
        
        foreach ($org['contact'][0]['telecom'] as $telecom) {
            if ($telecom['system'] === 'fax') {
                return $this->formatPhoneNumber($telecom['value']);
            }
        }
        
        return null;
    }
    
    protected function extractFacilityContactEmail(): ?string
    {
        $org = $this->fhirResources['organization'] ?? null;
        if (!$org || !isset($org['contact'][0]['telecom'])) return null;
        
        foreach ($org['contact'][0]['telecom'] as $telecom) {
            if ($telecom['system'] === 'email') {
                return $telecom['value'];
            }
        }
        
        return null;
    }
    
    // Helper methods
    protected function getQuestionnaireAnswer($questionnaireResponse, $linkId, $type = 'string')
    {
        if (!isset($questionnaireResponse['item'])) return null;
        
        foreach ($questionnaireResponse['item'] as $item) {
            if ($item['linkId'] === $linkId && isset($item['answer'][0])) {
                $answer = $item['answer'][0];
                
                switch ($type) {
                    case 'string':
                        return $answer['valueString'] ?? null;
                    case 'decimal':
                        return $answer['valueDecimal'] ?? null;
                    case 'integer':
                        return $answer['valueInteger'] ?? null;
                    case 'boolean':
                        return $answer['valueBoolean'] ?? null;
                    case 'date':
                        return $answer['valueDate'] ?? null;
                    case 'code':
                        return $answer['valueCoding']['code'] ?? null;
                    case 'display':
                        return $answer['valueCoding']['display'] ?? null;
                }
            }
        }
        
        return null;
    }
    
    protected function formatPhoneNumber(?string $phone): ?string
    {
        if (!$phone) return null;
        
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Format as (XXX) XXX-XXXX
        if (strlen($phone) === 10) {
            return sprintf('(%s) %s-%s', 
                substr($phone, 0, 3),
                substr($phone, 3, 3),
                substr($phone, 6)
            );
        }
        
        return $phone;
    }
    
    protected function isPrimaryCoverage($coverage): bool
    {
        // Check if coverage has a rank or order
        if (isset($coverage['order'])) {
            return $coverage['order'] === 1;
        }
        
        // Check class for primary indicator
        if (isset($coverage['class'])) {
            foreach ($coverage['class'] as $class) {
                if ($class['type']['text'] === 'coverage-type' && 
                    $class['value'] === 'primary') {
                    return true;
                }
            }
        }
        
        // Default to first coverage being primary
        return !isset($this->fhirResources['primary_coverage']);
    }
    
    protected function extractIdentifier($resource, $type): ?string
    {
        if (!$resource || !isset($resource['identifier'])) return null;
        
        foreach ($resource['identifier'] as $identifier) {
            if (Str::contains($identifier['system'] ?? '', $type)) {
                return $identifier['value'];
            }
        }
        
        return null;
    }
    
    /**
     * Apply manufacturer-specific business rules
     */
    protected function applyManufacturerRules(array $data, string $manufacturerKey): array
    {
        switch ($manufacturerKey) {
            case 'BioWound':
                // BioWound requires special California form handling
                if (($data['State'] ?? '') === 'CA' && 
                    ($data['Place of Service'] ?? '') !== 'Hospital Outpatient') {
                    $data['California Non-HOPD'] = 'Yes';
                }
                break;
                
            case 'Amnio_Amp':
                // Calculate total wound size for graft sizing
                if (isset($data['Wound Size L']) && isset($data['Wound Size W'])) {
                    $total = floatval($data['Wound Size L']) * floatval($data['Wound Size W']);
                    $data['Size of Graft Requested'] = $this->determineGraftSize($total);
                }
                break;
                
            case 'AmnioBand':
                // AmnioBand is only for STAT orders
                $data['Request Type'] = 'STAT';
                break;
        }
        
        return $data;
    }
    
    protected function determineGraftSize(float $totalSize): string
    {
        // Standard graft sizes
        if ($totalSize <= 4) return '2x2';
        if ($totalSize <= 8) return '2x4';
        if ($totalSize <= 16) return '4x4';
        if ($totalSize <= 24) return '4x6';
        if ($totalSize <= 32) return '4x8';
        return '8x8'; // Largest standard size
    }
    
    // Default extraction methods for common fields
    protected function extractSalesRepName(): ?string
    {
        return $this->fhirResources['sales_rep']['name'] ?? 'MSC Distribution';
    }
    
    protected function extractSalesRepEmail(): ?string
    {
        return $this->fhirResources['sales_rep']['email'] ?? null;
    }
    
    protected function extractAdditionalEmails(): ?string
    {
        return $this->fhirResources['context']['notification_emails'] ?? null;
    }
    
    protected function extractRequestType(): ?string
    {
        return $this->fhirResources['context']['request_type'] ?? 'New Request';
    }
    
    protected function extractSignature(): ?string
    {
        // For DocuSeal, this would be a signature field tag
        return '{{signature}}';
    }
    
    protected function extractSignatureDate(): ?string
    {
        return Carbon::now()->format('m/d/Y');
    }
    
    // Additional patient extraction methods
    protected function extractPatientGender(): ?string
    {
        $patient = $this->fhirResources['patient'] ?? null;
        if (!$patient || !isset($patient['gender'])) return null;
        
        return ucfirst($patient['gender']);
    }
    
    // Insurance extraction methods
    protected function extractInsuranceName(string $type): ?string
    {
        $coverage = $this->fhirResources["{$type}_coverage"] ?? null;
        if (!$coverage) return null;
        
        return $coverage['payor'][0]['display'] ?? null;
    }
    
    protected function extractPolicyNumber(string $type): ?string
    {
        $coverage = $this->fhirResources["{$type}_coverage"] ?? null;
        if (!$coverage) return null;
        
        return $coverage['subscriberId'] ?? null;
    }
    
    protected function extractPayerPhone(string $type): ?string
    {
        $coverage = $this->fhirResources["{$type}_coverage"] ?? null;
        if (!$coverage) return null;
        
        // This might be in an extension or related Organization resource
        // For now, return from context if available
        return $this->fhirResources['context']["{$type}_payer_phone"] ?? null;
    }
    
    protected function extractSubscriberName(string $type): ?string
    {
        $coverage = $this->fhirResources["{$type}_coverage"] ?? null;
        if (!$coverage) return null;
        
        // If patient is subscriber, use patient name
        if ($coverage['subscriber']['reference'] === $coverage['beneficiary']['reference']) {
            return $this->extractPatientName();
        }
        
        // Otherwise, check if subscriber name is stored
        return $coverage['subscriber']['display'] ?? null;
    }
    
    protected function extractSubscriberDob(string $type): ?string
    {
        $coverage = $this->fhirResources["{$type}_coverage"] ?? null;
        if (!$coverage) return null;
        
        // If patient is subscriber, use patient DOB
        if ($coverage['subscriber']['reference'] === $coverage['beneficiary']['reference']) {
            return $this->extractPatientDob();
        }
        
        // Otherwise, would need to fetch subscriber's Patient resource
        return null;
    }
    
    protected function extractPlanType(string $type): ?string
    {
        $coverage = $this->fhirResources["{$type}_coverage"] ?? null;
        if (!$coverage) return null;
        
        // Look for plan type in class
        if (isset($coverage['class'])) {
            foreach ($coverage['class'] as $class) {
                if ($class['type']['text'] === 'plan') {
                    return $class['value'];
                }
            }
        }
        
        return null;
    }
    
    // Provider extraction methods
    protected function extractProviderName(): ?string
    {
        $practitioner = $this->fhirResources['practitioner'] ?? null;
        if (!$practitioner) return null;
        
        $name = $practitioner['name'][0] ?? null;
        if (!$name) return null;
        
        $prefix = $name['prefix'][0] ?? '';
        $given = implode(' ', $name['given'] ?? []);
        $family = $name['family'] ?? '';
        $suffix = $name['suffix'][0] ?? '';
        
        return trim("{$prefix} {$given} {$family} {$suffix}");
    }
    
    protected function extractProviderNpi(): ?string
    {
        $practitioner = $this->fhirResources['practitioner'] ?? null;
        if (!$practitioner || !isset($practitioner['identifier'])) return null;
        
        foreach ($practitioner['identifier'] as $identifier) {
            if ($identifier['system'] === 'http://hl7.org/fhir/sid/us-npi') {
                return $identifier['value'];
            }
        }
        
        return null;
    }
    
    protected function extractProviderTaxId(): ?string
    {
        return $this->extractIdentifier($this->fhirResources['practitioner'], 'tax-id');
    }
    
    protected function extractProviderPtan(): ?string
    {
        return $this->extractIdentifier($this->fhirResources['practitioner'], 'ptan');
    }
    
    protected function extractProviderMedicaidNumber(): ?string
    {
        return $this->extractIdentifier($this->fhirResources['practitioner'], 'medicaid');
    }
    
    protected function extractProviderSpecialty(): ?string
    {
        $practitioner = $this->fhirResources['practitioner'] ?? null;
        if (!$practitioner || !isset($practitioner['qualification'])) return null;
        
        return $practitioner['qualification'][0]['code']['text'] ?? null;
    }
    
    protected function extractProviderPhone(): ?string
    {
        $practitioner = $this->fhirResources['practitioner'] ?? null;
        if (!$practitioner || !isset($practitioner['telecom'])) return null;
        
        foreach ($practitioner['telecom'] as $telecom) {
            if ($telecom['system'] === 'phone' && $telecom['use'] === 'work') {
                return $this->formatPhoneNumber($telecom['value']);
            }
        }
        
        return null;
    }
    
    // Clinical extraction methods
    protected function extractWoundType(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        return $this->getQuestionnaireAnswer($qr, 'wound-type', 'display');
    }
    
    protected function extractWoundLocation(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        return $this->getQuestionnaireAnswer($qr, 'wound-location', 'string');
    }
    
    protected function extractWoundSizeLength(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        $value = $this->getQuestionnaireAnswer($qr, 'wound-size-length', 'decimal');
        return $value ? number_format($value, 1) : null;
    }
    
    protected function extractWoundSizeWidth(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        $value = $this->getQuestionnaireAnswer($qr, 'wound-size-width', 'decimal');
        return $value ? number_format($value, 1) : null;
    }
    
    protected function extractWoundSizeTotal(): ?string
    {
        $length = $this->extractWoundSizeLength();
        $width = $this->extractWoundSizeWidth();
        
        if ($length && $width) {
            $total = floatval($length) * floatval($width);
            return number_format($total, 1) . ' cm²';
        }
        
        return null;
    }
    
    protected function extractWoundDuration(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        return $this->getQuestionnaireAnswer($qr, 'wound-duration', 'string');
    }
    
    protected function extractDiagnosisCodes(): ?string
    {
        $codes = [];
        
        // From QuestionnaireResponse
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if ($qr) {
            $primary = $this->getQuestionnaireAnswer($qr, 'primary-diagnosis', 'code');
            if ($primary) $codes[] = $primary;
            
            $secondary = $this->getQuestionnaireAnswer($qr, 'secondary-diagnosis', 'code');
            if ($secondary) $codes[] = $secondary;
        }
        
        // From DeviceRequest reason
        $dr = $this->fhirResources['device_request'] ?? null;
        if ($dr && isset($dr['reason'])) {
            foreach ($dr['reason'] as $reason) {
                if (isset($reason['coding'][0]['code'])) {
                    $codes[] = $reason['coding'][0]['code'];
                }
            }
        }
        
        return implode(', ', array_unique($codes));
    }
    
    protected function extractPrimaryDiagnosisCode(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        return $this->getQuestionnaireAnswer($qr, 'primary-diagnosis', 'code');
    }
    
    protected function extractSecondaryDiagnosisCodes(): ?string
    {
        $codes = [];
        
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if ($qr) {
            $secondary = $this->getQuestionnaireAnswer($qr, 'secondary-diagnosis', 'code');
            if ($secondary) $codes[] = $secondary;
            
            $tertiary = $this->getQuestionnaireAnswer($qr, 'tertiary-diagnosis', 'code');
            if ($tertiary) $codes[] = $tertiary;
        }
        
        return implode(', ', $codes);
    }
    
    protected function extractCptCodes(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        $codes = $this->getQuestionnaireAnswer($qr, 'cpt-codes', 'string');
        return $codes ?: '15271, 15272'; // Default CPT codes for skin substitutes
    }
    
    protected function extractPlaceOfService(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        $code = $this->getQuestionnaireAnswer($qr, 'place-of-service', 'code');
        
        // Map common POS codes to descriptions
        $posMap = [
            '11' => 'Office',
            '12' => 'Home',
            '21' => 'Inpatient Hospital',
            '22' => 'Outpatient Hospital',
            '31' => 'Skilled Nursing Facility',
            '32' => 'Nursing Facility'
        ];
        
        return $posMap[$code] ?? $code;
    }
    
    protected function extractSnfStatus(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        $status = $this->getQuestionnaireAnswer($qr, 'snf-status', 'boolean');
        return $status ? 'Yes' : 'No';
    }
    
    protected function extractSnfDays(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        return $this->getQuestionnaireAnswer($qr, 'snf-days', 'integer');
    }
    
    protected function extractHospiceStatus(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        $status = $this->getQuestionnaireAnswer($qr, 'hospice-status', 'boolean');
        return $status ? 'Yes' : 'No';
    }
    
    protected function extractPartAStatus(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        $status = $this->getQuestionnaireAnswer($qr, 'part-a-status', 'boolean');
        return $status ? 'Yes' : 'No';
    }
    
    protected function extractGlobalPeriodStatus(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        $status = $this->getQuestionnaireAnswer($qr, 'global-period-status', 'boolean');
        return $status ? 'Yes' : 'No';
    }
    
    protected function extractGlobalPeriodCptCodes(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        return $this->getQuestionnaireAnswer($qr, 'global-period-cpt', 'string');
    }
    
    protected function extractGlobalPeriodSurgeryDate(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        $date = $this->getQuestionnaireAnswer($qr, 'global-period-surgery-date', 'date');
        return $date ? Carbon::parse($date)->format('m/d/Y') : null;
    }
    
    protected function extractPreviousTreatments(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        return $this->getQuestionnaireAnswer($qr, 'previous-treatments', 'string');
    }
    
    protected function extractComorbidities(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        return $this->getQuestionnaireAnswer($qr, 'comorbidities', 'string');
    }
    
    // Product extraction methods
    protected function extractSelectedProducts(): ?string
    {
        $products = $this->fhirResources['selected_products'] ?? [];
        if (empty($products)) {
            // Try to get from DeviceRequest
            $dr = $this->fhirResources['device_request'] ?? null;
            if ($dr && isset($dr['code']['coding'][0]['display'])) {
                return $dr['code']['coding'][0]['display'];
            }
        }
        
        $productNames = array_map(function($p) {
            return $p['name'] ?? $p['display'] ?? '';
        }, $products);
        
        return implode(', ', array_filter($productNames));
    }
    
    protected function extractProductSizes(): ?string
    {
        $products = $this->fhirResources['selected_products'] ?? [];
        if (empty($products)) {
            // Try to get from DeviceRequest parameters
            $dr = $this->fhirResources['device_request'] ?? null;
            if ($dr && isset($dr['parameter'])) {
                foreach ($dr['parameter'] as $param) {
                    if ($param['code']['text'] === 'size') {
                        return $param['valueString'];
                    }
                }
            }
        }
        
        $sizes = array_map(function($p) {
            return $p['size'] ?? '';
        }, $products);
        
        return implode(', ', array_filter($sizes));
    }
    
    protected function extractAnticipatedTreatmentDate(): ?string
    {
        $dr = $this->fhirResources['device_request'] ?? null;
        if ($dr && isset($dr['occurrenceDateTime'])) {
            return Carbon::parse($dr['occurrenceDateTime'])->format('m/d/Y');
        }
        
        return Carbon::now()->format('m/d/Y');
    }
    
    protected function extractAnticipatedApplications(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        $apps = $this->getQuestionnaireAnswer($qr, 'anticipated-applications', 'integer');
        return $apps ?: '1';
    }
    
    protected function extractPriorAuthPermission(): ?string
    {
        $qr = $this->fhirResources['questionnaire_response'] ?? null;
        if (!$qr) return null;
        
        $permission = $this->getQuestionnaireAnswer($qr, 'prior-auth-permission', 'boolean');
        return $permission ? 'Yes' : 'No';
    }
}

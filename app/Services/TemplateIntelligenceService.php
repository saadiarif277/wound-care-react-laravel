<?php

namespace App\Services;

use App\Services\DocumentIntelligenceService;
use App\Models\CanonicalFieldMapping;
use App\Models\DocusealTemplate;
use App\Models\ManufacturerDocusealMapping;
use App\Models\Order\Manufacturer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * TemplateIntelligenceService
 * 
 * This service bridges the gap between Docuseal templates and our canonical field system
 * using Azure Document Intelligence for automatic field detection and mapping suggestions.
 */
class TemplateIntelligenceService
{
    private DocumentIntelligenceService $documentIntelligence;

    public function __construct(DocumentIntelligenceService $documentIntelligence)
    {
        $this->documentIntelligence = $documentIntelligence;
    }

    /**
     * Analyze template using multiple intelligence methods
     */
    public function analyzeTemplate(array $templateData, array $detailedTemplate): array
    {
        $templateName = $templateData['name'] ?? 'Unknown Template';
        $folderInfo = $templateData['_folder_info'] ?? null;

        $analysis = [
            'manufacturer' => null,
            'document_type' => 'IVR',
            'confidence_score' => 0,
            'analysis_methods' => [],
            'field_mappings' => [],
            'metadata' => []
        ];

        // Method 1: Folder-based analysis (highest confidence)
        if ($folderInfo && !$folderInfo['is_top_level']) {
            $folderAnalysis = $this->analyzeFolderStructure($folderInfo['folder_name']);
            if ($folderAnalysis['manufacturer']) {
                $analysis['manufacturer'] = $folderAnalysis['manufacturer'];
                $analysis['document_type'] = $folderAnalysis['document_type'];
                $analysis['confidence_score'] = 95;
                $analysis['analysis_methods'][] = 'folder_structure';
            }
        }

        // Method 2: Template name pattern analysis
        if (!$analysis['manufacturer'] || $analysis['confidence_score'] < 90) {
            $nameAnalysis = $this->analyzeTemplateName($templateName);
            if ($nameAnalysis['manufacturer'] && $nameAnalysis['confidence_score'] > $analysis['confidence_score']) {
                $analysis['manufacturer'] = $nameAnalysis['manufacturer'];
                $analysis['document_type'] = $nameAnalysis['document_type'];
                $analysis['confidence_score'] = $nameAnalysis['confidence_score'];
                $analysis['analysis_methods'][] = 'template_name_pattern';
            }
        }

        // Method 3: Document Intelligence (if PDF available)
        if ($analysis['confidence_score'] < 85) {
            $documentAnalysis = $this->analyzeTemplateContent($templateData);
            if ($documentAnalysis['manufacturer'] && $documentAnalysis['confidence_score'] > $analysis['confidence_score']) {
                $analysis['manufacturer'] = $documentAnalysis['manufacturer'];
                $analysis['document_type'] = $documentAnalysis['document_type'];
                $analysis['confidence_score'] = $documentAnalysis['confidence_score'];
                $analysis['analysis_methods'][] = 'document_intelligence';
                $analysis['metadata']['content_analysis'] = $documentAnalysis['metadata'];
            }
        }

        // Analyze field mappings
        $analysis['field_mappings'] = $this->analyzeFieldMappings($detailedTemplate, $analysis);

        // Final confidence adjustment based on multiple factors
        $analysis['confidence_score'] = $this->calculateFinalConfidence($analysis);

        return $analysis;
    }

    /**
     * Analyze folder structure for manufacturer and document type
     */
    private function analyzeFolderStructure(string $folderName): array
    {
        $result = [
            'manufacturer' => null,
            'document_type' => 'IVR',
            'confidence_score' => 0
        ];

        // Advanced folder pattern matching
        $folderPatterns = [
            // Manufacturer-specific patterns with document type hints
            '/^ACZ\s*(.*)$/i' => ['manufacturer' => 'ACZ', 'type_hint' => '$1'],
            '/^Advanced Health\s*(.*)$/i' => ['manufacturer' => 'Advanced Health', 'type_hint' => '$1'],
            '/^Amnio\s*Amp.*MSC.*BAA/i' => ['manufacturer' => 'MiMedx', 'document_type' => 'OnboardingForm'],
            '/^AmnioBand\s*(.*)$/i' => ['manufacturer' => 'AmnioBand', 'type_hint' => '$1'],
            '/^BioWound\s*(.*)$/i' => ['manufacturer' => 'BioWound', 'type_hint' => '$1'],
            '/^BioWerX\s*(.*)$/i' => ['manufacturer' => 'BioWerX', 'type_hint' => '$1'],
            '/^Extremity Care\s*(.*)$/i' => ['manufacturer' => 'Extremity Care', 'type_hint' => '$1'],
            '/^SKYE.*Onboarding/i' => ['manufacturer' => 'SKYE', 'document_type' => 'OnboardingForm'],
            '/^SKYE\s*(.*)$/i' => ['manufacturer' => 'SKYE', 'type_hint' => '$1'],
            '/^Total Ancillary\s*(.*)$/i' => ['manufacturer' => 'Total Ancillary', 'type_hint' => '$1'],
            '/^Integra\s*(.*)$/i' => ['manufacturer' => 'Integra', 'type_hint' => '$1'],
            '/^Kerecis\s*(.*)$/i' => ['manufacturer' => 'Kerecis', 'type_hint' => '$1'],
            '/^MiMedx\s*(.*)$/i' => ['manufacturer' => 'MiMedx', 'type_hint' => '$1'],
            '/^Organogenesis\s*(.*)$/i' => ['manufacturer' => 'Organogenesis', 'type_hint' => '$1'],
            '/^Smith.*Nephew\s*(.*)$/i' => ['manufacturer' => 'Smith & Nephew', 'type_hint' => '$1'],
            '/^StimLabs\s*(.*)$/i' => ['manufacturer' => 'StimLabs', 'type_hint' => '$1'],
            '/^Tissue Tech\s*(.*)$/i' => ['manufacturer' => 'Tissue Tech', 'type_hint' => '$1'],
        ];

        foreach ($folderPatterns as $pattern => $info) {
            if (preg_match($pattern, $folderName, $matches)) {
                $result['manufacturer'] = $this->getOrCreateManufacturer($info['manufacturer']);
                $result['confidence_score'] = 95;

                // Determine document type from folder suffix or explicit type
                if (isset($info['document_type'])) {
                    $result['document_type'] = $info['document_type'];
                } else {
                    $typeHint = $matches[1] ?? '';
                    $result['document_type'] = $this->determineDocumentTypeFromHint($typeHint);
                }

                break;
            }
        }

        return $result;
    }

    /**
     * Analyze template name for manufacturer and document type
     */
    private function analyzeTemplateName(string $templateName): array
    {
        $result = [
            'manufacturer' => null,
            'document_type' => 'IVR',
            'confidence_score' => 0
        ];

        // Advanced template name patterns
        $namePatterns = [
            // Manufacturer + Document Type patterns
            '/\b(ACZ|Advanced Clinical Zone)\b.*\b(IVR|Prior Auth|Authorization)\b/i' => 
                ['manufacturer' => 'ACZ', 'document_type' => 'IVR', 'confidence' => 90],
            '/\b(Integra)\b.*\b(IVR|Prior Auth)\b/i' => 
                ['manufacturer' => 'Integra', 'document_type' => 'IVR', 'confidence' => 90],
            '/\b(Kerecis)\b.*\b(IVR|Authorization)\b/i' => 
                ['manufacturer' => 'Kerecis', 'document_type' => 'IVR', 'confidence' => 90],
            '/\b(MiMedx|Amnio\s*Amp)\b.*\b(IVR|Prior Auth)\b/i' => 
                ['manufacturer' => 'MiMedx', 'document_type' => 'IVR', 'confidence' => 90],
            '/\b(Organogenesis)\b.*\b(IVR|Prior Auth)\b/i' => 
                ['manufacturer' => 'Organogenesis', 'document_type' => 'IVR', 'confidence' => 90],
            '/\b(Smith.*Nephew)\b.*\b(IVR|Prior Auth)\b/i' => 
                ['manufacturer' => 'Smith & Nephew', 'document_type' => 'IVR', 'confidence' => 90],
            '/\b(StimLabs|Stim\s*Labs)\b.*\b(IVR|Prior Auth)\b/i' => 
                ['manufacturer' => 'StimLabs', 'document_type' => 'IVR', 'confidence' => 90],
            '/\b(Tissue\s*Tech|TissueTech)\b.*\b(IVR|Prior Auth)\b/i' => 
                ['manufacturer' => 'Tissue Tech', 'document_type' => 'IVR', 'confidence' => 90],

            // Onboarding patterns
            '/\b(.*)\b.*\b(Onboarding|Enrollment|BAA|Agreement)\b/i' => 
                ['manufacturer' => '$1', 'document_type' => 'OnboardingForm', 'confidence' => 85],

            // Order form patterns
            '/\b(.*)\b.*\b(Order|Purchase|Request)\b.*\bForm\b/i' => 
                ['manufacturer' => '$1', 'document_type' => 'OrderForm', 'confidence' => 80],

            // Insurance verification patterns
            '/\b(.*)\b.*\b(Insurance|Verification|Benefits)\b.*\bForm\b/i' => 
                ['manufacturer' => '$1', 'document_type' => 'InsuranceVerification', 'confidence' => 80],

            // General manufacturer patterns (lower confidence)
            '/\b(ACZ|Advanced Clinical Zone)\b/i' => 
                ['manufacturer' => 'ACZ', 'confidence' => 70],
            '/\b(Integra)\b/i' => 
                ['manufacturer' => 'Integra', 'confidence' => 70],
            '/\b(Kerecis)\b/i' => 
                ['manufacturer' => 'Kerecis', 'confidence' => 70],
            '/\b(MiMedx|Amnio\s*Amp)\b/i' => 
                ['manufacturer' => 'MiMedx', 'confidence' => 70],
            '/\b(Organogenesis)\b/i' => 
                ['manufacturer' => 'Organogenesis', 'confidence' => 70],
            '/\b(Smith.*Nephew)\b/i' => 
                ['manufacturer' => 'Smith & Nephew', 'confidence' => 70],
            '/\b(StimLabs|Stim\s*Labs)\b/i' => 
                ['manufacturer' => 'StimLabs', 'confidence' => 70],
            '/\b(Tissue\s*Tech|TissueTech)\b/i' => 
                ['manufacturer' => 'Tissue Tech', 'confidence' => 70],
            '/\b(BioWound|Bio\s*Wound)\b/i' => 
                ['manufacturer' => 'BioWound', 'confidence' => 70],
            '/\b(BioWerX|Bio\s*WerX)\b/i' => 
                ['manufacturer' => 'BioWerX', 'confidence' => 70],
            '/\b(AmnioBand|Amnio\s*Band)\b/i' => 
                ['manufacturer' => 'AmnioBand', 'confidence' => 70],
            '/\b(SKYE|Skye\s*Biologics)\b/i' => 
                ['manufacturer' => 'SKYE', 'confidence' => 70],
            '/\b(Extremity\s*Care)\b/i' => 
                ['manufacturer' => 'Extremity Care', 'confidence' => 70],
            '/\b(Total\s*Ancillary)\b/i' => 
                ['manufacturer' => 'Total Ancillary', 'confidence' => 70],
        ];

        foreach ($namePatterns as $pattern => $info) {
            if (preg_match($pattern, $templateName, $matches)) {
                $manufacturerName = $info['manufacturer'];
                
                // Handle dynamic manufacturer name from capture group
                if ($manufacturerName === '$1' && isset($matches[1])) {
                    $manufacturerName = $this->cleanManufacturerName($matches[1]);
                }

                if ($manufacturerName && $info['confidence'] > $result['confidence_score']) {
                    $result['manufacturer'] = $this->getOrCreateManufacturer($manufacturerName);
                    $result['document_type'] = $info['document_type'] ?? 'IVR';
                    $result['confidence_score'] = $info['confidence'];
                }
            }
        }

        return $result;
    }

    /**
     * Analyze template content using Document Intelligence
     */
    private function analyzeTemplateContent(array $templateData): array
    {
        $result = [
            'manufacturer' => null,
            'document_type' => 'IVR',
            'confidence_score' => 0,
            'metadata' => []
        ];

        try {
            // Try to get template PDF URL or content
            $templateId = $templateData['id'];
            $pdfUrl = $this->getTemplatePdfUrl($templateId);
            
            if (!$pdfUrl) {
                return $result;
            }

            // Use Document Intelligence to analyze the PDF
            $analysisResult = $this->documentIntelligence->extractFilledFormData($pdfUrl);

            if ($analysisResult['success']) {
                $extractedData = $analysisResult['data'] ?? [];
                $headerText = '';
                
                // Extract text from the structured data
                foreach ($extractedData as $key => $value) {
                    if (is_array($value) && isset($value['value'])) {
                        $headerText .= $value['value'] . ' ';
                    }
                }

                // Analyze header for manufacturer and document type
                $contentAnalysis = $this->analyzeExtractedContent($headerText, $extractedData);
                
                if ($contentAnalysis['manufacturer']) {
                    $result['manufacturer'] = $this->getOrCreateManufacturer($contentAnalysis['manufacturer']);
                    $result['document_type'] = $contentAnalysis['document_type'];
                    $result['confidence_score'] = $contentAnalysis['confidence_score'];
                    $result['metadata'] = [
                        'header_text' => $headerText,
                        'detected_fields' => $contentAnalysis['detected_fields'],
                        'analysis_date' => now()->toISOString()
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::warning('Document Intelligence analysis failed', [
                'template_id' => $templateData['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        }

        return $result;
    }

    /**
     * Get PDF URL for template (if available from Docuseal)
     */
    private function getTemplatePdfUrl(string $templateId): ?string
    {
        try {
            // Try to get template PDF URL from Docuseal API
            $response = Http::withHeaders([
                'X-Auth-Token' => config('docuseal.api_key'),
            ])->get(config('docuseal.api_url') . "/templates/{$templateId}/pdf");

            if ($response->successful()) {
                $data = $response->json();
                return $data['pdf_url'] ?? null;
            }

        } catch (\Exception $e) {
            Log::debug('Could not fetch template PDF URL', [
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Extract header text from Document Intelligence results
     */
    private function extractHeaderText(array $lines): string
    {
        // Get first few lines which typically contain header information
        $headerLines = array_slice($lines, 0, 5);
        
        $headerText = '';
        foreach ($headerLines as $line) {
            $headerText .= ($line['content'] ?? '') . ' ';
        }

        return trim($headerText);
    }

    /**
     * Analyze extracted content for manufacturer and document type
     */
    private function analyzeExtractedContent(string $headerText, array $extractedData): array
    {
        $result = [
            'manufacturer' => null,
            'document_type' => 'IVR',
            'confidence_score' => 0,
            'detected_fields' => []
        ];

        // Look for manufacturer mentions in header
        $manufacturerPatterns = [
            '/\b(ACZ|Advanced Clinical Zone)\b/i' => ['name' => 'ACZ', 'confidence' => 85],
            '/\b(Integra)\b/i' => ['name' => 'Integra', 'confidence' => 85],
            '/\b(Kerecis)\b/i' => ['name' => 'Kerecis', 'confidence' => 85],
            '/\b(MiMedx|Amnio\s*Amp)\b/i' => ['name' => 'MiMedx', 'confidence' => 85],
            '/\b(Organogenesis)\b/i' => ['name' => 'Organogenesis', 'confidence' => 85],
            '/\b(Smith.*Nephew)\b/i' => ['name' => 'Smith & Nephew', 'confidence' => 85],
            '/\b(StimLabs|Stim\s*Labs)\b/i' => ['name' => 'StimLabs', 'confidence' => 85],
            '/\b(Tissue\s*Tech|TissueTech)\b/i' => ['name' => 'Tissue Tech', 'confidence' => 85],
            '/\b(BioWound|Bio\s*Wound)\b/i' => ['name' => 'BioWound', 'confidence' => 85],
            '/\b(BioWerX|Bio\s*WerX)\b/i' => ['name' => 'BioWerX', 'confidence' => 85],
            '/\b(AmnioBand|Amnio\s*Band)\b/i' => ['name' => 'AmnioBand', 'confidence' => 85],
            '/\b(SKYE|Skye\s*Biologics)\b/i' => ['name' => 'SKYE', 'confidence' => 85],
            '/\b(Extremity\s*Care)\b/i' => ['name' => 'Extremity Care', 'confidence' => 85],
            '/\b(Total\s*Ancillary)\b/i' => ['name' => 'Total Ancillary', 'confidence' => 85],
        ];

        foreach ($manufacturerPatterns as $pattern => $info) {
            if (preg_match($pattern, $headerText)) {
                $result['manufacturer'] = $info['name'];
                $result['confidence_score'] = $info['confidence'];
                break;
            }
        }

        // Extract all text from the extracted data
        $fullText = $headerText;
        foreach ($extractedData as $key => $value) {
            if (is_array($value) && isset($value['value'])) {
                $fullText .= ' ' . $value['value'];
            }
        }

        // Determine document type from content
        $result['document_type'] = $this->determineDocumentTypeFromContent($fullText);

        // Detect form fields for additional context
        $result['detected_fields'] = $this->detectFormFieldsFromData($extractedData);

        return $result;
    }

    /**
     * Detect form fields from extracted data
     */
    private function detectFormFieldsFromData(array $extractedData): array
    {
        $detectedFields = [];
        
        foreach ($extractedData as $fieldName => $fieldData) {
            if (is_array($fieldData) && isset($fieldData['value'])) {
                $detectedFields[] = [
                    'name' => $fieldName,
                    'type' => $this->guessFieldTypeFromName($fieldName),
                    'value' => $fieldData['value'],
                    'confidence' => $fieldData['confidence'] ?? 80
                ];
            }
        }

        return $detectedFields;
    }

    /**
     * Guess field type from field name
     */
    private function guessFieldTypeFromName(string $fieldName): string
    {
        $lowerName = strtolower($fieldName);
        
        if (strpos($lowerName, 'date') !== false || strpos($lowerName, 'dob') !== false) {
            return 'date';
        }
        if (strpos($lowerName, 'email') !== false) {
            return 'email';
        }
        if (strpos($lowerName, 'phone') !== false || strpos($lowerName, 'tel') !== false) {
            return 'phone';
        }
        if (strpos($lowerName, 'check') !== false || strpos($lowerName, 'agree') !== false) {
            return 'checkbox';
        }
        
        return 'text';
    }

    /**
     * Determine document type from content analysis
     */
    private function determineDocumentTypeFromContent(string $content): string
    {
        $contentLower = strtolower($content);

        // Document type indicators with priority
        $typePatterns = [
            'OnboardingForm' => ['onboarding', 'enrollment', 'agreement', 'baa', 'business associate'],
            'IVR' => ['prior auth', 'authorization', 'ivr', 'insurance verification', 'benefits verification'],
            'OrderForm' => ['order form', 'purchase order', 'product request'],
            'InsuranceVerification' => ['insurance verification', 'benefits verification', 'eligibility']
        ];

        foreach ($typePatterns as $docType => $patterns) {
            foreach ($patterns as $pattern) {
                if (strpos($contentLower, $pattern) !== false) {
                    return $docType;
                }
            }
        }

        return 'IVR'; // Default
    }

    /**
     * Detect form fields from extracted content
     */
    private function detectFormFields(array $lines): array
    {
        $detectedFields = [];
        
        foreach ($lines as $line) {
            $content = $line['content'] ?? '';
            
            // Look for common form field patterns
            if (preg_match('/^(.+?):\s*_+\s*$/', $content, $matches)) {
                $fieldName = trim($matches[1]);
                $detectedFields[] = [
                    'name' => $fieldName,
                    'type' => 'text',
                    'confidence' => 90
                ];
            }
            
            // Look for checkbox patterns
            if (preg_match('/☐|□|\[\s*\]/', $content)) {
                $detectedFields[] = [
                    'name' => $content,
                    'type' => 'checkbox',
                    'confidence' => 85
                ];
            }
        }

        return $detectedFields;
    }

    /**
     * Analyze field mappings with intelligence
     */
    private function analyzeFieldMappings(array $detailedTemplate, array $analysis): array
    {
        $fieldMappings = [];
        $fields = $detailedTemplate['fields'] ?? $detailedTemplate['schema'] ?? [];

        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? $field['id'] ?? null;
            if (!$fieldName) continue;

            $mapping = $this->createIntelligentFieldMapping($fieldName, $field, $analysis);
            $fieldMappings[$fieldName] = $mapping;
        }

        return $fieldMappings;
    }

    /**
     * Create intelligent field mapping
     */
    private function createIntelligentFieldMapping(string $fieldName, array $field, array $analysis): array
    {
        return [
            'docuseal_field_name' => $fieldName,
            'field_type' => $field['type'] ?? 'text',
            'required' => $field['required'] ?? false,
            'local_field' => $this->mapToLocalField($fieldName),
            'system_field' => $this->mapToSystemField($fieldName),
            'data_type' => $this->determineDataType($field),
            'validation_rules' => $this->extractValidationRules($field),
            'default_value' => $field['default'] ?? null,
            'manufacturer_context' => $analysis['manufacturer']?->name,
            'document_type_context' => $analysis['document_type'],
            'mapping_confidence' => $this->calculateMappingConfidence($fieldName, $field),
            'extracted_at' => now()->toISOString()
        ];
    }

    /**
     * Helper methods
     */
    private function determineDocumentTypeFromHint(string $hint): string
    {
        $hintLower = strtolower($hint);
        
        if (strpos($hintLower, 'onboarding') !== false) return 'OnboardingForm';
        if (strpos($hintLower, 'order') !== false) return 'OrderForm';
        if (strpos($hintLower, 'insurance') !== false) return 'InsuranceVerification';
        
        return 'IVR';
    }

    private function cleanManufacturerName(string $name): string
    {
        // Clean up manufacturer name extracted from patterns
        $cleaned = trim($name);
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        
        // Handle common variations
        $variations = [
            'Smith Nephew' => 'Smith & Nephew',
            'Stim Labs' => 'StimLabs',
            'Tissue Tech' => 'Tissue Tech',
            'Bio Wound' => 'BioWound',
            'Bio WerX' => 'BioWerX',
            'Amnio Band' => 'AmnioBand',
        ];

        return $variations[$cleaned] ?? $cleaned;
    }

    private function getOrCreateManufacturer(string $name): Manufacturer
    {
        return Manufacturer::firstOrCreate(
            ['name' => $name],
            [
                'is_active' => true,
                'contact_email' => config("manufacturers.email_recipients.{$name}.0")
            ]
        );
    }

    private function mapToLocalField(string $fieldName): string
    {
        // Enhanced field mapping logic
        $fieldMappings = [
            // Patient information
            'PATIENT NAME' => 'patientInfo.patientName',
            'PATIENT DOB' => 'patientInfo.dateOfBirth',
            'DOB' => 'patientInfo.dateOfBirth',
            'PATIENT ID' => 'patientInfo.patientId',
            'MEMBER ID' => 'patientInfo.memberId',
            'MRN' => 'patientInfo.medicalRecordNumber',
            
            // Insurance information
            'PRIMARY INSURANCE' => 'insuranceInfo.primaryInsurance.name',
            'INSURANCE NAME' => 'insuranceInfo.primaryInsurance.name',
            'GROUP NUMBER' => 'insuranceInfo.primaryInsurance.groupNumber',
            'POLICY NUMBER' => 'insuranceInfo.primaryInsurance.policyNumber',
            'PAYER PHONE' => 'insuranceInfo.primaryInsurance.payerPhone',
            'SUBSCRIBER NAME' => 'insuranceInfo.primaryInsurance.subscriberName',
            
            // Provider information
            'PHYSICIAN NAME' => 'providerInfo.providerName',
            'PROVIDER NAME' => 'providerInfo.providerName',
            'DOCTOR NAME' => 'providerInfo.providerName',
            'NPI' => 'providerInfo.providerNPI',
            'TAX ID' => 'providerInfo.taxId',
            'PROVIDER NPI' => 'providerInfo.providerNPI',
            'PHYSICIAN NPI' => 'providerInfo.providerNPI',
            
            // Facility information
            'FACILITY NAME' => 'facilityInfo.facilityName',
            'PRACTICE NAME' => 'facilityInfo.facilityName',
            'CLINIC NAME' => 'facilityInfo.facilityName',
            'FACILITY ADDRESS' => 'facilityInfo.facilityAddress',
            'FACILITY PHONE' => 'facilityInfo.facilityPhone',
            'FACILITY NPI' => 'facilityInfo.facilityNPI',
            
            // Sales representative
            'REPRESENTATIVE NAME' => 'requestInfo.salesRepName',
            'SALES REP' => 'requestInfo.salesRepName',
            'REP NAME' => 'requestInfo.salesRepName',
            
            // Clinical information
            'DIAGNOSIS' => 'clinicalInfo.primaryDiagnosis',
            'ICD-10' => 'clinicalInfo.icd10Codes',
            'CPT CODE' => 'clinicalInfo.cptCodes',
            'PRODUCT' => 'clinicalInfo.requestedProduct',
            'WOUND SIZE' => 'clinicalInfo.woundSize',
        ];

        $upperFieldName = strtoupper(trim($fieldName));
        return $fieldMappings[$upperFieldName] ?? $fieldName;
    }

    private function mapToSystemField(string $fieldName): string
    {
        // Enhanced system field mapping
        $systemMappings = [
            'PATIENT NAME' => 'patient_name',
            'PATIENT DOB' => 'patient_dob',
            'DOB' => 'patient_dob',
            'MEMBER ID' => 'patient_member_id',
            'MRN' => 'patient_mrn',
            'PRIMARY INSURANCE' => 'payer_name',
            'INSURANCE NAME' => 'payer_name',
            'GROUP NUMBER' => 'group_number',
            'POLICY NUMBER' => 'policy_number',
            'PHYSICIAN NAME' => 'provider_name',
            'PROVIDER NAME' => 'provider_name',
            'NPI' => 'provider_npi',
            'TAX ID' => 'provider_tax_id',
            'FACILITY NAME' => 'facility_name',
            'REPRESENTATIVE NAME' => 'sales_rep_name',
            'SALES REP' => 'sales_rep_name',
            'DIAGNOSIS' => 'primary_diagnosis',
            'ICD-10' => 'icd10_codes',
            'CPT CODE' => 'cpt_codes',
            'PRODUCT' => 'requested_product',
        ];

        $upperFieldName = strtoupper(trim($fieldName));
        return $systemMappings[$upperFieldName] ?? Str::snake($fieldName);
    }

    private function determineDataType(array $field): string
    {
        $fieldType = $field['type'] ?? 'text';
        
        $typeMapping = [
            'date' => 'date',
            'number' => 'number',
            'email' => 'email',
            'phone' => 'phone',
            'tel' => 'phone',
            'checkbox' => 'boolean',
            'select' => 'select',
            'dropdown' => 'select',
            'text' => 'string',
            'textarea' => 'text',
            'signature' => 'signature'
        ];

        return $typeMapping[$fieldType] ?? 'string';
    }

    private function extractValidationRules(array $field): array
    {
        $rules = [];

        if ($field['required'] ?? false) {
            $rules[] = 'required';
        }

        if (isset($field['maxlength']) || isset($field['max_length'])) {
            $maxLength = $field['maxlength'] ?? $field['max_length'];
            $rules[] = 'max:' . $maxLength;
        }

        if (isset($field['minlength']) || isset($field['min_length'])) {
            $minLength = $field['minlength'] ?? $field['min_length'];
            $rules[] = 'min:' . $minLength;
        }

        $fieldType = $field['type'] ?? 'text';
        if ($fieldType === 'email') {
            $rules[] = 'email';
        } elseif ($fieldType === 'date') {
            $rules[] = 'date';
        } elseif ($fieldType === 'number') {
            $rules[] = 'numeric';
        } elseif ($fieldType === 'phone' || $fieldType === 'tel') {
            $rules[] = 'regex:/^[0-9\-\(\)\+\s]+$/';
        }

        return $rules;
    }

    private function calculateMappingConfidence(string $fieldName, array $field): int
    {
        $confidence = 50; // Base confidence
        
        // Higher confidence for exact matches
        $exactMatches = [
            'PATIENT NAME', 'PATIENT DOB', 'NPI', 'MEMBER ID', 'INSURANCE NAME',
            'PROVIDER NAME', 'FACILITY NAME', 'SALES REP', 'TAX ID'
        ];
        
        if (in_array(strtoupper($fieldName), $exactMatches)) {
            $confidence += 40;
        }
        
        // Partial matches get medium confidence
        $partialMatches = ['PATIENT', 'PROVIDER', 'INSURANCE', 'FACILITY', 'REP'];
        foreach ($partialMatches as $match) {
            if (strpos(strtoupper($fieldName), $match) !== false) {
                $confidence += 20;
                break;
            }
        }
        
        // Required fields get higher confidence
        if ($field['required'] ?? false) {
            $confidence += 10;
        }
        
        return min($confidence, 100);
    }

    private function calculateFinalConfidence(array $analysis): int
    {
        $baseConfidence = $analysis['confidence_score'];
        
        // Adjust based on analysis methods used
        $methodBonus = 0;
        if (in_array('folder_structure', $analysis['analysis_methods'])) {
            $methodBonus += 10;
        }
        if (in_array('template_name_pattern', $analysis['analysis_methods'])) {
            $methodBonus += 5;
        }
        if (in_array('document_intelligence', $analysis['analysis_methods'])) {
            $methodBonus += 15;
        }
        
        // Adjust based on field mapping quality
        $fieldMappings = $analysis['field_mappings'] ?? [];
        if (!empty($fieldMappings)) {
            $avgMappingConfidence = array_sum(array_column($fieldMappings, 'mapping_confidence')) / count($fieldMappings);
            $methodBonus += ($avgMappingConfidence > 70) ? 5 : 0;
        }
        
        return min($baseConfidence + $methodBonus, 100);
    }
}

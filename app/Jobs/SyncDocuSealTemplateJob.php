<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Docuseal\DocusealTemplate;
use App\Models\Order\Manufacturer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncDocuSealTemplateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $templateData;
    protected array $detailedTemplate;
    protected ?Manufacturer $manufacturer;
    protected string $documentType;

    /**
     * Create a new job instance.
     */
    public function __construct(
        array $templateData,
        array $detailedTemplate,
        ?Manufacturer $manufacturer,
        string $documentType
    ) {
        $this->templateData = $templateData;
        $this->detailedTemplate = $detailedTemplate;
        $this->manufacturer = $manufacturer;
        $this->documentType = $documentType;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $templateId = $this->templateData['id'];
            $templateName = $this->templateData['name'];

            // Extract field mappings from template structure
            $fieldMappings = $this->extractFieldMappings($this->detailedTemplate);

            // Create or update template record
            $template = DocusealTemplate::updateOrCreate(
                ['docuseal_template_id' => $templateId],
                [
                    'template_name' => $templateName,
                    'manufacturer_id' => $this->manufacturer?->id,
                    'document_type' => $this->documentType,
                    'is_default' => $this->isDefaultTemplate($templateName, $this->manufacturer, $this->documentType),
                    'field_mappings' => $fieldMappings,
                    'is_active' => true,
                    'extraction_metadata' => [
                        'docuseal_created_at' => $this->templateData['created_at'] ?? null,
                        'docuseal_updated_at' => $this->templateData['updated_at'] ?? null,
                        'total_fields' => count($fieldMappings),
                        'sync_date' => now()->toISOString(),
                        'sync_version' => '1.0',
                        'processed_via_queue' => true
                    ],
                    'field_discovery_status' => 'completed',
                    'last_extracted_at' => now()
                ]
            );

            Log::info('DocuSeal template synced via queue', [
                'template_id' => $templateId,
                'template_name' => $templateName,
                'manufacturer' => $this->manufacturer?->name,
                'document_type' => $this->documentType,
                'field_count' => count($fieldMappings),
                'job_id' => $this->job->getJobId()
            ]);

        } catch (\Exception $e) {
            Log::error('DocuSeal template sync job failed', [
                'template_id' => $this->templateData['id'] ?? 'unknown',
                'template_name' => $this->templateData['name'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job->getJobId()
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Extract field mappings from DocuSeal template structure
     */
    private function extractFieldMappings(array $detailedTemplate): array
    {
        $fieldMappings = [];

        // Extract fields from template schema
        $fields = $detailedTemplate['fields'] ?? $detailedTemplate['schema'] ?? [];

        foreach ($fields as $field) {
            $fieldName = $field['name'] ?? $field['id'] ?? null;
            if (!$fieldName) continue;

            $fieldMappings[$fieldName] = [
                'docuseal_field_name' => $fieldName,
                'field_type' => $field['type'] ?? 'text',
                'required' => $field['required'] ?? false,
                'local_field' => $this->mapToLocalField($fieldName),
                'system_field' => $this->mapToSystemField($fieldName),
                'data_type' => $this->determineDataType($field),
                'validation_rules' => $this->extractValidationRules($field),
                'default_value' => $field['default'] ?? null,
                'extracted_at' => now()->toISOString()
            ];
        }

        return $fieldMappings;
    }

    /**
     * Map DocuSeal field name to local system field
     */
    private function mapToLocalField(string $docusealFieldName): string
    {
        $fieldMappings = [
            // Patient fields
            'PATIENT NAME' => 'patientInfo.patientName',
            'PATIENT DOB' => 'patientInfo.dateOfBirth',
            'PATIENT ID' => 'patientInfo.patientId',
            'MEMBER ID' => 'patientInfo.memberId',
            
            // Insurance fields
            'PRIMARY INSURANCE' => 'insuranceInfo.primaryInsurance.name',
            'INSURANCE NAME' => 'insuranceInfo.primaryInsurance.name',
            'GROUP NUMBER' => 'insuranceInfo.primaryInsurance.groupNumber',
            'PAYER PHONE' => 'insuranceInfo.primaryInsurance.payerPhone',
            
            // Provider fields
            'PHYSICIAN NAME' => 'providerInfo.providerName',
            'PROVIDER NAME' => 'providerInfo.providerName',
            'NPI' => 'providerInfo.providerNPI',
            'TAX ID' => 'providerInfo.taxId',
            
            // Facility fields
            'FACILITY NAME' => 'facilityInfo.facilityName',
            'FACILITY ADDRESS' => 'facilityInfo.facilityAddress',
            
            // Sales rep fields
            'REPRESENTATIVE NAME' => 'requestInfo.salesRepName',
            'SALES REP' => 'requestInfo.salesRepName',
        ];

        $upperFieldName = strtoupper($docusealFieldName);
        return $fieldMappings[$upperFieldName] ?? $docusealFieldName;
    }

    /**
     * Map to system field path for QuickRequest integration
     */
    private function mapToSystemField(string $docusealFieldName): string
    {
        // This maps to the actual data structure from QuickRequest
        $systemMappings = [
            'PATIENT NAME' => 'patient_name',
            'PATIENT DOB' => 'patient_dob',
            'MEMBER ID' => 'patient_member_id',
            'PRIMARY INSURANCE' => 'payer_name',
            'GROUP NUMBER' => 'group_number',
            'PHYSICIAN NAME' => 'provider_name',
            'NPI' => 'provider_npi',
            'FACILITY NAME' => 'facility_name',
            'REPRESENTATIVE NAME' => 'sales_rep_name',
        ];

        $upperFieldName = strtoupper($docusealFieldName);
        return $systemMappings[$upperFieldName] ?? Str::snake($docusealFieldName);
    }

    /**
     * Determine data type from field structure
     */
    private function determineDataType(array $field): string
    {
        $fieldType = $field['type'] ?? 'text';
        
        $typeMapping = [
            'date' => 'date',
            'number' => 'number',
            'email' => 'email',
            'phone' => 'phone',
            'checkbox' => 'boolean',
            'select' => 'select',
            'text' => 'string',
            'textarea' => 'text'
        ];

        return $typeMapping[$fieldType] ?? 'string';
    }

    /**
     * Extract validation rules from field
     */
    private function extractValidationRules(array $field): array
    {
        $rules = [];

        if ($field['required'] ?? false) {
            $rules[] = 'required';
        }

        if (isset($field['maxlength'])) {
            $rules[] = 'max:' . $field['maxlength'];
        }

        if (isset($field['minlength'])) {
            $rules[] = 'min:' . $field['minlength'];
        }

        $fieldType = $field['type'] ?? 'text';
        if ($fieldType === 'email') {
            $rules[] = 'email';
        } elseif ($fieldType === 'date') {
            $rules[] = 'date';
        } elseif ($fieldType === 'number') {
            $rules[] = 'numeric';
        }

        return $rules;
    }

    /**
     * Determine if this should be the default template for this manufacturer/type
     */
    private function isDefaultTemplate(string $templateName, ?Manufacturer $manufacturer, string $documentType): bool
    {
        if (!$manufacturer) {
            return false;
        }

        // Check if there's already a default template for this manufacturer/type
        $existingDefault = DocusealTemplate::where('manufacturer_id', $manufacturer->id)
            ->where('document_type', $documentType)
            ->where('is_default', true)
            ->exists();

        // If no default exists, make this one the default
        return !$existingDefault;
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('DocuSeal template sync job failed permanently', [
            'template_id' => $this->templateData['id'] ?? 'unknown',
            'template_name' => $this->templateData['name'] ?? 'unknown',
            'manufacturer' => $this->manufacturer?->name,
            'document_type' => $this->documentType,
            'exception' => $exception->getMessage(),
            'job_id' => $this->job->getJobId()
        ]);
    }
}

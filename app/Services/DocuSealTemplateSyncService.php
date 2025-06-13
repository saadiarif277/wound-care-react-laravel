<?php

namespace App\Services;

use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Support\Facades\Log;
use Exception;
use Docuseal\Api as DocusealApi;
use Illuminate\Support\Facades\DB;
use App\Services\IvrFieldMappingService;
use App\Services\DocuSealFieldFormatterService;

class DocuSealTemplateSyncService
{
    private IvrFieldMappingService $ivrFieldMappingService;
    private DocuSealFieldFormatterService $fieldFormatter;

    public function __construct(IvrFieldMappingService $ivrFieldMappingService, DocuSealFieldFormatterService $fieldFormatter)
    {
        $this->ivrFieldMappingService = $ivrFieldMappingService;
        $this->fieldFormatter = $fieldFormatter;
    }

    /**
     * Pull templates from DocuSeal API and update local database.
     *
     * @return array List of templates pulled and updated
     */
    public function pullTemplatesFromDocuSeal(): array
    {
        $apiKey = config('services.docuseal.api_key');
        $apiUrl = config('services.docuseal.api_url', 'https://api.docuseal.com');
        $docusealApi = new DocusealApi($apiKey, $apiUrl);

        $results = [];
        $manufacturerConfigs = config('ivr-field-mappings.manufacturers');
        try {
            $response = $docusealApi->listTemplates(['limit' => 100]);
            $templates = $response['data'] ?? [];

            foreach ($templates as $tpl) {
                $type = $tpl['preferences']['type'] ?? null;
                $folderId = (string)($tpl['folder_id'] ?? '');
                $isIvr = false;
                // Heuristic: IVR if type is 'IVR' or template name contains 'IVR'
                if (strtolower($type) === 'ivr' || str_contains(strtolower($tpl['name'] ?? ''), 'ivr')) {
                    $isIvr = true;
                }

                // Find manufacturer key by folder_id
                $manufacturerKey = null;
                foreach ($manufacturerConfigs as $key => $cfg) {
                    if ((string)($cfg['folder_id'] ?? '') === $folderId) {
                        $manufacturerKey = $key;
                        break;
                    }
                }

                $fieldMappings = $tpl['fields'] ?? [];
                $mappedFields = [];
                $fieldTypes = [];

                if ($isIvr && $manufacturerKey) {
                    foreach ($fieldMappings as $field) {
                        $fieldName = $field['name'] ?? '';
                        $fieldType = $this->fieldFormatter->detectFieldType($fieldName);
                        $fieldTypes[$fieldName] = $fieldType;
                        $localField = $this->ivrFieldMappingService->getManufacturerConfig($manufacturerKey)['field_mappings'][$fieldName] ?? null;
                        $mappedFields[$fieldName] = [
                            'local_field' => $localField,
                            'type' => $fieldType,
                        ];
                    }
                } else {
                    // TODO: Add order/onboarding mapping when ready
                }

                $template = DocusealTemplate::updateOrCreate(
                    [
                        'docuseal_template_id' => $tpl['id'],
                    ],
                    [
                        'template_name' => $tpl['name'],
                        'document_type' => $type,
                        'manufacturer_id' => $manufacturerKey, // Store manufacturer key for reference
                        'is_default' => false, // Set based on your logic
                        'is_active' => empty($tpl['archived_at']),
                        'field_mappings' => $mappedFields,
                    ]
                );
                $results[] = $template;
            }
        } catch (\Exception $e) {
            Log::error('Failed to pull templates from DocuSeal', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
        return $results;
    }

    /**
     * Push a local template to DocuSeal (create or update).
     *
     * @param DocusealTemplate $template
     * @return array DocuSeal API response
     */
    public function pushTemplateToDocuSeal(DocusealTemplate $template): array
    {
        // TODO: Implement DocuSeal API call to create/update template
        return [];
    }

    /**
     * Analyze a template PDF with Azure Document Intelligence to extract field schema.
     *
     * @param string $pdfPath Path to the template PDF
     * @return array Extracted field schema
     */
    public function analyzeTemplateWithADI(string $pdfPath): array
    {
        // TODO: Implement ADI API call and field extraction
        return [];
    }

    /**
     * Suggest field mappings between ADI-extracted fields and local model fields.
     *
     * @param array $adiFields
     * @param array $localFields
     * @return array Suggested mappings
     */
    public function suggestFieldMappings(array $adiFields, array $localFields): array
    {
        // TODO: Implement mapping suggestion logic (AI/heuristics)
        return [];
    }
}

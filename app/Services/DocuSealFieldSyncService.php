<?php

namespace App\Services;

use App\Models\Docuseal\DocusealTemplate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DocuSealFieldSyncService
{
    private string $apiKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.docuseal.api_key');
        $this->apiUrl = config('services.docuseal.api_url');
    }

    /**
     * Fetch template fields from DocuSeal API
     */
    public function fetchTemplateFields(string $docusealTemplateId): array
    {
        try {
            $response = Http::withHeaders([
                'X-Auth-Token' => $this->apiKey,
                'Accept' => 'application/json',
            ])->get("{$this->apiUrl}/templates/{$docusealTemplateId}");

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch template from DocuSeal: ' . $response->body());
            }

            $template = $response->json();
            
            // Extract fields from DocuSeal template
            $fields = [];
            
            // DocuSeal templates have schema/fields that define the form fields
            if (isset($template['schema'])) {
                foreach ($template['schema'] as $schemaItem) {
                    if (isset($schemaItem['fields']) && is_array($schemaItem['fields'])) {
                        foreach ($schemaItem['fields'] as $field) {
                            $fields[] = [
                                'name' => $field['name'] ?? '',
                                'type' => $field['type'] ?? 'text',
                                'label' => $field['label'] ?? $field['name'] ?? '',
                                'required' => $field['required'] ?? false,
                                'options' => $field['options'] ?? null,
                                'description' => $field['description'] ?? '',
                                'default_value' => $field['default_value'] ?? null,
                            ];
                        }
                    }
                }
            }
            
            // Alternative: Fields might be in a different structure
            if (empty($fields) && isset($template['fields'])) {
                foreach ($template['fields'] as $field) {
                    $fields[] = [
                        'name' => $field['name'] ?? '',
                        'type' => $field['type'] ?? 'text',
                        'label' => $field['label'] ?? $field['name'] ?? '',
                        'required' => $field['required'] ?? false,
                        'options' => $field['options'] ?? null,
                        'description' => $field['description'] ?? '',
                        'default_value' => $field['default_value'] ?? null,
                    ];
                }
            }

            // Also check for submission fields (form fields)
            if (empty($fields) && isset($template['submitters'])) {
                foreach ($template['submitters'] as $submitter) {
                    if (isset($submitter['fields']) && is_array($submitter['fields'])) {
                        foreach ($submitter['fields'] as $fieldData) {
                            $fields[] = [
                                'name' => $fieldData['name'] ?? '',
                                'type' => $fieldData['type'] ?? 'text',
                                'label' => $fieldData['label'] ?? $fieldData['name'] ?? '',
                                'required' => $fieldData['required'] ?? false,
                                'options' => $fieldData['options'] ?? null,
                                'description' => $fieldData['description'] ?? '',
                                'default_value' => $fieldData['default_value'] ?? null,
                            ];
                        }
                    }
                }
            }

            return [
                'template_id' => $template['id'] ?? $docusealTemplateId,
                'template_name' => $template['name'] ?? '',
                'fields' => $fields,
                'raw_template' => $template, // For debugging
            ];

        } catch (\Exception $e) {
            Log::error('Failed to fetch DocuSeal template fields', [
                'template_id' => $docusealTemplateId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Sync DocuSeal fields with our local template
     */
    public function syncTemplateFields(DocusealTemplate $template): array
    {
        try {
            // Fetch fields from DocuSeal
            $docusealData = $this->fetchTemplateFields($template->docuseal_template_id);
            
            // Build field mappings based on DocuSeal fields
            $fieldMappings = $template->field_mappings ?? [];
            $newFields = [];
            $updatedFields = [];

            foreach ($docusealData['fields'] as $field) {
                $fieldName = $field['name'];
                
                // Check if we already have a mapping for this field
                if (!isset($fieldMappings[$fieldName])) {
                    // Try to auto-map based on field name
                    $suggestedMapping = $this->suggestMapping($fieldName, $field['type']);
                    
                    $fieldMappings[$fieldName] = [
                        'docuseal_type' => $field['type'],
                        'docuseal_label' => $field['label'],
                        'required' => $field['required'],
                        'local_field' => $suggestedMapping['field'] ?? null,
                        'mapping_confidence' => $suggestedMapping['confidence'] ?? 0,
                        'auto_mapped' => !empty($suggestedMapping['field']),
                        'synced_at' => now()->toIso8601String(),
                    ];
                    
                    $newFields[] = $fieldName;
                } else {
                    // Update field metadata
                    $fieldMappings[$fieldName]['docuseal_type'] = $field['type'];
                    $fieldMappings[$fieldName]['docuseal_label'] = $field['label'];
                    $fieldMappings[$fieldName]['required'] = $field['required'];
                    $fieldMappings[$fieldName]['last_synced'] = now()->toIso8601String();
                    
                    $updatedFields[] = $fieldName;
                }
            }

            // Update template
            $template->update([
                'field_mappings' => $fieldMappings,
                'extraction_metadata' => array_merge($template->extraction_metadata ?? [], [
                    'last_docuseal_sync' => now()->toIso8601String(),
                    'docuseal_field_count' => count($docusealData['fields']),
                    'new_fields' => count($newFields),
                    'updated_fields' => count($updatedFields),
                ])
            ]);

            return [
                'success' => true,
                'fields' => $docusealData['fields'],
                'new_fields' => $newFields,
                'updated_fields' => $updatedFields,
                'total_fields' => count($docusealData['fields']),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to sync DocuSeal template fields', [
                'template_id' => $template->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Suggest mapping based on DocuSeal field name
     */
    private function suggestMapping(string $fieldName, string $fieldType): array
    {
        // Common DocuSeal to system field mappings
        $directMappings = [
            'physician_name' => 'provider_name',
            'provider_name' => 'provider_name',
            'doctor_name' => 'provider_name',
            'physician_npi' => 'provider_npi',
            'provider_npi' => 'provider_npi',
            'npi' => 'provider_npi',
            'tax_id' => 'provider_tax_id',
            'tin' => 'provider_tax_id',
            'facility_name' => 'facility_name',
            'facility_npi' => 'facility_npi',
            'patient_name' => 'patient_name',
            'patient_dob' => 'patient_dob',
            'date_of_birth' => 'patient_dob',
            'primary_insurance' => 'primary_insurance_name',
            'insurance_name' => 'primary_insurance_name',
            'policy_number' => 'primary_policy_number',
            'member_id' => 'primary_policy_number',
            'wound_location' => 'wound_location',
            'wound_size' => 'wound_size_total',
            'icd_10' => 'diagnosis_codes',
            'diagnosis_codes' => 'diagnosis_codes',
            'cpt_codes' => 'application_cpt_codes',
        ];

        // Normalize field name for matching
        $normalized = strtolower(str_replace(['-', '_', ' '], '', $fieldName));
        
        foreach ($directMappings as $pattern => $systemField) {
            $normalizedPattern = strtolower(str_replace(['-', '_', ' '], '', $pattern));
            if ($normalized === $normalizedPattern) {
                return [
                    'field' => $systemField,
                    'confidence' => 0.95,
                    'type' => 'direct_match'
                ];
            }
        }

        // Partial matching
        foreach ($directMappings as $pattern => $systemField) {
            if (stripos($fieldName, $pattern) !== false) {
                return [
                    'field' => $systemField,
                    'confidence' => 0.7,
                    'type' => 'partial_match'
                ];
            }
        }

        return [
            'field' => null,
            'confidence' => 0,
            'type' => 'no_match'
        ];
    }
}
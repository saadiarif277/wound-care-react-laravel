<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class DocuSealFieldMapper
{
    /**
     * Turn an associative map into DocuSeal's expected [ {name, value}, … ].
     *
     * @param array<string,mixed> $mappedFields  e.g. ['amnio_amp_size'=>'4x4', …]
     * @return array<array{name:string,value:mixed}>
     */
    public static function toDocuSealFields(array $mappedFields): array
    {
        return array_map(
            function(string $name) use ($mappedFields) {
                return [
                    'name'  => $name,
                    'value' => $mappedFields[$name],
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

        // Insurance fields
        $insuranceFields = self::mapJsonToDocuSealFields(
            "{$basePath}/insurance_form_mappings.json",
            ['insuranceInformation', 'primaryInsurance'],
            $formId
        );

        // Order fields
        $orderFields = self::mapJsonToDocuSealFields(
            "{$basePath}/order-form-mappings.json",
            ['orderFormFieldMappings', 'standardFields', 'orderInformation'],
            $formId
        );

        // Physician fields
        $physicianFields = self::mapJsonToDocuSealFields(
            "{$basePath}/insurance_form_mappings.json",
            ['standardFieldMappings', 'physicianInformation'],
            $formId
        );

        // Patient fields
        $patientFields = self::mapJsonToDocuSealFields(
            "{$basePath}/insurance_form_mappings.json",
            ['standardFieldMappings', 'patientInformation'],
            $formId
        );

        // Merge all field mappings
        $allMappings = array_merge(
            $insuranceFields,
            $orderFields,
            $physicianFields,
            $patientFields
        );

        // Now map the input data to the correct field names
        $fieldMap = [];
        foreach ($allMappings as $field) {
            if ($field['value'] !== null) {
                $fieldMap[$field['name']] = $field['value'];
            }
        }

        // Map input data to DocuSeal fields
        foreach ($inputData as $key => $value) {
            // Direct mapping if field exists
            if (isset($fieldMap[$key])) {
                $mappedFields[] = [
                    'name' => $key,
                    'value' => $value
                ];
            }
            // Try to find a matching label
            else {
                foreach ($fieldMap as $fieldName => $label) {
                    if (stripos($label, $key) !== false || stripos($key, $fieldName) !== false) {
                        $mappedFields[] = [
                            'name' => $fieldName,
                            'value' => $value
                        ];
                        break;
                    }
                }
            }
        }

        // Special handling for MedLife specific fields
        if (in_array($manufacturerName, ['MedLife', 'MedLife Solutions'])) {
            // Add amnio_amp_size if present
            if (isset($inputData['amnio_amp_size'])) {
                $mappedFields[] = [
                    'name' => 'amnio_amp_size',
                    'value' => $inputData['amnio_amp_size']
                ];
            }
        }

        Log::info('DocuSeal field mapping complete', [
            'manufacturer' => $manufacturerName,
            'form_id' => $formId,
            'input_fields' => count($inputData),
            'mapped_fields' => count($mappedFields),
            'sample_mappings' => array_slice($mappedFields, 0, 5)
        ]);

        return $mappedFields;
    }
}
<?php

/**
 * ACZ & Associates IVR Field Mapping Service
 *
 * This service implements the field mapping strategy for the ACZ & Associates IVR template
 * to achieve 100% form completion accuracy.
 */

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ACZIVRFieldMappingService
{
    private array $fieldMappings;
    private array $transformations;
    private array $validation;

    public function __construct()
    {
        $this->loadConfiguration();
    }

    /**
     * Load field mapping configuration
     */
    private function loadConfiguration(): void
    {
        $config = require __DIR__ . '/field-mapping-config.php';
        $this->fieldMappings = $config['field_mappings'];
        $this->transformations = $config['transformations'];
        $this->validation = $config['validation'];
    }

    /**
     * Map form data to Docuseal fields
     */
    public function mapFormDataToDocuseal(array $formData): array
    {
        $mappedFields = [];
        $errors = [];

        Log::info('Starting ACZ IVR field mapping', [
            'form_data_keys' => array_keys($formData),
            'total_fields' => count($this->fieldMappings)
        ]);

        foreach ($this->fieldMappings as $docusealField => $mapping) {
            try {
                $value = $this->extractFieldValue($formData, $mapping);

                if ($value !== null) {
                    $transformedValue = $this->applyTransformation($value, $mapping['transform'] ?? null);
                    $mappedFields[$docusealField] = $transformedValue;

                    Log::info('Field mapped successfully', [
                        'docuseal_field' => $docusealField,
                        'source_value' => $value,
                        'transformed_value' => $transformedValue
                    ]);
                } elseif ($mapping['required'] ?? false) {
                    $errors[] = "Required field '{$docusealField}' is missing";
                    Log::warning('Required field missing', [
                        'field' => $docusealField,
                        'mapping' => $mapping
                    ]);
                }
            } catch (\Exception $e) {
                $errors[] = "Error mapping field '{$docusealField}': " . $e->getMessage();
                Log::error('Field mapping error', [
                    'field' => $docusealField,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Validate mapped fields
        $validationErrors = $this->validateMappedFields($mappedFields);
        $errors = array_merge($errors, $validationErrors);

        Log::info('ACZ IVR field mapping completed', [
            'mapped_fields' => count($mappedFields),
            'errors' => count($errors),
            'success_rate' => count($mappedFields) / count($this->fieldMappings) * 100
        ]);

        return [
            'fields' => $mappedFields,
            'errors' => $errors,
            'success' => empty($errors)
        ];
    }

    /**
     * Extract field value from form data
     */
    private function extractFieldValue(array $formData, array $mapping): mixed
    {
        $source = $mapping['source'];

        if (is_array($source)) {
            // Multiple source fields
            $values = [];
            foreach ($source as $field) {
                if (isset($formData[$field])) {
                    $values[] = $formData[$field];
                }
            }
            return $values;
        } else {
            // Single source field
            return $formData[$source] ?? null;
        }
    }

    /**
     * Apply transformation to field value
     */
    private function applyTransformation(mixed $value, ?string $transformName): mixed
    {
        if (empty($transformName) || !isset($this->transformations[$transformName])) {
            return $value;
        }

        $transformFunction = $this->transformations[$transformName];

        if (is_callable($transformFunction)) {
            return $transformFunction($value);
        }

        return $value;
    }

    /**
     * Validate mapped fields
     */
    private function validateMappedFields(array $mappedFields): array
    {
        $errors = [];

        // Check required fields
        foreach ($this->validation['required_fields'] as $requiredField) {
            if (!isset($mappedFields[$requiredField]) || empty($mappedFields[$requiredField])) {
                $errors[] = "Required field '{$requiredField}' is missing or empty";
            }
        }

        // Validate radio button values
        foreach ($this->validation['radio_fields'] as $radioField) {
            if (isset($mappedFields[$radioField])) {
                $mapping = $this->fieldMappings[$radioField];
                $validOptions = array_keys($mapping['options'] ?? []);

                if (!empty($validOptions) && !in_array($mappedFields[$radioField], $validOptions)) {
                    $errors[] = "Invalid radio button value for '{$radioField}': {$mappedFields[$radioField]}";
                }
            }
        }

        // Validate conditional fields
        foreach ($this->validation['conditional_fields'] as $conditionalField => $condition) {
            $conditionField = array_keys($condition)[0];
            $conditionValue = array_values($condition)[0];

            if (isset($mappedFields[$conditionField]) && $mappedFields[$conditionField] === $conditionValue) {
                if (!isset($mappedFields[$conditionalField])) {
                    $errors[] = "Conditional field '{$conditionalField}' is required when '{$conditionField}' is '{$conditionValue}'";
                }
            }
        }

        return $errors;
    }

    /**
     * Get mapping statistics
     */
    public function getMappingStatistics(array $formData): array
    {
        $result = $this->mapFormDataToDocuseal($formData);

        return [
            'total_template_fields' => count($this->fieldMappings),
            'mapped_fields' => count($result['fields']),
            'mapping_success_rate' => count($result['fields']) / count($this->fieldMappings) * 100,
            'errors' => count($result['errors']),
            'required_fields_mapped' => $this->countRequiredFieldsMapped($result['fields']),
            'radio_fields_mapped' => $this->countRadioFieldsMapped($result['fields']),
            'conditional_fields_mapped' => $this->countConditionalFieldsMapped($result['fields'])
        ];
    }

    /**
     * Count required fields that were successfully mapped
     */
    private function countRequiredFieldsMapped(array $mappedFields): int
    {
        $count = 0;
        foreach ($this->validation['required_fields'] as $requiredField) {
            if (isset($mappedFields[$requiredField]) && !empty($mappedFields[$requiredField])) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Count radio fields that were successfully mapped
     */
    private function countRadioFieldsMapped(array $mappedFields): int
    {
        $count = 0;
        foreach ($this->validation['radio_fields'] as $radioField) {
            if (isset($mappedFields[$radioField])) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Count conditional fields that were successfully mapped
     */
    private function countConditionalFieldsMapped(array $mappedFields): int
    {
        $count = 0;
        foreach ($this->validation['conditional_fields'] as $conditionalField => $condition) {
            if (isset($mappedFields[$conditionalField])) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get field mapping report
     */
    public function getFieldMappingReport(array $formData): array
    {
        $result = $this->mapFormDataToDocuseal($formData);
        $stats = $this->getMappingStatistics($formData);

        return [
            'template_info' => [
                'template_id' => 852440,
                'template_name' => 'ACZ & Associates IVR',
                'manufacturer' => 'ACZ & ASSOCIATES'
            ],
            'mapping_results' => [
                'success' => $result['success'],
                'mapped_fields' => $result['fields'],
                'errors' => $result['errors']
            ],
            'statistics' => $stats,
            'field_categories' => [
                'product_selection' => $this->getCategoryFields('Product Q Code'),
                'representative_info' => $this->getCategoryFields(['Sales Rep', 'ISO if applicable', 'Additional Emails for Notification']),
                'physician_info' => $this->getCategoryFields(['Physician Name', 'Physician NPI', 'Physician Specialty', 'Physician Tax ID', 'Physician PTAN', 'Physician Medicaid #', 'Physician Phone #', 'Physician Fax #', 'Physician Organization']),
                'facility_info' => $this->getCategoryFields(['Facility NPI', 'Facility Tax ID', 'Facility Name', 'Facility PTAN', 'Facility Address', 'Facility Medicaid #', 'Facility City, State, Zip', 'Facility Phone #', 'Facility Contact Name', 'Facility Fax #', 'Facility Contact Phone # / Facility Contact Email', 'Facility Organization']),
                'place_of_service' => $this->getCategoryFields(['Place of Service', 'POS Other Specify']),
                'patient_info' => $this->getCategoryFields(['Patient Name', 'Patient DOB', 'Patient Address', 'Patient City, State, Zip', 'Patient Phone #', 'Patient Email', 'Patient Caregiver Info']),
                'insurance_info' => $this->getCategoryFields(['Primary Insurance Name', 'Secondary Insurance Name', 'Primary Policy Number', 'Secondary Policy Number', 'Primary Payer Phone #', 'Secondary Payer Phone #']),
                'network_status' => $this->getCategoryFields(['Physician Status With Primary', 'Physician Status With Secondary']),
                'authorization_questions' => $this->getCategoryFields(['Permission To Initiate And Follow Up On Prior Auth?', 'Is The Patient Currently in Hospice?', 'Is The Patient In A Facility Under Part A Stay?', 'Is The Patient Under Post-Op Global Surgery Period?']),
                'surgery_fields' => $this->getCategoryFields(['If Yes, List Surgery CPTs', 'Surgery Date']),
                'clinical_info' => $this->getCategoryFields(['Location of Wound', 'ICD-10 Codes', 'Total Wound Size', 'Medical History'])
            ]
        ];
    }

    /**
     * Get fields for a specific category
     */
    private function getCategoryFields($fieldNames): array
    {
        $fields = [];
        $fieldNames = is_array($fieldNames) ? $fieldNames : [$fieldNames];

        foreach ($fieldNames as $fieldName) {
            if (isset($this->fieldMappings[$fieldName])) {
                $fields[$fieldName] = $this->fieldMappings[$fieldName];
            }
        }

        return $fields;
    }
}

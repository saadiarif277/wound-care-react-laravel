# Field Mapping Consolidation Implementation Plan

## New Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    UnifiedFieldMappingService                │
│  ┌─────────────┐  ┌──────────────┐  ┌──────────────────┐  │
│  │ DataExtractor│  │FieldMapper  │  │FieldTransformer │  │
│  └─────────────┘  └──────────────┘  └──────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                      DocuSealService                         │
│         (Consolidated from 3 DocuSeal services)             │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                   config/field-mapping.php                   │
│              (Single source of truth for all configs)        │
└─────────────────────────────────────────────────────────────┘
```

## Phase 1: Create Base Infrastructure

### 1.1 Unified Configuration File

```php
// config/field-mapping.php
<?php

return [
    'manufacturers' => [
        'ACZ' => [
            'id' => 1,
            'name' => 'ACZ',
            'template_id' => '852440',
            'signature_required' => true,
            'has_order_form' => false,
            'duration_requirement' => 'greater_than_4_weeks',
            'fields' => [
                'patient_name' => [
                    'source' => 'computed',
                    'computation' => 'patient_first_name + patient_last_name',
                    'required' => true,
                    'type' => 'string'
                ],
                'patient_dob' => [
                    'source' => 'patient_dob',
                    'transform' => 'date:m/d/Y',
                    'required' => true,
                    'type' => 'date'
                ],
                'patient_phone' => [
                    'source' => 'patient_phone',
                    'transform' => 'phone:US',
                    'required' => true,
                    'type' => 'phone'
                ],
                'wound_size' => [
                    'source' => 'computed',
                    'computation' => 'wound_size_length * wound_size_width',
                    'transform' => 'number:2',
                    'type' => 'number'
                ],
                // ... all other fields
            ]
        ],
        // ... other manufacturers
    ],
    
    'transformers' => [
        'date' => [
            'm/d/Y' => 'convertToMDY',
            'Y-m-d' => 'convertToISO'
        ],
        'phone' => [
            'US' => 'formatUSPhone'
        ],
        'boolean' => [
            'yes_no' => 'booleanToYesNo'
        ]
    ],
    
    'field_aliases' => [
        // Common field name variations
        'patient_first_name' => ['first_name', 'fname', 'patient_fname'],
        'patient_last_name' => ['last_name', 'lname', 'patient_lname'],
        'date_of_birth' => ['dob', 'patient_dob', 'birth_date'],
        // ... more aliases
    ],
    
    'validation_rules' => [
        'phone' => '/^\d{10}$/',
        'zip' => '/^\d{5}(-\d{4})?$/',
        'npi' => '/^\d{10}$/',
        // ... more rules
    ]
];
```

### 1.2 Field Transformer Service

```php
// app/Services/FieldMapping/FieldTransformer.php
<?php

namespace App\Services\FieldMapping;

class FieldTransformer
{
    private array $transformers;

    public function __construct()
    {
        $this->transformers = [
            'date' => [
                'm/d/Y' => fn($value) => $this->convertToMDY($value),
                'Y-m-d' => fn($value) => $this->convertToISO($value),
            ],
            'phone' => [
                'US' => fn($value) => $this->formatUSPhone($value),
            ],
            'boolean' => [
                'yes_no' => fn($value) => $value ? 'Yes' : 'No',
            ],
            'address' => [
                'full' => fn($data) => $this->formatFullAddress($data),
            ],
            'number' => [
                '2' => fn($value) => round((float)$value, 2),
            ]
        ];
    }

    public function transform(mixed $value, string $transformer): mixed
    {
        if (!$transformer || $value === null) {
            return $value;
        }

        [$type, $format] = explode(':', $transformer);
        
        if (!isset($this->transformers[$type][$format])) {
            throw new \InvalidArgumentException("Unknown transformer: {$transformer}");
        }

        return $this->transformers[$type][$format]($value);
    }

    private function convertToMDY($value): string
    {
        if (empty($value)) return '';
        
        try {
            $date = new \DateTime($value);
            return $date->format('m/d/Y');
        } catch (\Exception $e) {
            return $value;
        }
    }

    private function convertToISO($value): string
    {
        if (empty($value)) return '';
        
        try {
            $date = new \DateTime($value);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return $value;
        }
    }

    private function formatUSPhone($value): string
    {
        $digits = preg_replace('/\D/', '', $value);
        
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s',
                substr($digits, 0, 3),
                substr($digits, 3, 3),
                substr($digits, 6)
            );
        }
        
        return $value;
    }

    private function formatFullAddress(array $data): string
    {
        $parts = array_filter([
            $data['line1'] ?? '',
            $data['line2'] ?? '',
            implode(', ', array_filter([
                $data['city'] ?? '',
                $data['state'] ?? '',
                $data['zip'] ?? ''
            ]))
        ]);
        
        return implode(', ', $parts);
    }
}
```

### 1.3 Data Extractor Service

```php
// app/Services/FieldMapping/DataExtractor.php
<?php

namespace App\Services\FieldMapping;

use App\Services\FhirService;
use Illuminate\Support\Facades\Cache;

class DataExtractor
{
    public function __construct(
        private FhirService $fhirService
    ) {}

    public function extractEpisodeData(int $episodeId): array
    {
        return Cache::remember("episode_data_{$episodeId}", 300, function() use ($episodeId) {
            $episode = \App\Models\Episode::with([
                'patient',
                'productRequests.product',
                'provider',
                'facility'
            ])->findOrFail($episodeId);

            // Extract all data once
            $data = [
                'episode' => $episode->toArray(),
                'fhir' => $this->extractFhirData($episode),
                'computed' => $this->computeDerivedFields($episode)
            ];

            return $this->flattenData($data);
        });
    }

    private function extractFhirData($episode): array
    {
        $fhirData = [];

        // Patient FHIR data
        if ($episode->patient?->fhir_patient_id) {
            try {
                $patient = $this->fhirService->getPatient($episode->patient->fhir_patient_id);
                $fhirData['patient'] = $this->parseFhirPatient($patient);
            } catch (\Exception $e) {
                \Log::warning("Failed to fetch FHIR patient: {$e->getMessage()}");
            }
        }

        // Coverage FHIR data
        if ($episode->patient?->fhir_patient_id) {
            try {
                $coverages = $this->fhirService->searchCoverage([
                    'patient' => $episode->patient->fhir_patient_id,
                    'status' => 'active'
                ]);
                $fhirData['coverage'] = $this->parseFhirCoverages($coverages);
            } catch (\Exception $e) {
                \Log::warning("Failed to fetch FHIR coverage: {$e->getMessage()}");
            }
        }

        // More FHIR extractions...
        
        return $fhirData;
    }

    private function computeDerivedFields($episode): array
    {
        $computed = [];
        
        // Wound size calculation
        if ($episode->wound_length && $episode->wound_width) {
            $computed['wound_size_total'] = $episode->wound_length * $episode->wound_width;
        }

        // Duration calculation
        if ($episode->wound_start_date) {
            $start = new \DateTime($episode->wound_start_date);
            $now = new \DateTime();
            $diff = $start->diff($now);
            
            $computed['wound_duration_days'] = $diff->days;
            $computed['wound_duration_weeks'] = floor($diff->days / 7);
            $computed['wound_duration_months'] = $diff->m + ($diff->y * 12);
        }

        // Full name
        if ($episode->patient) {
            $computed['patient_full_name'] = trim(
                ($episode->patient->first_name ?? '') . ' ' . 
                ($episode->patient->last_name ?? '')
            );
        }

        return $computed;
    }

    private function flattenData(array $data, string $prefix = ''): array
    {
        $result = [];
        
        foreach ($data as $key => $value) {
            $newKey = $prefix ? "{$prefix}_{$key}" : $key;
            
            if (is_array($value) && !isset($value[0])) {
                $result = array_merge($result, $this->flattenData($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }

    private function parseFhirPatient($patient): array
    {
        // Extract relevant fields from FHIR Patient resource
        return [
            'id' => $patient['id'] ?? null,
            'first_name' => $patient['name'][0]['given'][0] ?? null,
            'last_name' => $patient['name'][0]['family'] ?? null,
            'dob' => $patient['birthDate'] ?? null,
            'gender' => $patient['gender'] ?? null,
            'phone' => $patient['telecom'][0]['value'] ?? null,
            'address' => $this->parseFhirAddress($patient['address'][0] ?? [])
        ];
    }

    private function parseFhirAddress($address): array
    {
        return [
            'line1' => $address['line'][0] ?? '',
            'line2' => $address['line'][1] ?? '',
            'city' => $address['city'] ?? '',
            'state' => $address['state'] ?? '',
            'zip' => $address['postalCode'] ?? ''
        ];
    }
}
```

### 1.4 Unified Field Mapping Service

```php
// app/Services/UnifiedFieldMappingService.php
<?php

namespace App\Services;

use App\Services\FieldMapping\DataExtractor;
use App\Services\FieldMapping\FieldTransformer;
use App\Services\FieldMapping\FieldMatcher;
use Illuminate\Support\Facades\Log;

class UnifiedFieldMappingService
{
    private array $config;

    public function __construct(
        private DataExtractor $dataExtractor,
        private FieldTransformer $fieldTransformer,
        private FieldMatcher $fieldMatcher
    ) {
        $this->config = config('field-mapping');
    }

    /**
     * Main entry point for all field mapping needs
     */
    public function mapEpisodeToTemplate(
        int $episodeId, 
        string $manufacturerName,
        array $additionalData = []
    ): array {
        // 1. Extract all data once
        $sourceData = $this->dataExtractor->extractEpisodeData($episodeId);
        $sourceData = array_merge($sourceData, $additionalData);

        // 2. Get manufacturer configuration
        $manufacturerConfig = $this->getManufacturerConfig($manufacturerName);
        if (!$manufacturerConfig) {
            throw new \InvalidArgumentException("Unknown manufacturer: {$manufacturerName}");
        }

        // 3. Map fields according to configuration
        $mappedData = $this->mapFields($sourceData, $manufacturerConfig['fields']);

        // 4. Apply manufacturer-specific business rules
        $mappedData = $this->applyBusinessRules($mappedData, $manufacturerConfig);

        // 5. Validate mapped data
        $validation = $this->validateMapping($mappedData, $manufacturerConfig);

        return [
            'data' => $mappedData,
            'validation' => $validation,
            'manufacturer' => $manufacturerConfig,
            'completeness' => $this->calculateCompleteness($mappedData, $manufacturerConfig)
        ];
    }

    private function mapFields(array $sourceData, array $fieldConfig): array
    {
        $mapped = [];

        foreach ($fieldConfig as $targetField => $config) {
            $value = null;

            // Handle different source types
            switch ($config['source']) {
                case 'computed':
                    $value = $this->computeField($config['computation'], $sourceData);
                    break;
                
                case 'fuzzy':
                    $match = $this->fieldMatcher->findBestMatch(
                        $targetField, 
                        array_keys($sourceData),
                        $sourceData
                    );
                    $value = $match ? $sourceData[$match['field']] : null;
                    break;
                
                default:
                    // Direct field mapping
                    $value = $this->getValueByPath($sourceData, $config['source']);
            }

            // Apply transformation if specified
            if ($value !== null && isset($config['transform'])) {
                $value = $this->fieldTransformer->transform($value, $config['transform']);
            }

            $mapped[$targetField] = $value;
        }

        return $mapped;
    }

    private function computeField(string $computation, array $data): mixed
    {
        // Handle concatenation (field1 + field2)
        if (str_contains($computation, ' + ')) {
            $parts = array_map('trim', explode(' + ', $computation));
            $values = array_map(fn($part) => $data[$part] ?? '', $parts);
            return implode(' ', array_filter($values));
        }

        // Handle multiplication (field1 * field2)
        if (str_contains($computation, ' * ')) {
            $parts = array_map('trim', explode(' * ', $computation));
            $values = array_map(fn($part) => (float)($data[$part] ?? 0), $parts);
            return array_reduce($values, fn($carry, $item) => $carry * $item, 1);
        }

        // Handle OR conditions (field1 || field2)
        if (str_contains($computation, ' || ')) {
            $parts = array_map('trim', explode(' || ', $computation));
            foreach ($parts as $part) {
                if (!empty($data[$part])) {
                    return $data[$part];
                }
            }
            return null;
        }

        return null;
    }

    private function applyBusinessRules(array $data, array $config): array
    {
        $manufacturerName = $config['name'];

        // ACZ specific rules
        if ($manufacturerName === 'ACZ' && isset($config['duration_requirement'])) {
            if ($config['duration_requirement'] === 'greater_than_4_weeks') {
                $weeks = $data['wound_duration_weeks'] ?? 0;
                if ($weeks <= 4) {
                    Log::warning("ACZ requires wound duration > 4 weeks, got {$weeks} weeks");
                }
            }
        }

        // Add more manufacturer-specific rules as needed

        return $data;
    }

    private function validateMapping(array $data, array $config): array
    {
        $errors = [];
        $warnings = [];

        foreach ($config['fields'] as $field => $fieldConfig) {
            // Check required fields
            if ($fieldConfig['required'] ?? false) {
                if (empty($data[$field])) {
                    $errors[] = "Required field '{$field}' is missing";
                }
            }

            // Validate format if specified
            if (!empty($data[$field]) && isset($fieldConfig['type'])) {
                $validationRule = $this->config['validation_rules'][$fieldConfig['type']] ?? null;
                if ($validationRule && !preg_match($validationRule, $data[$field])) {
                    $warnings[] = "Field '{$field}' format may be invalid";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings
        ];
    }

    private function calculateCompleteness(array $data, array $config): array
    {
        $total = count($config['fields']);
        $filled = 0;
        $required = 0;
        $requiredFilled = 0;

        foreach ($config['fields'] as $field => $fieldConfig) {
            if (!empty($data[$field])) {
                $filled++;
            }
            
            if ($fieldConfig['required'] ?? false) {
                $required++;
                if (!empty($data[$field])) {
                    $requiredFilled++;
                }
            }
        }

        return [
            'percentage' => $total > 0 ? round(($filled / $total) * 100, 2) : 0,
            'required_percentage' => $required > 0 ? round(($requiredFilled / $required) * 100, 2) : 0,
            'filled' => $filled,
            'total' => $total,
            'required_filled' => $requiredFilled,
            'required_total' => $required
        ];
    }

    private function getManufacturerConfig(string $name): ?array
    {
        return $this->config['manufacturers'][$name] ?? null;
    }

    private function getValueByPath(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (!is_array($value) || !isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }
}
```

## Phase 2: Consolidated DocuSeal Service

```php
// app/Services/DocuSealService.php
<?php

namespace App\Services;

use DocuSeal\Api\Client;
use Illuminate\Support\Facades\Log;

class DocuSealService
{
    private Client $client;
    private UnifiedFieldMappingService $fieldMapper;

    public function __construct(UnifiedFieldMappingService $fieldMapper)
    {
        $this->client = new Client(config('services.docuseal.api_key'));
        $this->fieldMapper = $fieldMapper;
    }

    /**
     * Create a DocuSeal submission from an episode
     */
    public function createSubmissionFromEpisode(
        int $episodeId,
        string $manufacturerName,
        array $options = []
    ): array {
        // 1. Get mapped data using unified service
        $mappingResult = $this->fieldMapper->mapEpisodeToTemplate(
            $episodeId,
            $manufacturerName,
            $options['additional_data'] ?? []
        );

        // 2. Check if valid
        if (!$mappingResult['validation']['valid']) {
            throw new \InvalidArgumentException(
                'Invalid mapping: ' . implode(', ', $mappingResult['validation']['errors'])
            );
        }

        // 3. Get template ID
        $templateId = $mappingResult['manufacturer']['template_id'];
        if (!$templateId || $templateId === 'TBD') {
            throw new \InvalidArgumentException(
                "No template configured for manufacturer: {$manufacturerName}"
            );
        }

        // 4. Create DocuSeal submission
        $submissionData = $this->prepareSubmissionData($mappingResult);
        
        try {
            if ($options['use_builder_token'] ?? false) {
                return $this->createBuilderToken($templateId, $submissionData);
            } else {
                return $this->createSubmission($templateId, $submissionData);
            }
        } catch (\Exception $e) {
            Log::error('DocuSeal submission failed', [
                'error' => $e->getMessage(),
                'episode_id' => $episodeId,
                'manufacturer' => $manufacturerName
            ]);
            throw $e;
        }
    }

    private function prepareSubmissionData(array $mappingResult): array
    {
        $data = $mappingResult['data'];
        $manufacturer = $mappingResult['manufacturer'];

        // Format for DocuSeal API
        $fields = [];
        foreach ($data as $field => $value) {
            if ($value !== null) {
                $fields[] = [
                    'name' => $field,
                    'value' => (string)$value
                ];
            }
        }

        return [
            'template_id' => $manufacturer['template_id'],
            'submitter' => [
                'email' => $data['provider_email'] ?? 'noreply@mscwoundcare.com',
                'name' => $data['provider_name'] ?? 'Provider'
            ],
            'fields' => $fields,
            'send_email' => false,
            'metadata' => [
                'episode_id' => $data['episode_id'] ?? null,
                'manufacturer' => $manufacturer['name'],
                'completeness' => $mappingResult['completeness']['percentage']
            ]
        ];
    }

    private function createSubmission(string $templateId, array $data): array
    {
        $response = $this->client->submissions->create($data);
        
        return [
            'submission_id' => $response['id'],
            'slug' => $response['slug'],
            'status' => $response['status'],
            'created_at' => $response['created_at']
        ];
    }

    private function createBuilderToken(string $templateId, array $data): array
    {
        // Implementation for builder token generation
        // This would use DocuSeal's builder API
        return [
            'token' => 'generated_token',
            'expires_at' => now()->addHours(24)->toIso8601String()
        ];
    }

    /**
     * Handle webhook from DocuSeal
     */
    public function handleWebhook(array $payload): void
    {
        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? [];

        switch ($event) {
            case 'submission.completed':
                $this->handleSubmissionCompleted($data);
                break;
            
            case 'submission.created':
                $this->handleSubmissionCreated($data);
                break;
            
            default:
                Log::info('Unhandled DocuSeal webhook event', ['event' => $event]);
        }
    }

    private function handleSubmissionCompleted(array $data): void
    {
        // Update episode with completion status
        if ($episodeId = $data['metadata']['episode_id'] ?? null) {
            $episode = \App\Models\Episode::find($episodeId);
            if ($episode) {
                $episode->update([
                    'ivr_completed_at' => now(),
                    'ivr_submission_id' => $data['id']
                ]);
            }
        }

        // Trigger any additional workflows
        event(new \App\Events\IVRCompleted($data));
    }

    private function handleSubmissionCreated(array $data): void
    {
        // Log submission creation
        Log::info('DocuSeal submission created', [
            'submission_id' => $data['id'],
            'template_id' => $data['template_id']
        ]);
    }

    /**
     * Get submission status
     */
    public function getSubmissionStatus(string $submissionId): array
    {
        try {
            $submission = $this->client->submissions->get($submissionId);
            return [
                'status' => $submission['status'],
                'completed_at' => $submission['completed_at'],
                'documents' => $submission['documents'] ?? []
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get submission status', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
```

## Phase 3: Frontend Consolidation

```typescript
// resources/js/utils/fieldMapping.ts
import { fieldMappingConfig } from '@/config/field-mapping';

export interface FieldConfig {
  source: string;
  transform?: string;
  required?: boolean;
  type?: string;
  computation?: string;
}

export interface ManufacturerConfig {
  id: number;
  name: string;
  templateId: string;
  signatureRequired: boolean;
  hasOrderForm: boolean;
  fields: Record<string, FieldConfig>;
}

/**
 * Field transformation utilities (matching backend)
 */
export const fieldTransformers = {
  date: {
    'MDY': (value: string) => {
      if (!value) return '';
      const date = new Date(value);
      return `${date.getMonth() + 1}/${date.getDate()}/${date.getFullYear()}`;
    },
    'ISO': (value: string) => {
      if (!value) return '';
      const date = new Date(value);
      return date.toISOString().split('T')[0];
    }
  },
  
  phone: {
    'US': (value: string) => {
      const digits = value.replace(/\D/g, '');
      if (digits.length === 10) {
        return `(${digits.slice(0, 3)}) ${digits.slice(3, 6)}-${digits.slice(6)}`;
      }
      return value;
    }
  },
  
  boolean: {
    'yes_no': (value: boolean) => value ? 'Yes' : 'No'
  }
};

/**
 * Get manufacturer configuration
 */
export function getManufacturerConfig(name: string): ManufacturerConfig | null {
  return fieldMappingConfig.manufacturers[name] || null;
}

/**
 * Map form data to manufacturer fields
 */
export function mapFormDataToManufacturer(
  formData: Record<string, any>,
  manufacturerName: string
): Record<string, any> {
  const config = getManufacturerConfig(manufacturerName);
  if (!config) {
    throw new Error(`Unknown manufacturer: ${manufacturerName}`);
  }

  const mapped: Record<string, any> = {};

  Object.entries(config.fields).forEach(([targetField, fieldConfig]) => {
    let value = null;

    if (fieldConfig.source === 'computed' && fieldConfig.computation) {
      value = computeField(fieldConfig.computation, formData);
    } else {
      value = formData[fieldConfig.source];
    }

    if (value !== null && fieldConfig.transform) {
      value = applyTransform(value, fieldConfig.transform);
    }

    mapped[targetField] = value;
  });

  return mapped;
}

/**
 * Compute field value from expression
 */
function computeField(computation: string, data: Record<string, any>): any {
  // Handle concatenation
  if (computation.includes(' + ')) {
    const parts = computation.split(' + ').map(p => p.trim());
    const values = parts.map(part => data[part] || '').filter(Boolean);
    return values.join(' ');
  }

  // Handle multiplication
  if (computation.includes(' * ')) {
    const parts = computation.split(' * ').map(p => p.trim());
    const values = parts.map(part => parseFloat(data[part]) || 0);
    return values.reduce((a, b) => a * b, 1);
  }

  // Handle OR
  if (computation.includes(' || ')) {
    const parts = computation.split(' || ').map(p => p.trim());
    for (const part of parts) {
      if (data[part]) return data[part];
    }
  }

  return null;
}

/**
 * Apply field transformation
 */
function applyTransform(value: any, transform: string): any {
  const [type, format] = transform.split(':');
  const transformer = fieldTransformers[type]?.[format];
  
  if (!transformer) {
    console.warn(`Unknown transformer: ${transform}`);
    return value;
  }

  return transformer(value);
}

/**
 * Validate form data against manufacturer requirements
 */
export function validateManufacturerFields(
  formData: Record<string, any>,
  manufacturerName: string
): { valid: boolean; errors: string[]; warnings: string[] } {
  const config = getManufacturerConfig(manufacturerName);
  if (!config) {
    return { valid: false, errors: [`Unknown manufacturer: ${manufacturerName}`], warnings: [] };
  }

  const errors: string[] = [];
  const warnings: string[] = [];

  Object.entries(config.fields).forEach(([field, fieldConfig]) => {
    const value = formData[field];

    // Check required fields
    if (fieldConfig.required && !value) {
      errors.push(`Required field '${field}' is missing`);
    }

    // Validate format
    if (value && fieldConfig.type) {
      const valid = validateFieldType(value, fieldConfig.type);
      if (!valid) {
        warnings.push(`Field '${field}' format may be invalid`);
      }
    }
  });

  return {
    valid: errors.length === 0,
    errors,
    warnings
  };
}

/**
 * Validate field type
 */
function validateFieldType(value: any, type: string): boolean {
  const validators = {
    phone: /^\d{10}$/,
    zip: /^\d{5}(-\d{4})?$/,
    npi: /^\d{10}$/,
    email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  };

  const pattern = validators[type];
  if (!pattern) return true;

  const cleanValue = typeof value === 'string' ? value.replace(/\D/g, '') : String(value);
  return pattern.test(cleanValue);
}
```

```typescript
// resources/js/config/field-mapping.ts
// This would be generated from the backend config to ensure consistency

export const fieldMappingConfig = {
  manufacturers: {
    'ACZ': {
      id: 1,
      name: 'ACZ',
      templateId: '852440',
      signatureRequired: true,
      hasOrderForm: false,
      fields: {
        // ... field definitions matching backend
      }
    },
    // ... other manufacturers
  }
};
```

## Phase 4: Migration Strategy

### 4.1 Feature Flag Implementation

```php
// app/Http/Controllers/QuickRequestController.php
public function generateDocuSealSubmission(Request $request)
{
    $useNewService = config('features.use_unified_field_mapping', false);

    if ($useNewService) {
        // New unified service
        return app(DocuSealService::class)->createSubmissionFromEpisode(
            $request->episode_id,
            $request->manufacturer_name,
            ['additional_data' => $request->all()]
        );
    } else {
        // Old service (temporarily)
        return app(EnhancedDocuSealIVRService::class)->createSubmission(
            $request->all()
        );
    }
}
```

### 4.2 Database Migration

```php
// database/migrations/2024_XX_XX_consolidate_field_mappings.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ConsolidateFieldMappings extends Migration
{
    public function up()
    {
        // Create new consolidated table
        Schema::create('field_mapping_configs', function (Blueprint $table) {
            $table->id();
            $table->string('manufacturer_name');
            $table->string('template_id')->nullable();
            $table->json('field_config');
            $table->json('business_rules')->nullable();
            $table->boolean('signature_required')->default(true);
            $table->boolean('has_order_form')->default(false);
            $table->timestamps();
            
            $table->unique('manufacturer_name');
            $table->index('template_id');
        });

        // Migrate data from old tables
        $this->migrateExistingData();
    }

    private function migrateExistingData()
    {
        // Migration logic to move data from old tables to new structure
        $oldMappings = DB::table('ivr_field_mappings')->get();
        
        foreach ($oldMappings as $mapping) {
            // Transform and insert into new table
            DB::table('field_mapping_configs')->insert([
                'manufacturer_name' => $mapping->manufacturer,
                'template_id' => $mapping->template_id,
                'field_config' => json_encode($this->transformFieldConfig($mapping)),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('field_mapping_configs');
    }
}
```

## Phase 5: Testing Strategy

```php
// tests/Feature/UnifiedFieldMappingTest.php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\UnifiedFieldMappingService;

class UnifiedFieldMappingTest extends TestCase
{
    private UnifiedFieldMappingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UnifiedFieldMappingService::class);
    }

    public function test_maps_all_required_fields_for_acl()
    {
        // Create test episode with FHIR data
        $episode = $this->createTestEpisode();
        
        // Map to ACZ template
        $result = $this->service->mapEpisodeToTemplate($episode->id, 'ACZ');
        
        // Assert all required fields are present
        $this->assertTrue($result['validation']['valid']);
        $this->assertArrayHasKey('patient_name', $result['data']);
        $this->assertArrayHasKey('patient_dob', $result['data']);
        $this->assertEquals(100, $result['completeness']['required_percentage']);
    }

    public function test_field_transformations_work_correctly()
    {
        $episode = $this->createTestEpisode([
            'patient' => [
                'phone' => '1234567890',
                'dob' => '1980-01-15'
            ]
        ]);
        
        $result = $this->service->mapEpisodeToTemplate($episode->id, 'ACZ');
        
        // Phone should be formatted
        $this->assertEquals('(123) 456-7890', $result['data']['patient_phone']);
        
        // Date should be in MM/DD/YYYY format
        $this->assertEquals('01/15/1980', $result['data']['patient_dob']);
    }

    public function test_computed_fields_calculate_correctly()
    {
        $episode = $this->createTestEpisode([
            'wound_length' => 5.0,
            'wound_width' => 3.0
        ]);
        
        $result = $this->service->mapEpisodeToTemplate($episode->id, 'ACZ');
        
        // Wound size should be calculated
        $this->assertEquals(15.0, $result['data']['wound_size']);
    }

    // More comprehensive tests...
}
```

## Benefits After Consolidation

1. **Single Source of Truth**: All field mappings in one config file
2. **Consistent Transformations**: Same formatting everywhere
3. **Easier Testing**: Test once, works everywhere
4. **Better Performance**: Data extracted once, cached, and reused
5. **Cleaner Architecture**: Clear separation of concerns
6. **Easier Maintenance**: Update in one place
7. **Type Safety**: Shared TypeScript definitions from backend config
8. **Audit Trail**: All mappings logged consistently
9. **Extensibility**: Easy to add new manufacturers or fields
10. **Reduced Code**: From ~10,000 lines to ~2,000 lines
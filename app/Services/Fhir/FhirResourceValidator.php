<?php

declare(strict_types=1);

namespace App\Services\Fhir;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FhirResourceValidator
{
    private array $profiles;
    private bool $strictMode;
    private array $requiredFields;

    public function __construct(array $profiles = [], bool $strictMode = false)
    {
        $this->profiles = $profiles;
        $this->strictMode = $strictMode;
        $this->requiredFields = config('fhir.validation.required_fields', []);
    }

    /**
     * Validate a FHIR resource
     */
    public function validate(array $resource, ?string $resourceType = null): array
    {
        $errors = [];
        
        // Determine resource type
        $type = $resourceType ?? $resource['resourceType'] ?? null;
        
        if (!$type) {
            $errors['resourceType'] = 'Resource type is required';
            return $errors;
        }

        // Validate resource structure
        $errors = array_merge($errors, $this->validateResourceStructure($resource, $type));
        
        // Validate required fields
        if (isset($this->requiredFields[$type])) {
            $errors = array_merge($errors, $this->validateRequiredFields($resource, $type));
        }
        
        // Validate specific resource types
        $method = 'validate' . $type;
        if (method_exists($this, $method)) {
            $errors = array_merge($errors, $this->$method($resource));
        }
        
        return $errors;
    }

    /**
     * Validate a FHIR resource or throw a ValidationException
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateOrThrow(array $resource, ?string $resourceType = null): void
    {
        $errors = $this->validate($resource, $resourceType);

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Validate basic resource structure
     */
    private function validateResourceStructure(array $resource, string $type): array
    {
        $errors = [];
        
        if (!isset($resource['resourceType']) || $resource['resourceType'] !== $type) {
            $errors['resourceType'] = "Resource type must be '{$type}'";
        }
        
        // Validate meta if present
        if (isset($resource['meta'])) {
            if (!is_array($resource['meta'])) {
                $errors['meta'] = 'Meta must be an object';
            }
        }
        
        return $errors;
    }

    /**
     * Validate required fields
     */
    private function validateRequiredFields(array $resource, string $type): array
    {
        $errors = [];
        $required = $this->requiredFields[$type] ?? [];
        
        foreach ($required as $field) {
            if (!$this->hasField($resource, $field)) {
                $errors[$field] = "The {$field} field is required";
            }
        }
        
        return $errors;
    }

    /**
     * Check if a field exists (supports nested fields)
     */
    private function hasField(array $resource, string $field): bool
    {
        $keys = explode('.', $field);
        $value = $resource;
        
        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return false;
            }
            $value = $value[$key];
        }
        
        return true;
    }

    /**
     * Validate Patient resource
     */
    private function validatePatient(array $resource): array
    {
        $errors = [];
        
        // Validate name
        if (isset($resource['name'])) {
            if (!is_array($resource['name']) || empty($resource['name'])) {
                $errors['name'] = 'Name must be a non-empty array';
            } else {
                foreach ($resource['name'] as $index => $name) {
                    if (!is_array($name)) {
                        $errors["name.{$index}"] = 'Each name must be an object';
                    } elseif (!isset($name['family']) && !isset($name['given'])) {
                        $errors["name.{$index}"] = 'Name must have family or given name';
                    }
                }
            }
        }
        
        // Validate gender
        if (isset($resource['gender'])) {
            $validGenders = ['male', 'female', 'other', 'unknown'];
            if (!in_array($resource['gender'], $validGenders)) {
                $errors['gender'] = 'Gender must be one of: ' . implode(', ', $validGenders);
            }
        }
        
        // Validate birthDate
        if (isset($resource['birthDate'])) {
            if (!preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $resource['birthDate'])) {
                $errors['birthDate'] = 'Birth date must be in FHIR date format';
            }
        }
        
        // Validate identifier
        if (isset($resource['identifier'])) {
            foreach ($resource['identifier'] as $index => $identifier) {
                if (!isset($identifier['system']) || !isset($identifier['value'])) {
                    $errors["identifier.{$index}"] = 'Identifier must have system and value';
                }
            }
        }
        
        return $errors;
    }

    /**
     * Validate Practitioner resource
     */
    private function validatePractitioner(array $resource): array
    {
        $errors = [];
        
        // Validate identifier (NPI required)
        if (isset($resource['identifier'])) {
            $hasNpi = false;
            foreach ($resource['identifier'] as $identifier) {
                if (($identifier['system'] ?? '') === 'http://hl7.org/fhir/sid/us-npi') {
                    $hasNpi = true;
                    if (!preg_match('/^\d{10}$/', $identifier['value'] ?? '')) {
                        $errors['identifier.npi'] = 'NPI must be 10 digits';
                    }
                }
            }
            
            if (!$hasNpi && $this->strictMode) {
                $errors['identifier'] = 'NPI identifier is required';
            }
        }
        
        return $errors;
    }

    /**
     * Validate Organization resource
     */
    private function validateOrganization(array $resource): array
    {
        $errors = [];
        
        // Validate type
        if (isset($resource['type'])) {
            foreach ($resource['type'] as $index => $type) {
                if (!isset($type['coding']) || !is_array($type['coding'])) {
                    $errors["type.{$index}"] = 'Type must contain coding array';
                }
            }
        }
        
        // Validate address
        if (isset($resource['address'])) {
            foreach ($resource['address'] as $index => $address) {
                if (!isset($address['line']) && !isset($address['city']) && !isset($address['state'])) {
                    $errors["address.{$index}"] = 'Address must have at least one component';
                }
            }
        }
        
        return $errors;
    }

    /**
     * Validate EpisodeOfCare resource
     */
    private function validateEpisodeOfCare(array $resource): array
    {
        $errors = [];
        
        // Validate status
        if (isset($resource['status'])) {
            $validStatuses = ['planned', 'waitlist', 'active', 'onhold', 'finished', 'cancelled', 'entered-in-error'];
            if (!in_array($resource['status'], $validStatuses)) {
                $errors['status'] = 'Invalid episode status';
            }
        }
        
        // Validate patient reference
        if (isset($resource['patient'])) {
            if (!isset($resource['patient']['reference'])) {
                $errors['patient'] = 'Patient must have a reference';
            } elseif (!preg_match('/^Patient\/[A-Za-z0-9\-\.]{1,64}$/', $resource['patient']['reference'])) {
                $errors['patient.reference'] = 'Invalid patient reference format';
            }
        }
        
        // Validate period
        if (isset($resource['period'])) {
            if (!isset($resource['period']['start'])) {
                $errors['period.start'] = 'Period must have a start date';
            }
        }
        
        return $errors;
    }

    /**
     * Validate Coverage resource
     */
    private function validateCoverage(array $resource): array
    {
        $errors = [];
        
        // Validate status
        if (isset($resource['status'])) {
            $validStatuses = ['active', 'cancelled', 'draft', 'entered-in-error'];
            if (!in_array($resource['status'], $validStatuses)) {
                $errors['status'] = 'Invalid coverage status';
            }
        }
        
        // Validate beneficiary
        if (isset($resource['beneficiary'])) {
            if (!isset($resource['beneficiary']['reference'])) {
                $errors['beneficiary'] = 'Beneficiary must have a reference';
            }
        }
        
        // Validate payor
        if (isset($resource['payor'])) {
            if (!is_array($resource['payor']) || empty($resource['payor'])) {
                $errors['payor'] = 'Payor must be a non-empty array';
            }
        }
        
        return $errors;
    }

    /**
     * Validate Bundle resource
     */
    private function validateBundle(array $resource): array
    {
        $errors = [];
        
        // Validate type
        if (isset($resource['type'])) {
            $validTypes = ['document', 'message', 'transaction', 'transaction-response', 'batch', 'batch-response', 'history', 'searchset', 'collection'];
            if (!in_array($resource['type'], $validTypes)) {
                $errors['type'] = 'Invalid bundle type';
            }
        }
        
        // Validate entries
        if (isset($resource['entry'])) {
            if (!is_array($resource['entry'])) {
                $errors['entry'] = 'Entry must be an array';
            } else {
                foreach ($resource['entry'] as $index => $entry) {
                    if ($resource['type'] === 'transaction' || $resource['type'] === 'batch') {
                        if (!isset($entry['request'])) {
                            $errors["entry.{$index}.request"] = 'Transaction entries must have a request';
                        }
                    }
                }
            }
        }
        
        return $errors;
    }

    /**
     * Validate a reference
     */
    public function validateReference(string $reference): bool
    {
        return preg_match('/^(https?:\/\/[^\s\/]+\/)?[A-Z][a-zA-Z]+\/[A-Za-z0-9\-\.]{1,64}(\/\_history\/[A-Za-z0-9\-\.]{1,64})?$/', $reference);
    }

    /**
     * Validate a FHIR date
     */
    public function validateDate(string $date): bool
    {
        return preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $date);
    }

    /**
     * Validate a FHIR datetime
     */
    public function validateDateTime(string $datetime): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{3})?(Z|[+-]\d{2}:\d{2})$/', $datetime);
    }

    /**
     * Create a Laravel validator for a FHIR resource
     */
    public function makeValidator(array $data, string $resourceType): \Illuminate\Validation\Validator
    {
        $rules = $this->getValidationRules($resourceType);
        $messages = $this->getValidationMessages($resourceType);
        
        return Validator::make($data, $rules, $messages);
    }

    /**
     * Get validation rules for a resource type
     */
    private function getValidationRules(string $resourceType): array
    {
        $baseRules = [
            'resourceType' => 'required|in:' . $resourceType,
            'id' => 'sometimes|fhir_id',
            'meta' => 'sometimes|array',
            'meta.versionId' => 'sometimes|string',
            'meta.lastUpdated' => 'sometimes|fhir_datetime',
        ];
        
        $specificRules = match($resourceType) {
            'Patient' => [
                'name' => 'required|array|min:1',
                'name.*.family' => 'required_without:name.*.given|string',
                'name.*.given' => 'required_without:name.*.family|array',
                'gender' => 'required|in:male,female,other,unknown',
                'birthDate' => 'required|fhir_date',
            ],
            'Practitioner' => [
                'name' => 'required|array|min:1',
                'identifier' => 'required|array|min:1',
                'identifier.*.system' => 'required|string',
                'identifier.*.value' => 'required|string',
            ],
            default => []
        };
        
        return array_merge($baseRules, $specificRules);
    }

    /**
     * Get validation messages
     */
    private function getValidationMessages(string $resourceType): array
    {
        return [
            'resourceType.required' => 'Resource type is required',
            'resourceType.in' => "Resource type must be {$resourceType}",
            'name.required' => 'At least one name is required',
            'gender.required' => 'Gender is required',
            'gender.in' => 'Gender must be male, female, other, or unknown',
            'birthDate.required' => 'Birth date is required',
            'birthDate.fhir_date' => 'Birth date must be a valid FHIR date',
        ];
    }
}

<?php

namespace App\Services\Fhir;

use Illuminate\Support\Collection;
use Carbon\Carbon;

class FhirSearchBuilder
{
    private string $resourceType;
    private array $parameters = [];
    private array $includes = [];
    private array $reverseIncludes = [];
    private ?int $count = null;
    private ?int $offset = null;
    private array $sort = [];
    private ?string $searchId = null;
    
    public function __construct(string $resourceType)
    {
        $this->resourceType = $resourceType;
    }

    /**
     * Create a new search builder
     */
    public static function for(string $resourceType): self
    {
        return new self($resourceType);
    }

    /**
     * Add a search parameter
     */
    public function where(string $parameter, $value, ?string $modifier = null): self
    {
        $key = $modifier ? "{$parameter}:{$modifier}" : $parameter;
        
        if (!isset($this->parameters[$key])) {
            $this->parameters[$key] = [];
        }
        
        $this->parameters[$key][] = $this->formatValue($value);
        
        return $this;
    }

    /**
     * Add exact match parameter
     */
    public function whereExact(string $parameter, $value): self
    {
        return $this->where($parameter, $value, 'exact');
    }

    /**
     * Add contains parameter
     */
    public function whereContains(string $parameter, $value): self
    {
        return $this->where($parameter, $value, 'contains');
    }

    /**
     * Add reference parameter
     */
    public function whereReference(string $parameter, string $resourceType, string $id): self
    {
        return $this->where($parameter, "{$resourceType}/{$id}");
    }

    /**
     * Add identifier search
     */
    public function whereIdentifier(string $system, string $value): self
    {
        return $this->where('identifier', "{$system}|{$value}");
    }

    /**
     * Add date range search
     */
    public function whereDateRange(string $parameter, $start, $end = null): self
    {
        if ($start) {
            $this->where($parameter, $start, 'ge');
        }
        
        if ($end) {
            $this->where($parameter, $end, 'le');
        }
        
        return $this;
    }

    /**
     * Add multiple values for OR search
     */
    public function whereIn(string $parameter, array $values): self
    {
        $this->parameters[$parameter] = array_map([$this, 'formatValue'], $values);
        return $this;
    }

    /**
     * Add token search (system|code)
     */
    public function whereToken(string $parameter, ?string $system, string $code): self
    {
        $value = $system ? "{$system}|{$code}" : $code;
        return $this->where($parameter, $value);
    }

    /**
     * Add composite search
     */
    public function whereComposite(string $parameter, array $components): self
    {
        $value = implode('$', array_map([$this, 'formatValue'], $components));
        return $this->where($parameter, $value);
    }

    /**
     * Include related resources
     */
    public function include(string $resource, string $reference): self
    {
        $this->includes[] = "{$resource}:{$reference}";
        return $this;
    }

    /**
     * Include resources that reference this resource
     */
    public function revInclude(string $resource, string $reference): self
    {
        $this->reverseIncludes[] = "{$resource}:{$reference}";
        return $this;
    }

    /**
     * Set result count
     */
    public function limit(int $count): self
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Set offset for pagination
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Add sort parameter
     */
    public function orderBy(string $parameter, string $direction = 'asc'): self
    {
        $prefix = $direction === 'desc' ? '-' : '';
        $this->sort[] = $prefix . $parameter;
        return $this;
    }

    /**
     * Order by last updated descending
     */
    public function latest(): self
    {
        return $this->orderBy('_lastUpdated', 'desc');
    }

    /**
     * Order by last updated ascending
     */
    public function oldest(): self
    {
        return $this->orderBy('_lastUpdated', 'asc');
    }

    /**
     * Set search ID for continued searches
     */
    public function continueSearch(string $searchId): self
    {
        $this->searchId = $searchId;
        return $this;
    }

    /**
     * Add _has parameter for reverse chaining
     */
    public function has(string $resource, string $reference, string $parameter, $value): self
    {
        $hasValue = "{$resource}:{$reference}:{$parameter}:{$value}";
        return $this->where('_has', $hasValue);
    }

    /**
     * Add text search
     */
    public function search(string $text): self
    {
        return $this->where('_text', $text);
    }

    /**
     * Add content search
     */
    public function content(string $text): self
    {
        return $this->where('_content', $text);
    }

    /**
     * Filter by resource IDs
     */
    public function ids(array $ids): self
    {
        return $this->where('_id', implode(',', $ids));
    }

    /**
     * Filter by tag
     */
    public function tag(string $system, string $code): self
    {
        return $this->where('_tag', "{$system}|{$code}");
    }

    /**
     * Filter by security label
     */
    public function security(string $system, string $code): self
    {
        return $this->where('_security', "{$system}|{$code}");
    }

    /**
     * Filter by profile
     */
    public function profile(string $profile): self
    {
        return $this->where('_profile', $profile);
    }

    /**
     * Only return count (no resources)
     */
    public function countOnly(): self
    {
        return $this->where('_summary', 'count');
    }

    /**
     * Return summary only
     */
    public function summary(): self
    {
        return $this->where('_summary', 'true');
    }

    /**
     * Build the query parameters
     */
    public function build(): array
    {
        $params = [];
        
        // Add search parameters
        foreach ($this->parameters as $key => $values) {
            if (count($values) === 1) {
                $params[$key] = $values[0];
            } else {
                // Multiple values are OR'd together
                $params[$key] = implode(',', $values);
            }
        }
        
        // Add includes
        if (!empty($this->includes)) {
            $params['_include'] = $this->includes;
        }
        
        // Add reverse includes
        if (!empty($this->reverseIncludes)) {
            $params['_revinclude'] = $this->reverseIncludes;
        }
        
        // Add pagination
        if ($this->count !== null) {
            $params['_count'] = $this->count;
        }
        
        if ($this->offset !== null) {
            $params['_offset'] = $this->offset;
        }
        
        // Add sort
        if (!empty($this->sort)) {
            $params['_sort'] = implode(',', $this->sort);
        }
        
        // Add search ID for continued searches
        if ($this->searchId) {
            $params['_searchId'] = $this->searchId;
        }
        
        return $params;
    }

    /**
     * Build query string
     */
    public function toQueryString(): string
    {
        $params = $this->build();
        $parts = [];
        
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $parts[] = urlencode($key) . '=' . urlencode($v);
                }
            } else {
                $parts[] = urlencode($key) . '=' . urlencode($value);
            }
        }
        
        return implode('&', $parts);
    }

    /**
     * Get the resource type
     */
    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    /**
     * Format value for FHIR search
     */
    private function formatValue($value): string
    {
        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }
        
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        return (string) $value;
    }

    /**
     * Common search builders for specific resources
     */
    
    /**
     * Patient search builder
     */
    public static function patient(): self
    {
        return new self('Patient');
    }

    /**
     * Search patients by name
     */
    public function byName(string $name): self
    {
        return $this->where('name', $name);
    }

    /**
     * Search patients by birth date
     */
    public function byBirthDate($date): self
    {
        return $this->where('birthdate', $date);
    }

    /**
     * Search patients by gender
     */
    public function byGender(string $gender): self
    {
        return $this->where('gender', $gender);
    }

    /**
     * Practitioner search builder
     */
    public static function practitioner(): self
    {
        return new self('Practitioner');
    }

    /**
     * Search practitioners by NPI
     */
    public function byNpi(string $npi): self
    {
        return $this->whereIdentifier('http://hl7.org/fhir/sid/us-npi', $npi);
    }

    /**
     * Organization search builder
     */
    public static function organization(): self
    {
        return new self('Organization');
    }

    /**
     * Search organizations by type
     */
    public function byType(string $system, string $code): self
    {
        return $this->whereToken('type', $system, $code);
    }

    /**
     * EpisodeOfCare search builder
     */
    public static function episodeOfCare(): self
    {
        return new self('EpisodeOfCare');
    }

    /**
     * Search episodes by status
     */
    public function byStatus(string $status): self
    {
        return $this->where('status', $status);
    }

    /**
     * Search episodes by patient
     */
    public function byPatient(string $patientId): self
    {
        return $this->whereReference('patient', 'Patient', $patientId);
    }
}
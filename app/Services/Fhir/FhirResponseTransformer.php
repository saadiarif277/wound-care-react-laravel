<?php

namespace App\Services\Fhir;

use Illuminate\Support\Str;
use Carbon\Carbon;

class FhirResponseTransformer
{
    /**
     * Transform a FHIR resource to a simplified DTO
     */
    public function transform(array $resource): array
    {
        $resourceType = $resource['resourceType'] ?? null;
        
        if (!$resourceType) {
            return $resource;
        }
        
        $method = 'transform' . $resourceType;
        
        if (method_exists($this, $method)) {
            return $this->$method($resource);
        }
        
        return $this->transformGeneric($resource);
    }

    /**
     * Transform multiple resources
     */
    public function transformCollection(array $resources): array
    {
        return array_map([$this, 'transform'], $resources);
    }

    /**
     * Transform a Bundle response
     */
    public function transformBundle(array $bundle): array
    {
        $transformed = [
            'type' => $bundle['type'] ?? 'unknown',
            'total' => $bundle['total'] ?? 0,
            'timestamp' => $bundle['timestamp'] ?? now()->toIso8601String(),
            'entries' => [],
            'links' => []
        ];
        
        // Transform entries
        if (isset($bundle['entry']) && is_array($bundle['entry'])) {
            $transformed['entries'] = array_map(function ($entry) {
                return $this->transform($entry['resource'] ?? []);
            }, $bundle['entry']);
        }
        
        // Extract pagination links
        if (isset($bundle['link']) && is_array($bundle['link'])) {
            foreach ($bundle['link'] as $link) {
                $relation = $link['relation'] ?? '';
                if (in_array($relation, ['self', 'next', 'previous', 'first', 'last'])) {
                    $transformed['links'][$relation] = $link['url'] ?? '';
                }
            }
        }
        
        return $transformed;
    }

    /**
     * Transform Patient resource
     */
    private function transformPatient(array $patient): array
    {
        return [
            'id' => $patient['id'] ?? null,
            'resourceType' => 'Patient',
            'identifier' => $this->extractIdentifiers($patient['identifier'] ?? []),
            'name' => $this->extractHumanName($patient['name'] ?? []),
            'displayName' => $this->getDisplayName($patient['name'] ?? []),
            'gender' => $patient['gender'] ?? 'unknown',
            'birthDate' => $patient['birthDate'] ?? null,
            'age' => $this->calculateAge($patient['birthDate'] ?? null),
            'contact' => $this->extractContactInfo($patient),
            'address' => $this->extractAddress($patient['address'] ?? []),
            'active' => $patient['active'] ?? true,
            'meta' => $this->extractMeta($patient['meta'] ?? []),
            'extensions' => $this->extractExtensions($patient['extension'] ?? [])
        ];
    }

    /**
     * Transform Practitioner resource
     */
    private function transformPractitioner(array $practitioner): array
    {
        return [
            'id' => $practitioner['id'] ?? null,
            'resourceType' => 'Practitioner',
            'identifier' => $this->extractIdentifiers($practitioner['identifier'] ?? []),
            'npi' => $this->extractNpi($practitioner['identifier'] ?? []),
            'name' => $this->extractHumanName($practitioner['name'] ?? []),
            'displayName' => $this->getDisplayName($practitioner['name'] ?? []),
            'contact' => $this->extractTelecom($practitioner['telecom'] ?? []),
            'qualification' => $this->extractQualifications($practitioner['qualification'] ?? []),
            'active' => $practitioner['active'] ?? true,
            'meta' => $this->extractMeta($practitioner['meta'] ?? [])
        ];
    }

    /**
     * Transform Organization resource
     */
    private function transformOrganization(array $organization): array
    {
        return [
            'id' => $organization['id'] ?? null,
            'resourceType' => 'Organization',
            'identifier' => $this->extractIdentifiers($organization['identifier'] ?? []),
            'npi' => $this->extractNpi($organization['identifier'] ?? []),
            'name' => $organization['name'] ?? 'Unknown Organization',
            'type' => $this->extractCodeableConcepts($organization['type'] ?? []),
            'contact' => $this->extractTelecom($organization['telecom'] ?? []),
            'address' => $this->extractAddress($organization['address'] ?? []),
            'active' => $organization['active'] ?? true,
            'meta' => $this->extractMeta($organization['meta'] ?? [])
        ];
    }

    /**
     * Transform EpisodeOfCare resource
     */
    private function transformEpisodeOfCare(array $episode): array
    {
        return [
            'id' => $episode['id'] ?? null,
            'resourceType' => 'EpisodeOfCare',
            'status' => $episode['status'] ?? 'unknown',
            'type' => $this->extractCodeableConcepts($episode['type'] ?? []),
            'patient' => $this->extractReference($episode['patient'] ?? []),
            'managingOrganization' => $this->extractReference($episode['managingOrganization'] ?? []),
            'period' => $this->extractPeriod($episode['period'] ?? []),
            'diagnosis' => $this->extractDiagnosis($episode['diagnosis'] ?? []),
            'team' => $this->extractReferences($episode['team'] ?? []),
            'meta' => $this->extractMeta($episode['meta'] ?? [])
        ];
    }

    /**
     * Transform Coverage resource
     */
    private function transformCoverage(array $coverage): array
    {
        return [
            'id' => $coverage['id'] ?? null,
            'resourceType' => 'Coverage',
            'status' => $coverage['status'] ?? 'unknown',
            'type' => $this->extractCodeableConcept($coverage['type'] ?? []),
            'policyType' => $this->getPolicyType($coverage),
            'subscriber' => $this->extractReference($coverage['subscriber'] ?? []),
            'subscriberId' => $coverage['subscriberId'] ?? null,
            'beneficiary' => $this->extractReference($coverage['beneficiary'] ?? []),
            'payor' => $this->extractPayors($coverage['payor'] ?? []),
            'period' => $this->extractPeriod($coverage['period'] ?? []),
            'order' => $coverage['order'] ?? 1,
            'class' => $this->extractCoverageClasses($coverage['class'] ?? []),
            'meta' => $this->extractMeta($coverage['meta'] ?? [])
        ];
    }

    /**
     * Transform generic resource
     */
    private function transformGeneric(array $resource): array
    {
        return [
            'id' => $resource['id'] ?? null,
            'resourceType' => $resource['resourceType'] ?? 'Unknown',
            'meta' => $this->extractMeta($resource['meta'] ?? []),
            'data' => $resource
        ];
    }

    /**
     * Extract identifiers
     */
    private function extractIdentifiers(array $identifiers): array
    {
        return array_map(function ($identifier) {
            return [
                'system' => $identifier['system'] ?? null,
                'value' => $identifier['value'] ?? null,
                'type' => $this->extractCodeableConcept($identifier['type'] ?? []),
                'use' => $identifier['use'] ?? null
            ];
        }, $identifiers);
    }

    /**
     * Extract NPI from identifiers
     */
    private function extractNpi(array $identifiers): ?string
    {
        foreach ($identifiers as $identifier) {
            if (($identifier['system'] ?? '') === 'http://hl7.org/fhir/sid/us-npi') {
                return $identifier['value'] ?? null;
            }
        }
        return null;
    }

    /**
     * Extract human name
     */
    private function extractHumanName(array $names): array
    {
        if (empty($names)) {
            return [];
        }
        
        return array_map(function ($name) {
            return [
                'use' => $name['use'] ?? 'official',
                'text' => $name['text'] ?? null,
                'family' => $name['family'] ?? null,
                'given' => $name['given'] ?? [],
                'prefix' => $name['prefix'] ?? [],
                'suffix' => $name['suffix'] ?? []
            ];
        }, $names);
    }

    /**
     * Get display name from name array
     */
    private function getDisplayName(array $names): string
    {
        if (empty($names)) {
            return 'Unknown';
        }
        
        $primaryName = $names[0];
        
        if (isset($primaryName['text'])) {
            return $primaryName['text'];
        }
        
        $parts = [];
        
        if (!empty($primaryName['prefix'])) {
            $parts = array_merge($parts, $primaryName['prefix']);
        }
        
        if (!empty($primaryName['given'])) {
            $parts = array_merge($parts, $primaryName['given']);
        }
        
        if (!empty($primaryName['family'])) {
            $parts[] = $primaryName['family'];
        }
        
        if (!empty($primaryName['suffix'])) {
            $parts = array_merge($parts, $primaryName['suffix']);
        }
        
        return implode(' ', $parts) ?: 'Unknown';
    }

    /**
     * Extract contact information from Patient
     */
    private function extractContactInfo(array $patient): array
    {
        $contact = [
            'phone' => [],
            'email' => []
        ];
        
        if (isset($patient['telecom']) && is_array($patient['telecom'])) {
            foreach ($patient['telecom'] as $telecom) {
                $system = $telecom['system'] ?? '';
                $value = $telecom['value'] ?? '';
                $use = $telecom['use'] ?? 'home';
                
                if ($system === 'phone' && $value) {
                    $contact['phone'][] = [
                        'value' => $value,
                        'use' => $use
                    ];
                } elseif ($system === 'email' && $value) {
                    $contact['email'][] = [
                        'value' => $value,
                        'use' => $use
                    ];
                }
            }
        }
        
        return $contact;
    }

    /**
     * Extract telecom
     */
    private function extractTelecom(array $telecoms): array
    {
        return array_map(function ($telecom) {
            return [
                'system' => $telecom['system'] ?? null,
                'value' => $telecom['value'] ?? null,
                'use' => $telecom['use'] ?? null
            ];
        }, $telecoms);
    }

    /**
     * Extract address
     */
    private function extractAddress(array $addresses): array
    {
        return array_map(function ($address) {
            return [
                'use' => $address['use'] ?? 'home',
                'type' => $address['type'] ?? 'physical',
                'text' => $address['text'] ?? null,
                'line' => $address['line'] ?? [],
                'city' => $address['city'] ?? null,
                'state' => $address['state'] ?? null,
                'postalCode' => $address['postalCode'] ?? null,
                'country' => $address['country'] ?? 'USA'
            ];
        }, $addresses);
    }

    /**
     * Extract CodeableConcept
     */
    private function extractCodeableConcept(array $concept): array
    {
        if (empty($concept)) {
            return [];
        }
        
        return [
            'coding' => $concept['coding'] ?? [],
            'text' => $concept['text'] ?? null
        ];
    }

    /**
     * Extract multiple CodeableConcepts
     */
    private function extractCodeableConcepts(array $concepts): array
    {
        return array_map([$this, 'extractCodeableConcept'], $concepts);
    }

    /**
     * Extract reference
     */
    private function extractReference(array $reference): array
    {
        if (empty($reference)) {
            return [];
        }
        
        return [
            'reference' => $reference['reference'] ?? null,
            'type' => $reference['type'] ?? null,
            'display' => $reference['display'] ?? null
        ];
    }

    /**
     * Extract multiple references
     */
    private function extractReferences(array $references): array
    {
        return array_map([$this, 'extractReference'], $references);
    }

    /**
     * Extract period
     */
    private function extractPeriod(array $period): array
    {
        return [
            'start' => $period['start'] ?? null,
            'end' => $period['end'] ?? null
        ];
    }

    /**
     * Extract meta information
     */
    private function extractMeta(array $meta): array
    {
        return [
            'versionId' => $meta['versionId'] ?? null,
            'lastUpdated' => $meta['lastUpdated'] ?? null,
            'source' => $meta['source'] ?? null,
            'profile' => $meta['profile'] ?? [],
            'security' => $meta['security'] ?? [],
            'tag' => $meta['tag'] ?? []
        ];
    }

    /**
     * Extract extensions
     */
    private function extractExtensions(array $extensions): array
    {
        $extracted = [];
        
        foreach ($extensions as $extension) {
            $url = $extension['url'] ?? '';
            
            // Handle MSC custom extensions
            if (str_starts_with($url, 'http://mscwoundcare.com/')) {
                $key = basename($url);
                $extracted[$key] = $this->extractExtensionValue($extension);
            }
        }
        
        return $extracted;
    }

    /**
     * Extract extension value
     */
    private function extractExtensionValue(array $extension)
    {
        // Handle nested extensions
        if (isset($extension['extension'])) {
            $nested = [];
            foreach ($extension['extension'] as $ext) {
                $key = basename($ext['url'] ?? 'unknown');
                $nested[$key] = $this->extractExtensionValue($ext);
            }
            return $nested;
        }
        
        // Extract simple value
        foreach ($extension as $key => $value) {
            if (str_starts_with($key, 'value')) {
                return $value;
            }
        }
        
        return null;
    }

    /**
     * Calculate age from birth date
     */
    private function calculateAge(?string $birthDate): ?int
    {
        if (!$birthDate) {
            return null;
        }
        
        try {
            return Carbon::parse($birthDate)->age;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract diagnosis from EpisodeOfCare
     */
    private function extractDiagnosis(array $diagnoses): array
    {
        return array_map(function ($diagnosis) {
            return [
                'condition' => $this->extractReference($diagnosis['condition'] ?? []),
                'role' => $this->extractCodeableConcept($diagnosis['role'] ?? []),
                'rank' => $diagnosis['rank'] ?? null
            ];
        }, $diagnoses);
    }

    /**
     * Extract qualifications from Practitioner
     */
    private function extractQualifications(array $qualifications): array
    {
        return array_map(function ($qualification) {
            return [
                'identifier' => $this->extractIdentifiers($qualification['identifier'] ?? []),
                'code' => $this->extractCodeableConcept($qualification['code'] ?? []),
                'period' => $this->extractPeriod($qualification['period'] ?? []),
                'issuer' => $this->extractReference($qualification['issuer'] ?? [])
            ];
        }, $qualifications);
    }

    /**
     * Get policy type from Coverage
     */
    private function getPolicyType(array $coverage): string
    {
        $order = $coverage['order'] ?? 1;
        
        return match($order) {
            1 => 'primary',
            2 => 'secondary',
            3 => 'tertiary',
            default => 'other'
        };
    }

    /**
     * Extract payors from Coverage
     */
    private function extractPayors(array $payors): array
    {
        return array_map([$this, 'extractReference'], $payors);
    }

    /**
     * Extract coverage classes
     */
    private function extractCoverageClasses(array $classes): array
    {
        return array_map(function ($class) {
            return [
                'type' => $this->extractCodeableConcept($class['type'] ?? []),
                'value' => $class['value'] ?? null,
                'name' => $class['name'] ?? null
            ];
        }, $classes);
    }
}
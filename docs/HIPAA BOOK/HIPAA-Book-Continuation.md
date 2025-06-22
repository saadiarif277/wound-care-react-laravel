# Continuation from Chapter 4

        $this->info("\n✅ All tests passed!");
        
        return 0;
    }
    
    private function testEnvironmentVariables(): void
    {
        $required = [
            'AZURE_TENANT_ID',
            'AZURE_CLIENT_ID',
            'AZURE_CLIENT_SECRET',
            'AZURE_FHIR_ENDPOINT',
        ];
        
        foreach ($required as $var) {
            if (empty(env($var))) {
                $this->error("✗ Missing environment variable: {$var}");
                exit(1);
            }
        }
        
        $this->info('✓ Environment variables configured');
    }
    
    private function testOAuthToken(): void
    {
        try {
            $authService = app(FhirAuthService::class);
            $token = $authService->getAccessToken();
            
            if (empty($token)) {
                $this->error('✗ Failed to acquire OAuth token');
                exit(1);
            }
            
            $this->info('✓ OAuth token acquired');
        } catch (\Exception $e) {
            $this->error('✗ OAuth error: ' . $e->getMessage());
            exit(1);
        }
    }
    
    private function testMetadataEndpoint(FhirService $fhirService): void
    {
        try {
            $health = $fhirService->healthCheck();
            
            if ($health['status'] !== 'healthy') {
                $this->error('✗ FHIR service unhealthy: ' . $health['error']);
                exit(1);
            }
            
            $this->info('✓ FHIR metadata endpoint accessible');
            $this->info('  FHIR Version: ' . $health['fhir_version']);
        } catch (\Exception $e) {
            $this->error('✗ Metadata error: ' . $e->getMessage());
            exit(1);
        }
    }
    
    private function testCrudOperations(FhirService $fhirService): void
    {
        $testPatient = [
            'resourceType' => 'Patient',
            'identifier' => [
                [
                    'system' => 'https://mscwoundcare.com/test',
                    'value' => 'TEST-' . uniqid(),
                ],
            ],
            'name' => [
                [
                    'use' => 'official',
                    'family' => 'Test',
                    'given' => ['Connection'],
                ],
            ],
            'gender' => 'unknown',
            'birthDate' => '2000-01-01',
        ];
        
        try {
            // Create
            $created = $fhirService->createResource('Patient', $testPatient);
            $this->info('✓ Test patient created');
            
            // Read
            $retrieved = $fhirService->getResource('Patient', $created['id']);
            $this->info('✓ Test patient retrieved');
            
            // Delete
            $fhirService->deleteResource('Patient', $created['id']);
            $this->info('✓ Test patient deleted');
            
        } catch (\Exception $e) {
            $this->error('✗ CRUD operation failed: ' . $e->getMessage());
            exit(1);
        }
    }
}
```

### Performance Optimization

#### 1. **Connection Pooling**

```php
// app/Services/FhirConnectionPool.php
namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlMultiHandler;

class FhirConnectionPool
{
    private array $clients = [];
    private int $maxConnections;
    
    public function __construct(int $maxConnections = 10)
    {
        $this->maxConnections = $maxConnections;
    }
    
    public function getClient(): Client
    {
        // Reuse existing client if available
        foreach ($this->clients as $client) {
            if (!$client->inUse) {
                $client->inUse = true;
                return $client->instance;
            }
        }
        
        // Create new client if under limit
        if (count($this->clients) < $this->maxConnections) {
            $handler = new CurlMultiHandler([
                'handle_factory' => new \GuzzleHttp\Handler\CurlFactory(50),
            ]);
            
            $stack = HandlerStack::create($handler);
            
            $client = new Client([
                'handler' => $stack,
                'pool_size' => 50,
            ]);
            
            $this->clients[] = (object)[
                'instance' => $client,
                'inUse' => true,
            ];
            
            return $client;
        }
        
        // Wait for available client
        throw new \RuntimeException('Connection pool exhausted');
    }
}
```

#### 2. **Request Batching**

```php
// app/Services/FhirBatchService.php
namespace App\Services;

class FhirBatchService
{
    private FhirService $fhirService;
    
    public function __construct(FhirService $fhirService)
    {
        $this->fhirService = $fhirService;
    }
    
    public function executeBatch(array $requests): array
    {
        $bundle = [
            'resourceType' => 'Bundle',
            'type' => 'batch',
            'entry' => array_map(function ($request) {
                return [
                    'request' => [
                        'method' => $request['method'],
                        'url' => $request['url'],
                    ],
                    'resource' => $request['resource'] ?? null,
                ];
            }, $requests),
        ];
        
        $response = $this->fhirService->createResource('', $bundle);
        
        return $this->parseBatchResponse($response);
    }
    
    private function parseBatchResponse(array $bundle): array
    {
        $results = [];
        
        foreach ($bundle['entry'] ?? [] as $index => $entry) {
            $results[$index] = [
                'status' => $entry['response']['status'] ?? null,
                'resource' => $entry['resource'] ?? null,
                'outcome' => $entry['response']['outcome'] ?? null,
            ];
        }
        
        return $results;
    }
}
```

### Monitoring and Observability

```php
// app/Services/FhirMetricsCollector.php
namespace App\Services;

use Illuminate\Support\Facades\Cache;

class FhirMetricsCollector
{
    private const METRICS_KEY = 'fhir_metrics';
    
    public function recordRequest(string $operation, float $duration, bool $success): void
    {
        $metrics = Cache::get(self::METRICS_KEY, [
            'requests' => [],
            'errors' => [],
            'durations' => [],
        ]);
        
        $metrics['requests'][$operation] = ($metrics['requests'][$operation] ?? 0) + 1;
        
        if (!$success) {
            $metrics['errors'][$operation] = ($metrics['errors'][$operation] ?? 0) + 1;
        }
        
        $metrics['durations'][$operation][] = $duration;
        
        // Keep only last 1000 duration samples
        if (count($metrics['durations'][$operation]) > 1000) {
            array_shift($metrics['durations'][$operation]);
        }
        
        Cache::put(self::METRICS_KEY, $metrics, now()->addHours(24));
    }
    
    public function getMetrics(): array
    {
        $metrics = Cache::get(self::METRICS_KEY, []);
        
        $summary = [];
        
        foreach ($metrics['requests'] ?? [] as $operation => $count) {
            $durations = $metrics['durations'][$operation] ?? [];
            $errors = $metrics['errors'][$operation] ?? 0;
            
            $summary[$operation] = [
                'total_requests' => $count,
                'error_count' => $errors,
                'error_rate' => $count > 0 ? ($errors / $count) : 0,
                'avg_duration' => !empty($durations) ? array_sum($durations) / count($durations) : 0,
                'p95_duration' => $this->percentile($durations, 0.95),
                'p99_duration' => $this->percentile($durations, 0.99),
            ];
        }
        
        return $summary;
    }
    
    private function percentile(array $values, float $percentile): float
    {
        if (empty($values)) {
            return 0;
        }
        
        sort($values);
        $index = ceil($percentile * count($values)) - 1;
        
        return $values[$index] ?? 0;
    }
}
```

### Chapter Summary

Proper FHIR service configuration involves:

1. **Infrastructure Setup**: Creating Azure Health Data Services workspace and FHIR service
2. **Authentication Configuration**: App registration and role assignments
3. **Service Implementation**: Robust client with retry logic and error handling
4. **Testing Infrastructure**: Connection tests and health checks
5. **Performance Optimization**: Connection pooling and request batching
6. **Monitoring**: Metrics collection for observability

With this foundation in place, we can now implement patient resource management, which is covered in the next chapter.

---

## Chapter 5: Patient Resource Management {#chapter-5}

### Creating and Managing FHIR Patient Resources

This chapter focuses on implementing patient resource management in Azure FHIR, including creation, retrieval, updates, and the critical aspect of maintaining PHI separation while ensuring data integrity.

### Patient Data Model

#### 1. **FHIR Patient Resource Structure**

```php
// app/Models/Fhir/PatientResource.php
namespace App\Models\Fhir;

class PatientResource
{
    public static function create(array $data): array
    {
        return [
            'resourceType' => 'Patient',
            'identifier' => self::buildIdentifiers($data),
            'active' => true,
            'name' => self::buildName($data),
            'telecom' => self::buildTelecom($data),
            'gender' => self::mapGender($data['gender'] ?? null),
            'birthDate' => $data['birth_date'] ?? null,
            'address' => self::buildAddress($data),
            'contact' => self::buildEmergencyContacts($data['emergency_contacts'] ?? []),
            'communication' => self::buildCommunication($data),
            'extension' => self::buildExtensions($data),
        ];
    }
    
    private static function buildIdentifiers(array $data): array
    {
        $identifiers = [];
        
        // Medical Record Number (MRN)
        if (!empty($data['mrn'])) {
            $identifiers[] = [
                'use' => 'official',
                'type' => [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                            'code' => 'MR',
                            'display' => 'Medical Record Number',
                        ],
                    ],
                ],
                'system' => 'https://mscwoundcare.com/mrn',
                'value' => $data['mrn'],
                'period' => [
                    'start' => date('Y-m-d'),
                ],
            ];
        }
        
        // Social Security Number (encrypted)
        if (!empty($data['ssn'])) {
            $identifiers[] = [
                'use' => 'official',
                'type' => [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                            'code' => 'SS',
                            'display' => 'Social Security Number',
                        ],
                    ],
                ],
                'system' => 'http://hl7.org/fhir/sid/us-ssn',
                'value' => self::encryptSSN($data['ssn']),
                'period' => [
                    'start' => date('Y-m-d'),
                ],
            ];
        }
        
        // Insurance Member ID
        if (!empty($data['insurance_member_id'])) {
            $identifiers[] = [
                'use' => 'secondary',
                'type' => [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/v2-0203',
                            'code' => 'MB',
                            'display' => 'Member Number',
                        ],
                    ],
                ],
                'system' => 'https://mscwoundcare.com/insurance',
                'value' => $data['insurance_member_id'],
                'assigner' => [
                    'display' => $data['insurance_company'] ?? 'Unknown',
                ],
            ];
        }
        
        return $identifiers;
    }
    
    private static function buildName(array $data): array
    {
        $names = [];
        
        // Official name
        $names[] = [
            'use' => 'official',
            'text' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
            'family' => $data['last_name'] ?? null,
            'given' => array_filter([
                $data['first_name'] ?? null,
                $data['middle_name'] ?? null,
            ]),
            'prefix' => array_filter([$data['prefix'] ?? null]),
            'suffix' => array_filter([$data['suffix'] ?? null]),
        ];
        
        // Nickname if provided
        if (!empty($data['nickname'])) {
            $names[] = [
                'use' => 'nickname',
                'given' => [$data['nickname']],
            ];
        }
        
        return $names;
    }
    
    private static function buildTelecom(array $data): array
    {
        $telecom = [];
        
        // Phone numbers
        if (!empty($data['phone'])) {
            $telecom[] = [
                'system' => 'phone',
                'value' => self::normalizePhone($data['phone']),
                'use' => 'home',
                'rank' => 1,
            ];
        }
        
        if (!empty($data['mobile'])) {
            $telecom[] = [
                'system' => 'phone',
                'value' => self::normalizePhone($data['mobile']),
                'use' => 'mobile',
                'rank' => 2,
            ];
        }
        
        // Email
        if (!empty($data['email'])) {
            $telecom[] = [
                'system' => 'email',
                'value' => $data['email'],
                'use' => 'home',
            ];
        }
        
        return $telecom;
    }
    
    private static function buildAddress(array $data): array
    {
        $addresses = [];
        
        if (!empty($data['address_line_1'])) {
            $addresses[] = [
                'use' => 'home',
                'type' => 'both',
                'text' => self::formatFullAddress($data),
                'line' => array_filter([
                    $data['address_line_1'] ?? null,
                    $data['address_line_2'] ?? null,
                ]),
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'postalCode' => $data['zip_code'] ?? null,
                'country' => 'USA',
                'period' => [
                    'start' => $data['address_start_date'] ?? date('Y-m-d'),
                ],
            ];
        }
        
        return $addresses;
    }
    
    private static function mapGender(?string $gender): string
    {
        $genderMap = [
            'M' => 'male',
            'F' => 'female',
            'O' => 'other',
            'U' => 'unknown',
        ];
        
        return $genderMap[strtoupper($gender ?? '')] ?? 'unknown';
    }
}
```

### Patient Service Implementation

#### 1. **Complete Patient Service**

```php
// app/Services/PatientService.php
namespace App\Services;

use App\Models\Fhir\PatientResource;
use App\Services\FhirService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientService
{
    private FhirService $fhirService;
    private AuditService $auditService;
    
    public function __construct(FhirService $fhirService, AuditService $auditService)
    {
        $this->fhirService = $fhirService;
        $this->auditService = $auditService;
    }
    
    public function createPatient(array $data): array
    {
        DB::beginTransaction();
        
        try {
            // Generate display ID for UI (no PHI)
            $displayId = $this->generateDisplayId($data);
            
            // Create FHIR patient resource
            $fhirPatient = PatientResource::create($data);
            
            // Send to Azure FHIR
            $createdPatient = $this->fhirService->createResource('Patient', $fhirPatient);
            
            // Store reference in local database
            $patientRef = \App\Models\PatientReference::create([
                'fhir_id' => $createdPatient['id'],
                'display_id' => $displayId,
                'created_by' => auth()->id(),
            ]);
            
            // Audit the creation
            $this->auditService->logPatientCreation(
                $createdPatient['id'],
                auth()->user(),
                'Patient created via web interface'
            );
            
            DB::commit();
            
            return [
                'fhir_id' => $createdPatient['id'],
                'display_id' => $displayId,
                'patient' => $createdPatient,
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error('Patient creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }
    
    public function getPatient(string $fhirId): array
    {
        try {
            // Get from FHIR
            $patient = $this->fhirService->getResource('Patient', $fhirId);
            
            // Get local reference data
            $reference = \App\Models\PatientReference::where('fhir_id', $fhirId)->first();
            
            // Audit the access
            $this->auditService->logPatientAccess(
                $fhirId,
                auth()->user(),
                'Patient record viewed'
            );
            
            return [
                'patient' => $patient,
                'display_id' => $reference?->display_id,
                'local_data' => $this->getLocalPatientData($fhirId),
            ];
            
        } catch (\Exception $e) {
            Log::error('Patient retrieval failed', [
                'fhir_id' => $fhirId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    public function updatePatient(string $fhirId, array $updates): array
    {
        DB::beginTransaction();
        
        try {
            // Get current patient
            $currentPatient = $this->fhirService->getResource('Patient', $fhirId);
            
            // Merge updates
            $updatedPatient = $this->mergePatientUpdates($currentPatient, $updates);
            
            // Send update to FHIR
            $result = $this->fhirService->updateResource('Patient', $fhirId, $updatedPatient);
            
            // Audit the update
            $this->auditService->logPatientUpdate(
                $fhirId,
                auth()->user(),
                $updates,
                'Patient record updated'
            );
            
            DB::commit();
            
            return $result;
            
        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error('Patient update failed', [
                'fhir_id' => $fhirId,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    public function searchPatients(array $criteria): array
    {
        try {
            // Build FHIR search parameters
            $searchParams = $this->buildSearchParameters($criteria);
            
            // Execute search
            $results = $this->fhirService->searchResources('Patient', $searchParams);
            
            // Enhance with local data
            $enhancedResults = $this->enhanceSearchResults($results['resources']);
            
            // Audit the search
            $this->auditService->logPatientSearch(
                auth()->user(),
                $criteria,
                count($results['resources'])
            );
            
            return [
                'patients' => $enhancedResults,
                'total' => $results['total'],
                'links' => $results['link'] ?? [],
            ];
            
        } catch (\Exception $e) {
            Log::error('Patient search failed', [
                'criteria' => $criteria,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    private function generateDisplayId(array $data): string
    {
        // Use initials + sequential number (no PHI)
        $initials = strtoupper(
            substr($data['first_name'] ?? '', 0, 1) .
            substr($data['last_name'] ?? '', 0, 1)
        );
        
        // Get next sequence number
        $sequence = DB::table('patient_sequences')
            ->where('prefix', $initials)
            ->lockForUpdate()
            ->first();
            
        if ($sequence) {
            $nextNumber = $sequence->last_number + 1;
            DB::table('patient_sequences')
                ->where('prefix', $initials)
                ->update(['last_number' => $nextNumber]);
        } else {
            $nextNumber = 1;
            DB::table('patient_sequences')->insert([
                'prefix' => $initials,
                'last_number' => $nextNumber,
            ]);
        }
        
        return sprintf('%s%04d', $initials, $nextNumber);
    }
    
    private function buildSearchParameters(array $criteria): array
    {
        $params = [];
        
        // Name search
        if (!empty($criteria['name'])) {
            $params['name'] = $criteria['name'];
        }
        
        // Identifier search (MRN, SSN, etc.)
        if (!empty($criteria['identifier'])) {
            $params['identifier'] = $criteria['identifier'];
        }
        
        // Date of birth
        if (!empty($criteria['birthdate'])) {
            $params['birthdate'] = $criteria['birthdate'];
        }
        
        // Gender
        if (!empty($criteria['gender'])) {
            $params['gender'] = $criteria['gender'];
        }
        
        // Phone
        if (!empty($criteria['phone'])) {
            $params['phone'] = $criteria['phone'];
        }
        
        // Pagination
        if (!empty($criteria['page'])) {
            $params['_count'] = $criteria['per_page'] ?? 20;
            $params['_offset'] = (($criteria['page'] - 1) * $params['_count']);
        }
        
        return $params;
    }
}
```

### Patient Data Validation

#### 1. **Validation Rules**

```php
// app/Http/Requests/CreatePatientRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePatientRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'middle_name' => 'nullable|string|max:50',
            'prefix' => 'nullable|string|max:20',
            'suffix' => 'nullable|string|max:20',
            'gender' => 'required|in:M,F,O,U',
            'birth_date' => 'required|date|before:today',
            'ssn' => 'nullable|string|regex:/^\d{3}-?\d{2}-?\d{4}$/',
            'mrn' => 'nullable|string|max:50',
            
            // Contact Information
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            
            // Address
            'address_line_1' => 'nullable|string|max:100',
            'address_line_2' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:50',
            'state' => 'nullable|string|size:2',
            'zip_code' => 'nullable|string|regex:/^\d{5}(-\d{4})?$/',
            
            // Insurance
            'insurance_company' => 'nullable|string|max:100',
            'insurance_member_id' => 'nullable|string|max:50',
            'insurance_group_id' => 'nullable|string|max:50',
            
            // Emergency Contact
            'emergency_contacts' => 'nullable|array',
            'emergency_contacts.*.name' => 'required|string|max:100',
            'emergency_contacts.*.relationship' => 'required|string|max:50',
            'emergency_contacts.*.phone' => 'required|string|max:20',
        ];
    }
    
    public function messages(): array
    {
        return [
            'birth_date.before' => 'Birth date must be in the past',
            'ssn.regex' => 'SSN must be in format XXX-XX-XXXX',
            'state.size' => 'State must be 2-letter abbreviation',
            'zip_code.regex' => 'ZIP code must be in format XXXXX or XXXXX-XXXX',
        ];
    }
    
    protected function prepareForValidation(): void
    {
        // Normalize SSN format
        if ($this->filled('ssn')) {
            $ssn = preg_replace('/[^0-9]/', '', $this->ssn);
            if (strlen($ssn) === 9) {
                $this->merge([
                    'ssn' => substr($ssn, 0, 3) . '-' . substr($ssn, 3, 2) . '-' . substr($ssn, 5),
                ]);
            }
        }
        
        // Uppercase state
        if ($this->filled('state')) {
            $this->merge([
                'state' => strtoupper($this->state),
            ]);
        }
    }
}
```

### Patient Privacy Controls

#### 1. **Consent Management**

```php
// app/Services/PatientConsentService.php
namespace App\Services;

class PatientConsentService
{
    private FhirService $fhirService;
    
    public function recordConsent(
        string $patientId,
        string $consentType,
        array $provisions
    ): array {
        $consent = [
            'resourceType' => 'Consent',
            'status' => 'active',
            'scope' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/consentscope',
                        'code' => 'patient-privacy',
                    ],
                ],
            ],
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://loinc.org',
                            'code' => '59284-0',
                            'display' => 'Patient Consent',
                        ],
                    ],
                ],
            ],
            'patient' => [
                'reference' => "Patient/{$patientId}",
            ],
            'dateTime' => now()->toIso8601String(),
            'provision' => $this->buildProvisions($provisions),
        ];
        
        return $this->fhirService->createResource('Consent', $consent);
    }
    
    private function buildProvisions(array $provisions): array
    {
        return [
            'type' => $provisions['type'] ?? 'permit',
            'period' => [
                'start' => $provisions['start_date'] ?? now()->toDateString(),
                'end' => $provisions['end_date'] ?? null,
            ],
            'actor' => array_map(function ($actor) {
                return [
                    'role' => [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/v3-ParticipationType',
                                'code' => $actor['role'],
                            ],
                        ],
                    ],
                    'reference' => [
                        'reference' => $actor['reference'],
                    ],
                ];
            }, $provisions['actors'] ?? []),
            'action' => array_map(function ($action) {
                return [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/consentaction',
                            'code' => $action,
                        ],
                    ],
                ];
            }, $provisions['actions'] ?? []),
        ];
    }
}
```

### Chapter Summary

Patient resource management in FHIR requires:

1. **Structured Data Models**: Following FHIR R4 specifications for patient resources
2. **PHI Protection**: Separating identifiable information from operational data
3. **Display IDs**: Using non-PHI identifiers for UI display
4. **Comprehensive Validation**: Ensuring data quality and format compliance
5. **Audit Trails**: Tracking all patient data access and modifications
6. **Consent Management**: Recording and enforcing patient privacy preferences
7. **Search Capabilities**: Enabling efficient patient lookup while maintaining security

The next chapter explores clinical data storage patterns for managing medical information associated with patients.

---

## Chapter 6: Clinical Data Storage Patterns {#chapter-6}

### Storing Clinical Data in Azure FHIR

This chapter covers the implementation of clinical data storage patterns, including observations, conditions, procedures, and the creation of comprehensive clinical bundles that maintain referential integrity while ensuring HIPAA compliance.

### Clinical Data Architecture

#### 1. **FHIR Resource Hierarchy**

```
Patient (Root)
├── Condition (Diagnoses)
├── Observation (Measurements, Lab Results)
├── Procedure (Treatments, Surgeries)
├── MedicationRequest (Prescriptions)
├── DocumentReference (Clinical Documents)
└── Bundle (Grouped Clinical Data)
```

### Observation Resources

#### 1. **Wound Observation Model**

```php
// app/Models/Fhir/WoundObservation.php
namespace App\Models\Fhir;

class WoundObservation
{
    public static function create(array $data): array
    {
        return [
            'resourceType' => 'Observation',
            'status' => 'final',
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
                            'code' => 'exam',
                            'display' => 'Exam',
                        ],
                    ],
                ],
            ],
            'code' => [
                'coding' => [
                    [
                        'system' => 'http://snomed.info/sct',
                        'code' => '225552003',
                        'display' => 'Wound assessment',
                    ],
                ],
                'text' => 'Wound Assessment',
            ],
            'subject' => [
                'reference' => "Patient/{$data['patient_id']}",
            ],
            'effectiveDateTime' => $data['assessment_date'] ?? now()->toIso8601String(),
            'issued' => now()->toIso8601String(),
            'performer' => self::buildPerformer($data['performer'] ?? []),
            'component' => self::buildWoundComponents($data),
        ];
    }
    
    private static function buildWoundComponents(array $data): array
    {
        $components = [];
        
        // Wound dimensions
        if (!empty($data['length'])) {
            $components[] = [
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://loinc.org',
                            'code' => '39126-8',
                            'display' => 'Wound length',
                        ],
                    ],
                ],
                'valueQuantity' => [
                    'value' => $data['length'],
                    'unit' => 'cm',
                    'system' => 'http://unitsofmeasure.org',
                    'code' => 'cm',
                ],
            ];
        }
        
        if (!empty($data['width'])) {
            $components[] = [
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://loinc.org',
                            'code' => '39125-0',
                            'display' => 'Wound width',
                        ],
                    ],
                ],
                'valueQuantity' => [
                    'value' => $data['width'],
                    'unit' => 'cm',
                    'system' => 'http://unitsofmeasure.org',
                    'code' => 'cm',
                ],
            ];
        }
        
        if (!empty($data['depth'])) {
            $components[] = [
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://loinc.org',
                            'code' => '39127-6',
                            'display' => 'Wound depth',
                        ],
                    ],
                ],
                'valueQuantity' => [
                    'value' => $data['depth'],
                    'unit' => 'cm',
                    'system' => 'http://unitsofmeasure.org',
                    'code' => 'cm',
                ],
            ];
        }
        
        // Wound characteristics
        if (!empty($data['wound_bed'])) {
            $components[] = [
                'code' => [
                    'coding' => [
                        [
                            'system' => 'http://loinc.org',
                            'code' => '72371-8',
                            'display' => 'Wound bed appearance',
                        ],
                    ],
                ],
                'valueString' => $data['wound_bed'],
            ];
        }
        
        return $components;
    }
}
```

#### 2. **Lab Result Observations**

```php
// app/Models/Fhir/LabObservation.php
namespace App\Models\Fhir;

class LabObservation
{
    public static function create(array $data): array
    {
        return [
            'resourceType' => 'Observation',
            'status' => 'final',
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/observation-category',
                            'code' => 'laboratory',
                            'display' => 'Laboratory',
                        ],
                    ],
                ],
            ],
            'code' => self::getLabCode($data['test_type']),
            'subject' => [
                'reference' => "Patient/{$data['patient_id']}",
            ],
            'effectiveDateTime' => $data['collection_date'],
            'issued' => $data['result_date'] ?? now()->toIso8601String(),
            'performer' => [
                [
                    'display' => $data['lab_name'] ?? 'Clinical Laboratory',
                ],
            ],
            'valueQuantity' => [
                'value' => $data['value'],
                'unit' => $data['unit'],
                'system' => 'http://unitsofmeasure.org',
                'code' => $data['unit_code'],
            ],
            'interpretation' => self::getInterpretation($data),
            'referenceRange' => self::getReferenceRange($data),
        ];
    }
    
    private static function getLabCode(string $testType): array
    {
        $codes = [
            'hemoglobin' => [
                'system' => 'http://loinc.org',
                'code' => '718-7',
                'display' => 'Hemoglobin [Mass/volume] in Blood',
            ],
            'hba1c' => [
                'system' => 'http://loinc.org',
                'code' => '4548-4',
                'display' => 'Hemoglobin A1c/Hemoglobin.total in Blood',
            ],
            'albumin' => [
                'system' => 'http://loinc.org',
                'code' => '1751-7',
                'display' => 'Albumin [Mass/volume] in Serum or Plasma',
            ],
            'creatinine' => [
                'system' => 'http://loinc.org',
                'code' => '2160-0',
                'display' => 'Creatinine [Mass/volume] in Serum or Plasma',
            ],
        ];
        
        return [
            'coding' => [
                $codes[$testType] ?? [
                    'system' => 'http://loinc.org',
                    'code' => 'unknown',
                    'display' => 'Unknown test',
                ],
            ],
        ];
    }
}
```

### Condition Resources

#### 1. **Wound Condition Model**

```php
// app/Models/Fhir/WoundCondition.php
namespace App\Models\Fhir;

class WoundCondition
{
    public static function create(array $data): array
    {
        return [
            'resourceType' => 'Condition',
            'clinicalStatus' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                        'code' => $data['status'] ?? 'active',
                    ],
                ],
            ],
            'verificationStatus' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/condition-ver-status',
                        'code' => 'confirmed',
                    ],
                ],
            ],
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/condition-category',
                            'code' => 'problem-list-item',
                            'display' => 'Problem List Item',
                        ],
                    ],
                ],
            ],
            'severity' => self::getSeverity($data['severity'] ?? null),
            'code' => self::getWoundCode($data['wound_type']),
            'bodySite' => self::getBodySite($data['location']),
            'subject' => [
                'reference' => "Patient/{$data['patient_id']}",
            ],
            'onsetDateTime' => $data['onset_date'] ?? null,
            'recordedDate' => now()->toIso8601String(),
            'recorder' => [
                'reference' => "Practitioner/{$data['practitioner_id']}",
            ],
            'note' => [
                [
                    'text' => $data['clinical_notes'] ?? '',
                ],
            ],
            'stage' => self::getWoundStage($data['stage'] ?? null),
        ];
    }
    
    private static function getWoundCode(string $woundType): array
    {
        $codes = [
            'pressure_ulcer' => [
                'system' => 'http://snomed.info/sct',
                'code' => '420226006',
                'display' => 'Pressure ulcer',
            ],
            'diabetic_ulcer' => [
                'system' => 'http://snomed.info/sct',
                'code' => '371081005',
                'display' => 'Diabetic foot ulcer',
            ],
            'venous_ulcer' => [
                'system' => 'http://snomed.info/sct',
                'code' => '420014009',
                'display' => 'Venous ulcer',
            ],
            'arterial_ulcer' => [
                'system' => 'http://snomed.info/sct',
                'code' => '400047006',
                'display' => 'Arterial ulcer',
            ],
            'surgical_wound' => [
                'system' => 'http://snomed.info/sct',
                'code' => '449753006',
                'display' => 'Surgical wound',
            ],
        ];
        
        return [
            'coding' => [
                $codes[$woundType] ?? [
                    'system' => 'http://snomed.info/sct',
                    'code' => '95345007',
                    'display' => 'Wound',
                ],
            ],
        ];
    }
}
```

### Clinical Bundle Creation

#### 1. **Bundle Service**

```php
// app/Services/ClinicalBundleService.php
namespace App\Services;

use App\Models\Fhir\WoundObservation;
use App\Models\Fhir\WoundCondition;
use App\Models\Fhir\LabObservation;

class ClinicalBundleService
{
    private FhirService $fhirService;
    
    public function __construct(FhirService $fhirService)
    {
        $this->fhirService = $fhirService;
    }
    
    public function createClinicalAssessmentBundle(array $assessmentData): array
    {
        $bundle = [
            'resourceType' => 'Bundle',
            'type' => 'transaction',
            'entry' => [],
        ];
        
        // Add primary condition
        if (!empty($assessmentData['wound_type'])) {
            $condition = WoundCondition::create([
                'patient_id' => $assessmentData['patient_id'],
                'wound_type' => $assessmentData['wound_type'],
                'location' => $assessmentData['wound_location'],
                'onset_date' => $assessmentData['wound_onset_date'],
                'severity' => $assessmentData['wound_severity'],
                'stage' => $assessmentData['wound_stage'],
            ]);
            
            $bundle['entry'][] = [
                'fullUrl' => 'urn:uuid:' . $this->generateUuid(),
                'resource' => $condition,
                'request' => [
                    'method' => 'POST',
                    'url' => 'Condition',
                ],
            ];
        }
        
        // Add wound measurements
        if (!empty($assessmentData['wound_measurements'])) {
            $observation = WoundObservation::create([
                'patient_id' => $assessmentData['patient_id'],
                'assessment_date' => $assessmentData['assessment_date'],
                'length' => $assessmentData['wound_measurements']['length'],
                'width' => $assessmentData['wound_measurements']['width'],
                'depth' => $assessmentData['wound_measurements']['depth'],
                'wound_bed' => $assessmentData['wound_measurements']['wound_bed'],
            ]);
            
            $bundle['entry'][] = [
                'fullUrl' => 'urn:uuid:' . $this->generateUuid(),
                'resource' => $observation,
                'request' => [
                    'method' => 'POST',
                    'url' => 'Observation',
                ],
            ];
        }
        
        // Add lab results
        foreach ($assessmentData['lab_results'] ?? [] as $lab) {
            $labObs = LabObservation::create([
                'patient_id' => $assessmentData['patient_id'],
                'test_type' => $lab['test_type'],
                'value' => $lab['value'],
                'unit' => $lab['unit'],
                'unit_code' => $lab['unit_code'],
                'collection_date' => $lab['collection_date'],
                'result_date' => $lab['result_date'],
            ]);
            
            $bundle['entry'][] = [
                'fullUrl' => 'urn:uuid:' . $this->generateUuid(),
                'resource' => $labObs,
                'request' => [
                    'method' => 'POST',
                    'url' => 'Observation',
                ],
            ];
        }
        
        // Add document reference
        $documentRef = $this->createDocumentReference($assessmentData);
        $bundle['entry'][] = [
            'fullUrl' => 'urn:uuid:' . $this->generateUuid(),
            'resource' => $documentRef,
            'request' => [
                'method' => 'POST',
                'url' => 'DocumentReference',
            ],
        ];
        
        // Execute bundle transaction
        return $this->fhirService->createResource('', $bundle);
    }
    
    private function createDocumentReference(array $data): array
    {
        return [
            'resourceType' => 'DocumentReference',
            'status' => 'current',
            'type' => [
                'coding' => [
                    [
                        'system' => 'http://loinc.org',
                        'code' => '34133-9',
                        'display' => 'Summary of episode note',
                    ],
                ],
            ],
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => 'http://hl7.org/fhir/us/core/CodeSystem/us-core-documentreference-category',
                            'code' => 'clinical-note',
                            'display' => 'Clinical Note',
                        ],
                    ],
                ],
            ],
            'subject' => [
                'reference' => "Patient/{$data['patient_id']}",
            ],
            'date' => now()->toIso8601String(),
            'author' => [
                [
                    'reference' => "Practitioner/{$data['practitioner_id']}",
                ],
            ],
            'content' => [
                [
                    'attachment' => [
                        'contentType' => 'application/pdf',
                        'url' => $data['document_url'] ?? null,
                        'title' => 'Clinical Assessment Document',
                        'creation' => now()->toIso8601String(),
                    ],
                ],
            ],
            'context' => [
                'encounter' => [
                    'reference' => "Encounter/{$data['encounter_id']}",
                ],
                'period' => [
                    'start' => $data['assessment_date'],
                    'end' => $data['assessment_date'],
                ],
            ],
        ];
    }
    
    private function generateUuid(): string
    {
        return \Ramsey\Uuid\Uuid::uuid4()->toString();
    }
}
```

### Clinical Data Retrieval

#### 1. **Comprehensive Patient Clinical Data**

```php
// app/Services/PatientClinicalDataService.php
namespace App\Services;

class PatientClinicalDataService
{
    private FhirService $fhirService;
    private CacheService $cache;
    
    public function __construct(FhirService $fhirService, CacheService $cache)
    {
        $this->fhirService = $fhirService;
        $this->cache = $cache;
    }
    
    public function getCompleteClinicalRecord(string $patientId): array
    {
        $cacheKey = "clinical_record:{$patientId}";
        
        return $this->cache->remember($cacheKey, 300, function () use ($patientId) {
            return [
                'patient' => $this->getPatientData($patientId),
                'conditions' => $this->getConditions($patientId),
                'observations' => $this->getObservations($patientId),
                'procedures' => $this->getProcedures($patientId),
                'medications' => $this->getMedications($patientId),
                'documents' => $this->getDocuments($patientId),
                'timeline' => $this->buildClinicalTimeline($patientId),
            ];
        });
    }
    
    private function getConditions(string $patientId): array
    {
        $results = $this->fhirService->searchResources('Condition', [
            'patient' => $patientId,
            '_sort' => '-onset-date',
            '_count' => 100,
        ]);
        
        return array_map(function ($condition) {
            return [
                'id' => $condition['id'],
                'code' => $condition['code']['coding'][0]['display'] ?? 'Unknown',
                'status' => $condition['clinicalStatus']['coding'][0]['code'] ?? 'unknown',
                'onset' => $condition['onsetDateTime'] ?? null,
                'severity' => $condition['severity']['coding'][0]['display'] ?? null,
                'notes' => $condition['note'][0]['text'] ?? null,
            ];
        }, $results['resources']);
    }
    
    private function getObservations(string $patientId): array
    {
        $results = $this->fhirService->searchResources('Observation', [
            'patient' => $patientId,
            '_sort' => '-date',
            '_count' => 100,
        ]);
        
        return array_map(function ($observation) {
            return [
                'id' => $observation['id'],
                'type' => $observation['code']['coding'][0]['display'] ?? 'Unknown',
                'value' => $this->extractObservationValue($observation),
                'date' => $observation['effectiveDateTime'] ?? null,
                'status' => $observation['status'],
                'interpretation' => $observation['interpretation'][0]['coding'][0]['display'] ?? null,
            ];
        }, $results['resources']);
    }
    
    private function buildClinicalTimeline(string $patientId): array
    {
        $events = [];
        
        // Add conditions to timeline
        $conditions = $this->getConditions($patientId);
        foreach ($conditions as $condition) {
            if ($condition['onset']) {
                $events[] = [
                    'date' => $condition['onset'],
                    'type' => 'condition',
                    'title' => $condition['code'],
                    'description' => "Diagnosed with {$condition['code']}",
                    'severity' => $condition['severity'],
                    'resource_id' => $condition['id'],
                ];
            }
        }
        
        // Add observations to timeline
        $observations = $this->getObservations($patientId);
        foreach ($observations as $observation) {
            if ($observation['date']) {
                $events[] = [
                    'date' => $observation['date'],
                    'type' => 'observation',
                    'title' => $observation['type'],
                    'description' => "{$observation['type']}: {$observation['value']}",
                    'resource_id' => $observation['id'],
                ];
            }
        }
        
        // Sort by date descending
        usort($events, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $events;
    }
}
```

### Chapter Summary

Clinical data storage in FHIR requires:

1. **Resource Modeling**: Creating appropriate FHIR resources for different clinical data types
2. **Standardized Coding**: Using SNOMED CT, LOINC, and other standard terminologies
3. **Bundle Transactions**: Grouping related clinical data for atomic operations
4. **Referential Integrity**: Maintaining relationships between resources
5. **Efficient Retrieval**: Optimizing queries and implementing caching strategies
6. **Clinical Timeline**: Building comprehensive patient history views
7. **Data Validation**: Ensuring clinical data meets FHIR specifications

The next chapter explores integration patterns for connecting FHIR services with other systems.

---

## Chapter 7: Integration Patterns {#chapter-7}

### Integrating FHIR with External Systems

This chapter covers integration patterns for connecting Azure FHIR services with external systems, including EHRs, billing systems, document management, and third-party APIs while maintaining HIPAA compliance.

### Integration Architecture

#### 1. **Integration Layer Design**

```php
// app/Services/Integration/IntegrationService.php
namespace App\Services\Integration;

abstract class IntegrationService
{
    protected $config;
    protected $logger;
    protected $encryptor;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->logger = app('log');
        $this->encryptor = app(EncryptionService::class);
    }
    
    abstract public function authenticate(): bool;
    abstract public function sendData(array $data): array;
    abstract public function receiveData(array $params): array;
    abstract public function validateConnection(): bool;
    
    protected function logIntegration(string $action, array $data): void
    {
        $this->logger->info('Integration activity', [
            'service' => static::class,
            'action' => $action,
            'timestamp' => now()->toIso8601String(),
            'data_hash' => hash('sha256', json_encode($data)),
        ]);
    }
    
    protected function encryptSensitiveData(array $data): array
    {
        $sensitiveFields = $this->config['sensitive_fields'] ?? [];
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = $this->encryptor->encrypt($data[$field]);
            }
        }
        
        return $data;
    }
}
```

### EHR Integration

#### 1. **Epic Integration Service**

```php
// app/Services/Integration/EpicIntegrationService.php
namespace App\Services\Integration;

use App\Services\FhirService;
use GuzzleHttp\Client;

class EpicIntegrationService extends IntegrationService
{
    private Client $httpClient;
    private FhirService $fhirService;
    private ?string $accessToken = null;
    
    public function __construct(array $config, FhirService $fhirService)
    {
        parent::__construct($config);
        $this->fhirService = $fhirService;
        $this->httpClient = new Client([
            'base_uri' => $config['epic_base_url'],
            'timeout' => 30,
        ]);
    }
    
    public function authenticate(): bool
    {
        try {
            $response = $this->httpClient->post('/oauth2/token', [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'scope' => 'patient/*.read patient/*.write',
                ],
            ]);
            
            $data = json_decode($response->getBody(), true);
            $this->accessToken = $data['access_token'];
            
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Epic authentication failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    public function syncPatient(string $epicPatientId): array
    {
        $this->logIntegration('sync_patient_start', ['epic_id' => $epicPatientId]);
        
        try {
            // Get patient from Epic
            $epicPatient = $this->getEpicPatient($epicPatientId);
            
            // Transform to FHIR format
            $fhirPatient = $this->transformEpicToFhir($epicPatient);
            
            // Check if patient exists in our FHIR server
            $existingPatient = $this->findPatientByIdentifier($epicPatientId);
            
            if ($existingPatient) {
                // Update existing patient
                $result = $this->fhirService->updateResource(
                    'Patient',
                    $existingPatient['id'],
                    $fhirPatient
                );
            } else {
                // Create new patient
                $result = $this->fhirService->createResource('Patient', $fhirPatient);
            }
            
            $this->logIntegration('sync_patient_complete', [
                'epic_id' => $epicPatientId,
                'fhir_id' => $result['id'],
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error('Patient sync failed', [
                'epic_id' => $epicPatientId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    private function transformEpicToFhir(array $epicPatient): array
    {
        return [
            'resourceType' => 'Patient',
            'identifier' => [
                [
                    'system' => 'https://epic.hospital.org/patient-id',
                    'value' => $epicPatient['id'],
                ],
                [
                    'system' => 'http://hl7.org/fhir/sid/us-mrn',
                    'value' => $epicPatient['mrn'],
                ],
            ],
            'name' => [
                [
                    'use' => 'official',
                    'family' => $epicPatient['name']['family'],
                    'given' => $epicPatient['name']['given'],
                ],
            ],
            'gender' => strtolower($epicPatient['gender']),
            'birthDate' => $epicPatient['dateOfBirth'],
            'address' => $this->transformAddress($epicPatient['address']),
            'telecom' => $this->transformTelecom($epicPatient['telecom']),
        ];
    }
    
    public function sendClinicalDocument(string $patientId, array $document): array
    {
        $this->logIntegration('send_document_start', [
            'patient_id' => $patientId,
            'document_type' => $document['type'],
        ]);
        
        try {
            // Create Epic-compatible document
            $epicDocument = $this->transformFhirDocumentToEpic($document);
            
            // Send to Epic
            $response = $this->httpClient->post("/patients/{$patientId}/documents", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $epicDocument,
            ]);
            
            $result = json_decode($response->getBody(), true);
            
            $this->logIntegration('send_document_complete', [
                'patient_id' => $patientId,
                'epic_document_id' => $result['id'],
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error('Document send failed', [
                'patient_id' => $patientId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

### Document Management Integration

#### 1. **DocuSeal Integration**

```php
// app/Services/Integration/DocuSealIntegrationService.php
namespace App\Services\Integration;

use App\Services\FhirService;

class DocuSealIntegrationService extends IntegrationService
{
    private FhirService $fhirService;
    
    public function prefillIVRForm(string $patientId, string $templateId): array
    {
        try {
            // Get patient data from FHIR
            $patient = $this->fhirService->getResource('Patient', $patientId);
            
            // Get recent clinical data
            $clinicalData = $this->getRecentClinicalData($patientId);
            
            // Map FHIR data to DocuSeal fields
            $prefillData = $this->mapFhirToDocuSeal($patient, $clinicalData);
            
            // Create DocuSeal submission
            $submission = $this->createDocuSealSubmission($templateId, $prefillData);
            
            // Store reference in FHIR
            $this->createDocumentReference($patientId, $submission['id']);
            
            return $submission;
            
        } catch (\Exception $e) {
            $this->logger->error('DocuSeal prefill failed', [
                'patient_id' => $patientId,
                'template_id' => $templateId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    private function mapFhirToDocuSeal(array $patient, array $clinicalData): array
    {
        $mapping = [
            // Patient Demographics
            'patient_first_name' => $patient['name'][0]['given'][0] ?? '',
            'patient_last_name' => $patient['name'][0]['family'] ?? '',
            'patient_dob' => $patient['birthDate'] ?? '',
            'patient_gender' => $patient['gender'] ?? '',
            
            // Contact Information
            'patient_phone' => $this->extractPhone($patient['telecom'] ?? []),
            'patient_email' => $this->extractEmail($patient['telecom'] ?? []),
            
            // Address
            'patient_address_line1' => $patient['address'][0]['line'][0] ?? '',
            'patient_city' => $patient['address'][0]['city'] ?? '',
            'patient_state' => $patient['address'][0]['state'] ?? '',
            'patient_zip' => $patient['address'][0]['postalCode'] ?? '',
            
            // Clinical Data
            'primary_diagnosis

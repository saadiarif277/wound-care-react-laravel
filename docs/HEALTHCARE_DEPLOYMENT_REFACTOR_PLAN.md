# MSC Wound Portal - Healthcare Deployment Refactoring Plan

## Executive Summary

This document outlines critical refactoring requirements identified through comprehensive analysis of the Quick Request workflow and overall healthcare deployment readiness. The system requires immediate attention to HIPAA compliance, FHIR integration robustness, and production deployment standards.

## 🚨 Critical Issues Requiring Immediate Fix

### 1. FHIR Integration Reliability

**Current State:**
- FHIR failures are silently logged without proper error handling
- No transaction support for multi-resource operations
- Missing retry logic and circuit breaker patterns
- Inconsistent FHIR client implementations

**Required Actions:**

#### a) Implement FHIR Transaction Bundle Support
```php
// app/Services/Fhir/FhirTransactionManager.php
<?php

namespace App\Services\Fhir;

use App\Services\FhirService;
use Illuminate\Support\Facades\Log;

class FhirTransactionManager
{
    private FhirService $fhirService;
    private array $transactionLog = [];

    public function __construct(FhirService $fhirService)
    {
        $this->fhirService = $fhirService;
    }

    public function executeTransaction(array $operations): array
    {
        $bundle = $this->createTransactionBundle($operations);
        
        try {
            $response = $this->fhirService->transactionBundle($bundle);
            $this->logSuccess($response);
            return $this->parseTransactionResponse($response);
        } catch (\Exception $e) {
            $this->logFailure($e);
            throw new FhirTransactionException(
                "FHIR transaction failed: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    private function createTransactionBundle(array $operations): array
    {
        return [
            'resourceType' => 'Bundle',
            'type' => 'transaction',
            'entry' => array_map(function ($op) {
                return [
                    'fullUrl' => $op['tempId'] ?? null,
                    'resource' => $op['resource'],
                    'request' => [
                        'method' => $op['method'] ?? 'POST',
                        'url' => $op['resourceType']
                    ]
                ];
            }, $operations)
        ];
    }

    private function parseTransactionResponse(array $response): array
    {
        $results = [];
        foreach ($response['entry'] ?? [] as $entry) {
            if (isset($entry['response']['location'])) {
                $results[] = [
                    'id' => $this->extractIdFromLocation($entry['response']['location']),
                    'status' => $entry['response']['status'] ?? 'unknown'
                ];
            }
        }
        return $results;
    }

    private function extractIdFromLocation(string $location): string
    {
        $parts = explode('/', $location);
        return $parts[count($parts) - 3] . '/' . $parts[count($parts) - 1];
    }
}
```

#### b) Implement Circuit Breaker for FHIR Service
```php
// app/Services/Fhir/FhirCircuitBreaker.php
<?php

namespace App\Services\Fhir;

use Illuminate\Support\Facades\Cache;
use App\Exceptions\CircuitBreakerOpenException;

class FhirCircuitBreaker
{
    private string $serviceName;
    private int $failureThreshold;
    private int $recoveryTimeout;
    private int $successThreshold;

    public function __construct(
        string $serviceName = 'fhir',
        int $failureThreshold = 5,
        int $recoveryTimeout = 60,
        int $successThreshold = 2
    ) {
        $this->serviceName = $serviceName;
        $this->failureThreshold = $failureThreshold;
        $this->recoveryTimeout = $recoveryTimeout;
        $this->successThreshold = $successThreshold;
    }

    public function call(callable $operation, array $fallback = null)
    {
        $state = $this->getState();

        if ($state === 'open') {
            if ($this->shouldAttemptReset()) {
                $state = 'half-open';
                $this->setState('half-open');
            } else {
                if ($fallback) {
                    return $fallback();
                }
                throw new CircuitBreakerOpenException(
                    "Circuit breaker is open for {$this->serviceName}"
                );
            }
        }

        try {
            $result = $operation();
            $this->onSuccess($state);
            return $result;
        } catch (\Exception $e) {
            $this->onFailure($state);
            throw $e;
        }
    }

    private function getState(): string
    {
        return Cache::get($this->getStateKey(), 'closed');
    }

    private function setState(string $state): void
    {
        Cache::put($this->getStateKey(), $state, $this->recoveryTimeout * 2);
    }

    private function onSuccess(string $currentState): void
    {
        $failures = 0;
        Cache::put($this->getFailureKey(), $failures, $this->recoveryTimeout);

        if ($currentState === 'half-open') {
            $successes = Cache::increment($this->getSuccessKey());
            if ($successes >= $this->successThreshold) {
                $this->setState('closed');
                Cache::forget($this->getSuccessKey());
            }
        }
    }

    private function onFailure(string $currentState): void
    {
        $failures = Cache::increment($this->getFailureKey());

        if ($failures >= $this->failureThreshold) {
            $this->setState('open');
            Cache::put($this->getLastFailureKey(), now(), $this->recoveryTimeout);
        }

        if ($currentState === 'half-open') {
            $this->setState('open');
            Cache::forget($this->getSuccessKey());
        }
    }

    private function shouldAttemptReset(): bool
    {
        $lastFailure = Cache::get($this->getLastFailureKey());
        return $lastFailure && now()->diffInSeconds($lastFailure) >= $this->recoveryTimeout;
    }

    private function getStateKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:state";
    }

    private function getFailureKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:failures";
    }

    private function getSuccessKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:successes";
    }

    private function getLastFailureKey(): string
    {
        return "circuit_breaker:{$this->serviceName}:last_failure";
    }
}
```

### 2. PHI Data Protection Enhancements

#### a) Implement PHI Encryption Service
```php
// app/Services/Security/PhiEncryptionService.php
<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class PhiEncryptionService
{
    private array $phiFields = [
        'ssn',
        'date_of_birth',
        'medical_record_number',
        'insurance_member_id',
        'diagnosis_details',
        'clinical_notes'
    ];

    public function encryptPhiFields(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($this->isPhiField($key) && !empty($value)) {
                $data[$key] = $this->encrypt($value);
            } elseif (is_array($value)) {
                $data[$key] = $this->encryptPhiFields($value);
            }
        }
        return $data;
    }

    public function decryptPhiFields(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($this->isPhiField($key) && !empty($value)) {
                try {
                    $data[$key] = $this->decrypt($value);
                } catch (DecryptException $e) {
                    // Value might not be encrypted, leave as is
                    $data[$key] = $value;
                }
            } elseif (is_array($value)) {
                $data[$key] = $this->decryptPhiFields($value);
            }
        }
        return $data;
    }

    private function encrypt(string $value): string
    {
        return 'phi_encrypted:' . Crypt::encryptString($value);
    }

    private function decrypt(string $value): string
    {
        if (strpos($value, 'phi_encrypted:') === 0) {
            return Crypt::decryptString(substr($value, 14));
        }
        return $value;
    }

    private function isPhiField(string $field): bool
    {
        return in_array(strtolower($field), $this->phiFields);
    }

    public function addPhiField(string $field): void
    {
        if (!in_array($field, $this->phiFields)) {
            $this->phiFields[] = $field;
        }
    }
}
```

#### b) Implement PHI Audit Service
```php
// app/Services/Compliance/PhiAuditService.php
<?php

namespace App\Services\Compliance;

use App\Models\PhiAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class PhiAuditService
{
    public function logAccess(string $action, string $resourceType, string $resourceId, array $context = []): void
    {
        PhiAuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'session_id' => session()->getId(),
            'context' => $this->sanitizeContext($context),
            'accessed_at' => now()
        ]);
    }

    public function logBulkAccess(string $action, array $resources): void
    {
        $logs = [];
        foreach ($resources as $resource) {
            $logs[] = [
                'user_id' => Auth::id(),
                'action' => $action,
                'resource_type' => $resource['type'],
                'resource_id' => $resource['id'],
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'session_id' => session()->getId(),
                'context' => $this->sanitizeContext($resource['context'] ?? []),
                'accessed_at' => now()
            ];
        }
        
        PhiAuditLog::insert($logs);
    }

    private function sanitizeContext(array $context): array
    {
        // Remove any PHI from context
        $sanitized = [];
        $allowedKeys = ['reason', 'purpose', 'authorization_id', 'request_id'];
        
        foreach ($context as $key => $value) {
            if (in_array($key, $allowedKeys)) {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    public function generateComplianceReport(string $startDate, string $endDate): array
    {
        return PhiAuditLog::whereBetween('accessed_at', [$startDate, $endDate])
            ->selectRaw('action, COUNT(*) as count, COUNT(DISTINCT user_id) as unique_users')
            ->groupBy('action')
            ->get()
            ->toArray();
    }
}
```

### 3. Quick Request Workflow Refactoring

#### a) Create Missing Handler Classes
```php
// app/Services/QuickRequest/Handlers/PatientHandler.php
<?php

namespace App\Services\QuickRequest\Handlers;

use App\Services\FhirService;
use App\Services\Security\PhiEncryptionService;
use App\Services\Compliance\PhiAuditService;
use App\Logging\PhiSafeLogger;

class PatientHandler
{
    public function __construct(
        private FhirService $fhirService,
        private PhiEncryptionService $encryptionService,
        private PhiAuditService $auditService,
        private PhiSafeLogger $logger
    ) {}

    public function createOrUpdatePatient(array $patientData): string
    {
        $this->logger->info('Creating or updating patient in FHIR');
        
        // Check if patient exists
        $existingPatient = $this->findExistingPatient($patientData);
        
        if ($existingPatient) {
            $this->auditService->logAccess('patient.updated', 'Patient', $existingPatient['id']);
            return $existingPatient['id'];
        }
        
        // Create new patient
        $fhirPatient = $this->mapToFhirPatient($patientData);
        $response = $this->fhirService->createPatient($fhirPatient);
        
        $this->auditService->logAccess('patient.created', 'Patient', $response['id']);
        
        return $response['id'];
    }

    private function findExistingPatient(array $patientData): ?array
    {
        // Search by SSN (last 4 digits) and DOB
        if (!empty($patientData['ssn']) && !empty($patientData['date_of_birth'])) {
            $ssnLast4 = substr($patientData['ssn'], -4);
            
            $searchParams = [
                'identifier' => "http://hl7.org/fhir/sid/us-ssn|*{$ssnLast4}",
                'birthdate' => $patientData['date_of_birth']
            ];
            
            $results = $this->fhirService->searchPatients($searchParams);
            
            if (!empty($results['entry'])) {
                // Additional verification logic here
                return $results['entry'][0]['resource'];
            }
        }
        
        return null;
    }

    private function mapToFhirPatient(array $data): array
    {
        return [
            'resourceType' => 'Patient',
            'identifier' => [
                [
                    'system' => 'http://mscwoundcare.com/mrn',
                    'value' => $this->generateMrn()
                ]
            ],
            'name' => [
                [
                    'use' => 'official',
                    'family' => $data['last_name'],
                    'given' => [$data['first_name']]
                ]
            ],
            'telecom' => [
                [
                    'system' => 'phone',
                    'value' => $data['phone'],
                    'use' => 'mobile'
                ],
                [
                    'system' => 'email',
                    'value' => $data['email']
                ]
            ],
            'gender' => $data['gender'],
            'birthDate' => $data['date_of_birth'],
            'address' => [
                [
                    'use' => 'home',
                    'line' => [$data['address']['line1'], $data['address']['line2'] ?? ''],
                    'city' => $data['address']['city'],
                    'state' => $data['address']['state'],
                    'postalCode' => $data['address']['postal_code']
                ]
            ]
        ];
    }

    private function generateMrn(): string
    {
        return 'MRN' . str_pad(random_int(1, 999999), 6, '0', STR_PAD_LEFT);
    }
}
```

### 4. Enhanced Testing Suite

#### a) FHIR Integration Test
```php
// tests/Feature/Fhir/FhirIntegrationTest.php
<?php

namespace Tests\Feature\Fhir;

use Tests\TestCase;
use App\Services\FhirService;
use App\Services\Fhir\FhirCircuitBreaker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class FhirIntegrationTest extends TestCase
{
    private FhirService $fhirService;
    private FhirCircuitBreaker $circuitBreaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->fhirService = app(FhirService::class);
        $this->circuitBreaker = new FhirCircuitBreaker('fhir-test');
        
        // Clear circuit breaker state
        Cache::flush();
    }

    /** @test */
    public function it_handles_fhir_service_failures_with_circuit_breaker()
    {
        // Simulate FHIR failures
        Http::fake([
            '*' => Http::response(['error' => 'Service Unavailable'], 503)
        ]);

        // First 5 calls should fail normally
        for ($i = 0; $i < 5; $i++) {
            try {
                $this->circuitBreaker->call(function () {
                    return $this->fhirService->createPatient(['name' => 'Test']);
                });
            } catch (\Exception $e) {
                $this->assertStringContainsString('Service Unavailable', $e->getMessage());
            }
        }

        // 6th call should throw CircuitBreakerOpenException
        $this->expectException(\App\Exceptions\CircuitBreakerOpenException::class);
        
        $this->circuitBreaker->call(function () {
            return $this->fhirService->createPatient(['name' => 'Test']);
        });
    }

    /** @test */
    public function it_executes_fhir_transaction_bundle_atomically()
    {
        Http::fake([
            '*Bundle' => Http::sequence()
                ->push([
                    'resourceType' => 'Bundle',
                    'type' => 'transaction-response',
                    'entry' => [
                        [
                            'response' => [
                                'status' => '201 Created',
                                'location' => 'Patient/123/_history/1'
                            ]
                        ],
                        [
                            'response' => [
                                'status' => '201 Created',
                                'location' => 'Practitioner/456/_history/1'
                            ]
                        ]
                    ]
                ], 200)
        ]);

        $operations = [
            [
                'resourceType' => 'Patient',
                'resource' => ['name' => [['family' => 'Doe', 'given' => ['John']]]],
                'method' => 'POST'
            ],
            [
                'resourceType' => 'Practitioner',
                'resource' => ['name' => [['family' => 'Smith', 'given' => ['Jane']]]],
                'method' => 'POST'
            ]
        ];

        $manager = app(\App\Services\Fhir\FhirTransactionManager::class);
        $results = $manager->executeTransaction($operations);

        $this->assertCount(2, $results);
        $this->assertEquals('Patient/123', $results[0]['id']);
        $this->assertEquals('Practitioner/456', $results[1]['id']);
    }

    /** @test */
    public function it_validates_fhir_resources_before_submission()
    {
        $invalidPatient = [
            'resourceType' => 'Patient',
            // Missing required fields
        ];

        $validator = app(\App\Services\Fhir\FhirResourceValidator::class);
        $errors = $validator->validate($invalidPatient);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('gender', $errors);
    }

    /** @test */
    public function it_handles_fhir_search_with_pagination()
    {
        Http::fake([
            '*Patient*' => Http::response([
                'resourceType' => 'Bundle',
                'type' => 'searchset',
                'total' => 100,
                'link' => [
                    ['relation' => 'next', 'url' => 'http://example.com/Patient?_page=2']
                ],
                'entry' => array_fill(0, 20, [
                    'resource' => ['resourceType' => 'Patient', 'id' => 'test']
                ])
            ])
        ]);

        $results = $this->fhirService->searchPatients(['name' => 'Smith'], 20);

        $this->assertEquals(100, $results['total']);
        $this->assertCount(20, $results['entry']);
        $this->assertNotEmpty($results['link']);
    }
}
```

### 5. Healthcare-Specific Middleware

#### a) FHIR Request Validation Middleware
```php
// app/Http/Middleware/ValidateFhirRequest.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Fhir\FhirResourceValidator;

class ValidateFhirRequest
{
    private FhirResourceValidator $validator;

    public function __construct(FhirResourceValidator $validator)
    {
        $this->validator = $validator;
    }

    public function handle(Request $request, Closure $next, string $resourceType)
    {
        if ($request->isMethod('POST') || $request->isMethod('PUT')) {
            $data = $request->all();
            
            // Validate FHIR resource structure
            $errors = $this->validator->validate($data, $resourceType);
            
            if (!empty($errors)) {
                return response()->json([
                    'resourceType' => 'OperationOutcome',
                    'issue' => array_map(function ($field, $error) {
                        return [
                            'severity' => 'error',
                            'code' => 'invalid',
                            'details' => ['text' => $error],
                            'expression' => [$field]
                        ];
                    }, array_keys($errors), $errors)
                ], 422);
            }
        }

        return $next($request);
    }
}
```

### 6. Compliance Commands

#### a) HIPAA Compliance Check Command
```php
// app/Console/Commands/CheckHipaaCompliance.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Compliance\HipaaComplianceChecker;

class CheckHipaaCompliance extends Command
{
    protected $signature = 'hipaa:check {--fix : Attempt to fix issues}';
    protected $description = 'Check HIPAA compliance status';

    private HipaaComplianceChecker $checker;

    public function __construct(HipaaComplianceChecker $checker)
    {
        parent::__construct();
        $this->checker = $checker;
    }

    public function handle()
    {
        $this->info('Running HIPAA compliance check...');
        
        $checks = [
            'encryption' => 'Checking PHI encryption...',
            'access_controls' => 'Checking access controls...',
            'audit_logs' => 'Checking audit logging...',
            'session_timeout' => 'Checking session timeout...',
            'ssl_tls' => 'Checking SSL/TLS configuration...',
            'backup_encryption' => 'Checking backup encryption...',
            'phi_separation' => 'Checking PHI/non-PHI separation...'
        ];

        $results = [];
        $failed = false;

        foreach ($checks as $check => $description) {
            $this->output->write($description);
            
            $result = $this->checker->check($check);
            $results[$check] = $result;

            if ($result['passed']) {
                $this->info(' ✓');
            } else {
                $this->error(' ✗');
                $this->warn('  Issue: ' . $result['message']);
                
                if ($this->option('fix') && isset($result['fix'])) {
                    $this->output->write('  Attempting fix...');
                    if ($this->checker->fix($check)) {
                        $this->info(' Fixed!');
                    } else {
                        $this->error(' Failed to fix');
                    }
                }
                
                $failed = true;
            }
        }

        $this->line('');
        $this->info('Compliance Report:');
        $this->table(
            ['Check', 'Status', 'Details'],
            collect($results)->map(function ($result, $check) {
                return [
                    $check,
                    $result['passed'] ? 'Passed' : 'Failed',
                    $result['message']
                ];
            })
        );

        return $failed ? 1 : 0;
    }
}
```

### 7. Environment Configuration Updates

#### a) Updated .env.example
```env
# Application
APP_NAME="MSC Wound Portal"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

# Logging
LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

# Database (Azure SQL)
DB_CONNECTION=sqlsrv
DB_HOST=your-server.database.windows.net
DB_PORT=1433
DB_DATABASE=msc_wound_portal
DB_USERNAME=your-username
DB_PASSWORD=your-password
DB_ENCRYPT=true
DB_TRUST_SERVER_CERTIFICATE=false

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CLIENT=phpredis

# Session (Healthcare Optimized)
SESSION_DRIVER=redis
SESSION_LIFETIME=30
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict

# Cache
CACHE_DRIVER=redis
CACHE_PREFIX=msc_wound_

# Queue
QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=database

# Azure FHIR Configuration
AZURE_FHIR_URL=https://your-workspace.fhir.azurehealthcareapis.com
AZURE_FHIR_TENANT_ID=your-tenant-id
AZURE_FHIR_CLIENT_ID=your-client-id
AZURE_FHIR_CLIENT_SECRET=your-client-secret
AZURE_FHIR_RESOURCE=https://azurehealthcareapis.com
AZURE_FHIR_API_VERSION=2023-11-01

# DocuSeal Configuration
DOCUSEAL_API_URL=https://api.docuseal.co
DOCUSEAL_API_KEY=your-api-key
DOCUSEAL_WEBHOOK_SECRET=your-webhook-secret
DOCUSEAL_ACCOUNT_EMAIL=admin@mscwoundcare.com

# AWS S3 (PHI Document Storage)
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=msc-wound-portal-documents
AWS_BUCKET_ENCRYPTED=msc-wound-portal-documents-encrypted
AWS_KMS_KEY_ID=your-kms-key-id
AWS_USE_PATH_STYLE_ENDPOINT=false

# Mail
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@mscwoundcare.com"
MAIL_FROM_NAME="${APP_NAME}"

# Healthcare Compliance
HIPAA_AUDIT_ENABLED=true
HIPAA_PHI_ENCRYPTION=true
HIPAA_SESSION_TIMEOUT=30
HIPAA_PASSWORD_POLICY=strict
HIPAA_ACCESS_LOG_RETENTION_DAYS=2190

# Security Headers
SECURITY_HSTS_ENABLED=true
SECURITY_HSTS_MAX_AGE=31536000
SECURITY_CSP_ENABLED=true
SECURITY_XSS_PROTECTION=true

# Monitoring
SENTRY_LARAVEL_DSN=your-sentry-dsn
SENTRY_ENVIRONMENT="${APP_ENV}"
SENTRY_TRACES_SAMPLE_RATE=0.1
SENTRY_SEND_DEFAULT_PII=false

# Feature Flags
FEATURE_QUICK_REQUEST_V2=true
FEATURE_FHIR_TRANSACTIONS=true
FEATURE_DOCUSEAL_AUTOMATION=true
FEATURE_CIRCUIT_BREAKER=true
```

### 8. Deployment Configuration

#### a) Healthcare-Optimized Docker Configuration
```dockerfile
# Dockerfile
FROM php:8.3-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    curl \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    libxml2-dev \
    postgresql-dev \
    nodejs \
    npm

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
        zip \
        soap \
        opcache

# Install SQL Server drivers for Azure SQL
RUN curl -O https://download.microsoft.com/download/e/4/e/e4e67866-dffd-428c-aac7-8d28ddafb39b/msodbcsql17_17.10.2.1-1_amd64.apk \
    && curl -O https://download.microsoft.com/download/e/4/e/e4e67866-dffd-428c-aac7-8d28ddafb39b/mssql-tools17_17.10.1.1-1_amd64.apk \
    && apk add --allow-untrusted msodbcsql17_17.10.2.1-1_amd64.apk \
    && apk add --allow-untrusted mssql-tools17_17.10.1.1-1_amd64.apk \
    && pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Configure OPCache for production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

# Security hardening
RUN echo "expose_php = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "error_log = /var/log/php/error.log" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "session.cookie_httponly = 1" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "session.cookie_secure = 1" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "session.cookie_samesite = Strict" >> /usr/local/etc/php/conf.d/security.ini

# Create non-root user
RUN adduser -D -u 1000 -g 1000 laravel

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY --chown=laravel:laravel . .

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && npm ci \
    && npm run build

# Set correct permissions
RUN chown -R laravel:laravel /var/www/html \
    && chmod -R 755 storage bootstrap/cache \
    && mkdir -p /var/log/php \
    && chown laravel:laravel /var/log/php

# Switch to non-root user
USER laravel

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/api/health || exit 1

EXPOSE 9000

CMD ["php-fpm"]
```

## Deployment Checklist

### Pre-Production Requirements
- [ ] All HIPAA compliance checks passing
- [ ] FHIR integration tests passing
- [ ] PHI encryption verified
- [ ] Audit logging functional
- [ ] Security headers configured
- [ ] Rate limiting active
- [ ] Circuit breakers configured
- [ ] Backup procedures tested
- [ ] Disaster recovery plan documented

### Production Deployment
- [ ] SSL/TLS certificates valid
- [ ] Azure SQL firewall configured
- [ ] Azure FHIR permissions set
- [ ] Redis cluster configured
- [ ] Queue workers running
- [ ] Monitoring alerts configured
- [ ] PHI audit logs streaming
- [ ] Compliance reports scheduled

### Post-Deployment Verification
- [ ] End-to-end Quick Request test
- [ ] FHIR resource creation verified
- [ ] DocuSeal PDF generation working
- [ ] Email notifications sending
- [ ] PHI access logging
- [ ] Performance metrics acceptable
- [ ] Security scan completed

## Monitoring & Alerts

### Critical Alerts
1. FHIR service down > 5 minutes
2. PHI access without audit log
3. Failed authentication spike
4. Circuit breaker open
5. Queue backlog > 1000
6. Database connection pool exhausted

### Performance Metrics
1. API response time p95 < 500ms
2. FHIR operation time p95 < 2s
3. Queue processing time < 30s
4. Cache hit rate > 80%
5. Error rate < 0.1%

## Support & Escalation

### Tier 1 Support Checklist
1. Check system health dashboard
2. Verify FHIR connectivity
3. Check queue status
4. Review recent deployments
5. Check error logs

### Tier 2 Escalation
1. Circuit breaker issues
2. FHIR transaction failures
3. PHI audit discrepancies
4. Performance degradation
5. Security incidents

### Emergency Procedures
1. **PHI Breach**: Execute `php artisan security:lockdown`
2. **FHIR Outage**: Enable read-only mode
3. **Data Corruption**: Restore from backup
4. **Security Incident**: Isolate affected systems

---

This refactoring plan addresses the critical issues identified in the Quick Request workflow and provides a comprehensive approach to achieving healthcare deployment best practices. Implementation should be prioritized based on compliance requirements and production readiness needs.
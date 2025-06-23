# Building HIPAA-Compliant Healthcare Systems with Azure FHIR: A Complete Implementation Guide

## From Legacy Migration to Modern Episode-Based Workflows

---

**Author**: Technical Documentation Team  
**Version**: 1.0  
**Date**: June 2025  
**Pages**: 385  

---

## Table of Contents

- [Foreword](#foreword)
- [Introduction: The HIPAA Compliance Imperative](#introduction)

### Part I: The Foundation - Planning for HIPAA-Compliant Healthcare Systems
- [Chapter 1: The Migration Urgency - Azure API for FHIR Retirement](#chapter-1)
- [Chapter 2: Authentication and Security Patterns](#chapter-2)
- [Chapter 3: Compliance Architecture](#chapter-3)

### Part II: The Implementation - Building with Azure FHIR
- [Chapter 4: FHIR Service Configuration](#chapter-4)
- [Chapter 5: Patient Resource Management](#chapter-5)
- [Chapter 6: Clinical Data Storage Patterns](#chapter-6)
- [Chapter 7: Integration Patterns](#chapter-7)

### Part III: The Success Story - Episode-Based Workflows in Production
- [Chapter 8: The Database Foundation Crisis](#chapter-8)
- [Chapter 9: Episode-Based Architecture](#chapter-9)
- [Chapter 10: Frontend Implementation](#chapter-10)
- [Chapter 11: Testing and Validation](#chapter-11)
- [Chapter 12: Production Readiness](#chapter-12)

### Appendices
- [Appendix A: Complete Code Examples](#appendix-a)
- [Appendix B: Configuration Templates](#appendix-b)
- [Appendix C: Migration Checklists](#appendix-c)
- [Appendix D: Troubleshooting Guide](#appendix-d)
- [Appendix E: Glossary](#appendix-e)

---

## Foreword

In the rapidly evolving landscape of healthcare technology, the protection of patient health information (PHI) stands as a paramount concern. This book chronicles a real-world journey of building a HIPAA-compliant healthcare system using Azure FHIR services, from initial planning through complete implementation.

The healthcare industry faces a critical juncture: Azure API for FHIR will be retired on September 30, 2026, with new deployments blocked starting April 1, 2025. This creates an urgent need for healthcare organizations to migrate to Azure Health Data Services. This book serves as both a migration guide and a comprehensive implementation blueprint.

What sets this book apart is its foundation in actual implementation experience. Every challenge, solution, and best practice documented here comes from the successful deployment of the MSC Wound Care Distribution Platform—a system that manages sensitive patient data while maintaining strict HIPAA compliance through innovative architectural decisions.

---

## Introduction: The HIPAA Compliance Imperative {#introduction}

### The Challenge of Modern Healthcare IT

Healthcare organizations today face a complex challenge: building modern, efficient systems while maintaining strict compliance with HIPAA regulations. The Health Insurance Portability and Accountability Act (HIPAA) sets stringent requirements for handling Protected Health Information (PHI), and violations can result in severe penalties ranging from $100 to $1.5 million per incident.

### The Hybrid Architecture Solution

This book presents a proven architectural pattern that elegantly solves the PHI management challenge:

- **Operational Data**: Stored in Supabase for high performance and developer productivity
- **PHI Data**: Exclusively stored in Azure Health Data Services via FHIR R4
- **Referential Integrity**: Maintained through secure ID references

This hybrid approach allows organizations to leverage modern development tools while ensuring PHI remains in HIPAA-compliant infrastructure.

### What You'll Learn

Throughout this book, you'll discover:

1. **Migration Strategies**: How to migrate from Azure API for FHIR to Azure Health Data Services
2. **Security Patterns**: Implementing OAuth 2.0, token management, and audit logging
3. **FHIR Integration**: Creating and managing patient resources, clinical data, and documents
4. **Episode-Based Workflows**: Building efficient clinical workflows with proper data grouping
5. **Production Readiness**: Ensuring performance, reliability, and compliance in production

### Technology Stack Overview

The implementation uses a modern, scalable technology stack:

```yaml
Backend:
  - Laravel 11 (PHP 8.3+)
  - Azure Health Data Services (FHIR R4)
  - Supabase (PostgreSQL)
  - Redis for caching

Frontend:
  - Next.js 14+ (App Router)
  - React Server Components
  - TypeScript
  - Tailwind CSS

Infrastructure:
  - Azure App Service
  - Azure Key Vault
  - Azure Monitor
  - GitHub Actions for CI/CD
```

### Book Structure

This book is organized into three parts:

**Part I** focuses on planning and architecture, including the urgent migration requirements and security patterns necessary for HIPAA compliance.

**Part II** dives into implementation details, showing how to build FHIR services, manage patient data, and store clinical information securely.

**Part III** presents a complete success story, detailing how the episode-based workflow system was built, tested, and deployed to production.

---

# Part I: The Foundation - Planning for HIPAA-Compliant Healthcare Systems

## Chapter 1: The Migration Urgency - Azure API for FHIR Retirement {#chapter-1}

### 🚨 Critical Timeline

The healthcare IT landscape is facing a critical deadline that affects every organization using Azure's FHIR services:

```
CRITICAL DATES:
├── April 1, 2025: New Azure API for FHIR deployments blocked
├── September 30, 2026: Azure API for FHIR completely retired
└── Target: Migrate to Azure Health Data Services FHIR® service
```

This timeline creates an urgent need for organizations to plan and execute their migration strategy. Waiting until the last minute risks:

- Service disruptions
- Compliance violations
- Data loss
- Integration failures

### Current State Assessment

Before planning your migration, assess your current implementation:

```yaml
Current Setup Checklist:
  ✓ FHIR Endpoint URL and version
  ✓ Authentication mechanism (OAuth 2.0, API keys, etc.)
  ✓ Number of FHIR resources in use
  ✓ Custom extensions or profiles
  ✓ Integration points with other systems
  ✓ Data volume and growth rate
  ✓ Performance requirements
  ✓ Compliance certifications needed
```

### Migration Impact Analysis

The migration from Azure API for FHIR to Azure Health Data Services impacts several areas:

#### 1. **Service Architecture Changes**

```
Old Architecture:
┌─────────────────────┐
│ Azure API for FHIR  │ (Standalone service)
└─────────────────────┘

New Architecture:
┌──────────────────────────────┐
│ Azure Health Data Services   │
│  ┌──────────────────────┐   │
│  │ FHIR Service         │   │ (Integrated platform)
│  ├──────────────────────┤   │
│  │ DICOM Service        │   │
│  ├──────────────────────┤   │
│  │ MedTech Service      │   │
│  └──────────────────────┘   │
└──────────────────────────────┘
```

#### 2. **API Endpoint Changes**

```bash
# Old endpoint format:
https://{account-name}.azurehealthcareapis.com

# New endpoint format:
https://{workspace-name}-{fhir-service-name}.fhir.azurehealthcareapis.com
```

#### 3. **Authentication Updates**

While both services use OAuth 2.0 with Microsoft Entra ID (formerly Azure AD), the scope and audience parameters differ:

```javascript
// Old authentication scope
const oldScope = "https://azurehealthcareapis.com/.default";

// New authentication scope
const newScope = "https://{workspace-name}-{fhir-service-name}.fhir.azurehealthcareapis.com/.default";
```

### Migration Strategy Overview

A successful migration follows a phased approach:

#### Phase 1: Assessment & Planning (3-4 months)

1. **Inventory Current Resources**
   ```sql
   -- Example query to count resources by type
   SELECT resourceType, COUNT(*) as count
   FROM fhir_resources
   GROUP BY resourceType
   ORDER BY count DESC;
   ```

2. **Document Dependencies**
   - External systems connecting to FHIR
   - Internal applications using FHIR data
   - Reporting and analytics dependencies

3. **Review Custom Extensions**
   - Identify non-standard FHIR profiles
   - Document business logic in extensions
   - Plan migration of custom resources

#### Phase 2: Parallel Deployment (2-3 months)

1. **Set Up New Infrastructure**
   ```bash
   # Create Azure Health Data Services workspace
   az healthcareapis workspace create \
     --name "msc-health-workspace" \
     --resource-group "msc-health-rg" \
     --location "eastus"

   # Create FHIR service within workspace
   az healthcareapis fhir-service create \
     --name "msc-fhir-service" \
     --workspace-name "msc-health-workspace" \
     --resource-group "msc-health-rg" \
     --kind "fhir-R4"
   ```

2. **Configure Authentication**
   ```bash
   # Create app registration for FHIR access
   az ad app create \
     --display-name "MSC-FHIR-Client" \
     --sign-in-audience "AzureADMyOrg"
   ```

3. **Test Connectivity**
   ```php
   // Test new FHIR endpoint
   $testConnection = new FhirService([
       'endpoint' => $newFhirEndpoint,
       'tenant_id' => $tenantId,
       'client_id' => $clientId,
       'client_secret' => $clientSecret
   ]);
   
   $metadata = $testConnection->getMetadata();
   ```

#### Phase 3: Data Migration (1-2 months)

1. **Export from Old Service**
   ```bash
   # Use $export operation
   POST https://old-fhir-service.azurehealthcareapis.com/$export
   Content-Type: application/fhir+json
   Prefer: respond-async

   {
     "_type": "Patient,Observation,Condition",
     "_since": "2020-01-01T00:00:00Z"
   }
   ```

2. **Transform if Necessary**
   - Update resource IDs if needed
   - Modify reference URLs
   - Validate FHIR conformance

3. **Import to New Service**
   ```bash
   # Use $import operation
   POST https://new-fhir-service.fhir.azurehealthcareapis.com/$import
   ```

#### Phase 4: Cutover (1-2 weeks)

1. **Update Application Configuration**
2. **Switch DNS/Load Balancers**
3. **Monitor System Health**
4. **Validate Data Integrity**

### Risk Mitigation Strategies

To ensure a smooth migration:

1. **Maintain Parallel Operations**
   - Run both services simultaneously during migration
   - Implement dual-write patterns for new data
   - Use feature flags for gradual cutover

2. **Implement Rollback Procedures**
   ```php
   class FhirServiceFactory {
       public static function create($useNewService = false) {
           if ($useNewService && env('AZURE_HEALTH_SERVICES_ENABLED')) {
               return new AzureHealthDataServicesFhir();
           }
           return new LegacyAzureFhirService();
       }
   }
   ```

3. **Monitor Migration Progress**
   - Track resource migration status
   - Monitor error rates
   - Validate data completeness

### Compliance Considerations

During migration, maintain compliance by:

- **Audit Logging**: Track all data access during migration
- **Encryption**: Ensure data is encrypted during transfer
- **Access Control**: Limit migration access to authorized personnel
- **Data Residency**: Verify new service meets geographic requirements

### Cost Optimization

Azure Health Data Services offers new pricing models:

```yaml
Cost Comparison:
  Azure API for FHIR:
    - Flat monthly fee
    - Storage costs
    - Request units
  
  Azure Health Data Services:
    - Pay-per-use pricing
    - Integrated service discounts
    - Reserved capacity options
```

### Chapter Summary

The retirement of Azure API for FHIR represents both a challenge and an opportunity. While the migration requires careful planning and execution, Azure Health Data Services offers:

- Enhanced features and capabilities
- Better integration with other Azure services
- Improved performance and scalability
- More flexible pricing options

The key to success is starting early, planning thoroughly, and executing methodically. The remaining chapters in Part I will detail the security and compliance architecture needed for a successful migration.

---

## Chapter 2: Authentication and Security Patterns {#chapter-2}

### The Security Foundation

Healthcare systems require multiple layers of security to protect PHI. This chapter details the authentication and security patterns essential for HIPAA compliance in Azure FHIR implementations.

### OAuth 2.0 Implementation

Azure Health Data Services uses OAuth 2.0 with Microsoft Entra ID for authentication. The recommended flow for service-to-service communication is the Client Credentials flow:

```php
class FhirAuthService {
    private $tokenCache;
    private $httpClient;
    
    public function __construct(CacheInterface $cache, HttpClient $client) {
        $this->tokenCache = $cache;
        $this->httpClient = $client;
    }
    
    public function getAccessToken(): string {
        $cacheKey = 'fhir_access_token';
        
        // Check cache first
        if ($cachedToken = $this->tokenCache->get($cacheKey)) {
            return $cachedToken;
        }
        
        // Request new token
        $tokenEndpoint = sprintf(
            'https://login.microsoftonline.com/%s/oauth2/v2.0/token',
            config('azure.tenant_id')
        );
        
        $response = $this->httpClient->post($tokenEndpoint, [
            'form_params' => [
                'grant_type' => 'client_credentials',
                'client_id' => config('azure.client_id'),
                'client_secret' => config('azure.client_secret'),
                'scope' => config('azure.fhir_scope')
            ]
        ]);
        
        $data = json_decode($response->getBody(), true);
        
        // Cache token with buffer (50 minutes for 60-minute tokens)
        $this->tokenCache->set(
            $cacheKey,
            $data['access_token'],
            3000 // 50 minutes
        );
        
        return $data['access_token'];
    }
}
```

### Token Management Best Practices

#### 1. **Token Caching Strategy**

```php
class TokenManager {
    private const CACHE_KEY = 'azure_fhir_token';
    private const BUFFER_SECONDS = 300; // 5-minute buffer
    
    public function getValidToken(): string {
        $cached = Cache::get(self::CACHE_KEY);
        
        if ($cached && $this->isTokenValid($cached)) {
            return $cached['access_token'];
        }
        
        return $this->refreshToken();
    }
    
    private function isTokenValid(array $tokenData): bool {
        $expiresAt = $tokenData['expires_at'];
        $now = time();
        
        return ($expiresAt - $now) > self::BUFFER_SECONDS;
    }
    
    private function refreshToken(): string {
        $tokenData = $this->requestNewToken();
        
        // Calculate actual expiration time
        $tokenData['expires_at'] = time() + $tokenData['expires_in'];
        
        Cache::put(
            self::CACHE_KEY,
            $tokenData,
            $tokenData['expires_in'] - self::BUFFER_SECONDS
        );
        
        return $tokenData['access_token'];
    }
}
```

#### 2. **Token Rotation**

Implement automatic token rotation to prevent authentication failures:

```php
class RotatingTokenManager extends TokenManager {
    private $primary;
    private $secondary;
    
    public function getValidToken(): string {
        // Try primary token first
        if ($this->primary && $this->isTokenValid($this->primary)) {
            return $this->primary['access_token'];
        }
        
        // Fallback to secondary
        if ($this->secondary && $this->isTokenValid($this->secondary)) {
            // Promote secondary to primary
            $this->primary = $this->secondary;
            
            // Request new secondary token asynchronously
            dispatch(new RefreshSecondaryTokenJob());
            
            return $this->primary['access_token'];
        }
        
        // Both tokens invalid, force refresh
        return $this->forceRefresh();
    }
}
```

### Azure Key Vault Integration

Never store secrets in code or configuration files. Use Azure Key Vault:

```php
class AzureKeyVaultService {
    private $client;
    
    public function __construct() {
        $this->client = new SecretClient(
            config('azure.key_vault_url'),
            new DefaultAzureCredential()
        );
    }
    
    public function getSecret(string $name): string {
        try {
            $secret = $this->client->getSecret($name);
            return $secret->getValue();
        } catch (Exception $e) {
            Log::error('Key Vault access failed', [
                'secret_name' => $name,
                'error' => $e->getMessage()
            ]);
            throw new SecurityException('Unable to retrieve secret');
        }
    }
    
    public function setSecret(string $name, string $value): void {
        $this->client->setSecret($name, $value);
    }
}
```

### Implementing Defense in Depth

#### 1. **Network Security**

```yaml
# Azure Network Security Group rules
Inbound Rules:
  - Name: AllowHTTPS
    Port: 443
    Protocol: TCP
    Source: Application Gateway
    Destination: App Service
    Action: Allow
    
  - Name: DenyAll
    Port: *
    Protocol: *
    Source: *
    Destination: *
    Action: Deny
    
Outbound Rules:
  - Name: AllowAzureServices
    Port: 443
    Protocol: TCP
    Source: App Service
    Destination: AzureCloud
    Action: Allow
```

#### 2. **Application Security**

```php
// Middleware for FHIR endpoint protection
class FhirSecurityMiddleware {
    public function handle($request, Closure $next) {
        // Validate request origin
        if (!$this->isValidOrigin($request)) {
            return response()->json(['error' => 'Invalid origin'], 403);
        }
        
        // Check rate limiting
        if ($this->isRateLimited($request)) {
            return response()->json(['error' => 'Rate limit exceeded'], 429);
        }
        
        // Validate authentication token
        $token = $request->bearerToken();
        if (!$this->validateToken($token)) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
        
        // Log access for audit
        $this->logAccess($request);
        
        return $next($request);
    }
    
    private function isValidOrigin($request): bool {
        $allowedOrigins = config('security.allowed_origins');
        $origin = $request->header('Origin');
        
        return in_array($origin, $allowedOrigins);
    }
    
    private function isRateLimited($request): bool {
        $key = 'fhir_rate_limit:' . $request->ip();
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= config('security.rate_limit')) {
            return true;
        }
        
        Cache::increment($key);
        Cache::expire($key, 60); // Reset after 1 minute
        
        return false;
    }
}
```

### Audit Logging Implementation

HIPAA requires comprehensive audit logging for all PHI access:

```php
class FhirAuditLogger {
    public function logAccess(
        string $action,
        string $resourceType,
        string $resourceId,
        ?User $user = null
    ): void {
        $auditEntry = [
            'timestamp' => now()->toIso8601String(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'request_id' => request()->header('X-Request-ID'),
            'outcome' => 'success',
            'http_method' => request()->method(),
            'url_path' => request()->path(),
        ];
        
        // Log to multiple destinations for redundancy
        Log::channel('audit')->info('FHIR Access', $auditEntry);
        
        // Also store in database for querying
        FhirAuditLog::create($auditEntry);
        
        // Send to SIEM if configured
        if (config('security.siem_enabled')) {
            dispatch(new SendToSiemJob($auditEntry));
        }
    }
    
    public function logError(
        string $action,
        string $error,
        array $context = []
    ): void {
        $auditEntry = array_merge([
            'timestamp' => now()->toIso8601String(),
            'action' => $action,
            'outcome' => 'failure',
            'error' => $error,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ], $context);
        
        Log::channel('audit')->error('FHIR Error', $auditEntry);
        FhirAuditLog::create($auditEntry);
    }
}
```

### Encryption Patterns

#### 1. **Encryption at Rest**

```php
// All PHI data in Azure is encrypted at rest by default
// Additional application-level encryption for sensitive fields

class EncryptionService {
    private $key;
    
    public function __construct() {
        $this->key = base64_decode(config('app.encryption_key'));
    }
    
    public function encrypt(string $data): string {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        
        $encrypted = sodium_crypto_secretbox(
            $data,
            $nonce,
            $this->key
        );
        
        return base64_encode($nonce . $encrypted);
    }
    
    public function decrypt(string $encrypted): string {
        $decoded = base64_decode($encrypted);
        
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        
        $decrypted = sodium_crypto_secretbox_open(
            $ciphertext,
            $nonce,
            $this->key
        );
        
        if ($decrypted === false) {
            throw new DecryptionException('Failed to decrypt data');
        }
        
        return $decrypted;
    }
}
```

#### 2. **Encryption in Transit**

```nginx
# Nginx configuration for TLS 1.2+ only
server {
    listen 443 ssl http2;
    server_name api.mscwoundcare.com;
    
    # Strong SSL configuration
    ssl_certificate /etc/ssl/certs/mscwoundcare.crt;
    ssl_certificate_key /etc/ssl/private/mscwoundcare.key;
    
    # Only allow TLS 1.2 and 1.3
    ssl_protocols TLSv1.2 TLSv1.3;
    
    # Strong cipher suites
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    
    # Enable HSTS
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # Other security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
}
```

### Access Control Implementation

Role-Based Access Control (RBAC) for FHIR resources:

```php
class FhirAccessControl {
    private $permissions = [
        'provider' => [
            'Patient' => ['read', 'search'],
            'Observation' => ['read', 'create', 'search'],
            'Condition' => ['read', 'create', 'search'],
        ],
        'admin' => [
            'Patient' => ['read', 'create', 'update', 'delete', 'search'],
            'Observation' => ['read', 'create', 'update', 'delete', 'search'],
            'Condition' => ['read', 'create', 'update', 'delete', 'search'],
        ],
        'auditor' => [
            'AuditEvent' => ['read', 'search'],
        ],
    ];
    
    public function canAccess(
        User $user,
        string $resourceType,
        string $action
    ): bool {
        $role = $user->role;
        
        if (!isset($this->permissions[$role][$resourceType])) {
            return false;
        }
        
        return in_array($action, $this->permissions[$role][$resourceType]);
    }
    
    public function filterSearchResults(
        User $user,
        string $resourceType,
        array $results
    ): array {
        // Apply additional filters based on user context
        if ($user->role === 'provider') {
            // Only show patients assigned to this provider
            return array_filter($results, function($resource) use ($user) {
                return $this->isAssignedToProvider($resource, $user);
            });
        }
        
        return $results;
    }
}
```

### Security Monitoring and Alerts

```php
class SecurityMonitor {
    private $alertThresholds = [
        'failed_auth_attempts' => 5,
        'unusual_access_pattern' => 10,
        'bulk_data_access' => 100,
    ];
    
    public function checkSecurityEvents(): void {
        $this->checkFailedAuthentications();
        $this->checkUnusualAccessPatterns();
        $this->checkBulkDataAccess();
    }
    
    private function checkFailedAuthentications(): void {
        $failedAttempts = FhirAuditLog::where('outcome', 'failure')
            ->where('action', 'authenticate')
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();
            
        if ($failedAttempts >= $this->alertThresholds['failed_auth_attempts']) {
            $this->sendAlert('High number of failed authentication attempts', [
                'count' => $failedAttempts,
                'threshold' => $this->alertThresholds['failed_auth_attempts'],
                'time_window' => '15 minutes',
            ]);
        }
    }
    
    private function sendAlert(string $message, array $context): void {
        // Send to security team
        Mail::to(config('security.alert_email'))
            ->send(new SecurityAlertMail($message, $context));
            
        // Log to security channel
        Log::channel('security')->alert($message, $context);
        
        // Send to SIEM
        dispatch(new SendSecurityAlertJob($message, $context));
    }
}
```

### Chapter Summary

Security in healthcare systems requires a multi-layered approach:

1. **Strong Authentication**: OAuth 2.0 with proper token management
2. **Secret Management**: Azure Key Vault integration
3. **Defense in Depth**: Network, application, and data-level security
4. **Comprehensive Auditing**: Every PHI access logged and monitored
5. **Encryption**: Both at rest and in transit
6. **Access Control**: Role-based permissions for resources
7. **Monitoring**: Real-time security event detection

These patterns form the security foundation for any HIPAA-compliant system. The next chapter explores how to build a compliance-focused architecture on top of this security foundation.

---

## Chapter 3: Compliance Architecture {#chapter-3}

### Building for HIPAA Compliance

HIPAA compliance is not just about security—it's about creating an entire architecture that protects PHI throughout its lifecycle. This chapter details the architectural patterns and implementation strategies for building compliant healthcare systems.

### The Compliance Framework

HIPAA compliance requires addressing three main rules:

```yaml
HIPAA Rules:
  Privacy Rule:
    - Minimum necessary access
    - Patient rights to access their data
    - Disclosure tracking
    
  Security Rule:
    - Administrative safeguards
    - Physical safeguards
    - Technical safeguards
    
  Breach Notification Rule:
    - Breach detection
    - Risk assessment
    - Notification procedures
```

### PHI/Non-PHI Separation Architecture

The cornerstone of our compliance architecture is the separation of PHI and operational data:

```
┌─────────────────────────────────────────┐
│          Application Layer              │
├─────────────────────────────────────────┤
│         Data Access Layer               │
├──────────────────┬──────────────────────┤
│   Operational    │      PHI Data        │
│   (Supabase)     │  (Azure FHIR)       │
├──────────────────┼──────────────────────┤
│  - User accounts │  - Patient records   │
│  - Orders        │  - Clinical data     │
│  - Inventory     │  - Lab results       │
│  - Billing refs  │  - Medications       │
└──────────────────┴──────────────────────┘
```

### Implementing the Separation

#### 1. **Entity Design**

```php
// Operational entity (stored in Supabase)
class Order extends Model {
    protected $connection = 'supabase';
    
    protected $fillable = [
        'order_number',
        'status',
        'manufacturer_id',
        'sales_rep_id',
        'fhir_patient_id',      // Reference only
        'fhir_episode_id',      // Reference only
        'total_amount',
        'commission_amount',
    ];
    
    // No PHI stored locally
    public function getPatientAttribute() {
        return app(FhirService::class)
            ->getPatient($this->fhir_patient_id);
    }
}

// PHI access service
class PatientService {
    private $fhirService;
    
    public function getPatientWithOrders(string $patientId): array {
        // Get PHI from Azure
        $patient = $this->fhirService->getPatient($patientId);
        
        // Get operational data from Supabase
        $orders = Order::where('fhir_patient_id', $patientId)->get();
        
        // Combine for presentation
        return [
            'patient' => $patient,
            'orders' => $orders,
        ];
    }
}
```

#### 2. **Data Flow Patterns**

```php
class ComplianceDataFlow {
    private $fhirService;
    private $auditLogger;
    
    public function createPatientWithOrder(array $patientData, array $orderData): array {
        DB::beginTransaction();
        
        try {
            // Step 1: Create patient in Azure FHIR
            $fhirPatient = $this->fhirService->createPatient([
                'name' => $patientData['name'],
                'birthDate' => $patientData['birth_date'],
                'gender' => $patientData['gender'],
                'identifier' => [
                    'system' => 'https://mscwoundcare.com/mrn',
                    'value' => $patientData['mrn']
                ]
            ]);
            
            // Step 2: Create order in Supabase with FHIR reference
            $order = Order::create([
                'order_number' => $orderData['order_number'],
                'fhir_patient_id' => $fhirPatient['id'],
                'status' => 'pending',
                'manufacturer_id' => $orderData['manufacturer_id'],
            ]);
            
            // Step 3: Audit the transaction
            $this->auditLogger->logPatientCreation(
                $fhirPatient['id'],
                auth()->user(),
                'Created via order workflow'
            );
            
            DB::commit();
            
            return [
                'patient' => $fhirPatient,
                'order' => $order,
            ];
            
        } catch (Exception $e) {
            DB::rollback();
            $this->auditLogger->logError('Patient creation failed', $e);
            throw $e;
        }
    }
}
```

### Minimum Necessary Access Implementation

HIPAA's Privacy Rule requires implementing the "minimum necessary" standard:

```php
class MinimumNecessaryFilter {
    private $rolePermissions = [
        'billing' => [
            'Patient' => ['id', 'identifier', 'insurance'],
            'Order' => ['*'], // All order fields
        ],
        'clinical' => [
            'Patient' => ['*'], // All patient fields
            'Order' => ['id', 'status', 'items'],
        ],
        'shipping' => [
            'Patient' => ['id', 'name', 'address'],
            'Order' => ['id', 'items', 'shipping_address'],
        ],
    ];
    
    public function filterData(string $role, string $resourceType, array $data): array {
        $allowedFields = $this->rolePermissions[$role][$resourceType] ?? [];
        
        if (empty($allowedFields)) {
            return [];
        }
        
        if (in_array('*', $allowedFields)) {
            return $data;
        }
        
        return array_intersect_key(
            $data,
            array_flip($allowedFields)
        );
    }
}
```

### Audit Trail Architecture

Comprehensive audit logging is critical for HIPAA compliance:

```php
class ComplianceAuditTrail {
    private $storage;
    
    public function __construct(AuditStorageInterface $storage) {
        $this->storage = $storage;
    }
    
    public function logDataAccess(
        string $action,
        string $resourceType,
        string $resourceId,
        User $user,
        array $context = []
    ): void {
        $entry = new AuditEntry([
            'id' => Str::uuid(),
            'timestamp' => now()->toIso8601String(),
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'session_id' => session()->getId(),
            'context' => $context,
        ]);
        
        // Store in multiple locations for redundancy
        $this->storage->store($entry);
        
        // Real-time analysis for suspicious activity
        if ($this->isSuspiciousActivity($entry)) {
            event(new SuspiciousActivityDetected($entry));
        }
    }
    
    private function isSuspiciousActivity(AuditEntry $entry): bool {
        // Check for patterns indicating potential breaches
        $recentAccesses = $this->storage->getRecentByUser(
            $entry->user_id,
            now()->subMinutes(5)
        );
        
        // Flag if user is accessing many records quickly
        if ($recentAccesses->count() > 50) {
            return true;
        }
        
        // Flag if accessing records outside normal hours
        if (!$this->isNormalWorkingHours($entry->timestamp)) {
            return true;
        }
        
        return false;
    }
}
```

### Patient Rights Implementation

HIPAA grants patients specific rights regarding their health information:

```php
class PatientRightsService {
    private $fhirService;
    private $auditService;
    
    // Right to Access
    public function getPatientData(string $patientId, User $requestor): array {
        // Verify patient identity or authorized representative
        if (!$this->isAuthorizedAccess($patientId, $requestor)) {
            throw new UnauthorizedException('Not authorized to access patient data');
        }
        
        // Collect all patient data
        $data = [
            'demographics' => $this->fhirService->getPatient($patientId),
            'conditions' => $this->fhirService->getConditions($patientId),
            'observations' => $this->fhirService->getObservations($patientId),
            'medications' => $this->fhirService->getMedications($patientId),
            'documents' => $this->fhirService->getDocuments($patientId),
        ];
        
        // Log the access
        $this->auditService->logPatientDataAccess($patientId, $requestor);
        
        return $data;
    }
    
    // Right to Amendment
    public function requestAmendment(
        string $patientId,
        string $resourceType,
        string $resourceId,
        array $amendmentData,
        User $requestor
    ): Amendment {
        // Create amendment request
        $amendment = Amendment::create([
            'patient_id' => $patientId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'requested_changes' => $amendmentData,
            'requestor_id' => $requestor->id,
            'status' => 'pending',
        ]);
        
        // Notify clinical team for review
        event(new AmendmentRequested($amendment));
        
        return $amendment;
    }
    
    // Right to Accounting of Disclosures
    public function getDisclosureHistory(
        string $patientId,
        DateTime $startDate,
        DateTime $endDate
    ): Collection {
        return AuditLog::where('resource_type', 'Patient')
            ->where('resource_id', $patientId)
            ->where('action', 'disclosure')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->map(function ($log) {
                return [
                    'date' => $log->created_at,
                    'recipient' => $log->context['recipient'],
                    'purpose' => $log->context['purpose'],
                    'description' => $log->context['description'],
                ];
            });
    }
}
```

### Breach Detection and Response

Automated breach detection is crucial for compliance:

```php
class BreachDetectionService {
    private $thresholds = [
        'bulk_access' => 100,
        'failed_auth' => 10,
        'after_hours_access' => 20,
        'unusual_location' => 5,
    ];
    
    public function analyzeActivity(): void {
        $this->checkBulkAccess();
        $this->checkFailedAuthentications();
        $this->checkAfterHoursAccess();
        $this->checkUnusualLocations();
    }
    
    private function checkBulkAccess(): void {
        $bulkAccessUsers = DB::table('audit_logs')
            ->select('user_id', DB::raw('COUNT(*) as access_count'))
            ->where('created_at', '>=', now()->subHour())
            ->groupBy('user_id')
            ->having('access_count', '>', $this->thresholds['bulk_access'])
            ->get();
            
        foreach ($bulkAccessUsers as $user) {
            $this->triggerBreachInvestigation(
                'bulk_access',
                "User {$user->user_id} accessed {$user->access_count} records in 1 hour"
            );
        }
    }
    
    private function triggerBreachInvestigation(string $type, string $description): void {
        $investigation = BreachInvestigation::create([
            'type' => $type,
            'description' => $description,
            'status' => 'pending',
            'risk_level' => $this->calculateRiskLevel($type),
        ]);
        
        // Immediate notifications
        event(new PotentialBreachDetected($investigation));
        
        // If high risk, implement immediate containment
        if ($investigation->risk_level === 'high') {
            $this->implementContainment($investigation);
        }
    }
    
    private function implementContainment(BreachInvestigation $investigation): void {
        // Suspend user access if applicable
        if ($investigation->user_id) {
            User::find($investigation->user_id)->suspend();
        }
        
        // Enable enhanced logging
        config(['audit.enhanced_mode' => true]);
        
        // Notify security team
        Mail::to(config('security.team_email'))
            ->send(new HighRiskBreachAlert($investigation));
    }
}
```

### Business Associate Agreements (BAA)

Managing third-party compliance:

```php
class BusinessAssociateManager {
    public function validateBAA(string $vendorId): bool {
        $vendor = Vendor::find($vendorId);
        
        // Check if BAA exists and is current
        $baa = $vendor->currentBAA();
        
        if (!$baa) {
            return false;
        }
        
        // Validate BAA requirements
        $requirements = [
            'signed_date' => $baa->signed_date->isAfter(now()->subYear()),
            'includes_security_requirements' => $baa->has_security_requirements,
            'includes_breach_notification' => $baa->has_breach_notification,
            'includes_termination_clause' => $baa->has_termination_clause,
            'insurance_verified' => $baa->insurance_verified,
        ];
        
        return !in_array(false, $requirements, true);
    }
    
    public function trackDataSharing(string $vendorId, array $data): void {
        DataSharingLog::create([
            'vendor_id' => $vendorId,
            'data_type' => $data['type'],
            'record_count' => $data['count'],
            'purpose' => $data['purpose'],
            'shared_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }
}
```

### Compliance Monitoring Dashboard

```php
class ComplianceDashboard {
    public function getMetrics(): array {
        return [
            'access_metrics' => $this->getAccessMetrics(),
            'security_metrics' => $this->getSecurityMetrics(),
            'training_metrics' => $this->getTrainingMetrics(),
            'incident_metrics' => $this->getIncidentMetrics(),
        ];
    }
    
    private function getAccessMetrics(): array {
        $thirtyDaysAgo = now()->subDays(30);
        
        return [
            'total_accesses' => AuditLog::where('created_at', '>=', $thirtyDaysAgo)->count(),
            'unique_users' => AuditLog::where('created_at', '>=', $thirtyDaysAgo)->distinct('user_id')->count(),
            'after_hours_accesses' => AuditLog::where('created_at', '>=', $thirtyDaysAgo)
                ->whereTime('created_at', '<', '08:00:00')
                ->orWhereTime('created_at', '>', '18:00:00')
                ->count(),
            'minimum_necessary_violations' => MinimumNecessaryViolation::where('created_at', '>=', $thirtyDaysAgo)->count(),
        ];
    }
    
    private function getSecurityMetrics(): array {
        return [
            'failed_logins' => FailedLogin::where('created_at', '>=', now()->subDays(7))->count(),
            'suspicious_activities' => SuspiciousActivity::where('status', 'unresolved')->count(),
            'encryption_status' => [
                'at_rest' => true, // Azure handles this
                'in_transit' => $this->checkTLSCompliance(),
            ],
            'patch_compliance' => $this->checkPatchCompliance(),
        ];
    }
}
```

### Chapter Summary

Building a HIPAA-compliant architecture requires:

1. **Data Separation**: Clear boundaries between PHI and operational data
2. **Minimum Necessary**: Role-based access controls limiting data exposure
3. **Comprehensive Auditing**: Every access logged and monitored
4. **Patient Rights**: Implementing access, amendment, and disclosure tracking
5. **Breach Detection**: Automated monitoring and response procedures
6. **Third-Party Management**: BAAs and vendor compliance tracking
7. **Continuous Monitoring**: Dashboards and metrics for compliance health

This architecture provides the foundation for handling PHI securely while maintaining operational efficiency. The next chapters will show how to implement these patterns with Azure FHIR services.

---

# Part II: The Implementation - Building with Azure FHIR

## Chapter 4: FHIR Service Configuration {#chapter-4}

### Setting Up Azure Health Data Services

This chapter provides a step-by-step guide to configuring Azure Health Data Services for your FHIR implementation, including environment setup, service registration, and configuration management.

### Environment Setup

#### 1. **Creating the Azure Infrastructure**

```bash
# Set variables
RESOURCE_GROUP="msc-health-rg"
LOCATION="eastus"
WORKSPACE_NAME="msc-health-workspace"
FHIR_SERVICE_NAME="msc-fhir-service"

# Create resource group
az group create \
    --name $RESOURCE_GROUP \
    --location $LOCATION

# Create Azure Health Data Services workspace
az healthcareapis workspace create \
    --resource-group $RESOURCE_GROUP \
    --name $WORKSPACE_NAME \
    --location $LOCATION

# Create FHIR service within workspace
az healthcareapis fhir-service create \
    --resource-group $RESOURCE_GROUP \
    --workspace-name $WORKSPACE_NAME \
    --fhir-service-name $FHIR_SERVICE_NAME \
    --location $LOCATION \
    --kind fhir-R4
```

#### 2. **Configuring Authentication**

```bash
# Create app registration for FHIR access
APP_NAME="MSC-FHIR-Client"
APP_ID=$(az ad app create \
    --display-name $APP_NAME \
    --query appId \
    --output tsv)

# Create service principal
az ad sp create --id $APP_ID

# Create client secret
CLIENT_SECRET=$(az ad app credential reset \
    --id $APP_ID \
    --query password \
    --output tsv)

# Get tenant ID
TENANT_ID=$(az account show \
    --query tenantId \
    --output tsv)

# Assign FHIR Data Contributor role
FHIR_RESOURCE_ID=$(az healthcareapis fhir-service show \
    --resource-group $RESOURCE_GROUP \
    --workspace-name $WORKSPACE_NAME \
    --fhir-service-name $FHIR_SERVICE_NAME \
    --query id \
    --output tsv)

az role assignment create \
    --assignee $APP_ID \
    --role "FHIR Data Contributor" \
    --scope $FHIR_RESOURCE_ID
```

### Laravel Service Configuration

#### 1. **Environment Configuration**

Create a comprehensive configuration file for Azure services:

```php
// config/azure.php
return [
    'tenant_id' => env('AZURE_TENANT_ID'),
    'client_id' => env('AZURE_CLIENT_ID'),
    'client_secret' => env('AZURE_CLIENT_SECRET'),
    
    'health_data_services' => [
        'workspace_name' => env('AZURE_HEALTH_WORKSPACE_NAME'),
        'fhir_service_name' => env('AZURE_HEALTH_FHIR_SERVICE_NAME'),
        'fhir_endpoint' => env('AZURE_FHIR_ENDPOINT'),
        'api_version' => env('AZURE_HEALTH_API_VERSION', '2022-06-01'),
    ],
    
    'auth' => [
        'scope' => env('AZURE_FHIR_SCOPE'),
        'grant_type' => 'client_credentials',
        'token_endpoint' => sprintf(
            'https://login.microsoftonline.com/%s/oauth2/v2.0/token',
            env('AZURE_TENANT_ID')
        ),
    ],
    
    'retry' => [
        'max_attempts' => env('AZURE_RETRY_MAX_ATTEMPTS', 3),
        'delay_ms' => env('AZURE_RETRY_DELAY_MS', 1000),
        'multiplier' => env('AZURE_RETRY_MULTIPLIER', 2),
    ],
    
    'timeout' => [
        'connect' => env('AZURE_CONNECT_TIMEOUT', 10),
        'request' => env('AZURE_REQUEST_TIMEOUT', 30),
    ],
];
```

#### 2. **Service Provider Registration**

```php
// app/Providers/FHIRServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\FhirService;
use App\Services\FhirAuthService;
use App\Services\FhirClientFactory;

class FHIRServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register authentication service
        $this->app->singleton(FhirAuthService::class, function ($app) {
            return new FhirAuthService(
                $app['cache.store'],
                $app['log'],
                config('azure')
            );
        });
        
        // Register FHIR client factory
        $this->app->singleton(FhirClientFactory::class, function ($app) {
            return new FhirClientFactory(
                $app[FhirAuthService::class],
                config('azure')
            );
        });
        
        // Register main FHIR service
        $this->app->singleton(FhirService::class, function ($app) {
            return new FhirService(
                $app[FhirClientFactory::class],
                $app['log'],
                config('azure.health_data_services')
            );
        });
    }
    
    public function boot(): void
    {
        // Validate configuration on boot
        $this->validateConfiguration();
        
        // Register health check
        $this->app['health']->define('fhir', function () {
            return $this->app[FhirService::class]->healthCheck();
        });
    }
    
    private function validateConfiguration(): void
    {
        $required = [
            'azure.tenant_id',
            'azure.client_id',
            'azure.client_secret',
            'azure.health_data_services.fhir_endpoint',
        ];
        
        foreach ($required as $key) {
            if (empty(config($key))) {
                throw new \RuntimeException(
                    "Missing required FHIR configuration: {$key}"
                );
            }
        }
    }
}
```

### FHIR Client Implementation

#### 1. **Base FHIR Client**

```php
// app/Services/FhirClientFactory.php
namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;

class FhirClientFactory
{
    private FhirAuthService $authService;
    private array $config;
    
    public function __construct(FhirAuthService $authService, array $config)
    {
        $this->authService = $authService;
        $this->config = $config;
    }
    
    public function create(): Client
    {
        $stack = HandlerStack::create();
        
        // Add authentication middleware
        $stack->push($this->authenticationMiddleware());
        
        // Add retry middleware
        $stack->push($this->retryMiddleware());
        
        // Add logging middleware
        $stack->push($this->loggingMiddleware());
        
        return new Client([
            'base_uri' => $this->config['health_data_services']['fhir_endpoint'],
            'handler' => $stack,
            'timeout' => $this->config['timeout']['request'],
            'connect_timeout' => $this->config['timeout']['connect'],
            'headers' => [
                'Accept' => 'application/fhir+json',
                'Content-Type' => 'application/fhir+json',
            ],
        ]);
    }
    
    private function authenticationMiddleware(): callable
    {
        return Middleware::mapRequest(function (RequestInterface $request) {
            $token = $this->authService->getAccessToken();
            return $request->withHeader('Authorization', "Bearer {$token}");
        });
    }
    
    private function retryMiddleware(): callable
    {
        return Middleware::retry(
            function ($retries, $request, $response, $exception) {
                // Retry on 5xx errors or network issues
                if ($response && $response->getStatusCode() >= 500) {
                    return true;
                }
                
                // Retry on connection errors
                if ($exception instanceof \GuzzleHttp\Exception\ConnectException) {
                    return true;
                }
                
                // Retry on 429 (rate limit)
                if ($response && $response->getStatusCode() === 429) {
                    return true;
                }
                
                return false;
            },
            function ($retries) {
                // Exponential backoff
                $delay = $this->config['retry']['delay_ms'];
                $multiplier = $this->config['retry']['multiplier'];
                return $delay * pow($multiplier, $retries - 1);
            },
            $this->config['retry']['max_attempts']
        );
    }
    
    private function loggingMiddleware(): callable
    {
        return Middleware::log(
            app('log'),
            new \GuzzleHttp\MessageFormatter(
                '{method} {uri} HTTP/{version} - {code} {phrase} - {res_header_Content-Length} bytes'
            )
        );
    }
}
```

#### 2. **FHIR Service Implementation**

```php
// app/Services/FhirService.php
namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class FhirService
{
    private Client $client;
    private array $config;
    
    public function __construct(FhirClientFactory $clientFactory, array $config)
    {
        $this->client = $clientFactory->create();
        $this->config = $config;
    }
    
    public function createResource(string $resourceType, array $resource): array
    {
        try {
            $response = $this->client->post($resourceType, [
                'json' => $resource,
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            Log::info('FHIR resource created', [
                'resource_type' => $resourceType,
                'resource_id' => $data['id'] ?? null,
            ]);
            
            return $data;
            
        } catch (RequestException $e) {
            $this->handleRequestException($e);
        }
    }
    
    public function getResource(string $resourceType, string $id): array
    {
        try {
            $response = $this->client->get("{$resourceType}/{$id}");
            
            return json_decode($response->getBody()->getContents(), true);
            
        } catch (RequestException $e) {
            $this->handleRequestException($e);
        }
    }
    
    public function updateResource(string $resourceType, string $id, array $resource): array
    {
        try {
            $response = $this->client->put("{$resourceType}/{$id}", [
                'json' => $resource,
            ]);
            
            return json_decode($response->getBody()->getContents(), true);
            
        } catch (RequestException $e) {
            $this->handleRequestException($e);
        }
    }
    
    public function searchResources(string $resourceType, array $parameters = []): array
    {
        try {
            $response = $this->client->get($resourceType, [
                'query' => $parameters,
            ]);
            
            $bundle = json_decode($response->getBody()->getContents(), true);
            
            // Extract resources from bundle
            $resources = [];
            if (isset($bundle['entry'])) {
                foreach ($bundle['entry'] as $entry) {
                    $resources[] = $entry['resource'];
                }
            }
            
            return [
                'resources' => $resources,
                'total' => $bundle['total'] ?? 0,
                'link' => $bundle['link'] ?? [],
            ];
            
        } catch (RequestException $e) {
            $this->handleRequestException($e);
        }
    }
    
    public function executeOperation(string $operation, array $parameters = []): array
    {
        try {
            $response = $this->client->post($operation, [
                'json' => [
                    'resourceType' => 'Parameters',
                    'parameter' => $this->formatParameters($parameters),
                ],
            ]);
            
            return json_decode($response->getBody()->getContents(), true);
            
        } catch (RequestException $e) {
            $this->handleRequestException($e);
        }
    }
    
    private function formatParameters(array $parameters): array
    {
        $formatted = [];
        
        foreach ($parameters as $name => $value) {
            $param = ['name' => $name];
            
            if (is_string($value)) {
                $param['valueString'] = $value;
            } elseif (is_bool($value)) {
                $param['valueBoolean'] = $value;
            } elseif (is_int($value)) {
                $param['valueInteger'] = $value;
            } elseif (is_array($value)) {
                $param['resource'] = $value;
            }
            
            $formatted[] = $param;
        }
        
        return $formatted;
    }
    
    private function handleRequestException(RequestException $e): void
    {
        $response = $e->getResponse();
        $statusCode = $response ? $response->getStatusCode() : 0;
        $body = $response ? $response->getBody()->getContents() : '';
        
        Log::error('FHIR request failed', [
            'status_code' => $statusCode,
            'body' => $body,
            'message' => $e->getMessage(),
        ]);
        
        // Parse FHIR OperationOutcome if present
        if ($body) {
            $outcome = json_decode($body, true);
            if (isset($outcome['resourceType']) && $outcome['resourceType'] === 'OperationOutcome') {
                $issues = $outcome['issue'] ?? [];
                foreach ($issues as $issue) {
                    Log::error('FHIR issue', [
                        'severity' => $issue['severity'] ?? 'unknown',
                        'code' => $issue['code'] ?? 'unknown',
                        'diagnostics' => $issue['diagnostics'] ?? '',
                    ]);
                }
            }
        }
        
        throw new FhirException(
            "FHIR request failed: {$e->getMessage()}",
            $statusCode,
            $e
        );
    }
    
    public function healthCheck(): array
    {
        try {
            $response = $this->client->get('metadata');
            $metadata = json_decode($response->getBody()->getContents(), true);
            
            return [
                'status' => 'healthy',
                'fhir_version' => $metadata['fhirVersion'] ?? 'unknown',
                'software' => $metadata['software'] ?? [],
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }
}
```

### Testing the Configuration

#### 1. **Connection Test Script**

```php
// tests/FhirConnectionTest.php
namespace Tests;

use App\Services\FhirService;
use Illuminate\Foundation\Testing\TestCase;

class FhirConnectionTest extends TestCase
{
    private FhirService $fhirService;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->fhirService = app(FhirService::class);
    }
    
    public function testConnection(): void
    {
        $this->artisan('fhir:test-connection')
            ->expectsOutput('Testing FHIR connection...')
            ->expectsOutput('✓ Environment variables configured')
            ->expectsOutput('✓ OAuth token acquired')
            ->expectsOutput('✓ FHIR metadata endpoint accessible')
            ->expectsOutput('✓ Test patient created')
            ->expectsOutput('✓ Test patient retrieved')
            ->expectsOutput('✓ Test patient deleted')
            ->assertExitCode(0);
    }
}
```

#### 2. **Artisan Command for Testing**

```php
// app/Console/Commands/TestFhirConnection.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FhirService;

class TestFhirConnection extends Command
{
    protected $signature = 'fhir:test-connection';
    protected $description = 'Test Azure FHIR connection and permissions';
    
    public function handle(FhirService $fhirService): int
    {
        $this->info('Testing FHIR connection...');
        
        // Test 1: Check environment variables
        $this->testEnvironmentVariables();
        
        // Test 2: Test OAuth token acquisition
        $this->testOAuthToken();
        
        // Test 3: Test metadata endpoint
        $this->testMetadataEndpoint($fhirService);
        
        // Test 4: Test CRUD operations
        $this->testCrudOperations($fhirService);
        
        $this->info("\n✅ All tests passed!

# Troubleshooting Guide

**Version:** 1.0  
**Last Updated:** January 2025  
**Audience:** Developers, Support Staff, System Administrators

---

## 📋 Overview

This comprehensive troubleshooting guide covers common issues, error patterns, and resolution strategies for the MSC Wound Care Portal. Use this guide to quickly diagnose and resolve problems across all system components.

## 🚨 Common Issues & Quick Fixes

### Quick Reference Table
| Issue Category | Symptoms | Quick Check | Documentation Section |
|---------------|----------|-------------|----------------------|
| Login Problems | 401/403 errors | Check auth tokens | [Authentication Issues](#authentication-issues) |
| Slow Performance | Page load > 5s | Check cache status | [Performance Issues](#performance-issues) |
| FHIR Errors | 500 errors on patient data | Check Azure connection | [FHIR Integration Issues](#fhir-integration-issues) |
| Order Submission Fails | 422 validation errors | Check required fields | [Order Processing Issues](#order-processing-issues) |
| DocuSeal Problems | Document generation fails | Check template IDs | [Document Issues](#document-generation-issues) |
| Commission Calculations | Incorrect amounts | Check commission rules | [Commission Issues](#commission-calculation-issues) |

## 🔐 Authentication Issues

### Symptoms
- Users cannot log in
- "401 Unauthorized" errors
- "403 Forbidden" access denials
- Session timeouts

### Diagnostic Commands
```bash
# Check authentication configuration
php artisan config:show auth

# Verify Sanctum tokens
php artisan tinker
>>> \Laravel\Sanctum\PersonalAccessToken::all()

# Check session configuration
php artisan config:show session

# Test authentication middleware
php artisan route:list --name=auth
```

### Common Causes & Solutions

#### 1. Expired Sanctum Tokens
**Symptoms:** API calls return 401 after period of inactivity
```bash
# Check token expiration
php artisan tinker
>>> $token = \Laravel\Sanctum\PersonalAccessToken::find(1)
>>> $token->expires_at
>>> $token->last_used_at
```

**Solution:**
```php
// Extend token lifetime in config/sanctum.php
'expiration' => 525600, // 1 year in minutes

// Or implement token refresh
public function refreshToken(Request $request) {
    $request->user()->currentAccessToken()->delete();
    return $request->user()->createToken('api-token');
}
```

#### 2. Session Configuration Issues
**Symptoms:** Users logged out unexpectedly
```bash
# Check session driver
grep SESSION_DRIVER .env

# Verify Redis connection (if using Redis)
redis-cli ping

# Check session files (if using file driver)
ls -la storage/framework/sessions/
```

**Solution:**
```bash
# Clear session cache
php artisan session:flush

# Fix permissions
chmod -R 755 storage/framework/sessions
chown -R www-data:www-data storage/framework/sessions
```

#### 3. CORS Issues
**Symptoms:** Frontend can't access API from different domain
```bash
# Check CORS configuration
php artisan config:show cors
```

**Solution:**
```php
// config/cors.php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_methods' => ['*'],
'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],
'allowed_origins_patterns' => [],
'allowed_headers' => ['*'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => true,
```

## ⚡ Performance Issues

### Symptoms
- Slow page load times (> 3 seconds)
- High server response times
- Database query timeouts
- Memory exhaustion errors

### Diagnostic Tools

#### Laravel Telescope
```bash
# Enable Telescope
php artisan telescope:install
php artisan migrate

# Access at /telescope
# Check slow queries, requests, and cache misses
```

#### Database Performance
```sql
-- Check slow queries
SHOW PROCESSLIST;

-- Analyze query performance
EXPLAIN SELECT * FROM product_requests 
WHERE user_id = 1 AND status = 'pending';

-- Check index usage
SHOW INDEX FROM product_requests;
```

#### Memory & CPU Monitoring
```bash
# Check memory usage
free -h
ps aux --sort=-%mem | head

# Check CPU usage
top -p $(pgrep php)

# Check disk space
df -h
```

### Common Performance Problems

#### 1. N+1 Query Problems
**Symptoms:** Page loads slowly with many database queries
```bash
# Enable query logging
DB_LOG_QUERIES=true

# Check Telescope for query count
```

**Solution:**
```php
// Bad: N+1 queries
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->user->name; // N+1 query
}

// Good: Eager loading
$orders = Order::with('user')->get();
foreach ($orders as $order) {
    echo $order->user->name; // Single query
}
```

#### 2. Missing Database Indexes
**Symptoms:** Slow query performance
```sql
-- Check for missing indexes
SELECT * FROM information_schema.tables 
WHERE table_schema = 'your_database'
AND table_name NOT IN (
    SELECT DISTINCT table_name 
    FROM information_schema.statistics 
    WHERE table_schema = 'your_database'
);
```

**Solution:**
```sql
-- Add missing indexes
CREATE INDEX idx_product_requests_user_status 
ON product_requests (user_id, status);

CREATE INDEX idx_orders_created_at 
ON orders (created_at);
```

#### 3. Cache Misses
**Symptoms:** Repeated expensive operations
```bash
# Check cache status
redis-cli info stats

# Monitor cache hit ratio
redis-cli --latency-history -i 1
```

**Solution:**
```php
// Implement proper caching
public function getProviderDashboardData($userId) {
    return Cache::remember("dashboard_data_{$userId}", 300, function() use ($userId) {
        return $this->buildDashboardData($userId);
    });
}
```

## 🏥 FHIR Integration Issues

### Symptoms
- Azure FHIR API connection failures
- Patient data synchronization errors
- 500 errors when accessing patient records
- PHI data access violations

### Diagnostic Commands
```bash
# Test Azure FHIR connection
php artisan fhir:test-connection

# Check FHIR service status
php artisan tinker
>>> app(\App\Services\FhirService::class)->healthCheck()

# Verify FHIR configuration
php artisan config:show fhir
```

### Common FHIR Problems

#### 1. Azure Authentication Failures
**Symptoms:** 401 errors from Azure FHIR API
```bash
# Check Azure credentials
grep AZURE_ .env

# Test token acquisition
php artisan azure:test-auth
```

**Solution:**
```bash
# Refresh Azure credentials
az login
az account set --subscription "your-subscription-id"

# Update environment variables
AZURE_CLIENT_ID=your-client-id
AZURE_CLIENT_SECRET=your-client-secret
AZURE_TENANT_ID=your-tenant-id
```

#### 2. FHIR Resource Validation Errors
**Symptoms:** 400 errors when creating/updating FHIR resources
```php
// Debug FHIR validation
try {
    $patient = $fhirService->createPatient($patientData);
} catch (\App\Exceptions\FhirValidationException $e) {
    Log::error('FHIR Validation Error', [
        'errors' => $e->getValidationErrors(),
        'resource' => $e->getResourceData()
    ]);
}
```

**Solution:**
```php
// Validate FHIR data before sending
public function validatePatientData($data) {
    $validator = Validator::make($data, [
        'firstName' => 'required|string|max:255',
        'lastName' => 'required|string|max:255',
        'birthDate' => 'required|date|before:today',
        'gender' => 'required|in:male,female,other,unknown'
    ]);
    
    if ($validator->fails()) {
        throw new FhirValidationException($validator->errors());
    }
}
```

#### 3. PHI Access Control Issues
**Symptoms:** Users accessing unauthorized patient data
```bash
# Check PHI audit logs
tail -f storage/logs/phi-access.log

# Verify user permissions
php artisan user:check-permissions user@example.com
```

**Solution:**
```php
// Implement proper PHI access control
public function getPatientData($patientId) {
    $user = Auth::user();
    
    if (!$this->canAccessPatient($user, $patientId)) {
        throw new UnauthorizedAccessException('Cannot access patient data');
    }
    
    // Log PHI access
    $this->logPhiAccess($user, $patientId);
    
    return $this->fhirService->getPatient($patientId);
}
```

## 📋 Order Processing Issues

### Symptoms
- Order submissions fail with validation errors
- Orders stuck in "pending" status
- Document generation failures
- Commission calculations incorrect

### Diagnostic Tools
```bash
# Check order processing queue
php artisan queue:work --queue=orders

# Monitor failed jobs
php artisan queue:failed

# Check order status
php artisan order:status ORDER-123456
```

### Common Order Problems

#### 1. Validation Failures
**Symptoms:** 422 Unprocessable Entity errors
```php
// Debug validation errors
try {
    $order = $this->quickRequestService->submitOrder($data);
} catch (\Illuminate\Validation\ValidationException $e) {
    Log::error('Order validation failed', [
        'errors' => $e->errors(),
        'data' => $data
    ]);
    return response()->json(['errors' => $e->errors()], 422);
}
```

**Solution:**
```php
// Add comprehensive validation
public function validateOrderData($data) {
    return Validator::make($data, [
        'patient.firstName' => 'required|string|max:255',
        'patient.lastName' => 'required|string|max:255',
        'patient.birthDate' => 'required|date|before:today',
        'products' => 'required|array|min:1',
        'products.*.id' => 'required|exists:products,id',
        'products.*.quantity' => 'required|integer|min:1',
        'insurance.memberId' => 'required|string',
        'insurance.groupNumber' => 'required|string',
    ]);
}
```

#### 2. Queue Processing Issues
**Symptoms:** Jobs stuck in queue, not processing
```bash
# Check queue status
php artisan queue:work --verbose

# Check failed jobs
php artisan queue:failed-table
php artisan migrate
php artisan queue:failed
```

**Solution:**
```bash
# Restart queue workers
php artisan queue:restart

# Retry failed jobs
php artisan queue:retry all

# Check supervisor configuration
sudo supervisorctl status laravel-worker:*
```

#### 3. Manufacturer Integration Failures
**Symptoms:** Orders not sent to manufacturers
```bash
# Check manufacturer API status
php artisan manufacturer:test-connection --manufacturer=acell

# Check webhook deliveries
tail -f storage/logs/manufacturer-webhooks.log
```

**Solution:**
```php
// Implement retry logic for manufacturer APIs
public function sendToManufacturer($order, $manufacturer) {
    $maxRetries = 3;
    $attempt = 0;
    
    while ($attempt < $maxRetries) {
        try {
            return $this->manufacturerApiService->submitOrder($order, $manufacturer);
        } catch (\Exception $e) {
            $attempt++;
            if ($attempt >= $maxRetries) {
                Log::error('Manufacturer API failed after retries', [
                    'order_id' => $order->id,
                    'manufacturer' => $manufacturer,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
            sleep(pow(2, $attempt)); // Exponential backoff
        }
    }
}
```

## 📄 Document Generation Issues

### Symptoms
- DocuSeal forms not generating
- PDF creation failures
- Template mapping errors
- E-signature workflow broken

### Diagnostic Commands
```bash
# Test DocuSeal connection
php artisan docuseal:test-connection

# Check template status
php artisan docuseal:list-templates

# Debug form generation
php artisan docuseal:debug-form --episode=12345
```

### Common Document Problems

#### 1. DocuSeal API Failures
**Symptoms:** 500 errors when generating documents
```php
// Debug DocuSeal API calls
try {
    $submission = $this->docusealService->createSubmission($data);
} catch (\GuzzleHttp\Exception\RequestException $e) {
    Log::error('DocuSeal API Error', [
        'status_code' => $e->getResponse()->getStatusCode(),
        'response_body' => $e->getResponse()->getBody()->getContents(),
        'request_data' => $data
    ]);
}
```

**Solution:**
```php
// Implement proper error handling
public function createDocusealSubmission($data) {
    try {
        return $this->docusealApi->createSubmission($data);
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        if ($e->getResponse()->getStatusCode() === 422) {
            // Handle validation errors
            $errors = json_decode($e->getResponse()->getBody(), true);
            throw new DocusealValidationException($errors);
        }
        throw $e;
    } catch (\GuzzleHttp\Exception\ServerException $e) {
        // Retry on server errors
        return $this->retryDocusealRequest($data);
    }
}
```

#### 2. Template Mapping Issues
**Symptoms:** Form fields not populated correctly
```bash
# Check field mapping configuration
php artisan config:show docuseal-dynamic

# Test field mapping
php artisan field-mapping:test --manufacturer=acell
```

**Solution:**
```php
// Debug field mapping
public function mapFieldsForTemplate($templateId, $data) {
    $mapping = $this->getTemplateMapping($templateId);
    $mappedData = [];
    
    foreach ($mapping as $templateField => $dataPath) {
        $value = data_get($data, $dataPath);
        if ($value === null) {
            Log::warning('Missing data for template field', [
                'template_id' => $templateId,
                'field' => $templateField,
                'data_path' => $dataPath
            ]);
        }
        $mappedData[$templateField] = $value;
    }
    
    return $mappedData;
}
```

#### 3. E-signature Workflow Issues
**Symptoms:** Signatures not captured, workflow stuck
```bash
# Check webhook deliveries
tail -f storage/logs/docuseal-webhooks.log

# Check submission status
php artisan docuseal:check-submission --id=abc123
```

**Solution:**
```php
// Handle webhook events properly
public function handleDocusealWebhook(Request $request) {
    $payload = $request->all();
    
    try {
        switch ($payload['event_type']) {
            case 'form.completed':
                $this->handleFormCompleted($payload);
                break;
            case 'form.signed':
                $this->handleFormSigned($payload);
                break;
            default:
                Log::info('Unhandled webhook event', ['event' => $payload['event_type']]);
        }
    } catch (\Exception $e) {
        Log::error('Webhook processing failed', [
            'payload' => $payload,
            'error' => $e->getMessage()
        ]);
        return response('Error', 500);
    }
    
    return response('OK', 200);
}
```

## 💰 Commission Calculation Issues

### Symptoms
- Incorrect commission amounts
- Missing commission records
- Payout calculation errors
- Territory assignment problems

### Diagnostic Commands
```bash
# Check commission calculations
php artisan commission:calculate --user=123 --month=2025-01

# Verify commission rules
php artisan commission:list-rules

# Test territory assignment
php artisan territory:check-assignment --zip=12345
```

### Common Commission Problems

#### 1. Rule Configuration Errors
**Symptoms:** Commissions calculated with wrong rates
```php
// Debug commission rules
$rules = CommissionRule::where('user_id', $userId)
    ->where('effective_date', '<=', now())
    ->orderBy('effective_date', 'desc')
    ->first();

if (!$rules) {
    Log::error('No commission rules found for user', ['user_id' => $userId]);
}
```

**Solution:**
```php
// Validate commission rules
public function validateCommissionRules($userId) {
    $rules = CommissionRule::where('user_id', $userId)->get();
    
    foreach ($rules as $rule) {
        if ($rule->base_rate < 0 || $rule->base_rate > 1) {
            throw new InvalidCommissionRuleException('Invalid commission rate');
        }
        
        if ($rule->effective_date > $rule->expiry_date) {
            throw new InvalidCommissionRuleException('Invalid date range');
        }
    }
}
```

#### 2. Order Attribution Issues
**Symptoms:** Orders not attributed to correct sales rep
```bash
# Check order attribution
php artisan order:check-attribution --order=ORDER-123

# Verify organization assignments
php artisan organization:check-assignments
```

**Solution:**
```php
// Implement proper order attribution
public function attributeOrder($order) {
    // Try organization sales rep first
    if ($order->organization && $order->organization->sales_rep_id) {
        return $order->organization->sales_rep_id;
    }
    
    // Try facility-based attribution
    if ($order->facility) {
        $territory = Territory::whereJsonContains('zip_codes', $order->facility->zip_code)->first();
        if ($territory) {
            return $territory->sales_rep_id;
        }
    }
    
    // Default to submitting user's sales rep
    return $order->user->sales_rep_id;
}
```

## 🔧 System Administration Issues

### Environment Configuration
```bash
# Check environment configuration
php artisan config:show

# Verify database connection
php artisan db:show

# Check queue configuration
php artisan queue:monitor
```

### Log Analysis
```bash
# Monitor application logs
tail -f storage/logs/laravel.log

# Check error patterns
grep "ERROR" storage/logs/laravel.log | tail -20

# Analyze performance logs
grep "slow query" storage/logs/laravel.log
```

### Service Health Checks
```bash
# Check all services
php artisan health:check

# Individual service checks
php artisan fhir:health-check
php artisan docuseal:health-check
php artisan redis:health-check
```

## 🚨 Emergency Procedures

### Service Outage Response
1. **Immediate Assessment**
   ```bash
   # Check service status
   php artisan health:check --verbose
   
   # Check external dependencies
   ping api.docuseal.co
   nslookup mscfhir.azurehealthcareapis.com
   ```

2. **Enable Maintenance Mode**
   ```bash
   # Put application in maintenance mode
   php artisan down --render="errors::503" --retry=60
   
   # Allow specific IPs (admin access)
   php artisan down --allow=192.168.1.100
   ```

3. **Rollback Procedures**
   ```bash
   # Database rollback
   php artisan migrate:rollback --step=1
   
   # Application rollback
   git checkout previous-stable-tag
   composer install --no-dev
   php artisan migrate
   ```

### Data Recovery
```bash
# Database backup restore
mysql -u root -p woundcare_portal < backup-2025-01-15.sql

# File restore from backup
aws s3 sync s3://backups/2025-01-15/ storage/app/

# Clear all caches after restore
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### Escalation Matrix
| Severity | Response Time | Escalation |
|----------|---------------|------------|
| Critical (System Down) | 15 minutes | CTO, Lead Developer |
| High (Major Feature Down) | 1 hour | Technical Lead |
| Medium (Performance Issues) | 4 hours | Development Team |
| Low (Minor Issues) | 24 hours | Support Team |

## 📞 Getting Help

### Internal Resources
- **Technical Lead**: tech-lead@mscwoundcare.com
- **DevOps Team**: devops@mscwoundcare.com
- **On-Call Engineer**: +1-555-ONCALL

### External Support
- **Azure Support**: Azure Portal → Support
- **DocuSeal Support**: support@docuseal.co
- **Laravel Community**: https://laravel.io

### Documentation Updates
If you encounter an issue not covered in this guide:
1. Document the issue and resolution
2. Update this troubleshooting guide
3. Submit a pull request with the updates

---

**Related Documentation:**
- [Performance Optimization](./PERFORMANCE_OPTIMIZATION.md)
- [Monitoring Guide](../deployment/MONITORING.md)
- [System Architecture](../architecture/SYSTEM_ARCHITECTURE.md)

# AI-Enhanced DocuSeal Integration Migration Guide

## Overview

This guide documents the migration from multiple redundant AI services to a single, consolidated AI-enhanced field mapping system for DocuSeal form integration.

## Architecture Changes

### Before (Multiple Services)
```
MedicalAIServiceManager → Wrong endpoint (/api/v1/map-fields)
OptimizedMedicalAiService → Correct endpoint (/api/v1/enhance-mapping)  
UnifiedFieldMappingService → Has AI capability but inconsistent
```

### After (Consolidated)
```
Quick Request → QuickRequestOrchestrator → OptimizedMedicalAiService → Python AI Service → DocuSeal
                                              ↓ (on failure)
                                         Standard Mapping (fallback)
```

## Implementation Summary

### 1. Service Consolidation
- **Removed**: References to `MedicalAIServiceManager` 
- **Kept**: `OptimizedMedicalAiService` as the single AI integration point
- **Updated**: `QuickRequestOrchestrator` to use only `OptimizedMedicalAiService`

### 2. Configuration Updates
```php
// config/services.php
'medical_ai' => [
    'url' => env('MEDICAL_AI_SERVICE_URL', 'http://localhost:8081'),
    'enabled' => env('MEDICAL_AI_SERVICE_ENABLED', true),
    'use_for_docuseal' => env('MEDICAL_AI_USE_FOR_DOCUSEAL', true), // New feature flag
]
```

### 3. Controller Integration
The `DocusealController::generateSubmissionSlug()` now:
1. Checks if AI enhancement is enabled via feature flags
2. Attempts AI-enhanced mapping with `prepareAIEnhancedDocusealData()`
3. Falls back to standard mapping on failure
4. Logs all attempts and outcomes

### 4. Monitoring & Metrics
- Created `FieldMappingMetricsService` for tracking:
  - Success/failure rates
  - Response times
  - Confidence scores
  - Field completeness
- Added dedicated log channel: `ai_metrics`
- Created artisan command: `php artisan ai:metrics`

## Data Flow

### Step 1: Quick Request Collection
```php
// User completes 3-step Quick Request form
$episodeData = [
    'patient' => [...],
    'insurance' => [...],
    'clinical' => [...]
];
```

### Step 2: FHIR Resource Creation
```php
// QuickRequestOrchestrator creates FHIR resources
$patientFhirId = $this->patientHandler->createOrUpdatePatient($data);
$coverageIds = $this->insuranceHandler->createMultipleCoverages($data);
```

### Step 3: AI Enhancement (when enabled)
```php
if (config('services.medical_ai.use_for_docuseal')) {
    try {
        $enhancedData = $orchestrator->prepareAIEnhancedDocusealData(
            $episode,
            $templateId,
            'insurance'
        );
    } catch (\Exception $e) {
        // Fall back to standard preparation
        $enhancedData = $orchestrator->prepareDocusealData($episode);
    }
}
```

### Step 4: DocuSeal Submission
```php
// Enhanced data is sent to DocuSeal with proper field mappings
$result = $this->docusealService->createSubmissionForQuickRequest(
    $templateId,
    $integrationEmail,
    $submitterEmail,
    $enhancedData,
    $episodeId
);
```

## Python AI Service

### Endpoint
- URL: `http://localhost:8081/api/v1/enhance-mapping`
- Method: POST
- Expects: 
```json
{
    "context": {
        "base_data": {...},
        "fhir_context": {...},
        "manufacturer_config": {...}
    },
    "optimization_level": "high"
}
```

### Response
```json
{
    "enhanced_fields": {...},
    "confidence": 0.95,
    "method": "ai",
    "recommendations": [...]
}
```

## Testing

### 1. Debug Script
```bash
cd scripts
source .venv/bin/activate
python debug_ai_service.py
```

### 2. Integration Test
```bash
php tests/scripts/test-ai-docuseal-flow.php
```

### 3. View Metrics
```bash
php artisan ai:metrics
```

## Feature Flags

### Disable AI Enhancement
```env
MEDICAL_AI_USE_FOR_DOCUSEAL=false
```

### Disable AI Service Completely
```env
MEDICAL_AI_SERVICE_ENABLED=false
```

## Monitoring

### Log Files
- General: `storage/logs/laravel.log`
- AI Metrics: `storage/logs/ai-metrics.log`
- PHI Audit: `storage/logs/phi-audit.log`

### Key Metrics to Monitor
1. **Success Rate**: Should be > 95%
2. **Response Time**: Should be < 2 seconds
3. **Fallback Rate**: Should be < 5%
4. **Field Completeness**: Should be > 90%

### Alerts
Set up alerts when:
- Success rate drops below 80%
- Average response time exceeds 3 seconds
- Error spike detected (>20% failure rate)

## Troubleshooting

### AI Service Not Running
```bash
# Check status
systemctl status medical-ai-service

# Start manually
cd scripts
source .venv/bin/activate
python medical_ai_service.py
```

### Low Confidence Scores
- Check Azure OpenAI credentials
- Verify manufacturer configurations
- Review FHIR data completeness

### High Response Times
- Check network connectivity
- Monitor Azure OpenAI quotas
- Consider enabling caching

## Performance Optimization

### Caching (Future Implementation)
```php
// In OptimizedMedicalAiService
$cacheKey = md5(json_encode($mappingRequest));
return Cache::remember("ai:mapping:$cacheKey", 300, function() use ($mappingRequest) {
    return $this->callAiService($mappingRequest);
});
```

### Batch Processing
For multiple episodes, consider batch requests to reduce API calls.

## Security Considerations

1. **PHI Protection**: AI service receives anonymized field mappings
2. **API Security**: Use API keys for production
3. **Audit Logging**: All AI requests are logged for compliance
4. **Data Retention**: Metrics stored for 30 days, PHI audit logs for 6 years

## Next Steps

1. **Production Deployment**:
   - Configure systemd service for AI
   - Set up monitoring dashboards
   - Configure alerts

2. **Performance Tuning**:
   - Implement response caching
   - Optimize manufacturer configurations
   - Fine-tune AI prompts

3. **Enhanced Features**:
   - Multi-language support
   - Custom field validation rules
   - Real-time confidence scoring

## Support

- **AI Service Issues**: Check `scripts/medical_ai_service.py`
- **Integration Issues**: Review `QuickRequestOrchestrator.php`
- **Metrics**: Run `php artisan ai:metrics`
- **Logs**: Check `storage/logs/ai-metrics.log`
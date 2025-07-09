# AI Service Consolidation Task

## Summary

This task consolidates multiple overlapping AI services in the codebase into a single, well-organized service for DocuSeal field mapping and medical AI integration.

## Problem Statement

The codebase currently has **9 different AI-related services** with overlapping functionality:

1. Multiple services calling wrong endpoints
2. Port configuration mismatch (config says 8081, Python service runs on 8080)
3. Duplicate implementations of field mapping logic
4. Confusion about which service to use

## Services Identified

### To Keep (3 services)
- **OptimizedMedicalAiService** - Primary service, correctly integrated
- **AzureFoundryService** - Direct Azure OpenAI for other AI needs
- **DocumentIntelligenceService** - OCR service (different purpose)

### To Remove (6 services)
- **MedicalAIServiceManager** - Wrong endpoint, but has useful features to preserve
- **IntelligentFieldMappingService** - Duplicate functionality
- **AiFormFillerService** - Wrong endpoint, duplicate
- **DynamicFieldMappingService** - Wrong port, duplicate
- **LLMFieldMapper** - Another duplicate LLM service  
- **TemplateIntelligenceService** - Likely unused

## Key Issues to Fix

1. **Port Mismatch**: Config uses 8081, but Python service runs on 8080
2. **Wrong Endpoints**: Services calling `/map-fields` instead of `/api/v1/enhance-mapping`
3. **Service Confusion**: Too many services doing the same thing

## Benefits of Consolidation

1. **Clarity**: One service for AI field mapping instead of 6+
2. **Performance**: Less code to maintain, faster execution
3. **Reliability**: Single point of integration, easier to debug
4. **Maintainability**: Clear service boundaries and responsibilities

## Implementation Plan

See `todo.md` for detailed implementation steps.

## Files to Review

### Services to Consolidate
- `app/Services/AI/MedicalAIServiceManager.php`
- `app/Services/Medical/OptimizedMedicalAiService.php` ✓ (Keep & Enhance)
- `app/Services/AI/IntelligentFieldMappingService.php`
- `app/Services/AiFormFillerService.php`
- `app/Services/DocuSeal/DynamicFieldMappingService.php`
- `app/Services/DocuSeal/LLMFieldMapper.php`
- `app/Services/TemplateIntelligenceService.php`

### Configuration Files
- `config/services.php` - Fix port configuration
- `config/docuseal-dynamic.php` - May need updates

### Python Service
- `scripts/medical_ai_service.py` - Runs on port 8080

## Testing Requirements

After consolidation:
1. Quick Request flow must work with AI enhancement
2. DocuSeal field mapping must be accurate
3. Fallback mechanisms must work when AI is unavailable
4. Health checks must report correct status

## Migration Guide

For developers using the old services:

```php
// Old way (multiple services)
$aiManager = app(MedicalAIServiceManager::class);
$result = $aiManager->mapFields($data);

// New way (consolidated)
$medicalAi = app(OptimizedMedicalAiService::class);
$result = $medicalAi->enhanceDocusealFieldMapping($episode, $data, $formId);
```

## Notes from Migration Guide

The existing migration guide (`tasks/insurance-form-integration-optimization/MIGRATION_GUIDE.md`) already identified these issues:
- MedicalAIServiceManager calling wrong endpoint
- Port configuration issues
- Need for service consolidation

This task completes the consolidation that was started but not finished. 
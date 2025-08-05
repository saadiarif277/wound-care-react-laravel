# AI Services Backup Documentation

## Services to Backup

### 1. Services to Remove
- `app/Services/AI/MedicalAIServiceManager.php`
- `app/Services/AI/IntelligentFieldMappingService.php`  
- `app/Services/AiFormFillerService.php`
- `app/Services/DocuSeal/DynamicFieldMappingService.php`
- `app/Services/DocuSeal/LLMFieldMapper.php`
- `app/Services/TemplateIntelligenceService.php`

### 2. Services to Keep & Enhance
- `app/Services/Medical/OptimizedMedicalAiService.php` (primary service)
- `app/Services/AI/AzureFoundryService.php` (keep for direct Azure AI)
- `app/Services/DocumentIntelligenceService.php` (different purpose - OCR)

### 3. Configuration Files
- `config/services.php` (ai_form_filler and medical_ai sections)
- `config/docuseal-dynamic.php` (entire file)

### 4. Related Files
- `app/Http/Controllers/Api/DocumentProcessingController.php` (uses AiFormFillerService)
- `app/Console/Commands/TestAiFormFiller.php` (uses AiFormFillerService)
- `routes/debug.php` (uses MedicalAIServiceManager)
- `scripts/test-ai-mapping-with-biowound.php` (uses IntelligentFieldMappingService)

### 5. Python Service
- `scripts/medical_ai_service.py` (keep - this is the actual AI service)
- `scripts/debug_ai_service.py` (keep - debugging tool)

## Features to Preserve

### From MedicalAIServiceManager:
1. Health check functionality (`healthCheck()` method)
2. Service startup capability (`startService()` method)
3. Fallback mechanisms (`getFallbackMapping()` method)
4. Basic field mapping (`performBasicFieldMapping()` method)
5. Service status checking (`getStatus()` method)
6. Connection testing (`testConnection()` method)

### From IntelligentFieldMappingService:
1. Adaptive validation based on template requirements
2. Missing field detection and suggestions
3. Manufacturer-specific critical field identification

### From DynamicFieldMappingService:
1. Direct DocuSeal mapping without episode context
2. Template field fetching from DocuSeal API
3. Quality grading system

## Backup Command

```bash
# Create backup branch
git checkout -b backup/ai-services-before-consolidation

# Stage all AI service files
git add app/Services/AI/MedicalAIServiceManager.php
git add app/Services/AI/IntelligentFieldMappingService.php
git add app/Services/AiFormFillerService.php
git add app/Services/DocuSeal/DynamicFieldMappingService.php
git add app/Services/DocuSeal/LLMFieldMapper.php
git add app/Services/TemplateIntelligenceService.php

# Commit backup
git commit -m "Backup: AI services before consolidation"

# Return to main branch
git checkout -
``` 
# Service Layer Organization Strategy - 2025 Best Practices

## Current State Analysis

We currently have a mix of:
- **Flat services** in `/app/Services/` (majority)
- **Domain-grouped services** in subdirectories (DocuSeal, Medical, AI, etc.)
- **Feature-grouped services** (QuickRequest, Eligibility, etc.)

## 2025 Best Practices Summary

Based on research of Laravel best practices for 2025:

### 1. **Domain-Driven Organization (Recommended)**
- Group by business domain/feature, not technical type
- Keep related services together
- Makes it easier to understand business logic
- Better for team collaboration and onboarding

### 2. **Keep Laravel's Default Structure**
- Don't fight the framework
- Use subdirectories within `/app/Services/`
- Maintain compatibility with packages and tooling

### 3. **Pragmatic Approach - "Least Damage"**
- Don't reorganize everything at once
- Start with new features
- Gradually refactor during regular maintenance

## Recommended Structure

```
app/Services/
├── DocuSeal/                    # ✅ Already organized
│   ├── DocuSealApiClientV2.php
│   └── TemplateFieldValidationServiceV2.php
├── FieldMapping/                # ✅ Already organized
│   ├── DataExtractorV2.php
│   ├── FieldTransformerV2.php
│   └── FieldMatcherV2.php
├── QuickRequest/                # ✅ Already organized
│   └── QuickRequestOrchestrator.php
├── Medical/                     # ✅ Already organized
│   └── OptimizedMedicalAiService.php
├── AI/                          # ✅ Already organized
│   └── AzureFoundryService.php
├── Patient/                     # 🔄 TO CREATE - Group patient services
│   ├── PatientService.php      # Move here
│   └── WoundTypeService.php    # Move here
├── Order/                       # 🔄 TO CREATE - Group order services
│   ├── OrderCommissionProcessorService.php
│   ├── OrderItemCommissionCalculatorService.php
│   └── StatusChangeService.php
├── Commission/                  # 🔄 TO CREATE - Group commission services
│   ├── CommissionRuleFinderService.php
│   └── PayoutCalculatorService.php
├── Validation/                  # 🔄 TO CREATE - Group validation engines
│   ├── ValidationBuilderEngine.php
│   ├── WoundCareValidationEngine.php
│   ├── PulmonologyWoundCareValidationEngine.php
│   └── ValidationEngineMonitoring.php
├── Integration/                 # 🔄 TO CREATE - External integrations
│   ├── CmsCoverageApiService.php
│   ├── CmsEnrichmentService.php
│   └── NPIVerificationService.php
├── Notification/                # 🔄 TO CREATE
│   ├── EmailNotificationService.php
│   └── ManufacturerEmailService.php
├── Core/                        # 🔄 TO CREATE - Core utilities
│   ├── FeatureFlagService.php
│   ├── FileStorageService.php
│   └── CurrentOrganization.php
└── [Keep at root level for now - evaluate later]
    ├── DataExtractionService.php       # Modern consolidated service
    ├── EntityDataService.php           # Still actively used
    ├── MedicalTerminologyService.php   # Cross-cutting concern
    ├── OnboardingService.php           # Large, multi-domain
    └── FhirService.php                 # Core integration
```

## Implementation Strategy (Least Damage Approach)

### Phase 1: Immediate Actions (No Code Changes)
1. **Document the plan** ✅ (this document)
2. **Add V2 suffix to active services** (already in progress)
3. **Delete obsolete services** (already identified)

### Phase 2: New Development (Start Now)
- **All new services** go in appropriate subdirectories
- Follow domain grouping from day one
- Example: New payment service → `/app/Services/Payment/PaymentProcessingService.php`

### Phase 3: Gradual Migration (During Regular Work)
When touching existing services:
1. If making significant changes, move to appropriate directory
2. Update imports in affected files
3. Run tests to ensure nothing breaks
4. Document in PR

### Phase 4: Dedicated Refactoring (Future Sprint)
- Schedule dedicated time to move remaining services
- Do it by domain, not all at once
- Ensure comprehensive testing

## Benefits of This Approach

1. **Minimal Disruption** - No massive refactoring needed immediately
2. **Clear Intent** - Services grouped by what they do, not how they're built
3. **Team-Friendly** - New developers can find things by business domain
4. **Framework-Aligned** - Follows Laravel conventions
5. **Scalable** - Easy to add new domains/features

## Guidelines Going Forward

### DO:
- ✅ Create subdirectories for clear business domains
- ✅ Keep related services together
- ✅ Use descriptive, business-oriented folder names
- ✅ Move services when making significant changes
- ✅ Keep services focused on single responsibility

### DON'T:
- ❌ Create deeply nested structures (max 2 levels)
- ❌ Group by technical patterns (Repository/, Handler/, etc.)
- ❌ Move everything at once
- ❌ Fight Laravel's conventions
- ❌ Over-engineer the structure

## Example Migration

When ready to move a service:

```bash
# Move the file
mv app/Services/PatientService.php app/Services/Patient/PatientService.php

# Update namespace in the file
# From: namespace App\Services;
# To:   namespace App\Services\Patient;

# Update imports in all files using it
# From: use App\Services\PatientService;
# To:   use App\Services\Patient\PatientService;
```

## Conclusion

This approach balances 2025 best practices with pragmatism. We get better organization without disrupting active development. The key is to start with new code and gradually improve existing code as we work on it. 
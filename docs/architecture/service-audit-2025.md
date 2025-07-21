# Service Architecture Audit - January 2025

## Overview
This document audits all services in the application to ensure they follow the Single Responsibility Principle (SRP) and identifies refactoring needs.

## Core Principles
1. **Single Responsibility**: Each service should have ONE clear purpose
2. **No Business Logic in Orchestrators**: Orchestrators only coordinate
3. **No Mapping Logic in Services**: All field mappings belong in configs
4. **Clear Boundaries**: Services should not overlap in functionality

## Service Audit

### ✅ GOOD - Follow SRP

#### EntityDataService
- **Purpose**: Extract entity data based on user roles
- **Current**: Correctly focuses on data extraction by role
- **Status**: ✅ Good - follows SRP

#### FhirService
- **Purpose**: Interface with FHIR server
- **Current**: Handles FHIR API communication
- **Status**: ✅ Good - clear single responsibility

#### PhiSafeLogger
- **Purpose**: Log without exposing PHI
- **Current**: Sanitizes logs to remove PHI
- **Status**: ✅ Good - focused responsibility

#### DocusealService
- **Purpose**: Interface with DocuSeal API
- **Current**: Handles DocuSeal API operations
- **Status**: ⚠️ Needs cleanup - remove field mapping logic

### ❌ BAD - Violate SRP

#### QuickRequestOrchestrator (OLD)
- **Purpose**: Should coordinate workflow
- **Current**: 2,600+ lines with field mapping, transformations, business logic
- **Problems**:
  - Hard-coded field mappings (lines 600-1200+)
  - Business rule implementation
  - Data transformation logic
  - Duplicate mapping logic
- **Action**: Replace with QuickRequestOrchestratorV2

#### UnifiedFieldMappingService (OLD)
- **Purpose**: Should be a mapping engine
- **Current**: Contains mapping logic that belongs in configs
- **Problems**:
  - Hard-coded transformations
  - Business rules in code
  - Complex mapping logic
- **Action**: Replace with UnifiedFieldMappingServiceV2

### 🔍 NEEDS INVESTIGATION

#### DataExtractor (app/Services/FieldMapping/DataExtractor.php)
- **Purpose**: Extract data from various sources
- **Current**: Extracts episode data, FHIR data, etc.
- **Concern**: May have overlapping responsibilities with EntityDataService
- **Action**: Review and possibly merge with EntityDataService

#### FieldTransformer (app/Services/FieldMapping/FieldTransformer.php)
- **Purpose**: Transform field values
- **Current**: Contains transformation logic
- **Concern**: Transformations should be in manufacturer configs
- **Action**: Move all transformations to config files

#### QuickRequestFileService
- **Purpose**: Handle file uploads for Quick Requests
- **Current**: Manages file storage and metadata
- **Status**: ✅ Probably OK - focused on file handling

#### QuickRequestCalculationService
- **Purpose**: Calculate order totals and pricing
- **Current**: Handles pricing calculations
- **Status**: ✅ Good - focused calculation service

## Legacy Code to Remove

### 1. Old Orchestrator Methods
- `prepareDocusealData()` - 700+ lines of mapping logic
- `extractTargetedDocusealData()` - duplicate extraction
- `enhanceWithComprehensiveFacilityData()` - should use EntityDataService
- All hard-coded field mappings

### 2. Duplicate Services
- Check if `DataExtractor` duplicates `EntityDataService`
- Remove any service that duplicates field extraction logic

### 3. Transformation Logic
- All transformation functions in services
- Move to manufacturer config files

### 4. Business Rules in Code
- Medicare validation rules hard-coded in services
- Manufacturer-specific logic in orchestrator
- Move all to configuration

## Recommended Architecture

```
┌─────────────────────┐
│   Controllers       │
└──────────┬──────────┘
           │
┌──────────▼──────────┐
│   Orchestrators     │ (Coordinate only)
│   - No mapping      │
│   - No business     │
│   - No transform    │
└──────────┬──────────┘
           │
     ┌─────┴─────┬─────────┬──────────┐
     │           │         │          │
┌────▼───┐ ┌────▼───┐ ┌───▼───┐ ┌───▼────┐
│Entity  │ │Mapping │ │ FHIR  │ │DocuSeal│
│Data    │ │Engine  │ │Service│ │Service │
│Service │ │(V2)    │ │       │ │        │
└────┬───┘ └────┬───┘ └───────┘ └────────┘
     │          │
     │    ┌─────▼─────┐
     │    │Manufacturer│
     │    │  Configs   │
     │    │(All Logic) │
     │    └───────────┘
     │
┌────▼───────────────┐
│   Data Sources     │
│ - Database         │
│ - FHIR Server      │
└────────────────────┘
```

## Action Items

1. **Immediate**:
   - [x] Create QuickRequestOrchestratorV2
   - [x] Create UnifiedFieldMappingServiceV2
   - [ ] Migrate all field mappings to manufacturer configs
   - [ ] Update controllers to use new services

2. **Short Term**:
   - [ ] Review and consolidate DataExtractor/EntityDataService
   - [ ] Move all transformations to configs
   - [ ] Remove legacy orchestrator methods
   - [ ] Clean up DocusealService

3. **Long Term**:
   - [ ] Create service interfaces for all core services
   - [ ] Implement dependency injection properly
   - [ ] Add service health checks
   - [ ] Create service documentation

## Success Metrics

- No service > 500 lines
- No hard-coded field mappings in services
- No business logic in orchestrators
- Clear service boundaries
- All mapping logic in configs 
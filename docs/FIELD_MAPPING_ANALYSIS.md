# Field Mapping Services Analysis

## Overview
This document analyzes all field mapping services in the wound care application to identify unique functionality, duplications, and opportunities for consolidation.

## Service Inventory

### 1. FhirToIvrFieldExtractor (`app/Services/FhirToIvrFieldExtractor.php`)

**Primary Purpose**: Extract IVR field data from FHIR resources for specific manufacturers

**Key Functions**:
- `extractFields($episodeId, $manufacturerName)` - Main extraction method
- Direct FHIR resource queries (Patient, Coverage, EpisodeOfCare, etc.)
- Manufacturer-specific business rules application
- Field transformation (dates, phones, addresses)

**Unique Features**:
- Direct FHIR integration with specific resource queries
- Manufacturer-specific logic hardcoded (e.g., ACZ duration requirements)
- Error handling with detailed exception messages
- Complex address formatting logic
- Phone number formatting utilities

**Dependencies**:
- FhirService
- Direct database queries to episodes/product_requests

### 2. UnifiedTemplateMappingEngine (`app/Services/Templates/UnifiedTemplateMappingEngine.php`)

**Primary Purpose**: Universal mapping engine for insurance data to any template format

**Key Functions**:
- `mapToTemplate($insuranceData, $template, $options)` - Main mapping method
- Multiple mapping strategies with fallbacks
- Field transformation library (fullName, date, phone, address)
- Completeness calculation
- Performance tracking

**Unique Features**:
- Strategy pattern implementation (exact, fuzzy, pattern, semantic)
- Integration with fuzzy matching system
- Comprehensive transformation functions
- Analytics and reporting capabilities
- Support for custom transformations per template

**Dependencies**:
- IVRMappingRepository
- EnhancedFuzzyFieldMatcher
- ManufacturerTemplateHandler

### 3. EnhancedFuzzyFieldMatcher (`app/Services/FuzzyMapping/EnhancedFuzzyFieldMatcher.php`)

**Primary Purpose**: Advanced fuzzy matching for field names

**Key Functions**:
- `findBestMatch($sourceField, $targetFields, $context)` - Main matching method
- Multiple matching algorithms (Jaro-Winkler, Levenshtein)
- Semantic mapping database
- Pattern recognition
- Learning from successful mappings

**Unique Features**:
- Sophisticated matching algorithms
- Self-learning capability
- Context-aware matching
- Confidence scoring
- Semantic understanding of field relationships

**Dependencies**:
- IVRFieldMapping model
- Configuration from ivr-mapping.php

### 4. IVRMappingOrchestrator (`app/Services/FuzzyMapping/IVRMappingOrchestrator.php`)

**Primary Purpose**: Orchestrate the entire IVR mapping process

**Key Functions**:
- `orchestrateMapping($episodeId, $manufacturerName, $additionalData)` - Main orchestration
- Data preparation and flattening
- Template field retrieval
- Fallback strategy application
- Mapping analytics

**Unique Features**:
- High-level orchestration logic
- Integration of multiple services
- Comprehensive error handling
- Audit trail creation
- Performance optimization

**Dependencies**:
- FhirToIvrFieldExtractor
- UnifiedTemplateMappingEngine
- FallbackStrategy
- ValidationEngine

### 5. EnhancedDocuSealIVRService (`app/Services/EnhancedDocuSealIVRService.php`)

**Primary Purpose**: Enhanced DocuSeal service with FHIR integration

**Key Functions**:
- `createSubmissionFromEpisode($episodeId, $templateId)` - Create DocuSeal submission
- FHIR data extraction specific to DocuSeal
- Canonical field mapping
- File and signature field handling

**Unique Features**:
- DocuSeal-specific field structure
- Integration with DocuSealBuilder
- Normalized field mapping using DocuSealFields
- Special handling for provider/patient emails

**Dependencies**:
- FhirService
- DocuSealBuilder
- DocuSealFields constants

### 6. DocuSealBuilder (`app/Services/Templates/DocuSealBuilder.php`)

**Primary Purpose**: Manage DocuSeal template operations

**Key Functions**:
- `generateBuilderToken($templateId, $prefillData)` - Generate form tokens
- `createPrefillSubmission($templateId, $fieldData)` - Create submissions
- Template retrieval and management
- Webhook handling

**Unique Features**:
- Direct DocuSeal API integration
- Token generation for embedded forms
- Submission tracking
- Email notification handling

**Dependencies**:
- DocuSeal PHP SDK
- External DocuSeal API

## Duplication Analysis

### 1. FHIR Data Extraction
**Duplicated in**:
- FhirToIvrFieldExtractor
- EnhancedDocuSealIVRService
- IVRMappingOrchestrator (indirectly)

**Differences**:
- Each implements slightly different extraction logic
- Different error handling approaches
- Varying levels of data completeness

### 2. Field Transformation
**Duplicated in**:
- FhirToIvrFieldExtractor (inline transformations)
- UnifiedTemplateMappingEngine (transformation library)
- Frontend manufacturerFields.ts

**Differences**:
- Backend vs frontend implementation
- Different formatting rules
- Inconsistent date/phone formatting

### 3. Manufacturer-Specific Logic
**Scattered across**:
- FhirToIvrFieldExtractor (hardcoded)
- ManufacturerTemplateHandler
- Frontend manufacturerFields.ts
- Database seeders

**Issues**:
- No single source of truth
- Difficult to maintain
- Inconsistent rules application

### 4. Field Mapping Configuration
**Found in**:
- Database (IVRFieldMapping)
- Config files (ivr-mapping.php)
- Constants (DocuSealFields.php)
- Frontend TypeScript definitions

**Problems**:
- Multiple sources for same information
- Synchronization issues
- Version control challenges

## Consolidation Opportunities

### 1. Unified Data Extraction Layer
Combine all FHIR extraction logic into a single service with:
- Consistent extraction methods
- Shared transformation utilities
- Centralized error handling
- Caching layer

### 2. Single Mapping Engine
Merge fuzzy matching, template mapping, and orchestration:
- One service with multiple strategies
- Pluggable transformation functions
- Unified configuration
- Consistent analytics

### 3. Consolidated Configuration
Create single source of truth:
- All manufacturer configs in one place
- Shared between frontend and backend
- Version controlled
- Easy to update

### 4. Simplified DocuSeal Integration
Single service for all DocuSeal operations:
- Template management
- Submission creation
- Field mapping
- Webhook handling

## Next Steps

1. Create detailed mapping of which functions are truly unique vs duplicated
2. Design unified service interfaces
3. Plan migration strategy to avoid breaking existing functionality
4. Implement comprehensive test coverage before refactoring
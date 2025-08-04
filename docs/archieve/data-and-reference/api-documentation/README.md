# API Documentation

This directory contains all API-related documentation for the MSC Wound Care Portal, including FHIR implementations, Medicare validation, and CMS integration.

## 📋 Contents

### 🔥 FHIR Implementation
- **[FHIR Implementation Guide](./FHIR_Implementation_Guide.md)** - Complete FHIR R4 server implementation with Azure Health Data Services integration
- **[MSC-MVP FHIR Server REST API](./MSC-MVP%20FHIR%20Server%20REST%20API.md)** - OpenAPI 3.0 specification for FHIR endpoints
- **[FHIR Bundle Generator Conversion](./FHIR_Bundle_Generator_Conversion.md)** - Technical architecture for FHIR bundle generation

### 💰 Medicare & CMS Integration
- **[Medicare MAC Validation Routes](./MEDICARE_MAC_VALIDATION_ROUTES.md)** - Comprehensive API routes for Medicare Administrative Contractor validation
- **[CMS Coverage API Integration](./CMS_Coverage_API_Integration.md)** - Live CMS data integration and validation builder engine

## 🚀 Quick Start

### For Developers
1. Start with **FHIR Implementation Guide** for core FHIR setup
2. Review **Medicare MAC Validation Routes** for compliance endpoints
3. Check **CMS Coverage API Integration** for live coverage data

### For Integration Teams
1. Use **MSC-MVP FHIR Server REST API** for endpoint specifications
2. Reference **FHIR Bundle Generator** for data exchange patterns

## 🔧 API Categories

### FHIR R4 Endpoints
- Patient resource management
- Observation and condition tracking
- Document reference handling
- Bundle transactions
- Capability statements

### Medicare Validation
- MAC jurisdiction determination
- LCD/NCD compliance checking
- Prior authorization workflows
- Specialty-based validation

### CMS Coverage Integration
- Real-time LCD/NCD fetching
- Coverage determination rules
- Validation builder engine
- Specialty filtering

## 📊 API Statistics

- **Total Endpoints**: 45+ documented endpoints
- **FHIR Resources**: Patient, Observation, DocumentReference, Bundle
- **Medicare Specialties**: 6 supported (wound care, vascular surgery, cardiology, etc.)
- **CMS Integration**: Live api.coverage.cms.gov integration

## 🔒 Authentication & Security

All APIs require:
- Laravel Sanctum authentication
- Role-based access control
- HIPAA-compliant audit logging
- PHI separation (Azure HDS for PHI, Supabase for operational data)

## 📈 Performance Features

- Intelligent caching (1-24 hour TTL)
- Rate limiting compliance
- Pagination support
- Error resilience with graceful degradation

## 🧪 Testing

Each API includes:
- Unit test coverage
- Integration test suites
- Manual test scripts (see `/tests/Manual/Api/`)
- Performance benchmarks

---

**Last Updated**: January 2025  
**API Version**: 2.0.0 (with CMS Integration)  
**FHIR Version**: R4 
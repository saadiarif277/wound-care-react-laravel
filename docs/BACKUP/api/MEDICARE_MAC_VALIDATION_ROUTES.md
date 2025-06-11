# Medicare MAC Validation API Routes - Specialty-Based Organization

This document outlines the comprehensive Medicare MAC (Medicare Administrative Contractor) validation API routes organized by specialty for the Wound Care application.

## Overview

The Medicare MAC validation system provides specialty-specific compliance checking, prior authorization management, and reimbursement risk assessment for wound care and vascular procedures.

## Base URL Structure

All routes are prefixed with `/api/v1/` and require `auth:sanctum` middleware except where noted.

## Route Organization

### 1. Order-Specific Validation Routes

**Validate an Order**
```
POST /api/v1/orders/{order_id}/medicare-validation
```
- Validates Medicare compliance for a specific order
- Parameters: `validation_type`, `provider_specialty`, `enable_daily_monitoring`
- Returns: Validation status, compliance score, MAC contractor info

**Get Order Validation Status**
```
GET /api/v1/orders/{order_id}/medicare-validation
```
- Retrieves current validation status for an order
- Returns: Complete validation details, compliance breakdown

### 2. Specialty-Based Validation Groupings

#### Vascular Surgery Specialty
```
GET /api/v1/medicare-validation/specialty/vascular-surgery/
GET /api/v1/medicare-validation/specialty/vascular-surgery/dashboard
GET /api/v1/medicare-validation/specialty/vascular-surgery/compliance-report
```

**Specialty Requirements:**
- ABI measurements required
- Angiography documentation
- Vascular assessment completion
- Prior authorization for complex procedures
- Daily monitoring enabled by default

#### Interventional Radiology Specialty
```
GET /api/v1/medicare-validation/specialty/interventional-radiology/
GET /api/v1/medicare-validation/specialty/interventional-radiology/dashboard
```

**Specialty Requirements:**
- Diagnostic imaging reports
- Contrast allergy screening
- Renal function assessment
- Radiation safety protocols

#### Cardiology Specialty
```
GET /api/v1/medicare-validation/specialty/cardiology/
GET /api/v1/medicare-validation/specialty/cardiology/dashboard
```

**Specialty Requirements:**
- ECG results
- Echocardiogram documentation
- Stress test results
- Cardiac catheterization reports

#### Wound Care Specialty
```
GET /api/v1/medicare-validation/specialty/wound-care/
GET /api/v1/medicare-validation/specialty/wound-care/dashboard
```

**Specialty Requirements:**
- Wound type classification
- Wound measurements and photography
- Treatment history documentation
- Healing progression tracking

### 3. MAC Contractor Specific Routes

Access validations by specific Medicare Administrative Contractor:

```
GET /api/v1/medicare-validation/mac-contractor/novitas
GET /api/v1/medicare-validation/mac-contractor/cgs
GET /api/v1/medicare-validation/mac-contractor/palmetto
GET /api/v1/medicare-validation/mac-contractor/wisconsin-physicians
GET /api/v1/medicare-validation/mac-contractor/noridian
```

**MAC Contractor Coverage:**
- **Novitas Solutions**: DE, DC, MD, NJ, PA (Jurisdiction JL)
- **CGS Administrators**: NY (Jurisdiction JH)
- **Palmetto GBA**: GA, SC, WV, VA, NC (Jurisdiction JJ)
- **Wisconsin Physicians Service**: Multiple jurisdictions (J5, J6, J8)
- **Noridian Healthcare Solutions**: Western states (Jurisdiction J1)

### 4. Validation Type Groupings (Legacy Support)

```
GET /api/v1/medicare-validation/type/vascular-group
GET /api/v1/medicare-validation/type/wound-care-only
GET /api/v1/medicare-validation/type/vascular-only
```

### 5. Individual Validation Management

**Toggle Daily Monitoring**
```
PATCH /api/v1/medicare-validation/{validation_id}/monitoring
```

**Get Audit Trail**
```
GET /api/v1/medicare-validation/{validation_id}/audit
```

**Manual Revalidation**
```
POST /api/v1/medicare-validation/{validation_id}/revalidate
```

**Detailed Compliance Information**
```
GET /api/v1/medicare-validation/{validation_id}/compliance-details
```

### 6. Bulk Operations

**Bulk Validate Multiple Orders**
```
POST /api/v1/medicare-validation/bulk/validate
```
- Parameters: `order_ids[]`, `validation_type`, `provider_specialty`
- Maximum 50 orders per request

**Bulk Monitoring Management**
```
POST /api/v1/medicare-validation/bulk/enable-monitoring
POST /api/v1/medicare-validation/bulk/disable-monitoring
```

### 7. Reports and Analytics

**Compliance Summary Report**
```
GET /api/v1/medicare-validation/reports/compliance-summary
```
- Overall compliance statistics
- Breakdown by status, specialty, validation type
- Common compliance issues

**Reimbursement Risk Analysis**
```
GET /api/v1/medicare-validation/reports/reimbursement-risk
```
- Risk distribution analysis
- High-risk validation details
- Financial impact assessment

**Specialty Performance Report**
```
GET /api/v1/medicare-validation/reports/specialty-performance
```
- Performance metrics by specialty
- Compliance rates and trends
- Common issues by specialty

**MAC Contractor Analysis**
```
GET /api/v1/medicare-validation/reports/mac-contractor-analysis
```
- Performance by contractor
- Jurisdiction-specific analysis
- Reimbursement patterns

**Validation Trends**
```
GET /api/v1/medicare-validation/reports/validation-trends
```
- Historical trend analysis
- Daily, weekly, monthly groupings
- Compliance rate trends

### 8. Dashboard and Monitoring

**Main Dashboard**
```
GET /api/v1/medicare-validation/dashboard
```
- Overall statistics and summary
- High-risk validations
- Daily monitoring status

**Daily Monitoring Execution**
```
POST /api/v1/medicare-validation/daily-monitoring
```
- Runs automated daily monitoring
- Revalidates due validations
- Tracks compliance changes

## Common Request Parameters

### Filtering Parameters
- `facility_id`: Filter by facility
- `date_from`: Start date filter
- `date_to`: End date filter
- `status[]`: Filter by validation status
- `per_page`: Pagination (1-100, default 15)

### Validation Types
- `vascular_wound_care`: Combined vascular and wound care procedures
- `wound_care_only`: Wound care procedures only
- `vascular_only`: Vascular procedures only

### Provider Specialties
- `vascular_surgery`
- `interventional_radiology`
- `cardiology`
- `wound_care_specialty`
- `podiatry`
- `plastic_surgery`

## Response Format

All endpoints return JSON responses with consistent structure:

```json
{
  "success": true|false,
  "message": "Description of result",
  "data": {
    // Response data
  },
  "pagination": {
    // Pagination info for list endpoints
  }
}
```

## Compliance Scoring

Validations receive a compliance score (0-100) based on:
- Coverage requirements met (16.67%)
- Documentation complete (16.67%)
- Frequency compliance (16.67%)
- Medical necessity established (16.67%)
- Billing compliance (16.67%)
- Prior authorization (if required) (16.67%)

## Daily Monitoring

Validations with `daily_monitoring_enabled` are automatically revalidated to detect:
- Changes in coverage policies
- Documentation updates
- Prior authorization status changes
- Compliance drift

## Error Handling

- **400 Bad Request**: Invalid parameters
- **401 Unauthorized**: Authentication required
- **403 Forbidden**: Insufficient permissions
- **404 Not Found**: Resource not found
- **422 Unprocessable Entity**: Validation errors
- **500 Internal Server Error**: System errors

## Authentication

All routes (except callback endpoints) require authentication using Laravel Sanctum tokens:

```
Authorization: Bearer {api_token}
```

## Rate Limiting

API requests are subject to rate limiting based on Laravel's throttle middleware configuration.

---

## 🆕 CMS Coverage API Integration & Validation Builder Engine

### New Features Added

The Medicare MAC validation system has been enhanced with:

#### CMS Coverage API Integration
- **Live CMS Data**: Real-time integration with `api.coverage.cms.gov`
- **LCDs, NCDs, Articles**: Fetches current Local Coverage Determinations, National Coverage Determinations, and billing Articles
- **Specialty Filtering**: Advanced keyword-based filtering for wound care, vascular surgery, cardiology, etc.
- **State-based Queries**: MAC jurisdiction-specific coverage data
- **Comprehensive Search**: Full-text search across all CMS coverage documents

#### ValidationBuilderEngine
- **Comprehensive Wound Care Rules**: Based on the detailed "Wound Care MAC Validation & Compliance Questionnaire"
- **Pre-purchase Qualification**: Patient info, facility verification, medical history assessment
- **Wound Assessment**: Detailed wound classification, measurements, tissue assessment
- **Conservative Care Documentation**: 4-week minimum requirements with wound-specific protocols
- **Clinical Assessments**: Vascular studies, laboratory values, photography requirements
- **MAC Coverage Verification**: Automated LCD compliance checking

#### Enhanced Medicare MAC Validation
- **Live CMS Compliance**: Real-time checking against applicable LCDs and NCDs
- **Enhanced Validation Results**: Detailed reports combining base rules with live CMS data
- **Comprehensive Audit Trails**: CMS data references in compliance reports

### New API Endpoints

Added 12 new validation builder endpoints under `/api/v1/validation-builder/`:

```http
GET /rules                    # Get validation rules for specialty
GET /user-rules              # Get rules for authenticated user's specialty
POST /validate-order          # Validate order with comprehensive rules
POST /validate-product-request # Validate product request
GET /cms-lcds                 # Get LCDs for specialty/state
GET /cms-ncds                 # Get NCDs for specialty
GET /cms-articles             # Get Articles for specialty/state
GET /search-cms               # Search CMS coverage documents
GET /mac-jurisdiction         # Get MAC info for state
GET /specialties              # Get available specialties
POST /clear-cache             # Clear specialty cache
```

### Testing & Verification

Run the comprehensive test suite:

```bash
# Artisan command test
php artisan test:validation-builder --specialty=wound_care_specialty --state=CA

# Standalone test script
php test-validation-builder.php
```

### Performance Features

- **Intelligent Caching**: 1-hour cache for general data, 24-hour for detailed documents
- **Rate Limiting**: Respects CMS API 10,000 requests/second limit
- **Error Resilience**: Graceful degradation when CMS API unavailable
- **Background Processing**: Efficient handling of large CMS datasets

---

**Implementation Status:** ✅ Complete + Enhanced
**Medicare MAC Routes:** 33 original routes
**Validation Builder Routes:** 12 new routes  
**Total API Endpoints:** 45
**Last Updated:** December 2024
**Version:** 2.0.0 (with CMS Integration) 

I'll create updated versions of each document based on the Technical Alignment as the source of truth. Let me start with the most critical documents:

## 1. DOCUSEAL_LATEST_UPDATED.md

```markdown
# DocuSeal Integration - Current Implementation (Updated)

## Overview

DocuSeal is integrated into the MSC Wound Portal to handle electronic document generation for Insurance Verification Requests (IVRs) in the order fulfillment process. The integration enables automated document creation and tracking throughout the order lifecycle. **No signatures are required** - DocuSeal is used purely for PDF generation with pre-populated data.

## Architecture

### Core Components

1. **IvrDocusealService** (`/app/Services/IvrDocusealService.php`)
   - Primary service for IVR document generation
   - Handles PDF generation without signature fields
   - Manages document storage and retrieval
   - Integrates with FHIR for patient data

2. **DocusealService** (`/app/Services/DocusealService.php`)
   - Base service for DocuSeal API interactions
   - Handles document generation for orders
   - Manages submission creation for PDF generation only

3. **DocusealFolder** (`/app/Models/Docuseal/DocusealFolder.php`)
   - Maps manufacturers to specific DocuSeal folders
   - Stores folder IDs for template organization
   - Enables manufacturer-specific template selection

4. **DocusealTemplate** (`/app/Models/Docuseal/DocusealTemplate.php`)
   - Stores template information by type (IVR, Order Form, etc.)
   - Links templates to manufacturer folders
   - Tracks active/inactive status

5. **DocusealSubmission** (`/app/Models/Docuseal/DocusealSubmission.php`)
   - Records all document generation events
   - Tracks document URLs and generation status
   - Links to ProductRequest via order_id

## IVR Workflow Integration

### 1. IVR Generation Process (No Signatures Required)

When an admin clicks "Generate IVR" for a ProductRequest with status `pending_ivr`:

```php
// IvrDocusealService::generateIvr()
1. Validates order status is 'pending_ivr'
2. Identifies manufacturer from product
3. Retrieves manufacturer's DocuSeal folder
4. Selects IVR template from folder
5. Fetches patient data from FHIR
6. Pre-populates template fields (90% auto-filled)
7. Creates DocuSeal submission for PDF generation only
8. Generates document for admin download
9. Updates ProductRequest status to 'ivr_sent'
10. Stores document URL for admin access
```

### 2. Field Mapping (90% Auto-Population)

The IVR template is populated with data from a single efficient query:

**From Database Query (80%):**

- Provider name, NPI, credentials, email
- Facility name, address, NPI, phone, PTAN
- Organization name and tax ID
- Order details (request number, service date, wound type)
- Insurance information (payer name, ID)

**From FHIR (10%):**

- Patient name
- Date of birth
- Gender
- Patient identifier (de-identified)

**Provider Input (10%):**

- Member ID
- Specific clinical codes (if not auto-suggested)
- Any manufacturer-specific requirements

### 3. Admin Review & Send Workflow

1. **IVR Generation**
   - Admin generates IVR document (PDF only, no signature fields)
   - Document is pre-populated with all required data
   - System updates status to `ivr_sent`

2. **Admin Review**
   - Admin downloads generated IVR document
   - Reviews for accuracy and completeness
   - Makes any necessary corrections offline

3. **Manual Send to Manufacturer**
   - Admin emails IVR to manufacturer contact
   - Clicks "Mark as Sent to Manufacturer" button
   - System updates `manufacturer_sent_at` timestamp

4. **Manufacturer Approval Tracking**
   - Manufacturer reviews IVR via email
   - Sends approval confirmation back to admin
   - Admin clicks "Confirm Manufacturer Approval"
   - Enters approval reference number
   - Status updates to `manufacturer_approved`

## Database Schema

### Product Request IVR Fields (Simplified)

```sql
-- Active fields for IVR workflow
ivr_required BOOLEAN DEFAULT true
docuseal_submission_id VARCHAR(255) NULL
docuseal_template_id VARCHAR(255) NULL
ivr_sent_at TIMESTAMP NULL
ivr_document_url VARCHAR(255) NULL
manufacturer_sent_at TIMESTAMP NULL
manufacturer_sent_by BIGINT UNSIGNED NULL
manufacturer_approved BOOLEAN DEFAULT false
manufacturer_approved_at TIMESTAMP NULL
manufacturer_approval_reference VARCHAR(255) NULL
manufacturer_notes TEXT NULL

-- Deprecated fields (to be removed)
-- ivr_signed_at (no longer used - no signatures)
-- ivr_bypass_reason (simplified workflow)
-- ivr_bypassed_at (simplified workflow)
-- ivr_bypassed_by (simplified workflow)
```

## Configuration

### DocuSeal Settings

```php
// config/services.php
'docuseal' => [
    'api_key' => env('DOCUSEAL_API_KEY'),
    'api_url' => env('DOCUSEAL_API_URL', 'https://api.docuseal.com'),
    'timeout' => env('DOCUSEAL_TIMEOUT', 30),
    'signature_required' => false, // Never require signatures
    'generate_pdf_only' => true,    // Only generate PDFs
    'auto_submit' => false,         // Manual review required
];
```

### Manufacturer-Specific Configuration

```php
// config/manufacturers.php
return [
    'Extremity Care' => [
        'docuseal_folder_id' => 'folder_123',
        'ivr_template_id' => 'template_456',
        'email_contact' => 'orders@extremitycare.com',
        'submission_method' => 'email_fax',
        'pre_population_level' => 90 // 90% auto-filled
    ],
    'Stability Biologics' => [
        'docuseal_folder_id' => 'folder_789',
        'ivr_template_id' => 'template_012',
        'email_contact' => 'cservice@stabilitybio.com',
        'submission_method' => 'email',
        'pre_population_level' => 90
    ]
];
```

## Admin UI Integration

### Order Center Actions

The Admin Order Center (`/admin/orders`) provides streamlined IVR management:

1. **Generate IVR Button**
   - Visible when order status is `pending_ivr`
   - One-click generation (no modal needed)
   - Generates PDF immediately

2. **Download IVR Link**
   - Appears after generation
   - Downloads PDF for review

3. **Mark as Sent Button**
   - Confirms manual email to manufacturer
   - Records timestamp and user

4. **Manufacturer Approval**
   - Button to confirm manufacturer approval
   - Requires approval reference
   - Optional notes field

## API Endpoints

### Admin Routes

```php
// Generate IVR for an order (simplified - no signature options)
POST /admin/orders/{productRequest}/generate-ivr
// No request body needed - always generates without signature

// Mark IVR as sent to manufacturer
POST /admin/orders/{productRequest}/mark-ivr-sent

// Confirm manufacturer approval
POST /admin/orders/{productRequest}/manufacturer-approval
{
    "approval_reference": "string (required)",
    "notes": "string (optional)"
}
```

## Security Considerations

1. **PHI Protection**
   - Patient data fetched from FHIR only when needed
   - No PHI stored in DocuSeal metadata
   - Document URLs stored but documents remain in DocuSeal

2. **Access Control**
   - IVR generation requires `manage-orders` permission
   - All actions logged for audit trail

## Business Rules

1. **One Product Rule**
   - Each ProductRequest can only have one product type
   - Multiple sizes/quantities of same product allowed

2. **No Signature Required**
   - IVRs are generated as review documents only
   - No provider or patient signatures needed
   - Admin reviews before sending to manufacturer

3. **Manual Manufacturer Communication**
   - Admin manually emails IVR to manufacturer
   - Platform tracks send timestamp
   - Manual approval confirmation required

4. **Status Progression**
   - Must follow defined workflow: `pending_ivr` → `ivr_sent` → `manufacturer_approved`
   - Cannot skip stages
   - All status changes logged

## Implementation Checklist

### Completed Features

- ✅ PDF-only generation via DocuSeal API
- ✅ 90% field pre-population
- ✅ Admin UI for IVR management
- ✅ Manufacturer approval tracking
- ✅ FHIR integration for patient data
- ✅ Manufacturer-specific folder/template selection
- ✅ Manual send to manufacturer workflow

### Removed Features

- ❌ Signature workflows (not needed)
- ❌ IVR bypass functionality (simplified)
- ❌ Webhook processing (no signatures to track)
- ❌ Complex modal workflows (streamlined to one-click)

### Future Enhancements

- ⏳ Direct manufacturer API integration
- ⏳ Bulk IVR generation
- ⏳ Automated manufacturer notification
- ⏳ Real-time status updates

```

## 2. ORDER_FLOW_UPDATED.md

```markdown
# MSC Wound Portal Order Flow (Updated)

## Overview
This document outlines the streamlined order flow from initial product request submission to final order fulfillment. The system uses a single ProductRequest entity throughout the entire lifecycle, with a simplified status progression focused on speed and efficiency.

## Streamlined Status Workflow

### Status Progression
```yaml
# Provider Actions
draft                      # Provider creating request
submitted                  # Provider completed submission

# Admin Review  
processing                 # Admin reviewing request
approved                   # Admin approved for IVR generation

# IVR Process
pending_ivr               # Needs IVR generation
ivr_sent                  # IVR generated and sent to manufacturer

# Manufacturer Response
manufacturer_approved     # Manufacturer confirmed approval
submitted_to_manufacturer # Order sent to manufacturer for fulfillment

# Fulfillment
shipped                   # Product shipped
delivered                 # Product delivered

# Terminal States
cancelled                 # Cancelled at any stage
denied                    # Admin denied request
sent_back                # Admin sent back for corrections
```

## Order Flow Stages

### Stage 1: Product Request Submission (90 seconds)

**Actor:** Provider  
**Status:** `draft` → `submitted`

1. Provider initiates new product request:
   - **Patient Info (30 seconds):**
     - Name: "John Smith"
     - DOB: "01/15/1965"
     - Member ID: "M123456789"
     - Insurance: "Medicare"
     - Service Date: "06/25/2025"

   - **Clinical Context (30 seconds):**
     - Wound Type: "DFU" (dropdown)
     - Place of Service: "11" (auto-filled from facility)
     - ICD-10: "E11.621" (smart suggestion)
     - CPT: "15275" (smart suggestion)

   - **Product Selection (30 seconds):**
     - Product: "XCELLERATE Q4234"
     - Size: "4x4cm" (auto-suggested)
     - Quantity: 1

2. System validates and creates ProductRequest with status `submitted`

### Stage 2: Admin Review

**Actor:** MSC Admin  
**Status:** `submitted` → `processing` → `approved`/`denied`/`sent_back`

1. Admin accesses Order Center
2. Reviews request with all pre-populated data
3. Makes decision:
   - **Approve**: Proceeds to IVR generation
   - **Send Back**: Returns with comments
   - **Deny**: Rejects with reason

### Stage 3: IVR Generation (One-Click)

**Actor:** MSC Admin  
**Status:** `approved` → `pending_ivr` → `ivr_sent`

1. Status automatically updates to `pending_ivr` after approval
2. Admin clicks "Generate IVR" button
3. System:
   - Identifies manufacturer from product
   - Selects appropriate template
   - Pre-populates 90% of fields
   - Generates PDF (no signatures required)
   - Updates status to `ivr_sent`
4. Admin downloads PDF for review

### Stage 4: Manual Manufacturer Submission

**Actor:** MSC Admin  
**Status:** `ivr_sent` (with timestamp tracking)

1. Admin reviews generated IVR document
2. Manually emails IVR to manufacturer
3. Clicks "Mark as Sent to Manufacturer"
4. System records `manufacturer_sent_at` timestamp

### Stage 5: Manufacturer Approval

**Actor:** Manufacturer → MSC Admin  
**Status:** `ivr_sent` → `manufacturer_approved`

1. Manufacturer reviews IVR (external process)
2. Sends approval to MSC Admin
3. Admin clicks "Confirm Manufacturer Approval"
4. Enters approval reference number
5. Status updates to `manufacturer_approved`

### Stage 6: Order Submission

**Actor:** MSC Admin  
**Status:** `manufacturer_approved` → `submitted_to_manufacturer`

1. Admin generates order form (auto-populated)
2. Submits to manufacturer via configured method
3. Status updates to `submitted_to_manufacturer`

### Stage 7: Fulfillment Tracking

**Actor:** System/Manufacturer  
**Status:** `submitted_to_manufacturer` → `shipped` → `delivered`

1. Manufacturer processes order
2. Provides tracking information
3. System updates status as shipment progresses

## Key Integration Points

### Data Sources for 90% Auto-Population

```sql
-- Single query provides most IVR data
SELECT 
  pr.*, -- Request details
  u.*,  -- Provider info (NPI, credentials)
  f.*,  -- Facility info (address, PTAN)
  o.*   -- Organization info (tax ID)
FROM product_requests pr
JOIN users u ON pr.provider_id = u.id
JOIN facilities f ON pr.facility_id = f.id
JOIN organizations o ON f.organization_id = o.id
WHERE pr.id = ?;
```

### FHIR Integration (10% of data)

- Patient demographics only
- Retrieved on-demand during IVR generation
- Minimal PHI exposure

## Business Rules

1. **90-Second Provider Experience**: Total time from start to submit
2. **One Product Per Request**: Simplifies workflow and IVR generation
3. **No Signatures Required**: IVRs are informational documents only
4. **Status Progression**: Must follow defined workflow, no skipping
5. **Manual Manufacturer Communication**: Ensures human review before submission
6. **Audit Trail**: Every action logged with timestamp and user

## Status Transition Matrix

```typescript
const allowedTransitions = {
  'draft': ['submitted', 'cancelled'],
  'submitted': ['processing', 'cancelled'],
  'processing': ['approved', 'sent_back', 'denied'],
  'approved': ['pending_ivr', 'cancelled'],
  'pending_ivr': ['ivr_sent', 'cancelled'],
  'ivr_sent': ['manufacturer_approved', 'cancelled'],
  'manufacturer_approved': ['submitted_to_manufacturer'],
  'submitted_to_manufacturer': ['shipped', 'cancelled'],
  'shipped': ['delivered'],
  'delivered': [],
  'cancelled': [],
  'denied': [],
  'sent_back': ['submitted']
};
```

## Time Savings Summary

| Phase | Provider Time | Admin Time | Total Time Saved |
|-------|--------------|------------|------------------|
| Request Creation | 90 seconds | 0 | vs 15-20 minutes manually |
| IVR Generation | 0 | 30 seconds | vs 30-45 minutes manually |
| Total | 90 seconds | 30 seconds | 85-90% reduction |

```

## 3. ADMIN_ORDER_CENTER_UPDATED.md

```markdown
# Admin Order Management Center (Updated)

## Overview

The Admin Order Management Center enables MSC Admin users to efficiently manage provider-submitted product requests with a streamlined workflow focused on speed and clarity.

## Landing Page – Order Center Dashboard

### Dashboard Layout

```

┌─────────────────────────────────────────────────────────────┐
│ Order Management Center                          [Refresh]   │
├─────────────────────────────────────────────────────────────┤
│ [Orders Requiring Action] [All Orders]                       │
├─────────────────────────────────────────────────────────────┤
│ Request # │ Provider │ Status │ Service Date │ Action       │
│ REQ-001  │ Dr. Smith│ 🟠 Generate IVR │ 06/25 │ [Generate]  │
│ REQ-002  │ Dr. Jones│ 🔵 Awaiting Review │ 06/26 │ [Review] │
│ REQ-003  │ Dr. Brown│ 🟣 Awaiting Manuf. │ 06/27 │ [Track] │
└─────────────────────────────────────────────────────────────┘

```

### Status Indicators & Actions

| Status | Color | Badge Text | Available Actions |
|--------|-------|------------|-------------------|
| `submitted` | 🔵 Blue | Awaiting Review | Review → Approve/Deny/Send Back |
| `processing` | 🟡 Yellow | Under Review | Approve/Deny/Send Back |
| `pending_ivr` | 🟠 Orange | Generate IVR | Generate IVR |
| `ivr_sent` | 🟣 Purple | Awaiting Manufacturer | Mark Sent, Track Approval |
| `manufacturer_approved` | 🟢 Green | Ready to Submit | Submit to Manufacturer |
| `submitted_to_manufacturer` | 🔵 Blue | In Fulfillment | Track Shipment |
| `shipped` | 🟢 Green | Shipped | Track Delivery |
| `delivered` | ⚫ Gray | Complete | View History |
| `cancelled` | ⚫ Gray | Cancelled | View Details |
| `denied` | 🔴 Red | Denied | View Reason |
| `sent_back` | 🟠 Orange | Sent Back | View Comments |

### Urgent Actions Filter

**Orders Requiring Action** (default view) includes:
- `submitted` - Needs initial review
- `processing` - Needs decision
- `pending_ivr` - Needs IVR generation
- `manufacturer_approved` - Needs final submission

## Order Detail View

### Streamlined Layout

```

┌─────────────────────────────────────────────────────────────┐
│ Order REQ-001                    Status: 🟠 Generate IVR     │
├─────────────────────────────────────────────────────────────┤
│ Provider Info          │ Order Details                       │
│ Dr. Robert Smith       │ Service Date: 06/25/2025          │
│ NPI: 1234567890       │ Product: XCELLERATE 4x4cm          │
│ Healing Hands Clinic   │ Quantity: 1                        │
│                       │ Total Value: $10,062.08             │
├─────────────────────────────────────────────────────────────┤
│ Patient Reference     │ Insurance                           │
│ ID: PAT-12345        │ Medicare                            │
│ Service: 06/25/2025  │ ID: M123456789                      │
├─────────────────────────────────────────────────────────────┤
│                    ACTION PANEL                              │
│ [Generate IVR] [View Details] [Cancel Order]                 │
└─────────────────────────────────────────────────────────────┘

```

## Simplified Admin Actions

### 1. Generate IVR (One-Click)
**Precondition:** Status = `pending_ivr`

```javascript
// When admin clicks "Generate IVR"
1. System immediately generates PDF with 90% pre-populated data
2. No modal or options needed
3. Status updates to 'ivr_sent'
4. Download link appears
5. "Mark as Sent to Manufacturer" button becomes available
```

### 2. Mark as Sent to Manufacturer

**Precondition:** Status = `ivr_sent`

```javascript
// Simple confirmation when clicked
"Confirm IVR was sent to [Manufacturer Name]?"
[Cancel] [Confirm]

// On confirm:
- Records manufacturer_sent_at timestamp
- Logs admin user who sent
- No additional fields required
```

### 3. Confirm Manufacturer Approval

**Precondition:** Status = `ivr_sent` with manufacturer_sent_at

```javascript
// Simple form appears
┌─────────────────────────────────────┐
│ Manufacturer Approval               │
├─────────────────────────────────────┤
│ Approval Reference*: [___________]  │
│ Notes (optional): [_____________]   │
│                                     │
│ [Cancel] [Confirm Approval]         │
└─────────────────────────────────────┘

// On confirm:
- Status updates to 'manufacturer_approved'
- Ready for final order submission
```

### 4. Order Approval Actions

**Precondition:** Status = `submitted` or `processing`

- **Approve**: Advances to `pending_ivr`
- **Send Back**: Requires comment, returns to provider
- **Deny**: Requires reason, terminates request

## Key UI Features

### Action Availability Matrix

```typescript
const actionButtons = {
  'submitted': ['Approve', 'Send Back', 'Deny'],
  'processing': ['Approve', 'Send Back', 'Deny'],
  'pending_ivr': ['Generate IVR'],
  'ivr_sent': ['Mark as Sent', 'Confirm Approval'],
  'manufacturer_approved': ['Submit to Manufacturer'],
  'submitted_to_manufacturer': ['Track Shipment'],
  // Terminal states have view-only actions
  'delivered': ['View History'],
  'cancelled': ['View Details'],
  'denied': ['View Reason']
};
```

### Quick Stats Bar

```
┌─────────────────────────────────────────────────────────────┐
│ Pending Review: 5 | Awaiting IVR: 3 | In Transit: 12       │
└─────────────────────────────────────────────────────────────┘
```

## Mobile Optimization

- Responsive table design for tablets
- Touch-friendly action buttons
- Swipe actions for common tasks
- Minimum 1280px recommended for full experience

## Performance Features

- Auto-refresh every 60 seconds
- Pagination for large order volumes
- Lazy loading for order details
- Optimistic UI updates for actions

## Access Control

- Only users with `manage-orders` permission can access
- All actions logged with user ID and timestamp
- Audit trail available for compliance

## Future Enhancements

- Bulk IVR generation
- Direct manufacturer API integration
- Advanced filtering and search
- Customizable dashboard widgets

```

## 4. ORG_FACILITY_PROVIDER_MODEL_UPDATED.md

```markdown
# Organization → Facility → Provider Relationship Model (Updated)

Based on the Technical Alignment document, we maintain the existing hybrid approach with minimal changes.

## Core Relationship Architecture:

```

Organization (1) ←→ (Many) Facilities (1) ←→ (Many) Providers (Users)
     ↓                    ↓                      ↓
Business Entity      Physical Locations    Individual Practitioners
Contract Holder      Service Delivery      Order Creators

```

## Key Architecture Decision: Hybrid Users Table

**We maintain the current hybrid `users` table that contains both:**
- Platform authentication data (email, password)
- Provider professional data (NPI, credentials, license)

This avoids breaking changes while supporting the 90% IVR auto-population goal.

## 1. Organizations Table (No Changes)

```sql
-- Existing organizations table remains as-is
CREATE TABLE organizations (
  id BIGINT UNSIGNED PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  tax_id VARCHAR(255) ENCRYPTED,
  -- All existing fields maintained
);
```

## 2. Facilities Table (Minor Additions)

```sql
-- Add missing fields for IVR auto-population
ALTER TABLE facilities
ADD COLUMN ptan VARCHAR(255) DEFAULT NULL,
ADD COLUMN default_place_of_service VARCHAR(2) DEFAULT '11';

-- Resulting structure includes:
- id, organization_id, name
- npi (facility/group NPI)
- address, city, state, zip_code
- phone, email
- ptan (NEW - for Medicare billing)
- default_place_of_service (NEW - usually '11' for office)
- active status fields
```

## 3. Users Table (Hybrid - No Separation)

```sql
-- Current users table maintains dual purpose:
-- 1. Platform authentication
-- 2. Provider professional information

Key fields for IVR auto-population:
- first_name, last_name (provider name)
- npi_number (individual NPI)
- credentials (MD, DPM, NP, etc.)
- email (professional contact)
- practitioner_fhir_id (links to Azure FHIR)
- current_organization_id (active context)
```

## 4. Provider-Facility Relationships

```sql
-- Many-to-many relationship tracking
-- Providers (users) can work at multiple facilities
CREATE TABLE user_facilities (
  id BIGINT UNSIGNED PRIMARY KEY,
  user_id BIGINT UNSIGNED, -- References users.id
  facility_id BIGINT UNSIGNED, -- References facilities.id
  is_primary BOOLEAN DEFAULT false,
  can_order_products BOOLEAN DEFAULT true,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

## IVR Auto-Population Query (90% Coverage)

```sql
-- Single efficient query for IVR data
SELECT 
  -- From product_requests (15%)
  pr.request_number,
  pr.expected_service_date,
  pr.wound_type,
  pr.payer_name_submitted,
  
  -- From users/providers (25%)
  u.first_name as provider_first_name,
  u.last_name as provider_last_name,
  u.npi_number as provider_npi,
  u.credentials as provider_credentials,
  u.email as provider_email,
  
  -- From facilities (30%)
  f.name as facility_name,
  f.npi as facility_npi,
  f.address as facility_address,
  f.city, f.state, f.zip_code,
  f.phone as facility_phone,
  f.ptan as facility_ptan,
  f.default_place_of_service,
  
  -- From organizations (20%)
  o.name as organization_name,
  o.tax_id as organization_tax_id

FROM product_requests pr
JOIN users u ON pr.provider_id = u.id
JOIN facilities f ON pr.facility_id = f.id
JOIN organizations o ON f.organization_id = o.id
WHERE pr.id = ?;
```

## Real-World Examples

### Single Practice (Simple)

```yaml
Organization: "Dr. Smith Podiatry"
  └── Facility: "Dr. Smith Podiatry - Main Office"
      └── Provider: User ID 123 (Dr. James Smith, DPM)
```

### Hospital System (Complex)

```yaml
Organization: "Regional Health System"
  ├── Facility: "Regional Hospital - Wound Care Center"
  │   ├── Provider: User ID 124 (Dr. Sarah Jones, MD)
  │   └── Provider: User ID 125 (Dr. Mike Chen, DPM)
  └── Facility: "Regional Clinic - Downtown"
      └── Provider: User ID 124 (Dr. Sarah Jones - works both locations)
```

## Key Benefits of Hybrid Approach

1. **No Breaking Changes**: Existing code continues to work
2. **Simplified Queries**: One JOIN instead of two for provider data
3. **Easier User Management**: Single user record per provider
4. **Maintains Flexibility**: Can still have providers at multiple facilities

## Migration Tasks

```sql
-- Only two changes needed:
ALTER TABLE facilities 
ADD COLUMN ptan VARCHAR(255) DEFAULT NULL,
ADD COLUMN default_place_of_service VARCHAR(2) DEFAULT '11';

-- Everything else remains as-is
```

## Business Logic Implementation

### Check Provider Authorization

```php
// Can this provider order from this facility?
$canOrder = DB::table('user_facilities')
    ->where('user_id', $providerId)
    ->where('facility_id', $facilityId)
    ->where('can_order_products', true)
    ->exists();
```

### Get Provider's Primary Facility

```php
// For default selections in UI
$primaryFacility = DB::table('user_facilities')
    ->join('facilities', 'facilities.id', '=', 'user_facilities.facility_id')
    ->where('user_id', $providerId)
    ->where('is_primary', true)
    ->first();
```

This hybrid model supports both simple single-practice scenarios and complex multi-location health systems while maintaining the existing codebase structure.

```

## 5. SALES_TEAM_DASHBOARDS_UPDATED.md

```markdown
# Sales Rep & Sub-Rep Dashboard Plan (Updated)

## Overview
Sales reps and sub-reps earn commissions based on orders placed by the providers they bring to the platform. The dashboard reflects the streamlined order workflow from the Technical Alignment document.

## 1. Sales Rep Dashboard

### Core Components

#### A. Commission Overview Widget
```

┌─────────────────────────────────────────────┐
│ Commission Overview                         │
├─────────────────────────────────────────────┤
│ MTD Paid: $15,000                          │
│ Pending: $4,500                            │
│ Next Payout: Feb 15                        │
│ ████████░░ 50% of Target                   │
└─────────────────────────────────────────────┘

```

**Data Source:**
- Only orders with status `delivered` count as paid
- Orders in `shipped` status count as pending
- Uses streamlined status values

#### B. My Providers Section
```sql
-- Query for provider metrics
SELECT 
  u.id,
  u.first_name,
  u.last_name,
  COUNT(pr.id) as mtd_orders,
  SUM(pr.total_order_value) as mtd_revenue
FROM users u
LEFT JOIN product_requests pr ON pr.provider_id = u.id
  AND pr.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
  AND pr.order_status IN ('delivered', 'shipped', 'submitted_to_manufacturer')
WHERE u.acquired_by_rep_id = ?
GROUP BY u.id;
```

#### C. Recent Order Activity

Shows orders from assigned providers with current status badges:

- 🔵 `submitted` - New order from your provider
- 🟠 `pending_ivr` - Order in IVR generation
- 🟢 `manufacturer_approved` - Order approved
- 🚚 `shipped` - Commission pending
- ✅ `delivered` - Commission earned

### API Endpoints

```typescript
// GET /api/sales-rep/dashboard
{
  commissions: {
    mtd_paid: 15000.00,      // Only 'delivered' orders
    mtd_pending: 4500.00,    // 'shipped' orders
    ytd_earnings: 180000.00,
    monthly_target: 30000.00
  },
  providers: {
    total_active: 23,
    new_this_month: 2,
    top_performer: {
      name: "Dr. Smith",
      facility: "Metro Clinic",
      mtd_revenue: 25000.00
    }
  },
  recent_orders: [
    {
      request_number: "REQ-001",
      provider: "Dr. Smith",
      order_status: "shipped",
      status_color: "green",
      status_text: "Shipped",
      order_value: 10062.08,
      commission: 503.10
    }
  ]
}
```

## 2. Sub-Rep Dashboard

### Simplified Components

#### A. Personal Commission Widget

```
┌─────────────────────────────────────────────┐
│ My Earnings                                 │
├─────────────────────────────────────────────┤
│ This Month: $3,500 (Paid)                  │
│ Pending: $800                              │
│ Split Rate: 50%                            │
│ Parent Rep: Michael Thompson                │
└─────────────────────────────────────────────┘
```

#### B. Provider Performance

```sql
-- Streamlined query using current schema
SELECT 
  u.first_name,
  u.last_name,
  f.name as facility_name,
  COUNT(CASE WHEN pr.order_status = 'delivered' THEN 1 END) as completed_orders,
  COUNT(CASE WHEN pr.order_status IN ('submitted', 'processing', 'pending_ivr') THEN 1 END) as pending_orders
FROM users u
JOIN facilities f ON u.current_facility_id = f.id
LEFT JOIN product_requests pr ON pr.provider_id = u.id
WHERE u.acquired_by_subrep_id = ?
GROUP BY u.id;
```

## 3. Database Updates (Minimal)

```sql
-- Add tracking fields to users table
ALTER TABLE users 
ADD COLUMN acquired_by_rep_id BIGINT UNSIGNED NULL,
ADD COLUMN acquired_by_subrep_id BIGINT UNSIGNED NULL,
ADD COLUMN acquisition_date TIMESTAMP NULL,
ADD INDEX idx_acquired_by (acquired_by_rep_id, acquired_by_subrep_id);

-- Update msc_sales_reps for targets
ALTER TABLE msc_sales_reps 
ADD COLUMN monthly_target DECIMAL(10,2) DEFAULT 0,
ADD COLUMN provider_count INT DEFAULT 0;
```

## 4. Commission Calculation

### Based on Streamlined Status Flow

```php
// Commission calculation service
class CommissionCalculationService
{
    public function calculateCommissions($repId, $month)
    {
        // Only 'delivered' orders generate paid commissions
        $paidOrders = ProductRequest::where('order_status', 'delivered')
            ->whereHas('provider', function($q) use ($repId) {
                $q->where('acquired_by_rep_id', $repId)
                  ->orWhere('acquired_by_subrep_id', $repId);
            })
            ->whereMonth('delivered_at', $month)
            ->get();
            
        // 'shipped' orders are pending commissions
        $pendingOrders = ProductRequest::where('order_status', 'shipped')
            ->whereHas('provider', function($q) use ($repId) {
                $q->where('acquired_by_rep_id', $repId)
                  ->orWhere('acquired_by_subrep_id', $repId);
            })
            ->get();
            
        return [
            'paid' => $this->calculate($paidOrders),
            'pending' => $this->calculate($pendingOrders)
        ];
    }
}
```

## 5. Status-Based Features

### Order Tracking for Reps

Show providers' orders with meaningful status indicators:

| Status | Rep Sees | Meaning |
|--------|----------|---------|
| `submitted` | "New Order" | Provider submitted |
| `processing` | "Under Review" | Admin reviewing |
| `pending_ivr` | "Processing" | IVR being generated |
| `ivr_sent` | "With Manufacturer" | Awaiting approval |
| `manufacturer_approved` | "Approved" | Ready to ship |
| `submitted_to_manufacturer` | "In Fulfillment" | Being processed |
| `shipped` | "Shipped (Commission Pending)" | Earning pending |
| `delivered` | "Complete (Commission Earned)" | Earning confirmed |

### Performance Metrics

```typescript
interface RepMetrics {
  providers_acquired: number;
  active_providers: number;      // Had orders in last 90 days
  total_orders_mtd: number;      // All statuses except cancelled/denied
  delivered_orders_mtd: number;  // Only delivered status
  conversion_rate: number;       // delivered / total orders
  average_order_value: number;
  commission_earned_mtd: number; // Only from delivered
  commission_pending: number;    // From shipped
}
```

## 6. Implementation Priorities

### Phase 1: Core Dashboards (Week 1)

- Basic commission tracking with new statuses
- Provider list with order counts
- Simple status indicators

### Phase 2: Enhanced Tracking (Week 2)

- Real-time order status updates
- Commission projections
- Performance trends

### Phase 3: Team Features (Week 3)

- Sub-rep management
- Team performance rollups
- Commission split calculations

This updated dashboard design aligns with the streamlined order workflow while providing sales teams with clear visibility into their performance and earnings.

```

I'll continue with the remaining documents. Would you like me to proceed with:

6. NEW_ORDER_FLOW_MINUS_ELIGIBILITY_AND_OTHER_APIs_UPDATED.md
7. NON_PHI_DATA_MODELS_UPDATED.md
8. PHI_DATA_MODELS_UPDATED.md (minor updates)
9. PHI_FIELD_REFERENCE_UPDATED.md (minor updates)

These represent the core documentation updates needed to align with the Technical Alignment document. Shall I continue?

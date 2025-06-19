# MSC Wound Portal Order Flow

## Overview

This document outlines the complete order flow from initial product request submission to final order fulfillment. The system uses a single ProductRequest entity throughout the entire lifecycle, which transitions from a "request" to an "order" after approval. The process involves multiple stakeholders (Providers, MSC Admins, Manufacturers) and integrates with external systems (FHIR, DocuSeal).

## Order Flow Stages

### Stage 1: Product Request Submission

**Actor:** Provider or Office Manager  
**Status:** `draft` → `submitted`

1. Provider initiates a new product request through the 6-step workflow:
   - Step 1: Patient Information (pulled from FHIR)
   - Step 2: Clinical Assessment (wound type, location, measurements)
   - Step 3: Validation & Eligibility (MAC validation, insurance eligibility)
   - Step 4: Product Selection (single product, multiple sizes/quantities allowed)
   - Step 5: Clinical Opportunities (AI-powered recommendations)
   - Step 6: Review & Submit

2. System validates:
   - Medicare MAC jurisdiction
   - Insurance eligibility
   - Provider is onboarded with selected product
   - Clinical requirements met

3. ProductRequest created with status `submitted`

### Stage 2: Admin Review & Approval

**Actor:** MSC Admin  
**Status:** `submitted` → `processing` → `approved`/`rejected`

1. Admin accesses Product Request in Order Center (`/orders/center`)
2. Reviews clinical documentation and validation results
3. Makes decision:
   - **Approve**: Proceeds to Order creation
   - **Send Back**: Returns to provider with comments for correction
   - **Deny**: Rejects with reason

### Stage 3: IVR Requirement Check

**Actor:** MSC Admin  
**Status:** `approved` → `pending_ivr`

1. Admin determines if IVR is required for this order
2. If IVR required: Status updates to `pending_ivr`
3. If IVR not required: Admin provides justification and can proceed to final approval
4. System generates order number for tracking

### Stage 4: IVR Document Generation

**Actor:** MSC Admin  
**Status:** `pending_ivr` → `ivr_sent`

1. Admin navigates to Admin Order Center (`/admin/orders`)
2. Selects order requiring IVR
3. System determines if IVR is required:
   - If yes: Continues with IVR flow
   - If no: Admin must provide justification and can skip to approval

4. For IVR generation:
   - System identifies manufacturer from product
   - Retrieves manufacturer's DocuSeal folder
   - Selects appropriate IVR template
   - Pre-populates with:
     - Patient information from FHIR
     - Product details
     - Clinical data
     - Provider information

5. DocuSeal generates IVR document (no signature required)
6. Document URL stored for admin download
7. Order status updates to `ivr_sent`

### Stage 5: Admin Review & Send to Manufacturer

**Actor:** MSC Admin  
**Status:** `ivr_sent` → `ivr_sent` (with manufacturer_sent_at timestamp)

1. Admin reviews generated IVR document
2. Downloads IVR for quality check
3. Manually emails IVR to manufacturer contact
4. Clicks "Mark as Sent to Manufacturer" in system
5. System updates `manufacturer_sent_at` timestamp

### Stage 6: Manufacturer Approval

**Actor:** Manufacturer → MSC Admin  
**Status:** `ivr_sent` → `ivr_confirmed`

1. Manufacturer reviews IVR document via email
2. Sends approval confirmation to MSC Admin
3. Admin clicks "Confirm Manufacturer Approval"
4. Enters approval reference and optional notes
5. System updates status to `ivr_confirmed`

### Stage 7: Final Order Approval

**Actor:** MSC Admin  
**Status:** `ivr_confirmed` → `approved`

1. Admin verifies manufacturer approval is recorded
2. Clicks "Approve Order"
3. System validates prerequisites
4. Order status updates to `approved`

### Stage 9: Order Submission & Fulfillment

**Actor:** MSC Admin/System  
**Status:** `approved` → `submitted_to_manufacturer`

1. Admin submits approved order to manufacturer
2. Order details sent via configured method (email/EDI)
3. Status updates to `submitted_to_manufacturer`
4. Manufacturer processes and ships
5. Future statuses: `shipped`, `delivered`

## Status Summary

| Status | Description | Actor | Next Action |
|--------|-------------|-------|-------------|
| `draft` | Request in progress | Provider | Complete and submit |
| `submitted` | Awaiting admin review | MSC Admin | Review and approve/reject |
| `processing` | Under admin review | MSC Admin | Make decision |
| `pending_ivr` | Needs IVR generation | MSC Admin | Generate IVR or bypass |
| `ivr_sent` | IVR sent (to provider, then manufacturer) | Provider/Admin | Sign, then send to manufacturer |
| `ivr_confirmed` | Manufacturer confirmed IVR | MSC Admin | Final approval |
| `approved` | Approved and ready to submit | MSC Admin | Submit to manufacturer |
| `sent_back` | Sent back for corrections | Provider | Make corrections and resubmit |
| `denied` | Request denied | - | Cannot proceed |
| `submitted_to_manufacturer` | Order submitted for fulfillment | Manufacturer | Process and ship |
| `shipped` | Order shipped | - | Track delivery |
| `delivered` | Order delivered | - | Complete |
| `cancelled` | Request/Order cancelled | - | - |

## Key Integration Points

### FHIR Integration

- Patient demographics
- Clinical data
- Insurance information
- Provider credentials

### DocuSeal Integration

- Manufacturer-specific folders
- IVR templates per manufacturer
- Electronic signature workflow
- Webhook notifications for signature completion

### Future Enhancements

1. Automated manufacturer approval via API
2. Direct EDI submission to manufacturers
3. Real-time shipment tracking
4. Automated status updates via webhooks
5. Bulk IVR generation for multiple orders

## Business Rules

1. **One Product Rule**: Each ProductRequest can only contain one product type (multiple sizes/quantities allowed)
2. **Provider Onboarding**: Providers can only select products they are onboarded with
3. **Dual Approval**: Orders require both provider signature (IVR) AND manufacturer approval
4. **No PHI in Orders**: Patient identifiers are de-identified outside of FHIR
5. **Audit Trail**: Every status change and action is logged with timestamp and actor
6. **Request to Order Transition**: ProductRequests are considered "orders" once they reach `approved` status and beyond
7. **Order Number Generation**: System generates unique order number when status moves to `pending_ivr` or beyond

## Exception Handling

### IVR Not Required

- Admin can skip IVR generation with justification
- Must document reason in system
- Can proceed directly to approval actions

### Sent Back Status

- Admin returns order to provider for corrections
- Must include comments explaining issues
- Provider must create new ProductRequest

### Denied Orders

- Admin provides reason for denial
- Order cannot be reactivated
- Provider must submit new request if needed
